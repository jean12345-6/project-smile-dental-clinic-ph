<?php

namespace App\Support;

/**
 * Keeps every user inside their own clinic (FR-02, NFR-14).
 * Rows with no clinic yet (null) stay visible so older seed data keeps working.
 */
class Scope
{
    public static function clinic($user): ?int
    {
        if (! $user) {
            return null;
        }

        // A superadmin belongs to no clinic, so they saw every clinic's rows added together
        // on every screen while the header named one. The clinic they pick in the dashboard
        // switcher travels on this header with every request, and it was only ever read when
        // a record was created. Reading it here makes the choice apply to what they see as
        // well, on every page, not just the dashboard. "all", or no header, keeps the
        // combined view.
        if ($user->role === 'superadmin') {
            $picked = request()?->header('X-Clinic-Id');

            return ($picked && strtolower((string) $picked) !== 'all') ? (int) $picked : null;
        }

        return ! empty($user->clinic_id) ? (int) $user->clinic_id : null;
    }

    /** The dentist record of a logged-in dentist, or null for every other role. */
    public static function dentistId($user): ?int
    {
        if (! $user || $user->role !== 'dentist') {
            return null;
        }
        $id = \Illuminate\Support\Facades\DB::table('dentists')->where('user_id', $user->id)->value('dentist_id');

        return $id ? (int) $id : null;
    }

    /**
     * A dentist sees their own patients, not the whole clinic's. The daily queue has always
     * worked this way; the dashboard and the appointment list did not, so the same dentist
     * saw their own queue in one place and everybody's bookings in another.
     */
    public static function ownDentist($q, string $col, $user)
    {
        if ($did = self::dentistId($user)) {
            $q->where($col, $did);
        }

        return $q;
    }

    /** A staff account that belongs to no clinic. It must see nothing, never everything. */
    public static function blind($user): bool
    {
        return $user && in_array($user->role, ['clinic_admin', 'receptionist', 'dentist'], true) && empty($user->clinic_id);
    }

    /** Restrict a query to patients of the user's clinic, through a patient_id column. */
    public static function patients($q, string $col, $user)
    {
        if (self::blind($user)) {
            return $q->whereRaw('1 = 0');
        }
        if ($c = self::clinic($user)) {
            $q->whereIn($col, fn ($s) => $s->select('patient_id')->from('patients')->where(fn ($w) => $w->where('clinic_id', $c)->orWhereNull('clinic_id')));
        }

        return $q;
    }

    public static function invoices($q, string $col, $user)
    {
        if (self::blind($user)) {
            return $q->whereRaw('1 = 0');
        }
        if ($c = self::clinic($user)) {
            $q->whereIn($col, fn ($s) => $s->select('invoice_id')->from('invoices')->whereIn('patient_id', fn ($p) => $p->select('patient_id')->from('patients')->where(fn ($w) => $w->where('clinic_id', $c)->orWhereNull('clinic_id'))));
        }

        return $q;
    }

    /** Pick the right rule for any table, by the columns it has. */
    public static function table($q, string $table, $user)
    {
        if (self::blind($user)) {
            return $q->whereRaw('1 = 0');
        }
        $c = self::clinic($user);
        if (! $c) {
            return $q;
        }

        // The list of clinics is the one thing a superadmin must always see in full, or
        // picking a clinic would hide every other clinic from the page used to manage them.
        if ($table === 'clinics' && $user && $user->role === 'superadmin') {
            return $q;
        }
        if (Dbx::has($table, 'clinic_id')) {
            return $q->where(fn ($w) => $w->where("$table.clinic_id", $c)->orWhereNull("$table.clinic_id"));
        }
        if (Dbx::has($table, 'patient_id')) {
            return self::patients($q, "$table.patient_id", $user);
        }
        if ($table === 'payments') {
            return self::invoices($q, 'payments.invoice_id', $user);
        }
        if ($table === 'reminders') {
            return $q->whereIn('reminders.appointment_id', fn ($s) => self::patients($s->select('appointment_id')->from('appointments'), 'patient_id', $user));
        }
        if ($table === 'dentists' && Dbx::has($table, 'user_id')) {
            return $q->where(fn ($w) => $w->whereNull('dentists.user_id')->orWhereIn('dentists.user_id', fn ($s) => $s->select('id')->from('users')->where(fn ($x) => $x->where('clinic_id', $c)->orWhereNull('clinic_id'))));
        }
        if (Dbx::has($table, 'branch_id')) {
            return $q->where(fn ($w) => $w->whereNull("$table.branch_id")->orWhereIn("$table.branch_id", fn ($s) => $s->select('branch_id')->from('branches')->where(fn ($x) => $x->where('clinic_id', $c)->orWhereNull('clinic_id'))));
        }
        if (Dbx::has($table, 'user_id')) {
            return $q->whereIn("$table.user_id", fn ($s) => $s->select('id')->from('users')->where(fn ($x) => $x->where('clinic_id', $c)->orWhereNull('clinic_id')));
        }

        return $q;
    }
}
