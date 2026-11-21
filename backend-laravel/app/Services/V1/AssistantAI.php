<?php

namespace App\Services\V1;

use App\Support\Scope;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The language-model part of the assistant.
 *
 * It is reached only when no rule in ChatController matches the message. The model is given the
 * clinic's own data and is told to answer from that alone, so prices, hours and branches come from
 * the database and not from the model's own knowledge. Questions about symptoms, treatment or
 * medicines are filtered out before this class is called, and the model is told to refuse them too.
 *
 * Any failure returns null and the assistant escalates to staff exactly as it did before (NFR-17).
 */
class AssistantAI
{
    /** Answer a free-text question, or null when the model cannot or should not answer. */
    public function answer(string $question, ?int $clinicId, $patient = null): ?array
    {
        [$provider, $key, $model] = $this->provider();
        if (! config('assistant.enabled') || ! $key || trim($question) === '') {
            return null;
        }

        try {
            // Gathering the facts is inside the guard too: a fault here must fall back to the
            // rules like any other failure, never break the conversation.
            $prompt = $this->systemPrompt($this->facts($clinicId, $patient));

            $text = $provider === 'gemini'
                ? $this->askGemini($key, $model, $prompt, $question)
                : $this->askAnthropic($key, $model, $prompt, $question);
        } catch (\Throwable $e) {
            logger()->warning('Assistant AI failed: ' . $e->getMessage());

            return null;
        }

        $text = trim((string) $text);

        // A question that has nothing to do with the clinic is declined on the spot. It must not
        // be passed to staff: that would put a question about, say, the capital of Japan into the
        // clinic's inbox. The model writes the refusal itself so that it is in the patient's own
        // language.
        if (stripos($text, 'OUTOFSCOPE') === 0) {
            $said = trim(mb_substr($text, strlen('OUTOFSCOPE')), " :\u{2014}-\n\r\t");

            return ['kind' => 'outofscope', 'text' => $said !== '' ? $said
                : 'I can only help with questions about the clinic, your appointments and your records.'];
        }

        if ($text === '' || str_contains(strtoupper($text), 'ESCALATE')) {
            // Recorded so the clinic can see which questions the assistant could not answer from
            // its own data. A run of these usually means something is missing from the settings.
            logger()->info('Assistant AI declined: ' . ($text === '' ? '(empty reply)' : mb_substr($text, 0, 200)));

            return null;
        }

        return ['kind' => 'answer', 'text' => $text];
    }

    /** Which provider to use: the configured one, else whichever key is filled in. */
    private function provider(): array
    {
        $want = config('assistant.provider');
        $anth = config('assistant.anthropic.key');
        $gem = config('assistant.gemini.key');

        if ($want === 'gemini' || (! $want && ! $anth && $gem)) {
            return ['gemini', $gem, config('assistant.gemini.model')];
        }

        return ['anthropic', $anth, config('assistant.anthropic.model')];
    }

    /** The clinic's own data, written out as plain lines the model may quote from. */
    private function facts(?int $clinicId, $patient): string
    {
        $L = [];

        // The patient may ask about "today", "tomorrow" or a named day, so the model needs to
        // know where in the week it is. Clinic time, not server time.
        $now = now()->timezone('Asia/Manila');
        $L[] = 'Today is ' . $now->format('l, j F Y') . '.';
        $L[] = 'Tomorrow is ' . $now->copy()->addDay()->format('l, j F Y') . '.';

        $clinic = $clinicId ? DB::table('clinics')->where('clinic_id', $clinicId)->first() : null;
        $L[] = 'Clinic: ' . ($clinic->clinic_name ?? 'the clinic');

        $branches = DB::table('branches')->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))->get();
        foreach ($branches as $b) {
            $name = $b->branch_name ?? $b->name ?? 'Branch';
            $hours = isset($b->open_time) ? substr((string) $b->open_time, 0, 5) . ' to ' . substr((string) ($b->close_time ?? ''), 0, 5) : 'see clinic hours';
            $L[] = "Branch: {$name}; address: " . ($b->address ?? 'n/a') . "; open {$hours}";
        }

        // Per-day opening hours set by the clinic. These are more specific than the branch's own
        // open and close times, so they are given last and marked as the ones that decide.
        $days = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
            'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
        foreach ($days as $k => $name) {
            $v = Settings::get($clinicId, 'hours.' . $k, null);
            if ($v) {
                $L[] = strtolower((string) $v) === 'closed'
                    ? "Opening hours on {$name}: closed."
                    : "Opening hours on {$name}: " . str_replace('-', ' to ', (string) $v) . ' (these clinic hours decide, not the branch times above).';
            }
        }

        // The clinic's doors and the dentists' shifts are not the same thing. The clinic is
        // open until 7 PM on weekdays while every dentist stops at 5, so "open until 7 PM"
        // was a true sentence that sent patients looking for slots that cannot exist. Say
        // what can actually be booked, worked out from both.
        $shift = DB::table('dentist_schedules')
            ->join('dentists', 'dentists.dentist_id', '=', 'dentist_schedules.dentist_id')
            ->when($clinicId, fn ($q) => Scope::table($q, 'dentists', (object) ['role' => 'receptionist', 'clinic_id' => $clinicId]))
            ->whereNotNull('dentist_schedules.day_of_week')
            ->selectRaw('dentist_schedules.day_of_week as dow, MIN(dentist_schedules.start_time) as earliest, MAX(dentist_schedules.end_time) as latest')
            ->groupBy('dentist_schedules.day_of_week')->get()->keyBy('dow');

        $iso = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
        foreach ($days as $k => $name) {
            $v = (string) Settings::get($clinicId, 'hours.' . $k, '');
            if ($v === '' || strtolower($v) === 'closed') {
                continue;
            }
            $row = $shift[$iso[$k]] ?? ($iso[$k] === 7 ? ($shift[0] ?? null) : null);
            if (! $row || ! $row->earliest || ! $row->latest) {
                continue;
            }
            [$open, $close] = array_pad(explode('-', $v), 2, null);
            $from = max(substr(trim((string) $open), 0, 5), substr((string) $row->earliest, 0, 5));
            $to = min(substr(trim((string) $close), 0, 5), substr((string) $row->latest, 0, 5));
            $L[] = $to <= $from
                ? "No appointment can be booked on {$name}, because no dentist works while the clinic is open."
                : "Appointments on {$name} can only be booked between {$from} and {$to}, because that is when a dentist is working and the clinic is open. The clinic may stay open later than the last appointment.";
        }

        $services = DB::table('services')->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))->get();
        foreach ($services as $s) {
            $L[] = "Service: {$s->service_name}; price PHP " . number_format((float) $s->price, 2) . "; takes {$s->duration_min} minutes";
        }

        // Which dentist works on which day, so "sino ang dentist bukas?" can be answered. Free
        // times still come from the booking flow, never from here.
        $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        // The same rule the booking screens use, so the list the assistant describes and the
        // list the patient can actually choose from can never drift apart. Scoping it by hand
        // here is how they drifted in the first place.
        $dentists = DB::table('dentists')
            ->when($clinicId, fn ($q) => Scope::table($q, 'dentists', (object) ['role' => 'receptionist', 'clinic_id' => $clinicId]))
            ->get(['dentists.dentist_id', 'dentists.full_name', 'dentists.specialization']);
        foreach ($dentists as $d) {
            $rows = DB::table('dentist_schedules')->where('dentist_id', $d->dentist_id)
                ->whereNotNull('day_of_week')->get(['day_of_week', 'start_time', 'end_time']);
            $days = $rows->pluck('day_of_week')->unique()->sort()
                ->map(fn ($n) => $names[(int) $n] ?? '')->filter()->implode(', ');
            $line = 'Dentist: ' . $d->full_name;
            if (! empty($d->specialization)) {
                $line .= ' (' . $d->specialization . ')';
            }
            $line .= $days !== '' ? '; works on ' . $days . '.' : '; working days not recorded.';
            $L[] = $line;
        }
        $ids = $dentists->pluck('dentist_id')->all();
        $leave = $ids ? DB::table('dentist_schedules')
            ->whereIn('dentist_schedules.dentist_id', $ids)
            ->whereNotNull('dentist_schedules.leave_date')
            ->whereDate('dentist_schedules.leave_date', '>=', $now->toDateString())
            ->join('dentists', 'dentists.dentist_id', '=', 'dentist_schedules.dentist_id')
            ->get(['dentists.full_name', 'dentist_schedules.leave_date']) : collect();
        foreach ($leave as $lv) {
            $L[] = $lv->full_name . ' is on leave on ' . date('l, j F Y', strtotime($lv->leave_date)) . '.';
        }

        foreach (DB::table('faq_entries')->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))->get() as $f) {
            $L[] = "Clinic answer to \"{$f->question}\": {$f->answer}";
        }

        if ($patient) {
            $L[] = 'The patient asking is ' . $patient->full_name . '.';
            // Every upcoming appointment, not only the first: a patient asking "kailan appointment
            // ko" means all of them, and a pending one matters as much as a confirmed one.
            $upcoming = DB::table('appointments')->join('services', 'services.service_id', '=', 'appointments.service_id')
                ->where('appointments.patient_id', $patient->patient_id)
                ->whereIn('appointments.status', ['pending', 'confirmed'])
                ->where('appointments.start_time', '>=', now())
                ->orderBy('appointments.start_time')
                ->limit(5)
                ->get(['appointments.start_time', 'appointments.status', 'services.service_name']);
            if ($upcoming->isEmpty()) {
                $L[] = 'They have no upcoming appointment.';
            } else {
                foreach ($upcoming as $a) {
                    $L[] = 'Upcoming appointment: ' . date('D, M j, Y g:i A', strtotime($a->start_time))
                        . ' for ' . $a->service_name . ' (' . $a->status . ').';
                }
            }
            $bal = (float) DB::table('invoices')->where('patient_id', $patient->patient_id)->sum('balance');
            $L[] = 'Their unpaid balance is PHP ' . number_format($bal, 2) . '.';
        } else {
            $L[] = 'The person asking is not signed in as a patient, so no personal records are available.';
        }

        return implode("\n", $L);
    }

    private function systemPrompt(string $facts): string
    {
        return <<<TXT
        You are the assistant of a dental clinic, answering a patient in a chat window.

        Answer ONLY from the clinic facts below. Never invent a price, a schedule, an address or a
        policy that is not written there.

        Decide first whether the question has anything to do with this dental clinic, with dentistry,
        or with the patient's own appointments, records or bill.

        If it does NOT - for example a question about geography, news, politics, homework, another
        business, or small talk unrelated to the clinic - reply with the word OUTOFSCOPE followed by
        one short sentence, written in the same language the patient used, saying politely that you
        can only help with questions about the clinic. Write nothing else. Do not try to answer such
        a question, and do not pass it to staff: nobody at the clinic needs to see it.

        If it IS a clinic question but the facts below do not contain the answer, reply with the
        single word ESCALATE and nothing else, so that a member of staff can answer it.

        The facts may disagree with each other, because opening times are recorded in more than one
        place. That is not a reason to refuse. When they disagree about opening hours, use the line
        that gives the hours for that particular day; it is the one the clinic maintains. Answer
        with it plainly and do not mention that the records disagree.

        When the patient asks about booking, an appointment or a time to come in, and a line gives
        the hours an appointment can be booked on that day, answer with those hours, not with the
        clinic's opening hours. The clinic can be open at a time when no dentist is working, so
        quoting the opening hours would send the patient looking for a slot that does not exist.

        Also reply with the single word ESCALATE, and nothing else, if the question asks about
        symptoms, pain, bleeding, medicines, dosages, diagnosis or whether a treatment is needed.
        You are not permitted to give clinical advice of any kind.

        Do not book, move or cancel an appointment yourself and do not promise a specific slot. If
        the patient wants to book, tell them to tap "Book an appointment" or to type "book". You may
        say which dentists work on a given day, but never say that a particular time is free: free
        times are shown only by the booking flow, so send the patient there to see them.

        One subject is not covered by the clinic facts and you may still answer it: questions about
        what you are. You are the clinic's automated assistant, software and not a person, and you
        are answering with the help of a language model. Say so plainly whenever you are asked, and
        never claim or imply that you are a human being. You can then add that you book, move and
        cancel appointments and answer questions about the clinic, and that a member of staff can
        take over at any time if they tap "Talk to staff".

        Write at most three sentences, in the same language the patient used: English, Filipino or a
        mixture of the two. Be warm and plain. Give amounts in pesos as written in the facts.

        Patients write the way they speak. Read these everyday Filipino words as the service they
        refer to: linis, paglilinis or cleaning is oral prophylaxis; pasta is a composite filling;
        bunot or pabunot is a tooth extraction; pustiso is a denture; paputi is teeth whitening;
        konsulta or checkup is a consultation; brace is braces. Note also that "bukas" means both
        "open" and "tomorrow"; decide from the rest of the sentence, and if the patient is asking
        about tomorrow, work out which day that is from the date given in the facts and answer with
        that day's hours.

        Clinic facts:
        {$facts}
        TXT;
    }

    private function askAnthropic(string $key, string $model, string $system, string $question): ?string
    {
        $r = Http::timeout(config('assistant.timeout'))
            ->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 300,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $question]],
            ]);

        if (! $r->successful()) {
            throw new \RuntimeException('HTTP ' . $r->status());
        }

        // The reply is a list of blocks and the answer is not always the first one: when the model
        // reasons before answering, the reasoning comes first. Take the text blocks only.
        $parts = [];
        foreach ((array) $r->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $parts[] = $block['text'];
            }
        }

        return trim(implode("\n", $parts));
    }

    private function askGemini(string $key, string $model, string $system, string $question): ?string
    {
        $r = Http::timeout(config('assistant.timeout'))
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['parts' => [['text' => $question]]]],
                'generationConfig' => ['maxOutputTokens' => 300],
            ]);

        if (! $r->successful()) {
            throw new \RuntimeException('HTTP ' . $r->status());
        }

        $parts = [];
        foreach ((array) $r->json('candidates.0.content.parts', []) as $part) {
            if (isset($part['text'])) {
                $parts[] = $part['text'];
            }
        }

        return trim(implode("\n", $parts));
    }
}
