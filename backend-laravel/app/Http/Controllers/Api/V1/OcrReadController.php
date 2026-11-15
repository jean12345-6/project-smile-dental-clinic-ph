<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * @group Scanned forms
 *
 * Reads a HANDWRITTEN patient form with an AI vision model and returns the patient fields.
 * Needs ANTHROPIC_API_KEY in .env. Without it the screen falls back to the basic in-browser reader.
 */
class OcrReadController extends APIController
{
    /** POST /api/v1/ocr/read (multipart: image) */
    public function read(Request $r)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin', 'receptionist'], true)) {
            return Dbx::err('Not allowed.', 403);
        }
        $key = env('ANTHROPIC_API_KEY');
        if (! $key) {
            return response()->json(['errors' => [['message' => 'AI reader is not set up. Add ANTHROPIC_API_KEY to .env.']], 'configured' => false], 503);
        }
        $r->validate(['image' => 'required|image|max:8192']);
        $file = $r->file('image');
        $prompt = 'This is a HANDWRITTEN patient information form of a dental clinic in the Philippines. '
            . 'Read it carefully and answer with ONLY one JSON object, no other text, with exactly these keys: '
            . 'full_name, birthdate (format YYYY-MM-DD; dates are month/day/year), contact_no (digits only, Philippine mobile like 09171234567), '
            . 'email, address, medical_history (allergies, conditions and medicines, joined with "; "). '
            . 'Use an empty string for anything that is blank or that you cannot read with confidence. Never guess.';
        try {
            $res = Http::timeout(60)->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => env('OCR_MODEL', 'claude-sonnet-5-5'),
                    'max_tokens' => 800,
                    'messages' => [['role' => 'user', 'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $file->getMimeType() ?: 'image/jpeg', 'data' => base64_encode(file_get_contents($file->getRealPath()))]],
                        ['type' => 'text', 'text' => $prompt],
                    ]]],
                ]);
        } catch (\Throwable $e) {
            return Dbx::err('Could not reach the AI reader. Check the internet connection.', 502);
        }
        if (! $res->ok()) {
            return Dbx::err('The AI reader returned an error (' . $res->status() . ').', 502);
        }
        $text = (string) data_get($res->json(), 'content.0.text', '');
        if (! preg_match('/\{.*\}/s', $text, $m) || ! is_array($j = json_decode($m[0], true))) {
            return Dbx::err('The AI reader gave an unreadable answer. Please try again.', 502);
        }
        $out = [];
        foreach (['full_name', 'birthdate', 'contact_no', 'email', 'address', 'medical_history'] as $k) {
            $out[$k] = trim((string) ($j[$k] ?? ''));
        }
        Dbx::audit($r->user(), 'create', 'ocr_batches', 0, null, ['ai_read' => true]);

        return response()->json(['fields' => $out]);
    }
}
