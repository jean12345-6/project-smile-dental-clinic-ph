<?php

namespace App\Services\V1;

use App\Support\Dbx;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Reminders, risk flags, stale sessions and expired offers. Run by the schedule (smilesync:reminders)
 * or by the "Run reminders now" button.
 *
 * Email goes through Laravel Mail (set MAIL_* in .env; MAIL_MAILER=log writes to storage/logs).
 * SMS goes through Semaphore when SEMAPHORE_API_KEY is set in .env.
 */
class ReminderService
{
    public function run(?int $clinicId = null): array
    {
        $hours = (int) Settings::get($clinicId, 'reminder_hours', 24);
        $threshold = (int) Settings::get($clinicId, 'no_show_threshold', 3);
        $sent = 0;
        $failed = 0;

        $due = DB::table('appointments')
            ->join('patients', 'patients.patient_id', '=', 'appointments.patient_id')
            ->join('services', 'services.service_id', '=', 'appointments.service_id')
            ->whereIn('appointments.status', ['pending', 'confirmed'])
            ->whereBetween('appointments.start_time', [now(), now()->addHours($hours)])
            ->get(['appointments.appointment_id', 'appointments.start_time', 'patients.full_name', 'patients.email', 'patients.contact_no', 'services.service_name']);

        foreach ($due as $a) {
            if (DB::table('reminders')->where('appointment_id', $a->appointment_id)->exists()) {
                continue;
            }
            $when = date('D, M j \a\t g:i A', strtotime($a->start_time));
            $text = "Hi {$a->full_name}, this is SmileSync. Reminder: {$a->service_name} on {$when}. Open the assistant and send CONFIRM to confirm, or tell us if you need to move it.";
            $channels = [];
            if ($a->email) {
                $channels['email'] = fn () => Mail::raw($text, fn ($m) => $m->to($a->email)->subject('Your dental appointment'));
            }
            if ($a->contact_no && env('SEMAPHORE_API_KEY')) {
                $channels['sms'] = function () use ($a, $text) {
                    $r = Http::asForm()->post('https://api.semaphore.co/api/v4/messages', ['apikey' => env('SEMAPHORE_API_KEY'), 'number' => $a->contact_no, 'message' => $text, 'sendername' => env('SEMAPHORE_SENDER', 'SmileSync')]);
                    if (! $r->successful()) {
                        throw new \RuntimeException('SMS failed');
                    }
                };
            }
            if (! $channels) {
                Dbx::insert('reminders', ['appointment_id' => $a->appointment_id, 'type' => 'appointment', 'channel' => 'none', 'send_time' => now()->toDateTimeString(), 'send_status' => 'failed']);
                $failed++;
            }
            foreach ($channels as $name => $send) {
                try {
                    $send();
                    $status = 'sent';
                    $sent++;
                } catch (\Throwable $e) {
                    $status = 'failed';
                    $failed++;
                    logger()->warning("Reminder {$name} failed for appointment {$a->appointment_id}: " . $e->getMessage());
                }
                Dbx::insert('reminders', ['appointment_id' => $a->appointment_id, 'type' => 'appointment', 'channel' => $name, 'send_time' => now()->toDateTimeString(), 'send_status' => $status]);
            }
        }

        // no-show counts and risk flags (FR-28)
        DB::statement("UPDATE patients p SET no_show_count = (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id AND a.status = 'no_show')");
        $flagged = DB::table('appointments')->whereIn('status', ['pending', 'confirmed'])
            ->whereIn('patient_id', DB::table('patients')->where('no_show_count', '>=', $threshold)->select('patient_id'))->update(['risk_flag' => 1]);

        // offers that nobody confirmed move on to the next person
        $expired = app(WaitlistService::class)->expireOld((int) Settings::get($clinicId, 'offer_expiry_hours', 2));

        // sessions that were never closed ended by timeout (FR-04)
        $stale = DB::table('session_logs')->whereNull('logout_time')->where('login_time', '<', now()->subMinutes(40))
            ->update(['logout_time' => DB::raw('DATE_ADD(login_time, INTERVAL 30 MINUTE)'), 'end_reason' => 'timeout']);

        return ['remindersSent' => $sent, 'remindersFailed' => $failed, 'highRiskFlagged' => $flagged, 'offersExpired' => $expired, 'sessionsClosed' => $stale, 'reminderHours' => $hours, 'noShowThreshold' => $threshold];
    }
}
