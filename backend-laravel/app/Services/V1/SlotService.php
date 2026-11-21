<?php

namespace App\Services\V1;

use App\Support\ClinicHours;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SlotService
{
    // A new slot starts every 30 minutes.
    public const STEP_MINUTES = 30;

    // These statuses free the time, so they do not block a slot.
    private const FREE_STATUSES = ['cancelled', 'no_show'];

    /**
     * Returns the free slots of one dentist on one date.
     *
     * @return array<int, array{startTime: string, endTime: string}>
     */
    public function forDay(int $dentistId, string $date, int $durationMin, ?int $branchId = null, $user = null): array
    {
        $day = Carbon::parse($date)->startOfDay();

        // 1. Day off? (a row with leave_date)
        $onLeave = DB::table('dentist_schedules')
            ->where('dentist_id', $dentistId)
            ->whereDate('leave_date', $day->toDateString())
            ->exists();

        if ($onLeave) {
            return [];
        }

        // 2. Working hours for this weekday (1 = Monday ... 7 = Sunday)
        $iso = $day->dayOfWeekIso;
        $schedules = DB::table('dentist_schedules')
            ->where('dentist_id', $dentistId)
            ->whereIn('day_of_week', $iso === 7 ? [7, 0] : [$iso])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        // 2b. The clinic's own opening hours. A dentist's schedule may run past closing time
        //     or onto a day the clinic is shut, and those slots must not be offered.
        $clinicId = ClinicHours::clinicFor($branchId, $user);
        $hours = ClinicHours::on($clinicId, $date);
        if ($hours === null) {
            return [];
        }

        // 3. Appointments that already take time on this day
        $booked = DB::table('appointments')
            ->where('dentist_id', $dentistId)
            ->whereNotIn('status', self::FREE_STATUSES)
            ->where('start_time', '<', $day->copy()->endOfDay())
            ->where('end_time', '>', $day)
            ->get(['start_time', 'end_time'])
            ->map(fn ($a) => [Carbon::parse($a->start_time), Carbon::parse($a->end_time)]);

        // 4. Build the slots
        $now = now();
        $slots = [];

        foreach ($schedules as $s) {
            if (! $s->start_time || ! $s->end_time) {
                continue;
            }

            // Whichever opens later and whichever closes earlier wins.
            $open = Carbon::parse($date . ' ' . $s->start_time)->max($hours['open']);
            $close = Carbon::parse($date . ' ' . $s->end_time)->min($hours['close']);
            $breakStart = $s->break_start ? Carbon::parse($date . ' ' . $s->break_start) : null;
            $breakEnd = $s->break_end ? Carbon::parse($date . ' ' . $s->break_end) : null;

            for ($start = $open->copy(); $start->copy()->addMinutes($durationMin)->lte($close); $start->addMinutes(self::STEP_MINUTES)) {
                $end = $start->copy()->addMinutes($durationMin);

                // already in the past
                if ($start->lte($now)) {
                    continue;
                }

                // touches the break
                if ($breakStart && $breakEnd && $start->lt($breakEnd) && $end->gt($breakStart)) {
                    continue;
                }

                // overlaps an existing appointment
                if ($booked->contains(fn ($b) => $start->lt($b[1]) && $end->gt($b[0]))) {
                    continue;
                }

                $slots[] = [
                    'startTime' => $start->format('Y-m-d H:i:s'),
                    'endTime' => $end->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $slots;
    }
        /**
     * True when the dentist has no active appointment in this time range.
     */
    public function isFree(int $dentistId, string $start, string $end, ?int $ignoreAppointmentId = null): bool
    {
        return ! DB::table('appointments')
            ->where('dentist_id', $dentistId)
            ->whereNotIn('status', self::FREE_STATUSES)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('appointment_id', '!=', $ignoreAppointmentId))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
    }
}
