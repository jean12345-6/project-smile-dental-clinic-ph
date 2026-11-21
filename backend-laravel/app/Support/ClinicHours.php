<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The clinic's opening hours, as the administrator set them in Clinic info and hours.
 *
 * Until now these hours were only ever displayed — on the landing page, in the assistant's
 * answers and on the hours screen. Nothing checked them when a slot was offered or an
 * appointment was saved, so a dentist whose own schedule ran past closing time produced
 * bookable slots after the clinic had shut, and a closed day was bookable as well. The
 * dentist's schedule says when that dentist works; this says when the doors are open. A
 * slot needs both.
 */
class ClinicHours
{
    private const DAYS = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];

    /** The same fallbacks ClinicInfoController uses, so an unconfigured clinic behaves identically. */
    private const FALLBACK = ['sun' => 'closed', 'sat' => '08:00-15:00'];

    /**
     * Opening and closing time on one date, or null when the clinic is closed that day.
     *
     * @return array{open: Carbon, close: Carbon}|null
     */
    public static function on(?int $clinicId, string $date): ?array
    {
        $key = self::DAYS[Carbon::parse($date)->dayOfWeekIso] ?? 'mon';
        $raw = trim((string) Settings::get($clinicId, 'hours.' . $key, self::FALLBACK[$key] ?? '08:00-19:00'));

        if ($raw === '' || strcasecmp($raw, 'closed') === 0) {
            return null;
        }
        if (! preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $raw, $m)) {
            return null;
        }

        return [
            'open' => Carbon::parse($date . ' ' . $m[1]),
            'close' => Carbon::parse($date . ' ' . $m[2]),
        ];
    }

    /** The clinic a booking belongs to: its branch decides, then the user who is making it. */
    public static function clinicFor(?int $branchId, $user = null): ?int
    {
        if ($branchId) {
            $c = DB::table('branches')->where('branch_id', $branchId)->value('clinic_id');
            if ($c) {
                return (int) $c;
            }
        }

        return ! empty($user?->clinic_id) ? (int) $user->clinic_id : null;
    }

    /** A sentence for the patient, not a code. Used by the booking error and the assistant. */
    public static function closedMessage(?int $clinicId, string $date): string
    {
        $day = Carbon::parse($date)->format('l');
        $h = self::on($clinicId, $date);

        return $h === null
            ? "The clinic is closed on {$day}. Please choose another day."
            : "On {$day} the clinic is open from " . $h['open']->format('g:i A') . ' to ' . $h['close']->format('g:i A') . '. Please choose a time inside those hours.';
    }
}
