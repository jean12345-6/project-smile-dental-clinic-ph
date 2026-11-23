<?php

namespace Tests\Feature\Api;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sign in with Google. Google's tokeninfo endpoint is faked, so the tests run offline.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT = 'test-client.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => self::CLIENT]);
        Clinic::factory()->create();
    }

    private function fakeGoogle(array $claims = [], int $status = 200): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(array_merge([
            'aud' => self::CLIENT,
            'iss' => 'https://accounts.google.com',
            'exp' => (string) (time() + 3600),
            'email' => 'maria.santos@gmail.com',
            'email_verified' => 'true',
            'name' => 'Maria Santos',
        ], $claims), $status)]);
    }

    public function test_config_tells_the_login_page_the_client_id(): void
    {
        $this->getJson('/api/v1/auth/google/config')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.clientId', self::CLIENT);
    }

    public function test_existing_account_signs_in_with_google(): void
    {
        $user = User::factory()->create(['email' => 'maria.santos@gmail.com']);
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/google', ['credential' => 'header.payload.signature'])
            ->assertOk()
            ->assertJsonPath('data.new_account', false)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'login_google', 'user_id' => $user->id]);
        $this->assertDatabaseHas('session_logs', ['user_id' => $user->id]);
    }

    public function test_new_google_user_gets_a_patient_account(): void
    {
        $this->fakeGoogle(['email' => 'new.patient@gmail.com', 'name' => 'New Patient']);

        $this->postJson('/api/v1/auth/google', ['credential' => 'header.payload.signature'])
            ->assertOk()
            ->assertJsonPath('data.new_account', true);

        $this->assertDatabaseHas('users', ['email' => 'new.patient@gmail.com', 'role' => 'patient']);
        $this->assertDatabaseHas('patients', ['email' => 'new.patient@gmail.com', 'full_name' => 'New Patient']);
    }

    public function test_token_for_another_app_is_rejected(): void
    {
        $this->fakeGoogle(['aud' => 'someone-elses-app.apps.googleusercontent.com']);

        $this->postJson('/api/v1/auth/google', ['credential' => 'x.y.z'])->assertStatus(401);
        $this->assertDatabaseMissing('users', ['email' => 'maria.santos@gmail.com']);
    }

    public function test_unverified_email_is_rejected(): void
    {
        $this->fakeGoogle(['email_verified' => 'false']);

        $this->postJson('/api/v1/auth/google', ['credential' => 'x.y.z'])->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->fakeGoogle(['error' => 'invalid_token'], 400);

        $this->postJson('/api/v1/auth/google', ['credential' => 'x.y.z'])->assertStatus(401);
    }

    public function test_credential_is_required(): void
    {
        $this->postJson('/api/v1/auth/google', [])->assertStatus(422);
    }
}
