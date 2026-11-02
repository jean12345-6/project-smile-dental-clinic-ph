<?php

namespace App\Http\Middleware;

use App\Support\Dbx;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Writes every login, logout and timeout to session_logs (FR-04). Used on the login and logout routes. */
class SessionLogger
{
    public function handle(Request $request, Closure $next)
    {
        $logout = str_ends_with($request->path(), 'logout');

        if ($logout) {
            try {
                $user = auth('api')->user();
                if ($user) {
                    $row = DB::table('session_logs')->where('user_id', $user->id)->whereNull('logout_time')->orderByDesc('session_id')->first();
                    if ($row) {
                        $reason = $request->input('reason') === 'timeout' ? 'timeout' : 'logout';
                        DB::table('session_logs')->where('session_id', $row->session_id)->update(['logout_time' => now(), 'end_reason' => $reason]);
                    }
                }
            } catch (\Throwable $e) {
                // never block a logout because logging failed
            }
        }

        $response = $next($request);

        if (! $logout && $response->getStatusCode() === 200) {
            try {
                $uid = DB::table('users')->where('email', $request->input('email'))->value('id');
                if ($uid) {
                    Dbx::insert('session_logs', ['user_id' => $uid, 'login_time' => now()->toDateTimeString(), 'ip_address' => $request->ip(), 'device' => mb_substr((string) $request->userAgent(), 0, 190)]);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $response;
    }
}
