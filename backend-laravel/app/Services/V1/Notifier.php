<?php

namespace App\Services\V1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Messages that leave the system.
 *
 * Until now a patient only learned of a staff reply by opening the portal, and staff only saw an
 * escalated question by opening the inbox. This sends the same events out by electronic mail, and
 * by text message when an SMS gateway key is configured.
 *
 * Nothing here is allowed to break the request that triggered it: every send is guarded, and a
 * failure is written to the log and otherwise ignored. With MAIL_MAILER=log the mail is written to
 * storage/logs instead of being delivered, which is the behaviour on a server with no mail
 * credentials.
 */
class Notifier
{
    /** Tell a patient something. Returns the channels that were used. */
    public function toPatient($patient, string $subject, string $text): array
    {
        if (! $patient) {
            return [];
        }
        $sent = [];

        if (! empty($patient->email)) {
            $this->guard('patient email', function () use ($patient, $subject, $text) {
                Mail::raw($text, fn ($m) => $m->to($patient->email)->subject($subject));
            }) && $sent[] = 'email';
        }

        if (! empty($patient->contact_no) && env('SEMAPHORE_API_KEY')) {
            $this->guard('patient sms', function () use ($patient, $text) {
                $r = Http::asForm()->post('https://api.semaphore.co/api/v4/messages', [
                    'apikey' => env('SEMAPHORE_API_KEY'),
                    'number' => $patient->contact_no,
                    'message' => $text,
                    'sendername' => env('SEMAPHORE_SENDER', 'SmileSync'),
                ]);
                if (! $r->successful()) {
                    throw new \RuntimeException('SMS HTTP ' . $r->status());
                }
            }) && $sent[] = 'sms';
        }

        return $sent;
    }

    /** Tell the clinic something. Uses the clinic's own address, else the configured fallback. */
    public function toClinic(?int $clinicId, string $subject, string $text): array
    {
        $to = null;
        if ($clinicId) {
            $to = DB::table('clinics')->where('clinic_id', $clinicId)->value('email');
        }
        $to = $to ?: env('CLINIC_NOTICE_EMAIL');
        if (! $to) {
            return [];
        }

        return $this->guard('clinic email', function () use ($to, $subject, $text) {
            Mail::raw($text, fn ($m) => $m->to($to)->subject($subject));
        }) ? ['email'] : [];
    }

    private function guard(string $what, callable $send): bool
    {
        try {
            $send();

            return true;
        } catch (\Throwable $e) {
            logger()->warning("Notifier {$what} failed: " . $e->getMessage());

            return false;
        }
    }
}
