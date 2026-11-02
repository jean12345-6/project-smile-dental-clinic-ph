<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Appointment;
use App\Services\V1\ReminderService;
use App\Services\V1\WaitlistService;
use App\Support\Dbx;
use App\Support\Scope;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Clinic workflow
 *
 * Daily queue, check-in, walk-ins, invoices, reports, reminders, patient summary and OCR upload.
 */
class WorkflowController extends APIController
{
    private const STAFF = ['superadmin', 'clinic_admin', 'receptionist', 'dentist'];
    private const STATUSES = ['pending', 'confirmed', 'checked_in', 'in_treatment', 'completed', 'cancelled', 'no_show'];

    private function staff(Request $r): bool
    {
        return in_array($r->user()->role, self::STAFF, true);
    }

    /** GET /queue?date=&dentistId= — today's patients in order of arrival or appointment time. */
    public function queue(Request $r)
    {
        if (! $this->staff($r)) {
            return Dbx::err('Not allowed.', 403);
        }
        $q = DB::table('appointments')
            ->join('patients', 'patients.patient_id', '=', 'appointments.patient_id')
            ->join('services', 'services.service_id', '=', 'appointments.service_id')
            ->whereDate('appointments.start_time', $r->query('date', now()->toDateString()))
            ->whereNotIn('appointments.status', ['cancelled'])
            ->orderByRaw('appointments.check_in_time IS NULL, appointments.check_in_time, appointments.start_time');
        Scope::patients($q, 'appointments.patient_id', $r->user());
        if ($r->filled('dentistId')) {
            $q->where('appointments.dentist_id', $r->query('dentistId'));
        }
        // a dentist only sees their own queue
        if ($r->user()->role === 'dentist') {
            $did = DB::table('dentists')->where('user_id', $r->user()->id)->value('dentist_id');
            if ($did) {
                $q->where('appointments.dentist_id', $did);
            }
        }

        return response()->json(['data' => $q->get([
            'appointments.appointment_id', 'appointments.patient_id', 'appointments.dentist_id', 'appointments.service_id', 'appointments.start_time',
            'appointments.status', 'appointments.risk_flag', 'appointments.check_in_time', 'appointments.booked_via',
            'patients.full_name as patient', 'services.service_name as service', 'services.price',
        ])]);
    }

    /** POST /queue/{id}/status {status} — move an appointment through its statuses. */
    public function setStatus(Request $r, $id)
    {
        if (! $this->staff($r)) {
            return Dbx::err('Not allowed.', 403);
        }
        $status = $r->input('status');
        if (! in_array($status, self::STATUSES, true)) {
            return Dbx::err('Status must be one of: ' . implode(', ', self::STATUSES) . '.');
        }
        $a = Appointment::find($id);
        if (! $a) {
            return Dbx::err('Appointment not found.', 404);
        }
        $old = $a->status;
        $a->status = $status;
        if ($status === 'checked_in' && Dbx::has('appointments', 'check_in_time')) {
            $a->check_in_time = now();
        }
        $a->save(); // cancelled => slot offered to the waiting list

        if ($status === 'no_show' && $old !== 'no_show') {
            DB::table('patients')->where('patient_id', $a->patient_id)->increment('no_show_count');
        }

        return response()->json(['data' => $a->fresh()]);
    }

    /** POST /queue/{id}/followup {date} — books a follow-up at the first open slot of that day. */
    public function followUp(Request $r, $id, \App\Services\V1\SlotService $slots)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin', 'dentist'], true)) {
            return Dbx::err('Not allowed.', 403);
        }
        $r->validate(['date' => 'required|date|after:today']);
        $a = Appointment::find($id);
        if (! $a) {
            return Dbx::err('Appointment not found.', 404);
        }
        $svc = DB::table('services')->where('service_id', $a->service_id)->first();
        $list = $slots->forDay((int) $a->dentist_id, $r->date, (int) $svc->duration_min, $a->branch_id);
        if (! $list) {
            return Dbx::err('No open slot on that date. Pick another follow-up date.');
        }
        $n = new Appointment;
        $n->forceFill(Dbx::only('appointments', [
            'patient_id' => $a->patient_id, 'dentist_id' => $a->dentist_id, 'branch_id' => $a->branch_id, 'service_id' => $a->service_id,
            'start_time' => $list[0]['startTime'], 'end_time' => $list[0]['endTime'], 'status' => 'pending', 'risk_flag' => 0,
            'booked_via' => 'follow-up', 'follow_up_of' => $a->appointment_id,
        ]))->save();

        return response()->json(['data' => $n], 201);
    }

    /** POST /queue/walkin {patientId, dentistId, serviceId} */
    public function walkIn(Request $r)
    {
        if (! $this->staff($r)) {
            return Dbx::err('Not allowed.', 403);
        }
        $r->validate(['patientId' => 'required|integer', 'dentistId' => 'required|integer', 'serviceId' => 'required|integer']);
        $svc = DB::table('services')->where('service_id', $r->serviceId)->first();
        if (! $svc) {
            return Dbx::err('Service not found.', 404);
        }
        $start = now();
        $a = new Appointment;
        $a->forceFill(Dbx::only('appointments', [
            'patient_id' => $r->patientId, 'dentist_id' => $r->dentistId, 'service_id' => $r->serviceId,
            'branch_id' => DB::table('dentist_schedules')->where('dentist_id', $r->dentistId)->whereNotNull('day_of_week')->value('branch_id'),
            'start_time' => $start, 'end_time' => $start->copy()->addMinutes((int) $svc->duration_min),
            'status' => 'checked_in', 'check_in_time' => $start, 'risk_flag' => 0, 'booked_via' => 'walk-in',
        ]))->save();

        return response()->json(['data' => $a], 201);
    }

    /** POST /invoices/generate {appointmentId} — builds an invoice from the treatments done (or the service price). */
    public function generateInvoice(Request $r)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin', 'receptionist'], true)) {
            return Dbx::err('Not allowed.', 403);
        }
        $a = DB::table('appointments')->where('appointment_id', $r->input('appointmentId'))->first();
        if (! $a) {
            return Dbx::err('Appointment not found.', 404);
        }
        if ($ex = DB::table('invoices')->where('appointment_id', $a->appointment_id)->first()) {
            return Dbx::err('This visit already has invoice #' . $ex->invoice_id . '.');
        }
        $total = (float) DB::table('treatments')->where('appointment_id', $a->appointment_id)->sum('cost');
        if ($total <= 0) {
            $total = (float) DB::table('services')->where('service_id', $a->service_id)->value('price');
        }
        $id = Dbx::insert('invoices', ['appointment_id' => $a->appointment_id, 'patient_id' => $a->patient_id, 'total_amount' => round($total, 2), 'balance' => round($total, 2), 'date_issued' => now()->toDateTimeString()]);
        Dbx::audit($r->user(), 'create', 'invoices', $id, null, ['total' => $total]);

        return response()->json(['data' => DB::table('invoices')->where('invoice_id', $id)->first()], 201);
    }

    /** GET /patient-summary/{id} — automatic summary, clearly labelled. */
    public function summary(Request $r, $id)
    {
        $user = $r->user();
        if ($user->role === 'patient' && (int) ($user->patient?->patient_id) !== (int) $id) {
            return Dbx::err('You can only view your own records.', 403);
        }
        $p = DB::table('patients')->where('patient_id', $id)->first();
        if (! $p) {
            return Dbx::err('Patient not found.', 404);
        }
        $visits = DB::table('appointments')->where('patient_id', $id)->where('status', 'completed')->count();
        $last = DB::table('appointments')->where('patient_id', $id)->where('status', 'completed')->max('start_time');
        $tx = DB::table('treatments')->where('patient_id', $id)->orderByDesc('treatment_date')->limit(8)->get(['procedure_name', 'tooth_no', 'treatment_date']);
        $rx = DB::table('prescriptions')->where('patient_id', $id)->orderByDesc('prescription_id')->limit(8)->get(['medicine', 'dosage', 'frequency']);
        $lines = [];
        $lines[] = "{$p->full_name} has {$visits} completed visit(s)" . ($last ? ', the last on ' . Carbon::parse($last)->format('M j, Y') . '.' : '.');
        if ($tx->isNotEmpty()) {
            $lines[] = 'Recent treatments: ' . $tx->map(fn ($t) => $t->procedure_name . ($t->tooth_no ? " (tooth {$t->tooth_no})" : ''))->implode(', ') . '.';
        }
        if ($rx->isNotEmpty()) {
            $lines[] = 'Medicines prescribed: ' . $rx->pluck('medicine')->unique()->implode(', ') . '.';
        }
        if (! empty($p->medical_history)) {
            $lines[] = 'Medical history on file: ' . $p->medical_history;
        }

        return response()->json(['label' => 'Automatically generated summary. Not a clinical finding. Check the full records.', 'summary' => implode(' ', $lines), 'links' => ['treatments' => "/treatments?patient_id={$id}", 'prescriptions' => "/prescriptions?patient_id={$id}"]]);
    }

    /** GET /reports?from=&to= */
    public function reports(Request $r)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin', 'receptionist', 'dentist'], true)) {
            return Dbx::err('Not allowed.', 403);
        }
        $from = $r->query('from', now()->startOfMonth()->toDateString());
        $to = $r->query('to', now()->toDateString());
        $user = $r->user();
        $base = fn () => Scope::patients(DB::table('appointments')->whereBetween(DB::raw('DATE(appointments.start_time)'), [$from, $to]), 'appointments.patient_id', $user);

        $byDay = $base()->select(DB::raw('DATE(start_time) as date'), DB::raw('count(*) as total'),
            DB::raw("sum(status='completed') as completed"), DB::raw("sum(status='cancelled') as cancelled"), DB::raw("sum(status='no_show') as no_show"))
            ->groupBy(DB::raw('DATE(start_time)'))->orderBy('date')->get();
        $byDentist = $base()->join('dentists', 'dentists.dentist_id', '=', 'appointments.dentist_id')
            ->select('dentists.dentist_id', 'dentists.full_name as name', DB::raw('count(*) as total'), DB::raw("sum(appointments.status='completed') as completed"))
            ->groupBy('dentists.dentist_id', 'dentists.full_name')->get();
        $byBranch = $base()->join('branches', 'branches.branch_id', '=', 'appointments.branch_id')
            ->select('branches.branch_id', 'branches.branch_name as name', DB::raw('count(*) as total'), DB::raw("sum(appointments.status='completed') as completed"))
            ->groupBy('branches.branch_id', 'branches.branch_name')->get();
        $byMonth = Scope::patients(DB::table('appointments'), 'appointments.patient_id', $user)->select(DB::raw("DATE_FORMAT(start_time,'%Y-%m') as month"), DB::raw('count(*) as total'), DB::raw("sum(status='completed') as completed"))
            ->groupBy(DB::raw("DATE_FORMAT(start_time,'%Y-%m')"))->orderByDesc('month')->limit(12)->get();
        $revenue = (float) Scope::invoices(DB::table('payments')->whereBetween(DB::raw('DATE(paid_at)'), [$from, $to]), 'payments.invoice_id', $user)->sum('amount');

        return response()->json(['from' => $from, 'to' => $to, 'revenue' => $revenue, 'byDay' => $byDay, 'byDentist' => $byDentist, 'byBranch' => $byBranch, 'byMonth' => $byMonth]);
    }

    /** POST /reminders/run — the same job the schedule runs (php artisan smilesync:reminders). */
    public function runReminders(Request $r, ReminderService $svc)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin'], true)) {
            return Dbx::err('Not allowed.', 403);
        }

        return response()->json($svc->run(Scope::clinic($r->user())));
    }

    /** POST /waitlist/{id}/confirm — the patient takes the offered slot and the appointment is created. */
    public function confirmOffer(Request $r, $id, WaitlistService $svc)
    {
        $p = $r->user()->patient;
        if (! $p) {
            return Dbx::err('Only patients can confirm an offer.', 403);
        }
        [$ok, $msg] = $svc->confirm((int) $id, (int) $p->patient_id);

        return $ok ? response()->json(['message' => $msg]) : Dbx::err($msg);
    }

    /** GET /app-settings — reminder timing, no-show threshold, offer expiry. */
    public function settings(Request $r)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin'], true)) {
            return Dbx::err('Not allowed.', 403);
        }

        return response()->json(Settings::all($r->user()->clinic_id));
    }

    /** PUT /app-settings */
    public function saveSettings(Request $r)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin'], true)) {
            return Dbx::err('Not allowed.', 403);
        }
        $r->validate(['reminder_hours' => 'integer|min:1|max:168', 'no_show_threshold' => 'integer|min:1|max:20', 'offer_expiry_hours' => 'integer|min:1|max:72']);
        foreach (array_keys(Settings::DEFAULTS) as $k) {
            if ($r->filled($k)) {
                Settings::set($r->user()->clinic_id, $k, (int) $r->input($k));
            }
        }
        Dbx::audit($r->user(), 'update', 'clinic_settings', $r->user()->clinic_id, null, $r->only(array_keys(Settings::DEFAULTS)));

        return response()->json(Settings::all($r->user()->clinic_id));
    }

    /** POST /clinic/logo (multipart: image) — the clinic logo (FR-07). */
    public function clinicLogo(Request $r)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin'], true)) {
            return Dbx::err('Not allowed.', 403);
        }
        $r->validate(['image' => 'required|image|max:4096', 'clinicId' => 'nullable|integer']);
        $cid = $r->user()->role === 'superadmin' ? ($r->clinicId ?: $r->user()->clinic_id) : $r->user()->clinic_id;
        $path = $r->file('image')->store('logos', 'public');
        DB::table('clinics')->where('clinic_id', $cid)->update(['logo_path' => $path]);
        Dbx::audit($r->user(), 'update', 'clinics', $cid, null, ['logo_path' => $path]);

        return response()->json(['logo_path' => $path, 'url' => asset('storage/' . $path)]);
    }

    /** POST /clinics/{id}/admin {name,email,password} — SuperAdmin creates the Clinic Administrator account (FR-06). */
    public function createClinicAdmin(Request $r, $id)
    {
        if ($r->user()->role !== 'superadmin') {
            return Dbx::err('Only the SuperAdmin can do this.', 403);
        }
        $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8']);
        if (! DB::table('clinics')->where('clinic_id', $id)->exists()) {
            return Dbx::err('Clinic not found.', 404);
        }
        $uid = Dbx::insert('users', ['name' => $r->name, 'email' => $r->email, 'username' => $r->email, 'password' => Hash::make($r->password), 'role' => 'clinic_admin', 'clinic_id' => $id, 'status' => 'active']);
        Dbx::audit($r->user(), 'create', 'users', $uid, null, ['role' => 'clinic_admin', 'clinic_id' => $id]);

        return response()->json(['data' => ['id' => $uid, 'name' => $r->name, 'email' => $r->email, 'clinicId' => (int) $id]], 201);
    }

    /** POST /ocr/upload (multipart: image, patientId, text?) */
    public function ocrUpload(Request $r)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin', 'receptionist'], true)) {
            return Dbx::err('Not allowed.', 403);
        }
        $r->validate(['image' => 'required|image|max:8192', 'patientId' => 'required|integer']);
        $path = $r->file('image')->store('ocr', 'public');
        $id = Dbx::insert('ocr_batches', ['patient_id' => $r->patientId, 'image_path' => $path, 'extracted_text' => $r->input('text', ''), 'verify_status' => 'pending']);
        Dbx::audit($r->user(), 'create', 'ocr_batches', $id, null, ['image' => $path]);

        return response()->json(['data' => DB::table('ocr_batches')->where('batch_id', $id)->first(), 'url' => asset('storage/' . $path)], 201);
    }
}
