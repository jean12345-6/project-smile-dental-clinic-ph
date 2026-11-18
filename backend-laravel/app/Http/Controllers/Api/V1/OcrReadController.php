<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * @group Scanned forms
 *
 * Reads a HANDWRITTEN patient form with an AI vision model and returns the patient fields.
 * Works with Google Gemini or Anthropic; set the matching key in .env. Settings are read
 * through config/ocr.php so they survive config caching in production.
 * Without a key the screen falls back to the basic in-browser reader.
 */
class OcrReadController extends APIController
{
    /** POST /api/v1/ocr/read (multipart: image) */
    public function read(Request $r)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin', 'receptionist'], true)) {
            return Dbx::err('Not allowed.', 403);
        }

        [$provider, $key, $model] = $this->pickProvider();

        if (! $key) {
            return response()->json(['errors' => [['message' => 'AI reader is not set up. Add GEMINI_API_KEY or ANTHROPIC_API_KEY to .env.']], 'configured' => false], 503);
        }

        $r->validate(['image' => 'required|image|max:8192']);
        $file = $r->file('image');
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $data = base64_encode(file_get_contents($file->getRealPath()));

        $prompt = 'This is a HANDWRITTEN patient information form of a dental clinic in the Philippines. '
            . 'Read it carefully and answer with ONLY one JSON object, no other text, with exactly these keys: '
            . 'full_name, birthdate (format YYYY-MM-DD; dates are month/day/year), contact_no (digits only, Philippine mobile like 09171234567), '
            . 'email, address, medical_history (allergies, conditions and medicines, joined with "; "). '
            . 'Use an empty string for anything that is blank or that you cannot read with confidence. Never guess.';

        try {
            $res = $provider === 'gemini'
                ? $this->askGemini($key, $model, $mime, $data, $prompt)
                : $this->askAnthropic($key, $model, $mime, $data, $prompt);
        } catch (\Throwable $e) {
            return Dbx::err('Could not reach the AI reader. Check the internet connection.', 502);
        }

        if (! $res->ok()) {
            return Dbx::err('The AI reader returned an error (' . $res->status() . ').', 502);
        }

        $text = $provider === 'gemini'
            ? (string) data_get($res->json(), 'candidates.0.content.parts.0.text', '')
            : (string) data_get($res->json(), 'content.0.text', '');

        if (! preg_match('/\{.*\}/s', $text, $m) || ! is_array($j = json_decode($m[0], true))) {
            return Dbx::err('The AI reader gave an unreadable answer. Please try again.', 502);
        }

        $out = [];
        foreach (['full_name', 'birthdate', 'contact_no', 'email', 'address', 'medical_history'] as $f) {
            $out[$f] = trim((string) ($j[$f] ?? ''));
        }
        Dbx::audit($r->user(), 'create', 'ocr_batches', 0, null, ['ai_read' => true, 'provider' => $provider]);

        return response()->json(['fields' => $out]);
    }

    /** Use the provider named in config, otherwise whichever key is filled in. */
    private function pickProvider(): array
    {
        $want = config('ocr.provider');
        $gemini = config('ocr.gemini.key');

        if ($want === 'gemini' || (! $want && $gemini)) {
            return ['gemini', $gemini, config('ocr.gemini.model')];
        }

        return ['anthropic', config('ocr.anthropic.key'), config('ocr.anthropic.model')];
    }

    private function askGemini(string $key, string $model, string $mime, string $data, string $prompt)
    {
        return Http::timeout(60)->withHeaders(['x-goog-api-key' => $key])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [['parts' => [
                    ['inline_data' => ['mime_type' => $mime, 'data' => $data]],
                    ['text' => $prompt],
                ]]],
            ]);
    }

    private function askAnthropic(string $key, string $model, string $mime, string $data, string $prompt)
    {
        return Http::timeout(60)->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 800,
                'messages' => [['role' => 'user', 'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $data]],
                    ['type' => 'text', 'text' => $prompt],
                ]]],
            ]);
    }
}
