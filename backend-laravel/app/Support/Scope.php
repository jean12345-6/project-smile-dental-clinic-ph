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
        return ($user && $user->role !== 'superadmin' && ! empty($user->clinic_id)) ? (int) $user->clinic_id : null;
    }

    /** Restrict a query to patients of the user's clinic, through a patient_id column. */
    public static function patients($q, string $col, $user)
    {
        if ($c = self::clinic($user)) {
            $q->whereIn($col, fn ($s) => $s->select('patient_id')->from('patients')->where(fn ($w) => $w->where('clinic_id', $c)->orWhereNull('clinic_id')));
        }

        return $q;
    }

    public static function invoices($q, string $col, $user)
    {
        if ($c = self::clinic($user)) {
            $q->whereIn($col, fn ($s) => $s->select('invoice_id')->from('invoices')->whereIn('patient_id', fn ($p) => $p->select('patient_id')->from('patients')->where(fn ($w) => $w->where('clinic_id', $c)->orWhereNull('clinic_id'))));
        }

        return $q;
    }

    /** Pick the right rule for any table, by the columns it has. */
    public static function table($q, string $table, $user)
    {
        $c = self::clinic($user);
        if (! $c) {
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
