<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Appointment;
use App\Services\V1\AssistantAI;
use App\Services\V1\SlotService;
use App\Services\V1\WaitlistService;
use App\Support\ClinicHours;
use App\Support\Dbx;
use App\Support\Scope;
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
    /** Set only while a list of times is on screen; a typed time is matched against that list alone. */
    private bool $keepSlots = false;

    /**
     * POST /api/v1/chat
     * Body: {"message": "gusto ko magpa-appointment"}  or  {"action": "book_service", "data": {...}}
     */
    public function send(Request $request, SlotService $slots)
    {
        $this->slots = $slots;
        $this->keepSlots = false;
        $this->user = $request->user();
        $this->patient = $this->user->patient;
        $action = $request->input('action');
        $data = (array) $request->input('data', []);
        $msg = trim((string) $request->input('message', ''));
        $this->in = $msg !== '' ? $msg : '[' . ($action ?: 'menu') . ']';

        // The language is decided by what the patient types and kept for the buttons that
        // follow, so a conversation begun in Filipino stays in Filipino when they tap a button.
        if ($msg !== '' && $this->user && ($lang = $this->typedLanguage()) !== null) {
            Cache::put('chat-lang-' . $this->user->id, $lang, 21600);
        }

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
            'cancel' => $this->askCancel($d),
            'cancel_yes' => $this->cancel($d),
            'resched' => $this->reschedule($d),
            'balance' => $this->balance(),
            'history' => $this->history_(),
            'services' => $this->services(),
            'hours' => $this->hours(),
            'confirm_offer' => $this->confirmOffer($d),
            'confirm_next' => $this->confirmNext(),
            'staff' => $this->escalate(['en' => 'You asked to speak with the clinic staff.', 'fil' => 'Hiniling n\'yo pong makausap ang staff ng clinic.']),
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
            return $this->escalate(['en' => 'Questions about symptoms or treatment need a dentist.', 'fil' => 'Ang mga tanong tungkol sa sakit o paggamot ay kailangang sagutin ng dentista.']);
        }
        // Booking, moving and cancelling are actions, so they stay with the deterministic flow and
        // are never handed to the model.
        if ($has('/resched|palitan|ilipat|ibang (oras|araw)|move my/')) {
            return $this->myAppointments($this->tr('Which appointment do you want to move?', 'Aling appointment po ang gusto n\'yong ilipat?'));
        }
        if ($has('/cancel|kansel|ikansel|huwag na|di na|hindi na ako/')) {
            return $this->myAppointments($this->tr('Which appointment do you want to cancel?', 'Aling appointment po ang gusto n\'yong i-cancel?'));
        }
        // A time typed while the list of times is on screen ("10:30 AM") is treated as a choice
        // from that list, so a taken time gets a clear answer instead of a general reply.
        if ($r = $this->typedTime($t)) {
            return $r;
        }
        if ($has('/\b(book|reserve)\b|magpa|mag-?book|pa-?book|schedule me|gusto ko magpa/')) {
            // The service and the day are often in the same sentence ("magpa-cleaning sa
            // susunod na linggo"), so they are taken from it instead of asked for again.
            $hint = $this->dayHint($t);
            $sid = $this->serviceFromText($t);

            return $sid ? $this->pickDentist(['serviceId' => $sid] + $hint) : $this->pickService($hint);
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
        if ($r = $this->aiReply()) {
            return $r;
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
        if ($sid = $this->serviceFromText($t)) {
            $s = $this->activeServices()->firstWhere('service_id', $sid);

            return $this->out($this->tr("{$s->service_name} costs ₱" . number_format($s->price, 2) . " and takes about {$s->duration_min} minutes.",
                "Ang {$s->service_name} po ay ₱" . number_format($s->price, 2) . " at tumatagal nang mga {$s->duration_min} minuto."), [
                $this->opt('Book ' . $s->service_name, 'book_service', ['serviceId' => $s->service_id]),
                $this->opt($this->tr('All services', 'Lahat ng serbisyo'), 'services'),
            ], 'service_price');
        }
        if ($has('/price|magkano|how much|service|rate|presyo|cost|fee|rates/')) {
            return $this->services();
        }

        if ($faq = $this->matchFaq($t)) {
            return $this->out($faq->answer, [$this->opt('Main menu', 'menu')], 'faq');
        }

        // No rule matched. Let the language model answer from the clinic's own data.
        if ($r = $this->aiReply()) {
            return $r;
        }

        return $this->escalate(['en' => "I'm not sure how to answer that.", 'fil' => 'Pasensya po, hindi ko po alam ang sagot diyan.']);
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

        // The full introduction is worth reading once. Coming back to the menu later in the same
        // conversation should not repeat the whole paragraph.
        $greeted = $this->patient && DB::table('chat_logs')
            ->where('patient_id', $this->patient->patient_id)
            ->where('timestamp', '>=', now()->subHours(6))
            ->where('intent', '!=', 'greeting')
            ->exists();

        $text = $greeted
            ? $this->tr("What would you like to do, {$name}?", "Ano po ang gusto n'yong gawin, {$name}?")
            : $this->tr("Hi {$name}! I'm the SmileSync assistant. I can book, move or cancel your appointment and answer questions about the clinic. Type in English or Filipino, or tap a button.",
                "Hi {$name}! Ako po ang SmileSync assistant. Puwede ko po kayong i-book, ilipat o i-cancel ang appointment, at sagutin ang mga tanong tungkol sa clinic. Mag-type po sa English o Filipino, o pumindot ng button.");

        return $this->out($text, $opts, 'greeting');
    }

    private function services()
    {
        $rows = $this->activeServices();
        $lines = $rows->map(fn ($s) => "• {$s->service_name} — ₱" . number_format($s->price, 2) . " ({$s->duration_min} min)")->implode("\n");

        return $this->out("Here are our services:\n{$lines}", [$this->opt('Book an appointment', 'book'), $this->opt('Main menu', 'menu')], 'services');
    }

    private function hours()
    {
        $rows = Scope::table(DB::table('branches'), 'branches', $this->user)->get();
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
        return $this->out($this->tr('Please log in with a patient account first. Booking and personal records are only for logged-in patients.', 'Mag-log in po muna gamit ang patient account. Para lang sa naka-log in na pasyente ang booking at personal na records.'), [$this->opt('Main menu', 'menu')], 'login_required');
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
            return $this->out($this->tr('You have no upcoming appointments. Want to book one?', 'Wala po kayong paparating na appointment. Gusto n\'yo po bang mag-book?'), [$this->opt('Book an appointment', 'book'), $this->opt('Main menu', 'menu')], 'next_appointment');
        }
        $lines = $rows->map(fn ($r) => '• ' . $this->when($r->start_time) . ' — ' . $r->service_name . ' (' . $r->status . ')')->implode("\n");
        $opts = [];
        foreach ($rows as $r) {
            $opts[] = $this->opt($this->tr('Reschedule ', 'Ilipat ') . Carbon::parse($r->start_time)->format('M j g:i A'), 'resched', ['id' => $r->appointment_id]);
            $opts[] = $this->opt($this->tr('Cancel ', 'I-cancel ') . Carbon::parse($r->start_time)->format('M j g:i A'), 'cancel', ['id' => $r->appointment_id]);
        }
        $opts[] = $this->opt('Main menu', 'menu');

        return $this->out(($ask ? $ask . "\n" : $this->tr('Your upcoming appointments:', 'Ang mga paparating n\'yong appointment:') . "\n") . $lines, $opts, 'next_appointment');
    }

    private function balance()
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $sum = (float) DB::table('invoices')->where('patient_id', $this->patient->patient_id)->sum('balance');
        $text = $sum > 0
            ? $this->tr('Your unpaid balance is ₱' . number_format($sum, 2) . '. You can pay at the clinic counter.', 'Ang hindi pa nababayarang balanse n\'yo ay ₱' . number_format($sum, 2) . '. Puwede po kayong magbayad sa counter ng clinic.')
            : $this->tr('You have no unpaid balance. Thank you!', 'Wala po kayong hindi nababayarang balanse. Salamat po!');

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


    // ------------------------------------------------------------------ language and hints

    /** The patient's language for this conversation: what they last typed, kept for the buttons. */
    private function fil(): bool
    {
        $typed = str_starts_with($this->in, '[') ? null : $this->typedLanguage();
        if ($typed !== null) {
            return $typed === 'fil';
        }

        return $this->user && Cache::get('chat-lang-' . $this->user->id) === 'fil';
    }

    /**
     * The language of what was typed: 'fil', 'en', or null when it is too short to tell,
     * such as "10:30 AM", in which case the conversation keeps the language it had.
     */
    private function typedLanguage(): ?string
    {
        if ($this->writtenInFilipino()) {
            return 'fil';
        }

        return preg_match_all('/[a-z]{3,}/i', $this->in) >= 2 ? 'en' : null;
    }

    /** The English or the Filipino wording, by the patient's language. */
    private function tr(string $en, string $fil): string
    {
        return $this->fil() ? $fil : $en;
    }

    /** The service named in a sentence, in English, Filipino or everyday words, or null. */
    private function serviceFromText(string $t): ?int
    {
        $syn = ['cleaning' => 'prophylaxis', 'linis' => 'prophylaxis', 'paglilinis' => 'prophylaxis', 'pasta' => 'filling', 'bunot' => 'extraction',
            'pabunot' => 'extraction', 'pustiso' => 'denture', 'paputi' => 'whitening', 'pagpaputi' => 'whitening',
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
                return (int) $s->service_id;
            }
        }

        return null;
    }

    /**
     * The day asked for in a sentence: "next week", "tomorrow", or a named day.
     * In Filipino "linggo" is both "week" and "Sunday", so "susunod na linggo" is read first.
     */
    private function dayHint(string $t): array
    {
        if (preg_match('/next week|susunod na linggo|sa isang linggo|sa sunod na linggo/', $t)) {
            return ['from' => now()->next(Carbon::MONDAY)->toDateString()];
        }
        if (preg_match('/tomorrow|bukas/', $t)) {
            return ['from' => now()->addDay()->toDateString()];
        }
        $days = ['monday|lunes' => 1, 'tuesday|martes' => 2, 'wednesday|miyerkules|miyerkoles' => 3, 'thursday|huwebes' => 4,
            'friday|biyernes|byernes' => 5, 'saturday|sabado' => 6, 'sunday|linggo' => 7];
        foreach ($days as $re => $n) {
            if (preg_match('/\b(' . $re . ')\b/', $t)) {
                return ['day' => $n];
            }
        }

        return [];
    }

    /** A time typed while the times for a day are on screen. */
    private function typedTime(string $t)
    {
        if (! $this->user || ! preg_match('/^\s*(?:at |sa |mga )?(\d{1,2})(?::(\d{2}))?\s*(am|pm|a\.m\.|p\.m\.)?\s*$/', $t, $m)) {
            return null;
        }
        $d = Cache::get('chat-slots-' . $this->user->id);
        if (! is_array($d) || empty($d['date'])) {
            return null;
        }
        $h = (int) $m[1];
        $ap = str_replace('.', '', $m[3] ?? '');
        if ($ap === 'pm' && $h < 12) {
            $h += 12;
        } elseif ($ap === 'am' && $h === 12) {
            $h = 0;
        } elseif ($ap === '' && $h < 7) {
            $h += 12; // "3" in a clinic means three in the afternoon
        }
        $at = Carbon::parse($d['date'])->setTime($h, (int) ($m[2] ?? 0));

        $service = DB::table('services')->where('service_id', $d['serviceId'] ?? 0)->first();
        if (! $service) {
            return null;
        }
        foreach ($this->slots->forDay((int) $d['dentistId'], $d['date'], (int) $service->duration_min, $this->branchOf((int) $d['dentistId']), $this->user) as $s) {
            if (Carbon::parse($s['startTime'])->equalTo($at)) {
                return $this->askConfirm($d + ['start' => $s['startTime'], 'end' => $s['endTime']]);
            }
        }

        $taken = DB::table('appointments')->where('dentist_id', $d['dentistId'])->whereIn('status', ['pending', 'confirmed'])
            ->where('start_time', '<', $at->copy()->addMinutes((int) $service->duration_min))->where('end_time', '>', $at)->exists();
        $label = $at->format('g:i A') . $this->tr(' on ', ', ') . $at->format('D, M j');
        $why = $taken
            ? $this->tr("Sorry, {$label} is already taken. Two appointments cannot share a time.", "Pasensya po, may naka-book na sa {$label}. Hindi po puwedeng dalawa ang appointment sa iisang oras.")
            : $this->tr("Sorry, {$label} is not available with this dentist.", "Pasensya po, hindi available ang {$label} sa dentistang ito.");

        return $this->pickSlot($d, $why);
    }

    /** One more step before cancelling, so a stray tap does not cancel a visit. */
    private function askCancel(array $d)
    {
        $a = $this->patient ? Appointment::where('appointment_id', $d['id'] ?? 0)->where('patient_id', $this->patient->patient_id)->first() : null;
        if (! $a || ! in_array($a->status, ['pending', 'confirmed'])) {
            return $this->out($this->tr('I could not find that appointment, or it can no longer be cancelled.', 'Hindi ko po makita ang appointment na iyan, o hindi na ito puwedeng i-cancel.'), [$this->opt($this->tr('My appointments', 'Mga appointment ko'), 'my_appts')], 'cancel');
        }

        return $this->out($this->tr('Cancel your appointment on ' . $this->when($a->start_time) . '?', 'I-cancel po ba ang appointment n\'yo sa ' . $this->when($a->start_time) . '?'), [
            $this->opt($this->tr('Yes, cancel it', 'Oo, i-cancel'), 'cancel_yes', ['id' => $a->appointment_id]),
            $this->opt($this->tr('No, keep it', 'Hindi, huwag i-cancel'), 'my_appts'),
        ], 'cancel');
    }

    // ------------------------------------------------------------------ booking flow

    private function pickService(array $hint = [])
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $opts = $this->activeServices()
            ->map(fn ($s) => $this->opt($s->service_name . ' · ₱' . number_format($s->price), 'book_service', ['serviceId' => $s->service_id] + $hint))->all();

        return $this->out($this->tr('Sure! Which service do you need?', 'Sige po! Anong serbisyo po ang kailangan n\'yo?'), $opts, 'book');
    }

    private function pickDentist(array $d)
    {
        $d = Arr::except($d, ['dentistId', 'date', 'start', 'end']);
        $opts = [];
        // Unscoped, this listed every dentist on the server, so a patient at one clinic was
        // offered the other clinic's dentists and could book with them.
        foreach (Scope::table(DB::table('dentists'), 'dentists', $this->user)->get() as $x) {
            $opts[] = $this->opt($this->dentistName($x), 'book_dentist', $d + ['dentistId' => $x->dentist_id]);
        }
        $opts[] = $this->back($this->tr('← Back to services', '← Bumalik sa mga serbisyo'), 'book');
        $opts[] = $this->opt($this->tr('Main menu', 'Main menu'), 'menu');

        $svc = DB::table('services')->where('service_id', $d['serviceId'] ?? 0)->value('service_name');

        return $this->out($svc
            ? $this->tr("{$svc} it is. Which dentist do you prefer?", "{$svc} po. Sinong dentista po ang gusto n'yo?")
            : $this->tr('Which dentist do you prefer?', "Sinong dentista po ang gusto n'yo?"), $opts, 'book');
    }

    private function pickDate(array $d)
    {
        $d = Arr::except($d, ['date', 'start', 'end']);
        $service = DB::table('services')->where('service_id', $d['serviceId'] ?? 0)->first();
        $branch = $this->branchOf((int) ($d['dentistId'] ?? 0));
        if (! $service) {
            return $this->menu();
        }
        // The days the clinic is closed, named outright, so a patient looking for Sunday is told
        // the clinic is closed instead of wondering why the day is missing.
        $closed = [];
        for ($i = 0; $i < 7; $i++) {
            $day = now()->addDays($i);
            if (ClinicHours::on($this->clinicId(), $day->toDateString()) === null) {
                $closed[$day->dayOfWeekIso] = $this->fil()
                    ? ['', 'Lunes', 'Martes', 'Miyerkules', 'Huwebes', 'Biyernes', 'Sabado', 'Linggo'][$day->dayOfWeekIso]
                    : $day->format('l');
            }
        }
        ksort($closed);

        $from = ! empty($d['from']) && Carbon::parse($d['from'])->isAfter(now()) ? Carbon::parse($d['from'])->startOfDay() : now()->startOfDay();
        $want = (int) ($d['day'] ?? 0);
        $note = '';
        if ($want && isset($closed[$want])) {
            $note = $this->tr("The clinic is closed on {$closed[$want]}. ", "Sarado po ang clinic tuwing {$closed[$want]}. ");
            $want = 0;
        }
        if (! empty($d['from'])) {
            $note .= $this->tr('Here are the dates from ' . $from->format('D, M j') . '. ', 'Ito po ang mga petsa simula ' . $from->format('D, M j') . '. ');
        }

        $collect = function (int $only) use ($d, $from, $service, $branch) {
            $opts = [];
            for ($i = 0; $i <= 28 && count($opts) < 6; $i++) {
                $date = $from->copy()->addDays($i);
                if ($date->lt(now()->startOfDay()) || ($only && $date->dayOfWeekIso !== $only)) {
                    continue;
                }
                $n = count($this->slots->forDay((int) $d['dentistId'], $date->toDateString(), (int) $service->duration_min, $branch, $this->user));
                if ($n > 0) {
                    $opts[] = $this->opt($date->format('D, M j') . ' (' . $n . ($this->fil() ? ' bakante' : ' slots') . ')', 'book_date', Arr::except($d, ['from', 'day']) + ['date' => $date->toDateString()]);
                }
            }

            return $opts;
        };
        $opts = $collect($want);
        if (! $opts && $want) {
            $note .= $this->tr('This dentist has no open time on that day. ', 'Walang bakanteng oras ang dentistang ito sa araw na iyan. ');
            $opts = $collect(0);
        }
        if (! $opts) {
            return $this->out($note . $this->tr('That dentist has no open slots in the coming weeks. I can put you on the waiting list and tell you when a slot opens.',
                'Wala pong bakanteng oras ang dentistang ito sa mga susunod na linggo. Puwede ko po kayong ilagay sa waiting list at sasabihan ko kayo kapag may nagbukas.'), [
                $this->opt($this->tr('Join the waiting list', 'Sumali sa waiting list'), 'join_waitlist', $d),
                $this->back($this->tr('← Pick another dentist', '← Pumili ng ibang dentista'), 'book_service', Arr::only($d, ['serviceId'])),
                $this->opt('Main menu', 'menu'),
            ], 'book');
        }
        $opts[] = empty($d['reschedule'])
            ? $this->back($this->tr('← Back to dentists', '← Bumalik sa mga dentista'), 'book_service', Arr::only($d, ['serviceId']))
            : $this->back($this->tr('← Back to my appointments', '← Bumalik sa mga appointment ko'), 'my_appts');
        $opts[] = $this->opt('Main menu', 'menu');

        $shut = $closed && ! str_contains($note, $this->tr('closed', 'Sarado')) ? $this->tr(' The clinic is closed on ' . implode(' and ', $closed) . '.', ' Sarado po ang clinic tuwing ' . implode(' at ', $closed) . '.') : '';

        return $this->out($note . $this->tr('Pick a date. Only days the clinic is open and this dentist is free are listed.', 'Pumili po ng petsa. Ang nakalista lang ay ang mga araw na bukas ang clinic at bakante ang dentista.') . $shut, $opts, 'book');
    }

    private function pickSlot(array $d, ?string $why = null)
    {
        if ($this->user) {
            // remembered so a time typed instead of tapped can be matched against this list
            $this->keepSlots = true;
            Cache::put('chat-slots-' . $this->user->id, Arr::only($d, ['serviceId', 'dentistId', 'date', 'reschedule']), 1800);
        }
        $service = DB::table('services')->where('service_id', $d['serviceId'] ?? 0)->first();
        $branch = $this->branchOf((int) $d['dentistId']);
        $list = $this->slots->forDay((int) $d['dentistId'], $d['date'], (int) $service->duration_min, $branch, $this->user);
        $opts = [];
        foreach (array_slice($list, 0, 14) as $s) {
            $opts[] = $this->opt(Carbon::parse($s['startTime'])->format('g:i A'), 'book_slot', Arr::except($d, ['start', 'end']) + ['start' => $s['startTime'], 'end' => $s['endTime']]);
        }
        if (! $opts) {
            return $this->pickDate($d);
        }
        $opts[] = $this->back($this->tr('← Back to dates', '← Bumalik sa mga petsa'), 'book_dentist', Arr::only($d, ['serviceId', 'dentistId', 'reschedule']));
        $opts[] = $this->opt('Main menu', 'menu');

        $ask = $this->tr('Pick a time on ' . Carbon::parse($d['date'])->format('l, M j') . '. Times already taken are not shown:',
            'Pumili po ng oras sa ' . Carbon::parse($d['date'])->format('l, M j') . '. Hindi na ipinapakita ang mga oras na may naka-book na:');

        return $this->out(($why ? $why . "\n" : '') . $ask, $opts, 'book');
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

        // The branch and the price were missing, so a patient confirmed a visit without
        // being told where to go or what it costs.
        $branchName = DB::table('branches')->where('branch_id', $this->branchOf((int) $d['dentistId']))->value('branch_name');
        $text = ($moving ? $this->tr("Please confirm the new time:\n", "Pakikumpirma po ang bagong oras:\n") : $this->tr("Please confirm your booking:\n", "Pakikumpirma po ang booking n'yo:\n"))
            . "• {$service->service_name} — ₱" . number_format((float) $service->price, 2) . "\n• " . $this->dentistName($dentist) . "\n• " . $this->when($d['start'])
            . ($branchName ? "\n• {$branchName}" : '');

        return $this->out($text, [
            $this->opt($moving ? $this->tr('Yes, move it', 'Oo, ilipat') : $this->tr('Yes, book it', 'Oo, i-book'), 'book_confirm', $d),
            $this->back($this->tr('← Pick another time', '← Pumili ng ibang oras'), 'book_date', Arr::only($d, ['serviceId', 'dentistId', 'date', 'reschedule'])),
            $this->opt('Main menu', 'menu'),
        ], 'book');
    }

    private function confirmSlot(array $d)
    {
        if (! $this->patient) {
            return $this->needPatient();
        }
        $service = DB::table('services')->where('service_id', $d['serviceId'])->first();

        // The options we offer are already scoped, but the action payload comes from the
        // client, so the dentist and the service are checked again here before anything is
        // written. Otherwise a crafted request could book another clinic's dentist.
        $okDentist = Scope::table(DB::table('dentists'), 'dentists', $this->user)->where('dentist_id', $d['dentistId'] ?? 0)->exists();
        if (! $service || ! $okDentist) {
            return $this->out('That service or dentist is not available at this clinic. Let us start again.', [$this->opt('Book an appointment', 'book'), $this->opt('Main menu', 'menu')], 'book');
        }

        // The clinic must actually be open at the time being booked (see ClinicHours).
        $hours = ClinicHours::on($this->clinicId(), Carbon::parse($d['start'])->toDateString());
        $start = Carbon::parse($d['start']);
        if ($hours === null || $start->lt($hours['open']) || Carbon::parse($d['end'])->gt($hours['close'])) {
            return $this->out(ClinicHours::closedMessage($this->clinicId(), $start->toDateString()), [$this->opt('Pick another date', 'book_dentist', $d), $this->opt('Main menu', 'menu')], 'book');
        }

        if (! $this->slots->isFree((int) $d['dentistId'], $d['start'], $d['end'], $d['reschedule'] ?? null)) {
            return $this->out($this->tr('Sorry, that time was just taken by someone else. Please pick another slot.', 'Pasensya po, may nakakuha na ng oras na iyan. Pumili po ng ibang oras.'), [$this->opt($this->tr('Show other times', 'Ipakita ang ibang oras'), 'book_dentist', $d)], 'book');
        }

        // reschedule an existing booking
        if (! empty($d['reschedule'])) {
            $a = Appointment::where('appointment_id', $d['reschedule'])->where('patient_id', $this->patient->patient_id)->first();
            if (! $a) {
                return $this->menu();
            }
            $a->forceFill(['start_time' => $d['start'], 'end_time' => $d['end'], 'dentist_id' => $d['dentistId'], 'status' => 'pending'])->save();

            return $this->out($this->tr('Done! Your appointment is moved to ' . $this->when($d['start']) . '.', 'Tapos na po! Nailipat na ang appointment n\'yo sa ' . $this->when($d['start']) . '.'), [$this->opt($this->tr('My appointments', 'Mga appointment ko'), 'my_appts'), $this->opt('Main menu', 'menu')], 'reschedule');
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

        $branchName = DB::table('branches')->where('branch_id', $this->branchOf((int) $d['dentistId']))->value('branch_name');

        $text = $this->fil()
            ? "Naka-book na po kayo!\n{$service->service_name} sa " . $this->when($d['start']) . ($branchName ? " sa {$branchName}" : '') . '.'
                . "\nPakihanda po ang ₱" . number_format((float) $service->price, 2) . '. Paaalalahanan po namin kayo bago ang inyong pagbisita.'
            : "You're booked!\n{$service->service_name} on " . $this->when($d['start']) . ($branchName ? " at {$branchName}" : '') . '.'
                . "\nPlease prepare ₱" . number_format((float) $service->price, 2) . '. We will remind you before the visit.';

        return $this->out($text, [$this->opt($this->tr('My appointments', 'Mga appointment ko'), 'my_appts'), $this->opt('Main menu', 'menu')], 'booked');
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

        return $this->out($this->tr("You're on the waiting list. I'll offer you the first slot that opens up.", 'Nasa waiting list na po kayo. Iaalok ko po sa inyo ang unang oras na magbubukas.'), [$this->opt('Main menu', 'menu')], 'waitlist');
    }

    private function cancel(array $d)
    {
        $a = $this->patient ? Appointment::where('appointment_id', $d['id'] ?? 0)->where('patient_id', $this->patient->patient_id)->first() : null;
        if (! $a || ! in_array($a->status, ['pending', 'confirmed'])) {
            return $this->out('I could not find that appointment, or it can no longer be cancelled.', [$this->opt('My appointments', 'my_appts')], 'cancel');
        }
        $a->status = 'cancelled';
        $a->save(); // frees the slot and offers it to the waiting list

        return $this->out($this->tr('Your appointment on ' . $this->when($a->start_time) . ' is cancelled. The slot is open again.', 'Na-cancel na po ang appointment n\'yo sa ' . $this->when($a->start_time) . '. Bukas na ulit ang oras na iyon.'), [$this->opt($this->tr('Book a new one', 'Mag-book ng bago'), 'book'), $this->opt('Main menu', 'menu')], 'cancel');
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

    /**
     * Ask the language model. Returns a reply, or null to fall through to the rules.
     * A question with nothing to do with the clinic is declined here and is NOT sent to staff.
     */
    private function aiReply()
    {
        $r = app(AssistantAI::class)->answer($this->in, $this->clinicId(), $this->patient);
        if (! $r) {
            return null;
        }

        if (($r['kind'] ?? '') === 'outofscope') {
            return $this->out($r['text'], [$this->opt('Main menu', 'menu')], 'out_of_scope');
        }

        return $this->out($r['text'], [
            $this->opt('Book an appointment', 'book'),
            $this->opt('Main menu', 'menu'),
        ], 'ai_answer');
    }

    /** The clinic the conversation belongs to, so the assistant answers with that clinic's data. */
    private function clinicId(): ?int
    {
        $cid = $this->patient->clinic_id ?? $this->user->clinic_id ?? null;

        return $cid ? (int) $cid : null;
    }

    private function escalate(array $why)
    {
        // Let the clinic know by mail too, so an escalated question is not waiting unseen in the
        // inbox until someone happens to sign in.
        $who = $this->patient->full_name ?? ($this->user->name ?? 'A patient');
        app(\App\Services\V1\Notifier::class)->toClinic(
            $this->clinicId(),
            'A patient question needs a reply',
            "{$who} asked the assistant:\n\n\"{$this->in}\"\n\nThe assistant could not answer it, so it has been passed to staff. Open the staff inbox in SmileSync to reply."
        );

        // Answer in the language the patient wrote in. A Filipino question answered in English
        // reads as though the assistant stopped listening.
        $text = $this->writtenInFilipino()
            ? $why['fil'] . ' Naipasa ko na po ang mensahe n\'yo sa aming staff. Lalabas po ang sagot nila dito mismo sa chat. Kung may matinding sakit o pagdurugo, tawagan po ninyo agad ang clinic.'
            : $why['en'] . " I've passed your message to our clinic staff. Their reply will appear right here in this chat. For urgent pain or bleeding, please call the clinic.";

        return $this->out($text, [$this->opt('Main menu', 'menu')], 'escalated', true);
    }

    /**
     * A rough guess at whether the patient wrote in Filipino, used only to choose the wording of
     * the handful of fixed replies. Anything the model writes is already in the right language.
     */
    private function writtenInFilipino(): bool
    {
        $t = ' ' . mb_strtolower($this->in) . ' ';

        return (bool) preg_match('/ (ang|ng|mga|po|ba|ako|ikaw|kayo|namin|natin|yung|iyong|meron|mayroon|wala|gusto|kailan|saan|bakit|paano|magkano|sakit|gamot|ngipin|bukas|opo|hindi|puwede|pwede|salamat) /', $t);
    }

    private function out(string $text, array $options = [], string $intent = 'other', bool $escalated = false)
    {
        // Any reply other than a list of times ends that list, so a time typed later in the
        // conversation is never applied to a booking the patient has moved on from.
        if (! $this->keepSlots && $this->user) {
            Cache::forget('chat-slots-' . $this->user->id);
        }

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
        // "at" means "and" in Tagalog, so the Filipino form uses a comma instead.
        return Carbon::parse($t)->format($this->fil() ? 'D, M j, g:i A' : 'D, M j \a\t g:i A');
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
