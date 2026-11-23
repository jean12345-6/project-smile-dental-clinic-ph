<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use App\Support\Scope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Clinic records
 *
 * One controller for the clinic modules (services, schedules, treatments, invoices, inventory, feedback ...).
 * Each resource lists who may read and who may write. Every create, update and delete is written to the audit log.
 */
class ClinicController extends APIController
{
    private const STAFF = ['superadmin', 'clinic_admin', 'receptionist', 'dentist'];
    private const ADMIN = ['superadmin', 'clinic_admin'];
    // the front desk: billing, payments and scanned forms are theirs, not the dentist's
    private const FRONT = ['superadmin', 'clinic_admin', 'receptionist'];

    /** table, primary key, who can read, who can write, columns used by ?q= search, own = patient sees only own rows */
    private function map(): array
    {
        return [
            'services' => ['services', 'service_id', ['*'], self::ADMIN, ['service_name']],
            'branches' => ['branches', 'branch_id', ['*'], self::ADMIN, ['branch_name', 'address']],
            'dentists' => ['dentists', 'dentist_id', ['*'], self::ADMIN, ['full_name', 'specialization']],
            'schedules' => ['dentist_schedules', 'schedule_id', self::STAFF, self::ADMIN, []],
            'faq' => ['faq_entries', 'faq_id', self::STAFF, self::ADMIN, ['question', 'answer', 'category']],
            'patient-records' => ['patients', 'patient_id', self::STAFF, ['superadmin', 'clinic_admin', 'receptionist'], ['full_name', 'contact_no', 'email']],
            'treatments' => ['treatments', 'treatment_id', [...self::STAFF, 'patient'], ['superadmin', 'clinic_admin', 'dentist'], ['procedure_name', 'notes']],
            'prescriptions' => ['prescriptions', 'prescription_id', [...self::STAFF, 'patient'], ['superadmin', 'clinic_admin', 'dentist'], ['medicine']],
            'dental-chart' => ['dental_chart', 'chart_id', [...self::STAFF, 'patient'], ['superadmin', 'clinic_admin', 'dentist'], ['condition']],
            'invoices' => ['invoices', 'invoice_id', [...self::FRONT, 'patient'], ['superadmin', 'clinic_admin', 'receptionist'], []],
            'payments' => ['payments', 'payment_id', self::FRONT, ['superadmin', 'clinic_admin', 'receptionist'], ['method']],
            'inventory' => ['inventory', 'item_id', self::STAFF, ['superadmin', 'clinic_admin', 'receptionist'], ['item_name']],
            'feedback' => ['feedback', 'feedback_id', [...self::STAFF, 'patient'], [...self::ADMIN, 'patient'], ['comment']],
            'reminders' => ['reminders', 'reminder_id', self::STAFF, ['superadmin'], ['type', 'channel', 'send_status']],
            'chat-logs' => ['chat_logs', 'chat_id', self::ADMIN, ['superadmin'], ['message_in', 'message_out']],
            'ocr' => ['ocr_batches', 'batch_id', self::FRONT, ['superadmin', 'clinic_admin', 'receptionist'], ['extracted_text']],
            'audit-logs' => ['audit_logs', 'audit_id', self::ADMIN, [], ['action', 'table_name']],
            'session-logs' => ['session_logs', 'session_id', self::ADMIN, [], ['ip_address', 'device']],
            'clinics' => ['clinics', 'clinic_id', ['superadmin', 'clinic_admin'], ['superadmin', 'clinic_admin'], ['clinic_name']],
        ];
    }

    private function cfg(string $res)
    {
        return $this->map()[$res] ?? null;
    }

    private function can(array $roles, string $role): bool
    {
        return in_array('*', $roles, true) || in_array($role, $roles, true);
    }

    private function ownerScope($q, $table, $user)
    {
        if ($user->role === 'patient' && Dbx::has($table, 'patient_id')) {
            $q->where($table . '.patient_id', $user->patient?->patient_id ?? -1);
        }
        if ($table === 'clinics' && ($c = Scope::clinic($user))) {
            return $q->where('clinics.clinic_id', $c);
        }

        return Scope::table($q, $table, $user); // own clinic only
    }

    public function index(Request $r, string $res)
    {
        if (! $c = $this->cfg($res)) {
            return Dbx::err('Unknown resource.', 404);
        }
        [$table, $pk, $read, , $search] = $c;
        $user = $r->user();
        if (! $this->can($read, $user->role)) {
            return Dbx::err('You are not allowed to view this.', 403);
        }

        $q = $this->ownerScope(DB::table($table), $table, $user);
        if ($res === 'patient-records' && Dbx::has($table, 'status') && ! $r->filled('status')) {
            $q->where(fn ($w) => $w->whereNull('status')->orWhere('status', '!=', 'archived'));
        }
        foreach (Dbx::cols($table) as $col) {
            if ($r->filled($col) && $col !== 'password') {
                $q->where($table . '.' . $col, $r->query($col));
            }
        }
        if ($r->filled('q') && $search) {
            $q->where(function ($w) use ($search, $r) {
                foreach ($search as $s) {
                    $w->orWhere($s, 'like', '%' . $r->query('q') . '%');
                }
            });
        }
        $total = (clone $q)->count();
        $per = min(100, max(1, (int) $r->query('perPage', 50)));
        $page = max(1, (int) $r->query('page', 1));
        $sort = (string) $r->query('sort', '');
        $dir = strtolower((string) $r->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $rows = ($sort !== '' && in_array($sort, Dbx::cols($table), true)
            ? $q->orderBy($table . '.' . $sort, $dir)->orderByDesc($pk)
            : $q->orderByDesc($pk))->forPage($page, $per)->get();

        // show the patient name beside each reminder (reminders only store appointment_id)
        if ($res === 'reminders' && $rows->isNotEmpty()) {
            $ids = $rows->pluck('appointment_id')->filter()->unique()->all();
            $names = DB::table('appointments')
                ->join('patients', 'patients.patient_id', '=', 'appointments.patient_id')
                ->whereIn('appointments.appointment_id', $ids)
                ->pluck('patients.full_name', 'appointments.appointment_id');
            foreach ($rows as $row) {
                $row->patient_name = $names[$row->appointment_id] ?? null;
            }
        }

        return response()->json(['data' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'perPage' => $per]]);
    }

    public function show(Request $r, string $res, $id)
    {
        if (! $c = $this->cfg($res)) {
            return Dbx::err('Unknown resource.', 404);
        }
        [$table, $pk, $read] = $c;
        if (! $this->can($read, $r->user()->role)) {
            return Dbx::err('You are not allowed to view this.', 403);
        }
        $row = $this->ownerScope(DB::table($table), $table, $r->user())->where($pk, $id)->first();

        return $row ? response()->json(['data' => $row]) : Dbx::err('Not found.', 404);
    }

    public function store(Request $r, string $res)
    {
        if (! $c = $this->cfg($res)) {
            return Dbx::err('Unknown resource.', 404);
        }
        [$table, $pk, , $write] = $c;
        $user = $r->user();
        if (! $this->can($write, $user->role)) {
            return Dbx::err('You are not allowed to add this.', 403);
        }

        if ($res === 'clinics' && $user->role !== 'superadmin') {
            return Dbx::err('Only the SuperAdmin can register a clinic.', 403);
        }
        $data = Dbx::only($table, $r->except([$pk, 'created_at', 'updated_at']));
        $now = now()->toDateTimeString();
        if (Dbx::has($table, 'clinic_id') && empty($data['clinic_id']) && ($c = Scope::clinic($user))) {
            $data['clinic_id'] = $c; // new rows belong to the user's clinic
        }
        if (Dbx::has($table, 'clinic_id') && empty($data['clinic_id']) && $user->role === 'superadmin') {
            // the SuperAdmin has no clinic of their own: use the X-Clinic-Id header, else the first clinic
            $data['clinic_id'] = (int) ($r->header('X-Clinic-Id') ?: DB::table('clinics')->orderBy('clinic_id')->value('clinic_id'));
        }
        if ($res === 'patient-records') {
            $data += ['status' => 'active', 'no_show_count' => 0];
        }

        // rules per resource
        if ($res === 'feedback') {
            $p = $user->patient;
            if (! $p) {
                return Dbx::err('Only patients can leave feedback.', 403);
            }
            $data['patient_id'] = $p->patient_id;
            $data['date_submitted'] = $now;
            $ok = DB::table('appointments')->where('appointment_id', $data['appointment_id'] ?? 0)->where('patient_id', $p->patient_id)->where('status', 'completed')->exists();
            if (! $ok) {
                return Dbx::err('You can only rate a completed visit of your own.');
            }
            if (DB::table('feedback')->where('appointment_id', $data['appointment_id'])->exists()) {
                return Dbx::err('You already rated this visit.');
            }
            if (($data['rating'] ?? 0) < 1 || $data['rating'] > 5) {
                return Dbx::err('The rating must be from 1 to 5.');
            }
        }
        if ($res === 'treatments') {
            // An empty cost reached the database as '' and failed there, so the dentist saw only
            // "Server error." The fields are checked here and named in the message instead.
            $miss = [];
            if (trim((string) ($data['procedure_name'] ?? '')) === '') {
                $miss[] = 'the procedure';
            }
            if (! isset($data['cost']) || $data['cost'] === '' || ! is_numeric($data['cost'])) {
                $miss[] = 'the cost';
            } elseif ((float) $data['cost'] < 0) {
                return Dbx::err('The cost cannot be less than zero.');
            }
            if ($miss) {
                return Dbx::err('Please enter ' . implode(' and ', $miss) . ' of the treatment.');
            }
            $data += ['treatment_date' => $now];
            $data['dentist_id'] = $data['dentist_id'] ?? DB::table('dentists')->where('user_id', $user->id)->value('dentist_id');
        }
        if ($res === 'prescriptions') {
            $data['dentist_id'] = $data['dentist_id'] ?? DB::table('dentists')->where('user_id', $user->id)->value('dentist_id');
        }
        if ($res === 'dental-chart') {
            $data['date_updated'] = $now;
        }
        if ($res === 'invoices') {
            $data += ['date_issued' => $now];
            $data['balance'] = $data['balance'] ?? ($data['total_amount'] ?? 0);
        }
        if ($res === 'payments') {
            $inv = DB::table('invoices')->where('invoice_id', $data['invoice_id'] ?? 0)->first();
            if (! $inv) {
                return Dbx::err('Invoice not found.', 404);
            }
            $amt = round((float) ($data['amount'] ?? 0), 2);
            if ($amt <= 0) {
                return Dbx::err('The payment amount must be more than zero.');
            }
            if ($amt > (float) $inv->balance + 0.001) {
                return Dbx::err('The payment is more than the remaining balance of ₱' . number_format($inv->balance, 2) . '.');
            }
            $data['paid_at'] = $now;
            $data['received_by'] = $user->id;
        }
        if ($res === 'inventory') {
            $data['quantity'] = $data['quantity'] ?? 0;
            $data['reorder_level'] = $data['reorder_level'] ?? 5;
            // Inventory hangs off a branch, not a clinic, and branch_id has no
            // default in the table, so a create without one failed with a 500.
            if (empty($data['branch_id'])) {
                $data['branch_id'] = $user->branch_id ?: DB::table('branches')
                    ->when(Scope::clinic($user), fn ($q, $cl) => $q->where('clinic_id', $cl))
                    ->orderBy('branch_id')->value('branch_id');
            }
            if (empty($data['branch_id'])) {
                return Dbx::err('Please choose a branch for this item.');
            }
        }

        if ($bad = $this->blankRequired($res, $data, true)) {
            return Dbx::err($bad);
        }
        try {
        $id = DB::transaction(function () use ($table, $data, $res) {
            $id = Dbx::insert($table, $data);
            if ($res === 'payments') {
                DB::table('invoices')->where('invoice_id', $data['invoice_id'])->update(['balance' => DB::raw('GREATEST(0, ROUND(balance - ' . (float) $data['amount'] . ', 2))')]);
            }

            return $id;
        });
        } catch (\Illuminate\Database\QueryException $e) {
            return Dbx::err($this->plainDbError($e));
        }

        Dbx::audit($user, 'create', $table, $id, null, $data);

        return response()->json(['data' => DB::table($table)->where($pk, $id)->first()], 201);
    }

    public function update(Request $r, string $res, $id)
    {
        if (! $c = $this->cfg($res)) {
            return Dbx::err('Unknown resource.', 404);
        }
        [$table, $pk, , $write] = $c;
        $user = $r->user();
        if (! $this->can($write, $user->role)) {
            return Dbx::err('You are not allowed to change this.', 403);
        }
        $old = $this->ownerScope(DB::table($table), $table, $user)->where($pk, $id)->first();
        if (! $old) {
            return Dbx::err('Not found.', 404);
        }
        $data = Dbx::only($table, $r->except([$pk, 'created_at', 'updated_at']));
        unset($data['clinic_id']);
        if ($res === 'ocr' && ($data['verify_status'] ?? '') === 'verified') {
            $data['verified_by'] = $user->id;
        }
        if ($res === 'dental-chart') {
            $data['date_updated'] = now()->toDateTimeString();
        }
        if (Dbx::has($table, 'updated_at')) {
            $data['updated_at'] = now()->toDateTimeString();
        }
        if ($bad = $this->blankRequired($res, $data, false)) {
            return Dbx::err($bad);
        }
        try {
            DB::table($table)->where($pk, $id)->update($data);
        } catch (\Illuminate\Database\QueryException $e) {
            return Dbx::err($this->plainDbError($e));
        }
        Dbx::audit($user, 'update', $table, $id, $old, $data);

        return response()->json(['data' => DB::table($table)->where($pk, $id)->first()]);
    }

    /**
     * The fields a person must fill in, named in words. An empty one used to reach the database
     * and come back to the screen as "Server error.", which told the user nothing.
     */
    private function blankRequired(string $res, array $data, bool $creating): ?string
    {
        $need = [
            'services' => ['service_name' => 'the service name', 'price' => 'the price', 'duration_min' => 'the duration'],
            'patient-records' => ['full_name' => 'the patient\'s name'],
            'inventory' => ['item_name' => 'the item name'],
            'branches' => ['branch_name' => 'the branch name'],
            'prescriptions' => ['medicine' => 'the medicine'],
            'dental-chart' => ['tooth_no' => 'the tooth number', 'condition' => 'the condition'],
        ][$res] ?? [];
        $miss = [];
        foreach ($need as $col => $words) {
            // On an edit only the fields that were sent are checked; on a new record all of them.
            if (! $creating && ! array_key_exists($col, $data)) {
                continue;
            }
            if (trim((string) ($data[$col] ?? '')) === '') {
                $miss[] = $words;
            }
        }
        foreach (['price', 'duration_min', 'quantity', 'reorder_level', 'total_amount'] as $num) {
            if (isset($data[$num]) && $data[$num] !== '' && (! is_numeric($data[$num]) || (float) $data[$num] < 0)) {
                return 'Please enter a number of zero or more for ' . str_replace('_', ' ', $num === 'duration_min' ? 'the duration' : 'the ' . $num) . '.';
            }
        }

        if (count($miss) > 1) {
            $last = array_pop($miss);

            return 'Please enter ' . implode(', ', $miss) . ' and ' . $last . '.';
        }

        return $miss ? 'Please enter ' . $miss[0] . '.' : null;
    }

    /** A database refusal, told in words instead of as a server error. */
    private function plainDbError(\Illuminate\Database\QueryException $e): string
    {
        $m = $e->getMessage();
        if (str_contains($m, 'Duplicate') || str_contains($m, 'UNIQUE')) {
            return 'A record with these details already exists.';
        }
        if (str_contains($m, 'foreign key') || str_contains($m, 'FOREIGN KEY')) {
            return 'This record is linked to something that does not exist or is still in use.';
        }

        return 'Some required details are missing or not valid. Please check the form and try again.';
    }

    public function destroy(Request $r, string $res, $id)
    {
        if (! $c = $this->cfg($res)) {
            return Dbx::err('Unknown resource.', 404);
        }
        [$table, $pk, , $write] = $c;
        $user = $r->user();
        if (! $this->can($write, $user->role) || in_array($res, ['audit-logs', 'session-logs', 'payments', 'invoices']) || ($res === 'clinics' && $user->role !== 'superadmin')) {
            return Dbx::err('You are not allowed to delete this.', 403);
        }
        $old = $this->ownerScope(DB::table($table), $table, $user)->where($pk, $id)->first();
        if (! $old) {
            return Dbx::err('Not found.', 404);
        }
        // patient records are archived, never erased
        if ($res === 'patient-records' && Dbx::has($table, 'status')) {
            DB::table($table)->where($pk, $id)->update(['status' => 'archived']);
            Dbx::audit($user, 'archive', $table, $id, $old, ['status' => 'archived']);

            return response()->json(['message' => 'Archived. The record is kept and can be restored.']);
        }
        DB::table($table)->where($pk, $id)->delete();
        Dbx::audit($user, 'delete', $table, $id, $old, null);

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * POST /api/v1/r/patient-records/{id}/restore
     * Brings an archived patient back into the lists (WST criterion 11: recovery).
     * Same roles that may archive; the audit log keeps the before and after.
     */
    public function restore(Request $r, string $res, $id)
    {
        if ($res !== 'patient-records') {
            return Dbx::err('Only archived patient records can be restored.', 422);
        }
        $c = $this->cfg($res);
        if (! $c) {
            return Dbx::err('Unknown resource.', 404);
        }
        [$table, $pk, , $write] = $c;
        $user = $r->user();
        if (! $this->can($write, $user->role)) {
            return Dbx::err('You are not allowed to restore records.', 403);
        }
        $old = $this->ownerScope(DB::table($table), $table, $user)->where($pk, $id)->first();
        if (! $old) {
            return Dbx::err('Not found.', 404);
        }
        if (($old->status ?? null) !== 'archived') {
            return Dbx::err('This record is not archived.', 422);
        }
        DB::table($table)->where($pk, $id)->update(['status' => 'active']);
        Dbx::audit($user, 'restore', $table, $id, ['status' => 'archived'], ['status' => 'active']);

        return response()->json(['message' => 'Restored. The patient is back in the list.']);
    }
}
