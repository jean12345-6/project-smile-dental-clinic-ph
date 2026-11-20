<?php

namespace App\Services\V1;

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
    public function answer(string $question, ?int $clinicId, $patient = null): ?string
    {
        [$provider, $key, $model] = $this->provider();
        if (! config('assistant.enabled') || ! $key || trim($question) === '') {
            return null;
        }

        $prompt = $this->systemPrompt($this->facts($clinicId, $patient));

        try {
            $text = $provider === 'gemini'
                ? $this->askGemini($key, $model, $prompt, $question)
                : $this->askAnthropic($key, $model, $prompt, $question);
        } catch (\Throwable $e) {
            logger()->warning('Assistant AI failed: ' . $e->getMessage());

            return null;
        }

        $text = trim((string) $text);
        if ($text === '' || str_contains(strtoupper($text), 'ESCALATE')) {
            return null;
        }

        return $text;
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

        $services = DB::table('services')->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))->get();
        foreach ($services as $s) {
            $L[] = "Service: {$s->service_name}; price PHP " . number_format((float) $s->price, 2) . "; takes {$s->duration_min} minutes";
        }

        foreach (DB::table('faq_entries')->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))->get() as $f) {
            $L[] = "Clinic answer to \"{$f->question}\": {$f->answer}";
        }

        if ($patient) {
            $L[] = 'The patient asking is ' . $patient->full_name . '.';
            $next = DB::table('appointments')->join('services', 'services.service_id', '=', 'appointments.service_id')
                ->where('appointments.patient_id', $patient->patient_id)
                ->whereIn('appointments.status', ['pending', 'confirmed'])
                ->where('appointments.start_time', '>=', now())
                ->orderBy('appointments.start_time')
                ->first(['appointments.start_time', 'services.service_name']);
            $L[] = $next
                ? 'Their next appointment is ' . date('D, M j, Y g:i A', strtotime($next->start_time)) . ' for ' . $next->service_name . '.'
                : 'They have no upcoming appointment.';
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
        policy that is not written there. If the facts do not contain the answer, reply with the
        single word ESCALATE and nothing else.

        Also reply with the single word ESCALATE, and nothing else, if the question asks about
        symptoms, pain, bleeding, medicines, dosages, diagnosis or whether a treatment is needed.
        You are not permitted to give clinical advice of any kind.

        Do not book, move or cancel an appointment yourself and do not promise a specific slot. If
        the patient wants to book, tell them to tap "Book an appointment" or to type "book".

        Write at most three sentences, in the same language the patient used: English, Filipino or a
        mixture of the two. Be warm and plain. Give amounts in pesos as written in the facts.

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

        return $r->json('content.0.text');
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

        return $r->json('candidates.0.content.parts.0.text');
    }
}
