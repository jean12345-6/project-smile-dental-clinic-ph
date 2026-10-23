<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginUserRequest;
use App\Http\Requests\Api\RegisterUserRequest;
use App\Models\User;
use App\Traits\ApiResponses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Gives this controller the ok(), success() and error() response helpers
    use ApiResponses;

    // Register: creates the account and logs the user in immediately
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
    public function login(LoginUserRequest $request)
    {
        $credentials = $request->only('email', 'password');

        // attempt() returns false when the email or password is wrong
        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return $this->error('Invalid credentials', 401);
        }

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