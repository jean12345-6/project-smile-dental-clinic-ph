<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checks the security rules the panel asked about (WST criteria 2, 3, 5 and 6).
 * Runs on every push through GitHub Actions (.github/workflows/tests.yml).
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'staff@smilesync.test'): User
    {
        return User::factory()->create(['email' => $email, 'password' => bcrypt('correct-password')]);
    }

    public function test_correct_password_returns_a_token(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', ['email' => 'staff@smilesync.test', 'password' => 'correct-password'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);
    }

    public function test_wrong_password_returns_401_and_says_how_many_tries_are_left(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', ['email' => 'staff@smilesync.test', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonFragment(['message' => 'Invalid credentials. 4 attempt(s) left before a short lock.']);
    }

    public function test_login_is_locked_after_five_wrong_passwords(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'staff@smilesync.test', 'password' => 'wrong'])->assertStatus(401);
        }

        // Even the correct password is refused while the lock is on
        $this->postJson('/api/v1/auth/login', ['email' => 'staff@smilesync.test', 'password' => 'correct-password'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_failed_logins_are_written_to_the_audit_log(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', ['email' => 'staff@smilesync.test', 'password' => 'wrong']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed', 'table_name' => 'users']);
    }

    public function test_protected_routes_need_a_token(): void
    {
        $this->getJson('/api/v1/appointments')->assertStatus(401);
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
    }

    public function test_cors_allows_the_clinic_site_only(): void
    {
        $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://smilesync.projectsmiledentalclinic.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', 'https://smilesync.projectsmiledentalclinic.com');

        $evil = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $this->assertNotEquals('https://evil.example.com', $evil->headers->get('Access-Control-Allow-Origin'));
    }
}
