<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use App\Support\Scope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @group Staff inbox
 *
 * Chats the assistant passed to staff. Staff read the conversation and reply, and the reply
 * shows up inside the patient's assistant panel.
 */
class StaffInboxController extends APIController
{
    private const STAFF = ['superadmin', 'clinic_admin', 'receptionist', 'dentist'];

    private function isStaff($u): bool
    {
        return $u && in_array($u->role, self::STAFF, true);
    }

    private function nameCol(): ?string
    {
        return collect(['full_name', 'name'])->first(fn ($c) => Schema::hasColumn('patients', $c));
    }

    /** One row per patient whose chat was passed to staff, newest first. */
    private function threads($u)
    {
        $esc = Scope::table(DB::table('chat_logs'), 'chat_logs', $u)->where('escalated', 1)->whereNotNull('patient_id')
            ->selectRaw('patient_id, MAX(chat_id) as last_esc_id')->groupBy('patient_id')->get();
        if ($esc->isEmpty()) {
            return collect();
        }
        $ids = $esc->pluck('patient_id')->all();
        $replied = DB::table('chat_logs')->where('intent', 'staff_reply')->whereIn('patient_id', $ids)
            ->selectRaw('patient_id, MAX(chat_id) as last_reply_id')->groupBy('patient_id')->pluck('last_reply_id', 'patient_id');
        $last = DB::table('chat_logs')->whereIn('chat_id', $esc->pluck('last_esc_id'))->get()->keyBy('chat_id');
        $col = $this->nameCol();
        $names = $col ? DB::table('patients')->whereIn('patient_id', $ids)->pluck($col, 'patient_id') : collect();

        return $esc->map(function ($e) use ($replied, $last, $names) {
            $row = $last[$e->last_esc_id] ?? null;
            $answered = isset($replied[$e->patient_id]) && (int) $replied[$e->patient_id] > (int) $e->last_esc_id;

            return [
                'patientId' => (int) $e->patient_id,
                'name' => $names[$e->patient_id] ?? ('Patient #' . $e->patient_id),
                'lastMessage' => $row ? (str_starts_with((string) $row->message_in, '[') ? '(asked for staff)' : $row->message_in) : '',
                'lastAt' => $row->timestamp ?? null,
                'lastId' => (int) $e->last_esc_id,
                'answered' => $answered,
            ];
        })->sortByDesc('lastId')->values();
    }

    /** Number of conversations still waiting for a staff reply (used by the dashboard). */
    public static function openCount($u): int
    {
        if (! Schema::hasTable('chat_logs')) {
            return 0;
        }

        return (new self)->threads($u)->where('answered', false)->count();
    }

    /** GET /api/v1/chat/inbox */
    public function index(Request $request)
    {
        $u = $request->user();
        if (! $this->isStaff($u)) {
            return Dbx::err('Only clinic staff can open the inbox.', 403);
        }

        return response()->json(['data' => $this->threads($u)]);
    }

    /** GET /api/v1/chat/inbox/{patientId} — the whole conversation. */
    public function thread(Request $request, int $patientId)
    {
        $u = $request->user();
        if (! $this->isStaff($u)) {
            return Dbx::err('Only clinic staff can open the inbox.', 403);
        }
        if (! Scope::table(DB::table('patients'), 'patients', $u)->where('patient_id', $patientId)->exists()) {
            return Dbx::err('Patient not found.', 404);
        }
        $rows = DB::table('chat_logs')->where('patient_id', $patientId)->orderByDesc('chat_id')->limit(40)->get()->reverse()->values();
        $msgs = [];
        foreach ($rows as $r) {
            if ($r->intent === 'staff_reply') {
                $msgs[] = ['id' => $r->chat_id, 'from' => 'staff', 'text' => $r->message_out, 'at' => $r->timestamp];
                continue;
            }
            if ($r->message_in !== null && $r->message_in !== '' && ! str_starts_with($r->message_in, '[')) {
                $msgs[] = ['id' => $r->chat_id, 'from' => 'patient', 'text' => $r->message_in, 'at' => $r->timestamp, 'escalated' => (bool) $r->escalated];
            }
            if (! empty($r->message_out)) {
                $msgs[] = ['id' => $r->chat_id, 'from' => 'assistant', 'text' => $r->message_out, 'at' => $r->timestamp];
            }
        }
        $col = $this->nameCol();
        $name = $col ? DB::table('patients')->where('patient_id', $patientId)->value($col) : null;

        return response()->json(['patient' => ['id' => $patientId, 'name' => $name], 'messages' => $msgs]);
    }

    /** POST /api/v1/chat/inbox/{patientId}/reply  body: {"message": "..."} */
    public function reply(Request $request, int $patientId)
    {
        $u = $request->user();
        if (! $this->isStaff($u)) {
            return Dbx::err('Only clinic staff can reply.', 403);
        }
        if (! Scope::table(DB::table('patients'), 'patients', $u)->where('patient_id', $patientId)->exists()) {
            return Dbx::err('Patient not found.', 404);
        }
        $text = trim((string) $request->input('message', ''));
        if ($text === '') {
            return Dbx::err('Please type a reply.');
        }
        if (mb_strlen($text) > 1000) {
            return Dbx::err('The reply is too long (1000 characters at most).');
        }
        $id = Dbx::insert('chat_logs', [
            'patient_id' => $patientId, 'message_in' => '[staff reply]', 'message_out' => $text,
            'intent' => 'staff_reply', 'escalated' => 0, 'timestamp' => now()->toDateTimeString(),
        ]);
        Dbx::audit($u, 'create', 'chat_logs', $id, null, ['patient_id' => $patientId, 'staff_reply' => mb_substr($text, 0, 200)]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

    /** GET /api/v1/chat/replies?after=ID — the logged-in patient's staff replies. */
    public function replies(Request $request)
    {
        $p = $request->user()->patient;
        if (! $p) {
            return response()->json(['data' => []]);
        }
        $after = (int) $request->query('after', 0);
        $q = DB::table('chat_logs')->where('patient_id', $p->patient_id)->where('intent', 'staff_reply');
        $rows = $after > 0
            ? $q->where('chat_id', '>', $after)->orderBy('chat_id')->limit(20)->get()
            : $q->orderByDesc('chat_id')->limit(5)->get()->reverse()->values();

        return response()->json(['data' => $rows->map(fn ($r) => ['id' => (int) $r->chat_id, 'text' => $r->message_out, 'at' => $r->timestamp])->values()]);
    }
}
