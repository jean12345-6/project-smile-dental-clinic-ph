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
            'invoices' => ['invoices', 'invoice_id', [...self::STAFF, 'patient'], ['superadmin', 'clinic_admin', 'receptionist'], []],
            'payments' => ['payments', 'payment_id', self::STAFF, ['superadmin', 'clinic_admin', 'receptionist'], ['method']],
            'inventory' => ['inventory', 'item_id', self::STAFF, ['superadmin', 'clinic_admin', 'receptionist'], ['item_name']],
            'feedback' => ['feedback', 'feedback_id', [...self::STAFF, 'patient'], [...self::ADMIN, 'patient'], ['comment']],
            'reminders' => ['reminders', 'reminder_id', self::STAFF, ['superadmin'], []],
            'chat-logs' => ['chat_logs', 'chat_id', self::STAFF, ['superadmin'], ['message_in', 'message_out']],
            'ocr' => ['ocr_batches', 'batch_id', self::STAFF, ['superadmin', 'clinic_admin', 'receptionist'], ['extracted_text']],
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
            $data += ['treatment_date' => $now, 'cost' => 0];
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
        }

        $id = DB::transaction(function () use ($table, $data, $res) {
            $id = Dbx::insert($table, $data);
            if ($res === 'payments') {
                DB::table('invoices')->where('invoice_id', $data['invoice_id'])->update(['balance' => DB::raw('GREATEST(0, ROUND(balance - ' . (float) $data['amount'] . ', 2))')]);
            }

            return $id;
        });

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
        DB::table($table)->where($pk, $id)->update($data);
        Dbx::audit($user, 'update', $table, $id, $old, $data);

        return response()->json(['data' => DB::table($table)->where($pk, $id)->first()]);
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
        } else {
            DB::table($table)->where($pk, $id)->delete();
        }
        Dbx::audit($user, 'delete', $table, $id, $old, null);

        return response()->json(['message' => 'Deleted.']);
    }
}
