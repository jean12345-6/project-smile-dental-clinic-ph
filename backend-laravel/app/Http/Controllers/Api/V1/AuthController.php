<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginUserRequest;
use App\Http\Requests\Api\RegisterUserRequest;
use App\Models\User;
use App\Traits\ApiResponses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\Dbx;


/**
 * @group Authentication
 *
 * Register, login, logout and refresh tokens.
 */
class AuthController extends Controller
{
    // Gives this controller the ok(), success() and error() response helpers
    use ApiResponses;

    // Login attempt limit (WST criterion 2)
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 60;

    // Register: creates the account and logs the user in immediately
    /**
     * Register
     *
     * Creates the account and logs the user in immediately.
     *
     * @unauthenticated
     */
    public function register(RegisterUserRequest $request)
    {
        // Self-registration is for patients only; staff roles are never set from the request
        $user = User::create([
            'clinic_id' => $request->clinic_id,
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => 'patient',
            'status'    => 'active',
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->ok('Registered successfully', $this->formatToken($token));
    }

    // Login: checks the credentials and returns a token when they are correct

        /**
     * Log in
     *
     * Returns an access token. Send it as a Bearer token on every other request.
     *
     * @unauthenticated
     */
    public function login(LoginUserRequest $request)
    {
        $credentials = $request->only('email', 'password');

        // Login attempt limit: 5 wrong passwords for the same email from the same IP
        // locks that pair for 60 seconds. The key is per email AND per IP, so one
        // attacker cannot lock out a real user who logs in from somewhere else.
        $key = 'login|' . mb_strtolower(trim((string) $request->input('email'))) . '|' . $request->ip();
        $userId = User::where('email', $request->input('email'))->value('id');

        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            Dbx::audit(null, 'login_locked', 'users', $userId, null, ['email' => $request->input('email'), 'ip' => $request->ip(), 'retry_in_seconds' => $seconds]);

            return $this->error("Too many login attempts. Please try again in {$seconds} seconds.", 429)
                ->header('Retry-After', $seconds);
        }

        // attempt() returns false when the email or password is wrong
        if (! $token = Auth::guard('api')->attempt($credentials)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
            $left = RateLimiter::remaining($key, self::MAX_LOGIN_ATTEMPTS);
            Dbx::audit(null, 'login_failed', 'users', $userId, null, ['email' => $request->input('email'), 'ip' => $request->ip(), 'attempts_left' => $left]);

            $msg = $left > 0
                ? "Invalid credentials. {$left} attempt(s) left before a short lock."
                : 'Invalid credentials. Too many attempts: login is locked for ' . self::LOCKOUT_SECONDS . ' seconds.';

            return $this->error($msg, 401);
        }

        // A correct login resets the counter
        RateLimiter::clear($key);

        return $this->ok('Login successful', $this->formatToken($token));
    }

    // Me: returns the user that owns the Bearer token
    public function me()
    {
        return $this->ok('User retrieved', Auth::guard('api')->user());
    }

    // Maps the raw token to access_token / token_type / expires_in (seconds)
    protected function formatToken(string $token): array
    {
        return [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
        ];
    }

    // Refresh: blacklists the current token and returns a brand-new one
    public function refresh()
    {
        return $this->ok('Token refreshed', $this->formatToken(Auth::guard('api')->refresh()));
    }

    // Logout: blacklists the current token so it can never be used again
    public function logout()
    {
        Auth::guard('api')->logout();

        return $this->ok('Successfully logged out', null);
    }
}
