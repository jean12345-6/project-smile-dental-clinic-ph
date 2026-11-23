<?php

namespace Tests\Feature\Api;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The superadmin sees every clinic on the Clinics page even after picking one clinic
 * in the dashboard switcher. A clinic admin still sees only their own clinic.
 */
class SuperadminClinicsTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $u, array $extra = []): array
    {
        return ['Authorization' => 'Bearer ' . Auth::guard('api')->login($u)] + $extra;
    }

    public function test_superadmin_sees_all_clinics_even_with_a_clinic_picked(): void
    {
        $a = Clinic::factory()->create();
        $b = Clinic::factory()->create();
        $super = User::factory()->create(['role' => 'superadmin', 'clinic_id' => null]);

        $ids = collect($this->getJson('/api/v1/r/clinics', $this->headers($super, ['X-Clinic-Id' => (string) $a->clinic_id]))
            ->assertOk()->json('data'))->pluck('clinic_id')->map(fn ($i) => (int) $i);
        $this->assertTrue($ids->contains($a->clinic_id));
        $this->assertTrue($ids->contains($b->clinic_id));
    }

    public function test_clinic_admin_sees_only_own_clinic(): void
    {
        $a = Clinic::factory()->create();
        $b = Clinic::factory()->create();
        $admin = User::factory()->create(['role' => 'clinic_admin', 'clinic_id' => $a->clinic_id]);

        $ids = collect($this->getJson('/api/v1/r/clinics', $this->headers($admin))
            ->assertOk()->json('data'))->pluck('clinic_id')->map(fn ($i) => (int) $i);
        $this->assertTrue($ids->contains($a->clinic_id));
        $this->assertFalse($ids->contains($b->clinic_id));
    }
}
