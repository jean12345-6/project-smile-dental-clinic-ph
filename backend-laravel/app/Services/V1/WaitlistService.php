<?php

namespace App\Services\V1;

use App\Models\Appointment;
use App\Support\Dbx;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WaitlistService
{
    /** A time was freed. Offer it to the first patient who is waiting for it. */
    public function offerFor(Appointment $freed): void
    {
        $date = Carbon::parse($freed->start_time)->toDateString();

        $entry = DB::table('waitlist')
            ->where('status', 'waiting')
            ->where('branch_id', $freed->branch_id)
            ->where('service_id', $freed->service_id)
            ->where(fn ($q) => $q->whereNull('dentist_id')->orWhere('dentist_id', $freed->dentist_id))
            ->where(fn ($q) => $q->whereNull('preferred_date')->orWhere('preferred_date', $date))
            ->orderBy('created_at')
            ->orderBy('waitlist_id')
            ->first();

        if ($entry) {
            $this->offer($entry->waitlist_id, $freed->start_time, $freed->end_time, $freed->dentist_id, $freed->branch_id);
        }
    }

    private function offer($id, $start, $end, $dentistId, $branchId): void
    {
        $fmt = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : (string) $v;
        DB::table('waitlist')->where('waitlist_id', $id)->update(Dbx::only('waitlist', [
            'status' => 'offered', 'offered_at' => now(), 'updated_at' => now(),
            'offered_start' => $fmt($start), 'offered_end' => $fmt($end),
            'offered_dentist_id' => $dentistId, 'offered_branch_id' => $branchId,
        ]));
    }

    /** Offers nobody confirmed in time go to the next person in line. Returns how many expired. */
    public function expireOld(int $hours): int
    {
        $n = 0;
        foreach (DB::table('waitlist')->where('status', 'offered')->where('offered_at', '<', now()->subHours($hours))->get() as $old) {
            DB::table('waitlist')->where('waitlist_id', $old->waitlist_id)->update(['status' => 'expired', 'updated_at' => now()]);
            $n++;
            if (! empty($old->offered_start)) {
                $next = DB::table('waitlist')->where('status', 'waiting')->where('service_id', $old->service_id)
                    ->where(fn ($q) => $q->whereNull('dentist_id')->orWhere('dentist_id', $old->offered_dentist_id))
                    ->orderBy('created_at')->first();
                if ($next && Carbon::parse($old->offered_start)->isFuture()) {
                    $this->offer($next->waitlist_id, $old->offered_start, $old->offered_end, $old->offered_dentist_id, $old->offered_branch_id);
                }
            }
        }

        return $n;
    }

    /** The patient takes the offered slot: the appointment is created right away. Returns [ok, message]. */
    public function confirm(int $waitlistId, int $patientId): array
    {
        $w = DB::table('waitlist')->where('waitlist_id', $waitlistId)->where('patient_id', $patientId)->first();
        if (! $w || $w->status !== 'offered') {
            return [false, 'This offer is no longer open.'];
        }
        if (empty($w->offered_start)) {
            DB::table('waitlist')->where('waitlist_id', $waitlistId)->update(['status' => 'confirmed']);

            return [true, 'Offer confirmed. The clinic will finish your booking.'];
        }
        $slots = app(SlotService::class);
        if (! $slots->isFree((int) $w->offered_dentist_id, $w->offered_start, $w->offered_end)) {
            DB::table('waitlist')->where('waitlist_id', $waitlistId)->update(['status' => 'waiting', 'updated_at' => now()]);

            return [false, 'Sorry, that slot was already taken. You are back on the waiting list.'];
        }
        $a = new Appointment;
        $a->forceFill(Dbx::only('appointments', [
            'patient_id' => $patientId, 'dentist_id' => $w->offered_dentist_id, 'branch_id' => $w->offered_branch_id, 'service_id' => $w->service_id,
            'start_time' => $w->offered_start, 'end_time' => $w->offered_end, 'status' => 'confirmed', 'risk_flag' => 0, 'booked_via' => 'waitlist',
        ]))->save();
        DB::table('waitlist')->where('waitlist_id', $waitlistId)->update(['status' => 'confirmed', 'updated_at' => now()]);

        return [true, 'Slot confirmed for ' . Carbon::parse($w->offered_start)->format('D, M j \a\t g:i A') . '. See you!'];
    }
}
