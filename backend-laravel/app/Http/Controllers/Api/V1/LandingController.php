<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Landing page
 */
class LandingController extends APIController
{
    private const PREFIX = 'landing.';

    private function content(): array
    {
        $out = [];
        foreach (DB::table('clinic_settings')->whereNull('clinic_id')->where('key', 'like', self::PREFIX . '%')->get(['key', 'value']) as $row) {
            $out[substr($row->key, strlen(self::PREFIX))] = (string) $row->value;
        }

        return $out;
    }

    /** GET /api/v1/public/landing — what visitors see (no login). Published feedback becomes testimonials. */
    public function show()
    {
        $tests = DB::table('feedback')->join('patients', 'patients.patient_id', '=', 'feedback.patient_id')
            ->where('feedback.published', 1)->whereNotNull('feedback.comment')->where('feedback.comment', '!=', '')
            ->orderByDesc('feedback.feedback_id')->limit(6)
            ->get(['feedback.rating', 'feedback.comment', 'patients.full_name'])
            ->map(function ($f) {
                $p = preg_split('/\s+/', trim($f->full_name));
                $name = $p[0] . (count($p) > 1 ? ' ' . mb_substr(end($p), 0, 1) . '.' : '');

                return ['quote' => $f->comment, 'name' => $name, 'rating' => (int) $f->rating];
            });

        return response()->json(['content' => (object) $this->content(), 'testimonials' => $tests]);
    }

    /** PUT /api/v1/landing {content:{key:value,...}} — admins only. Keys are limited to simple names. */
    public function save(Request $r)
    {
        if (! in_array($r->user()->role, ['superadmin', 'clinic_admin'], true)) {
            return Dbx::err('Not allowed.', 403);
        }
        $r->validate(['content' => 'required|array']);
        $n = 0;
        foreach ($r->input('content') as $k => $v) {
            if (! preg_match('/^[a-z0-9_.]{1,60}$/', (string) $k)) {
                continue;
            }
            $v = trim((string) $v);
            if (mb_strlen($v) > 250) {
                return Dbx::err("The text for \"{$k}\" is too long (250 characters at most).");
            }
            DB::table('clinic_settings')->updateOrInsert(['clinic_id' => null, 'key' => self::PREFIX . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]);
            $n++;
        }
        Dbx::audit($r->user(), 'update', 'landing_page', 0, null, ['fields' => $n]);

        return response()->json(['message' => 'Landing page saved.', 'saved' => $n]);
    }
}
