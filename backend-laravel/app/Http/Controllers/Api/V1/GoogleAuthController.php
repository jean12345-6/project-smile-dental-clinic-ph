<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Dbx;
use App\Traits\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @group Authentication
 *
 * Sign in with Google (WST criterion 2: authentication with Google OAuth).
 *
 * Flow:
 *  1. The login page shows Google's own "Sign in with Google" button (Google Identity Services).
 *  2. Google returns a signed ID token (a JWT) to the browser.
 *  3. The browser sends it here. We ask Google to verify it, then check that it was issued
 *     for OUR client ID and that Google has verified the email address.
 *  4. Existing account with that email -> logged in. No account -> a new patient account is
 *     created (staff accounts are only ever made by the clinic administrator).
 *  5. We return our own SmileSync JWT, the same as the normal login.
 */
class GoogleAuthController extends Controller
{
    use ApiResponses;

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * Google sign-in settings
     *
     * The login page calls this to know whether to show the Google button.
     *
     * @unauthenticated
     */
    public function config()
    {
        $clientId = (string) config('services.google.client_id');

        return $this->ok('Google sign-in settings', [
            'enabled'  => $clientId !== '',
            'clientId' => $clientId ?: null,
        ]);
    }

    /**
     * Sign in with Google
     *
     * Send the ID token ("credential") that Google's button returned.
     *
     * @unauthenticated
     *
     * @bodyParam credential string required The Google ID token.
     */
    public function login(Request $request)
    {
        $request->validate(['credential' => 'required|string|max:4096']);

        $clientId = (string) config('services.google.client_id');
        if ($clientId === '') {
            return $this->error('Google sign-in is not set up on this server.', 503);
        }

        // 1. Ask Google to check the token's signature and expiry
        try {
            $res = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $request->credential]);
        } catch (\Throwable $e) {
            return $this->error('Could not reach Google. Please try again.', 502);
        }
        if (! $res->ok()) {
            Dbx::audit(null, 'google_rejected', 'users', null, null, ['reason' => 'invalid token', 'ip' => $request->ip()]);

            return $this->error('Google sign-in failed: the token is not valid.', 401);
        }
        $claims = $res->json();

        // 2. The token must be for THIS app, from Google, with a verified email
        $verified = in_array((string) ($claims['email_verified'] ?? ''), ['true', '1'], true) || ($claims['email_verified'] ?? null) === true;
        if (($claims['aud'] ?? null) !== $clientId
            || ! in_array($claims['iss'] ?? '', self::ISSUERS, true)
            || (int) ($claims['exp'] ?? 0) < time()
            || ! $verified
            || empty($claims['email'])) {
            Dbx::audit(null, 'google_rejected', 'users', null, null, ['reason' => 'wrong app, expired or unverified email', 'ip' => $request->ip()]);

            return $this->error('Google sign-in failed: this Google account could not be verified for SmileSync.', 401);
        }

        $email = mb_strtolower($claims['email']);
        $name = trim((string) ($claims['name'] ?? '')) ?: Str::before($email, '@');

        // 3. Find the account, or create a patient account
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $created = false;

        if (! $user) {
            $clinicId = DB::table('clinics')->orderBy('clinic_id')->value('clinic_id');
            $uid = DB::transaction(function () use ($email, $name, $clinicId) {
                // A random password: this account signs in with Google until the patient sets one
                $uid = Dbx::insert('users', ['name' => $name, 'email' => $email, 'username' => $email, 'password' => Hash::make(Str::random(40)), 'role' => 'patient', 'clinic_id' => $clinicId, 'status' => 'active']);
                Dbx::insert('patients', ['user_id' => $uid, 'clinic_id' => $clinicId, 'full_name' => $name, 'email' => $email, 'no_show_count' => 0, 'status' => 'active']);

                return $uid;
            });
            $user = User::find($uid);
            $created = true;
            Dbx::audit($user, 'create', 'users', $user->id, null, ['role' => 'patient', 'via' => 'google']);
        }

        if (($user->status ?? 'active') === 'inactive') {
            return $this->error('This account is disabled. Please contact the clinic.', 403);
        }

        // 4. Our own token, exactly like the normal login
        $token = Auth::guard('api')->login($user);

        Dbx::audit($user, 'login_google', 'users', $user->id, null, ['ip' => $request->ip()]);
        try {
            Dbx::insert('session_logs', ['user_id' => $user->id, 'login_time' => now()->toDateTimeString(), 'ip_address' => $request->ip(), 'device' => mb_substr('Google sign-in · ' . $request->userAgent(), 0, 190)]);
        } catch (\Throwable $e) {
            // logging must never block a login
        }

        return $this->ok($created ? 'Account created with Google. Welcome!' : 'Login successful', [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
            'new_account'  => $created,
        ]);
    }
}
