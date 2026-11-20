<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Appointment;
use App\Services\V1\AssistantAI;
use App\Services\V1\SlotService;
use App\Services\V1\WaitlistService;
use App\Support\Dbx;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @group Assistant
 *
 * The SmileSync assistant. It understands English, Filipino or both, answers from the clinic's own data,
 * and books, reschedules and cancels through the same slot engine as the rest of the API.
 */
class ChatController extends APIController
{
    private SlotService $slots;
    private $user;
    private $patient;
    private string $in = '';

    /**
     * POST /api/v1/chat
     * Body: {"message": "gusto ko magpa-appointment"}  or  {"action": "book_service", "data": {...}}
     */
    public function send(Request $request, SlotService $slots)
    {
        $this->slots = $slots;
        $this->user = $request->user();
        $this->patient = $this->user->patient;
        $action = $request->input('action');
        $data = (array) $request->input('data', []);
        $msg = trim((string) $request->input('message', ''));
        $this->in = $msg !== '' ? $msg : '[' . ($action ?: 'menu') . ']';

        if ($action) {
            return $this->byAction($action, $data);
        }

        return $this->byText(mb_strtolower($msg));
    }

    /** GET /api/v1/chat/history — the logged-in patient's last messages. */
    public function history(Request $request)
    {
        $p = $request->user()->patient;
        if (! $p) {
            return response()->json(['data' => []]);
        }
        $rows = DB::table('chat_logs')->where('patient_id', $p->patient_id)->orderByDesc('chat_id')->limit(30)->get()->reverse()->values();

        return response()->json(['data' => $rows]);
    }

    // ------------------------------------------------------------------ routing

    private function byAction(string $a, array $d)
    {
        return match ($a) {
            'menu' => $this->menu(),
            'book' => $this->pickService(),
            'book_service' => $this->pickDentist($d),
            'book_dentist' => $this->pickDate($d),
            'book_date' => $this->pickSlot($d),
            'book_slot' => $this->askConfirm($d),
            'book_confirm' => $this->confirmSlot($d),
            'back' => $this->goBack(),
            'join_waitlist' => $this->joinWaitlist($d),
            'my_appts' => $this->myAppointments(),
            'cancel' => $this->cancel($d),
            'resched' => $this->reschedule($d),
            'balance' => $this->balance(),
            'history' => $this->history_(),
            'services' => $this->services(),
            'hours' => $this->hours(),
            'confirm_offer' => $this->confirmOffer($d),
            'confirm_next' => $this->confirmNext(),
            'staff' => $this->escalate('You asked to speak with the clinic staff.'),
            default => $this->menu(),
        };
    }

    private function byText(string $t)
    {
        $has = fn (string $re) => (bool) preg_match($re, $t);

        if ($t === '' || $has('/^(hi|hello|hey|helo|kumusta|kamusta|good (morning|afternoon|evening)|magandang)/')) {
            return $this->menu();
        }
        if ($has('/^(back|go back|balik|bumalik|previous)\b/')) {
            return $this->goBack();
        }
        if ($has('/^(confirm|kumpirma|confirmed|oo po|oo|yes|sige po|sige)\b/')) {
            return $this->confirmNext();
        }
        if ($has('/pain|sakit|masakit|dugo|bleed|swell|namamaga|lagnat|fever|antibiotic|gamot|medicine|infect|allerg|sensitiv|ngipin ko|toothache|nana|pus/')) {
            return $this->escalate('Questions about symptoms or treatment need a dentist.');
        }
        // Booking, moving and cancelling are actions, so they stay with the deterministic flow and
        // are never handed to the model.
        if ($has('/resched|palitan|ilipat|ibang (oras|araw)|move my/')) {
            return $this->myAppointments('Which appointment do you want to move?');
        }
        if ($has('/cancel|kansel|ikansel|huwag na|di na|hindi na ako/')) {
            return $this->myAppointments('Which appointment do you want to cancel?');
        }
        if ($has('/\b(book|reserve)\b|magpa|mag-?book|pa-?book|schedule me|gusto ko magpa/')) {
            return $this->pickService();
        }
        // Changing their mind part-way through booking: start the choice again rather than
        // leaving the patient at a dead end.
        if ($has('/(change|palit|palitan|ibang|iba)\s*(ang\s*)?(dentist|doktor|doctor|service|serbisyo)/')) {
            return $this->pickService();
        }

        // Everything else is a question in the patient's own words. The model answers it from the
        // clinic's own data, because it reads the whole sentence; the rules below answer only the
        // first subject they recognise, so they are kept as the fallback for when the model
        // cannot answer or is unreachable.
        if ($reply = app(AssistantAI::class)->answer($this->in, $this->clinicId(), $this->patient)) {
            return $this->out($reply, [
                $this->opt('Book an appointment', 'book'),
                $this->opt('Main menu', 'menu'),
            ], 'ai_answer');
        }

        if ($has('/next|susunod|kailan|when is my|my appointment|appointment ko|schedule ko/')) {
            return $this->myAppointments();
        }
        if ($has('/balance|utang|unpaid|\bowe\b|bayarin|how much do i|magkano ang utang|\bbill/')) {
            return $this->balance();
        }
        if ($has('/history|\bpast\b|\bdati\b|last visit|huling|nakaraan|treatment ko/')) {
            return $this->history_();
        }
        if ($has('/hour|\bopen|oras|bukas|sarado|\bclose|\bwhere|saan|location|address|branch|sangay|lugar/')) {
            return $this->hours();
        }
        // a specific service in the message (English, Filipino or common words)
        $syn = ['cleaning' => 'prophylaxis', 'linis' => 'prophylaxis', 'paglilinis' => 'prophylaxis', 'pasta' => 'filling', 'bunot' => 'extraction',
            'pabunot' => 'extraction', 'pustiso' => 'denture', 'pustiso' => 'denture', 'paputi' => 'whitening', 'pagpaputi' => 'whitening',
            'xray' => 'x-ray', 'x ray' => 'x-ray', 'brace' => 'braces', 'pyorrhea' => 'prophylaxis', 'konsulta' => 'consultation', 'checkup' => 'consultation', 'check-up' => 'consultation'];
        $needles = [];
        foreach ($syn as $word => $needle) {
            if (str_contains($t, $word)) {
                $needles[] = $needle;
            }
        }
        foreach ($this->activeServices() as $s) {
            $name = mb_strtolower($s->service_name);
            $hit = str_contains($t, $name);
            foreach ($needles as $n) {
                $hit = $hit || str_contains($name, $n);
            }
            if ($hit) {
                return $this->out("{$s->service_name} costs ₱" . number_format($s->price, 2) . " and takes about {$s->duration_min} minutes.", [
                    $this->opt('Book ' . $s->service_name, 'book_service', ['serviceId' => $s->service_id]),
                    $this->opt('All services', 'services'),
                ], 'service_price');
            }
        }
        if ($has('/price|magkano|how much|service|rate|presyo|cost|fee|rates/')) {
            return $this->services();
        }

        if ($faq = $this->matchFaq($t)) {
            return $this->out($faq->answer, [$this->opt('Main menu', 'menu')], 'faq');
        }

        // No rule matched. Let the language model answer from the clinic's own data.
        if ($reply = app(AssistantAI::class)->answer($this->in, $this->clinicId(), $this->patient)) {
            return $this->out($reply, [
                $this->opt('Book an appointment', 'book'),
                $this->opt('Talk to staff', 'staff'),
                $this->opt('Main menu', 'menu'),
            ], 'ai_answer');
        }

        return $this->escalate("I'm not sure how to answer that.");
    }

    // ------------------------------------------------------------------ small talk and info

    private function menu()
    {
        $name = $this->user->name ?? '';
        $opts = [
            $this->opt('Book an appointment', 'book'),
            $this->opt('My appointments', 'my_appts'),
            $this->opt('My balance', 'balance'),
            $this->opt('Services and prices', 'services'),
            $this->opt('Clinic hours and branches', 'hours'),
            $this->opt('Talk to staff', 'staff'),
        ];

        if ($this->patient) {
            foreach (DB::table('waitlist')->where('patient_id', $this->patient->patient_id)->where('status', 'offered')->get() as $w) {
                $label = ! empty($w->offered_start) ? 'Take the open slot on ' . Carbon::parse($w->offered_start)->format('M j g:i A') : 'Confirm my waitlist offer';
                array_unshift($opts, $this->opt($label, 'confirm_offer', ['id' => $w->waitlist_id]));
            }
        }

        return $this->out("Hi {$name}! I'm the SmileSync assistant. I can book, move or cancel your appointment and answer questions about the clinic. Type in English or Filipino, or tap a button.", $opts, 'greeting');
    }

    private function services()
    {
        $rows = $this->activeServices();
        $lines = $rows->map(fn ($s) => "• {$s->service_name} — ₱" . number_format($s->price, 2) . " ({$s->duration_min} min)")->implode("\n");

        return $this->out("Here are our services:\n{$lines}", [$this->opt('Book an appointment', 'book'), $this->opt('Main menu', 'menu')], 'services');
    }

    private function hours()
    {
        $rows = DB::table('branches')->get();
        $lines = $rows->map(function ($b) {
            $name = $b->branch_name ?? $b->name ?? 'Branch';
            $time = isset($b->open_time) ? substr($b->open_time, 0, 5) . '–' . substr($b->close_time ?? '', 0, 5) : '';
            $addr = $b->address ?? '';

            return "• {$name}" . ($addr ? " — {$addr}" : '') . ($time ? " (open {$time})" : '');
        })->implode("\n");

        return $this->out("Our branches:\n{$lines}", [$this->opt('Book an appointment', 'book'), $this->opt('Main menu', 'menu')], 'hours');
    }

    private function matchFaq(string $t)
    {
        if (! Schema::hasTable('faq_entries')) {
            return null;
        }
        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $t), fn ($w) => mb_strlen($w) > 3);
        $best = null;
        $bestScore = 0;
        foreach (DB::table('faq_entries')->get() as $f) {
            $hay = mb_strtolower(($f->question ?? '') . ' ' . ($f->category ?? ''));
            $score = 0;
            foreach ($words as $w) {
                if (str_contains($hay, $w)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $best = $f;
                $bestScore = $score;
            }
        }

        return $bestScore >= 1 ? $best : null;
    }

    // ------------------------------------------------------------------ confirmations

    private function confirmNext()
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $a = Appointment::where('patient_id', $this->patient->patient_id)->where('status', 'pending')->where('start_time', '>=', now())->orderBy('start_time')->first();
        if (! $a) {
            return $this->out('You have no appointment waiting for confirmation.', [$this->opt('My appointments', 'my_appts'), $this->opt('Main menu', 'menu')], 'confirm');
        }
        $a->status = 'confirmed';
        $a->save();

        return $this->out('Thank you! Your appointment on ' . $this->when($a->start_time) . ' is confirmed.', [$this->opt('My appointments', 'my_appts'), $this->opt('Main menu', 'menu')], 'confirm');
    }

    private function confirmOffer(array $d)
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        [$ok, $msg] = app(WaitlistService::class)->confirm((int) ($d['id'] ?? 0), (int) $this->patient->patient_id);

        return $this->out(($ok ? '' : '') . $msg, [$this->opt('My appointments', 'my_appts'), $this->opt('Main menu', 'menu')], 'waitlist_confirm');
    }

    // ------------------------------------------------------------------ personal answers

    private function needPatient()
    {
        return $this->out('Please log in with a patient account first. Booking and personal records are only for logged-in patients.', [$this->opt('Main menu', 'menu')], 'login_required');
    }

    private function myAppointments(?string $ask = null)
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $rows = DB::table('appointments')
            ->join('services', 'services.service_id', '=', 'appointments.service_id')
            ->where('appointments.patient_id', $this->patient->patient_id)
            ->whereIn('appointments.status', ['pending', 'confirmed'])
            ->where('appointments.start_time', '>=', now())
            ->orderBy('appointments.start_time')->limit(5)
            ->get(['appointments.*', 'services.service_name']);

        if ($rows->isEmpty()) {
            return $this->out("You have no upcoming appointments. Want to book one?", [$this->opt('Book an appointment', 'book'), $this->opt('Main menu', 'menu')], 'next_appointment');
        }
        $lines = $rows->map(fn ($r) => '• ' . $this->when($r->start_time) . ' — ' . $r->service_name . ' (' . $r->status . ')')->implode("\n");
        $opts = [];
        foreach ($rows as $r) {
            $opts[] = $this->opt('Reschedule ' . Carbon::parse($r->start_time)->format('M j g:i A'), 'resched', ['id' => $r->appointment_id]);
            $opts[] = $this->opt('Cancel ' . Carbon::parse($r->start_time)->format('M j g:i A'), 'cancel', ['id' => $r->appointment_id]);
        }
        $opts[] = $this->opt('Main menu', 'menu');

        return $this->out(($ask ? $ask . "\n" : 'Your upcoming appointments:' . "\n") . $lines, $opts, 'next_appointment');
    }

    private function balance()
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $sum = (float) DB::table('invoices')->where('patient_id', $this->patient->patient_id)->sum('balance');
        $text = $sum > 0 ? 'Your unpaid balance is ₱' . number_format($sum, 2) . '. You can pay at the clinic counter.' : 'You have no unpaid balance. Thank you!';

        return $this->out($text, [$this->opt('Main menu', 'menu')], 'balance');
    }

    private function history_()
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $rows = DB::table('appointments')
            ->join('services', 'services.service_id', '=', 'appointments.service_id')
            ->where('appointments.patient_id', $this->patient->patient_id)->where('appointments.status', 'completed')
            ->orderByDesc('appointments.start_time')->limit(5)->get(['appointments.start_time', 'services.service_name']);
        $text = $rows->isEmpty() ? 'No past visits yet.' : "Your recent visits:\n" . $rows->map(fn ($r) => '• ' . $this->when($r->start_time) . ' — ' . $r->service_name)->implode("\n");

        $fu = DB::table('appointments')->where('follow_up_of', '>', 0)->where('patient_id', $this->patient->patient_id)->where('start_time', '>=', now())->orderBy('start_time')->first();
        if ($fu) {
            $text .= "\nFollow-up booked: " . $this->when($fu->start_time);
        }

        return $this->out($text, [$this->opt('Main menu', 'menu')], 'history');
    }

    // ------------------------------------------------------------------ booking flow

    private function pickService()
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $opts = $this->activeServices()
            ->map(fn ($s) => $this->opt($s->service_name . ' · ₱' . number_format($s->price), 'book_service', ['serviceId' => $s->service_id]))->all();

        return $this->out('Sure! Which service do you need?', $opts, 'book');
    }

    private function pickDentist(array $d)
    {
        $d = Arr::except($d, ['dentistId', 'date', 'start', 'end']);
        $opts = [];
        foreach (DB::table('dentists')->get() as $x) {
            $opts[] = $this->opt($this->dentistName($x), 'book_dentist', $d + ['dentistId' => $x->dentist_id]);
        }
        $opts[] = $this->back('← Back to services', 'book');
        $opts[] = $this->opt('Main menu', 'menu');

        return $this->out('Which dentist do you prefer?', $opts, 'book');
    }

    private function pickDate(array $d)
    {
        $d = Arr::except($d, ['date', 'start', 'end']);
        $service = DB::table('services')->where('service_id', $d['serviceId'] ?? 0)->first();
        $branch = $this->branchOf((int) ($d['dentistId'] ?? 0));
        if (! $service) {
            return $this->menu();
        }
        $opts = [];
        for ($i = 0; $i <= 21 && count($opts) < 6; $i++) {
            $date = now()->addDays($i)->toDateString();
            $n = count($this->slots->forDay((int) $d['dentistId'], $date, (int) $service->duration_min, $branch));
            if ($n > 0) {
                $opts[] = $this->opt(Carbon::parse($date)->format('D, M j') . " ({$n} slots)", 'book_date', $d + ['date' => $date]);
            }
        }
        if (! $opts) {
            return $this->out('That dentist has no open slots in the next 3 weeks. I can put you on the waiting list and tell you when a slot opens.', [
                $this->opt('Join the waiting list', 'join_waitlist', $d),
                $this->back('← Pick another dentist', 'book_service', Arr::only($d, ['serviceId'])),
                $this->opt('Main menu', 'menu'),
            ], 'book');
        }
        $opts[] = empty($d['reschedule'])
            ? $this->back('← Back to dentists', 'book_service', Arr::only($d, ['serviceId']))
            : $this->back('← Back to my appointments', 'my_appts');
        $opts[] = $this->opt('Main menu', 'menu');

        return $this->out('Pick a date:', $opts, 'book');
    }

    private function pickSlot(array $d)
    {
        $service = DB::table('services')->where('service_id', $d['serviceId'] ?? 0)->first();
        $branch = $this->branchOf((int) $d['dentistId']);
        $list = $this->slots->forDay((int) $d['dentistId'], $d['date'], (int) $service->duration_min, $branch);
        $opts = [];
        foreach (array_slice($list, 0, 14) as $s) {
            $opts[] = $this->opt(Carbon::parse($s['startTime'])->format('g:i A'), 'book_slot', $d + ['start' => $s['startTime'], 'end' => $s['endTime']]);
        }
        if (! $opts) {
            return $this->pickDate($d);
        }
        $opts[] = $this->back('← Back to dates', 'book_dentist', Arr::only($d, ['serviceId', 'dentistId', 'reschedule']));
        $opts[] = $this->opt('Main menu', 'menu');

        return $this->out('Pick a time on ' . Carbon::parse($d['date'])->format('l, M j') . ':', $opts, 'book');
    }

    /** Last check before the booking is saved, so a wrong tap can be undone. */
    private function askConfirm(array $d)
    {
        $service = DB::table('services')->where('service_id', $d['serviceId'] ?? 0)->first();
        $dentist = DB::table('dentists')->where('dentist_id', $d['dentistId'] ?? 0)->first();
        if (! $service || ! $dentist || empty($d['start'])) {
            return $this->menu();
        }
        $moving = ! empty($d['reschedule']);
        $text = ($moving ? "Please confirm the new time:\n" : "Please confirm your booking:\n")
            . "• {$service->service_name}\n• " . $this->dentistName($dentist) . "\n• " . $this->when($d['start']);

        return $this->out($text, [
            $this->opt($moving ? 'Yes, move it' : 'Yes, book it', 'book_confirm', $d),
            $this->back('← Pick another time', 'book_date', Arr::only($d, ['serviceId', 'dentistId', 'date', 'reschedule'])),
            $this->opt('Main menu', 'menu'),
        ], 'book');
    }

    private function confirmSlot(array $d)
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $service = DB::table('services')->where('service_id', $d['serviceId'])->first();
        if (! $this->slots->isFree((int) $d['dentistId'], $d['start'], $d['end'], $d['reschedule'] ?? null)) {
            return $this->out('Sorry, that time was just taken by someone else. Please pick another slot.', [$this->opt('Show other times', 'book_dentist', $d)], 'book');
        }

        // reschedule an existing booking
        if (! empty($d['reschedule'])) {
            $a = Appointment::where('appointment_id', $d['reschedule'])->where('patient_id', $this->patient->patient_id)->first();
            if (! $a) {
                return $this->menu();
            }
            $a->forceFill(['start_time' => $d['start'], 'end_time' => $d['end'], 'dentist_id' => $d['dentistId'], 'status' => 'pending'])->save();

            return $this->out('Done! Your appointment is moved to ' . $this->when($d['start']) . '.', [$this->opt('My appointments', 'my_appts'), $this->opt('Main menu', 'menu')], 'reschedule');
        }

        $threshold = (int) Settings::get($this->user->clinic_id ?? null, 'no_show_threshold', 3); // set by the clinic (FR-28)
        $risk = ((int) ($this->patient->no_show_count ?? 0)) >= $threshold ? 1 : 0;

        $a = new Appointment;
        $a->forceFill(Dbx::only('appointments', [
            'patient_id' => $this->patient->patient_id,
            'dentist_id' => $d['dentistId'],
            'branch_id' => $this->branchOf((int) $d['dentistId']),
            'service_id' => $d['serviceId'],
            'start_time' => $d['start'],
            'end_time' => $d['end'],
            'status' => 'pending',
            'risk_flag' => $risk,
            'booked_via' => 'chatbot',
        ]))->save();

        return $this->out("You're booked!\n{$service->service_name} on " . $this->when($d['start']) . '.' . "\nWe will remind you before the visit.", [$this->opt('My appointments', 'my_appts'), $this->opt('Main menu', 'menu')], 'booked');
    }

    private function joinWaitlist(array $d)
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $exists = DB::table('waitlist')->where('patient_id', $this->patient->patient_id)->where('service_id', $d['serviceId'])->whereIn('status', ['waiting', 'offered'])->exists();
        if (! $exists) {
            Dbx::insert('waitlist', [
                'patient_id' => $this->patient->patient_id, 'service_id' => $d['serviceId'], 'branch_id' => $this->branchOf((int) $d['dentistId']),
                'dentist_id' => $d['dentistId'], 'status' => 'waiting',
            ]);
        }

        return $this->out("You're on the waiting list. I'll offer you the first slot that opens up.", [$this->opt('Main menu', 'menu')], 'waitlist');
    }

    private function cancel(array $d)
    {
        $a = $this->patient ? Appointment::where('appointment_id', $d['id'] ?? 0)->where('patient_id', $this->patient->patient_id)->first() : null;
        if (! $a || ! in_array($a->status, ['pending', 'confirmed'])) {
            return $this->out('I could not find that appointment, or it can no longer be cancelled.', [$this->opt('My appointments', 'my_appts')], 'cancel');
        }
        $a->status = 'cancelled';
        $a->save(); // frees the slot and offers it to the waiting list

        return $this->out('Your appointment on ' . $this->when($a->start_time) . ' is cancelled. The slot is open again.', [$this->opt('Book a new one', 'book'), $this->opt('Main menu', 'menu')], 'cancel');
    }

    private function reschedule(array $d)
    {
        $a = $this->patient ? Appointment::where('appointment_id', $d['id'] ?? 0)->where('patient_id', $this->patient->patient_id)->first() : null;
        if (! $a || ! in_array($a->status, ['pending', 'confirmed'])) {
            return $this->out('I could not find that appointment.', [$this->opt('My appointments', 'my_appts')], 'reschedule');
        }

        return $this->pickDate(['serviceId' => $a->service_id, 'dentistId' => $a->dentist_id, 'reschedule' => $a->appointment_id]);
    }

    // ------------------------------------------------------------------ escalation + output

    /** The clinic the conversation belongs to, so the assistant answers with that clinic's data. */
    private function clinicId(): ?int
    {
        $cid = $this->patient->clinic_id ?? $this->user->clinic_id ?? null;

        return $cid ? (int) $cid : null;
    }

    private function escalate(string $why)
    {
        // Let the clinic know by mail too, so an escalated question is not waiting unseen in the
        // inbox until someone happens to sign in.
        $who = $this->patient->full_name ?? ($this->user->name ?? 'A patient');
        app(\App\Services\V1\Notifier::class)->toClinic(
            $this->clinicId(),
            'A patient question needs a reply',
            "{$who} asked the assistant:\n\n\"{$this->in}\"\n\nThe assistant could not answer it, so it has been passed to staff. Open the staff inbox in SmileSync to reply."
        );

        return $this->out($why . " I've passed your message to our clinic staff. Their reply will appear right here in this chat. For urgent pain or bleeding, please call the clinic.", [$this->opt('Main menu', 'menu')], 'escalated', true);
    }

    private function out(string $text, array $options = [], string $intent = 'other', bool $escalated = false)
    {
        try {
            Dbx::insert('chat_logs', [
                'patient_id' => $this->patient->patient_id ?? null,
                'message_in' => $this->in, 'message_out' => $text, 'intent' => $intent,
                'escalated' => $escalated ? 1 : 0, 'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            // never block a reply because logging failed
        }

        return response()->json(['reply' => $text, 'options' => $options, 'intent' => $intent, 'escalated' => $escalated]);
    }

    /** Active services for the patient's clinic, one row per service name. */
    private function activeServices()
    {
        $q = DB::table('services')->where('is_active', 1);
        $cid = $this->patient->clinic_id ?? $this->user->clinic_id ?? null;
        if ($cid && Dbx::has('services', 'clinic_id') && DB::table('services')->where('clinic_id', $cid)->exists()) {
            $q->where('clinic_id', $cid);
        }

        return $q->orderBy('service_name')->get()->unique('service_name')->values();
    }

    /** A Back button. It is also remembered so typing "back" does the same thing. */
    private function back(string $label, string $action, array $data = []): array
    {
        if ($this->user) {
            Cache::put('chat-back-' . $this->user->id, [$action, $data], 1800);
        }

        return $this->opt($label, $action, $data);
    }

    private function goBack()
    {
        $b = $this->user ? Cache::get('chat-back-' . $this->user->id) : null;
        if (! is_array($b)) {
            return $this->menu();
        }

        return $this->byAction($b[0], (array) $b[1]);
    }

    private function opt(string $label, string $action, array $data = []): array
    {
        return ['label' => $label, 'action' => $action, 'data' => (object) $data];
    }

    private function when($t): string
    {
        return Carbon::parse($t)->format('D, M j \a\t g:i A');
    }

    private function branchOf(int $dentistId): ?int
    {
        return DB::table('dentist_schedules')->where('dentist_id', $dentistId)->whereNotNull('day_of_week')->value('branch_id');
    }

    private function dentistName($d): string
    {
        if (! empty($d->full_name)) {
            return 'Dr. ' . preg_replace('/^dr\.?\s*/i', '', $d->full_name);
        }
        if (! empty($d->user_id)) {
            $n = DB::table('users')->where('id', $d->user_id)->value('name');
            if ($n) {
                return 'Dr. ' . preg_replace('/^dr\.?\s*/i', '', $n);
            }
        }

        return 'Dentist #' . $d->dentist_id;
    }
}
