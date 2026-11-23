<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * A patient sees only their own appointments and account, never another patient's
 * (WST criterion 3, least privilege). Found in the full-system test of 23 November 2026.
 */
class OwnRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $u): array
    {
        return ['Authorization' => 'Bearer ' . Auth::guard('api')->login($u)];
    }

    public function test_patient_lists_only_own_appointments_and_cannot_open_anothers(): void
    {
        $clinic = Clinic::factory()->create();
        $meUser = User::factory()->create(['role' => 'patient', 'clinic_id' => $clinic->clinic_id]);
        $me = Patient::factory()->create(['user_id' => $meUser->id, 'clinic_id' => $clinic->clinic_id]);
        $other = Patient::factory()->create(['clinic_id' => $clinic->clinic_id]);
        $mine = Appointment::factory()->create(['patient_id' => $me->patient_id]);
        $theirs = Appointment::factory()->create(['patient_id' => $other->patient_id]);
        $h = $this->headers($meUser);

        $ids = collect($this->getJson('/api/v1/appointments', $h)->assertOk()->json('data'))->pluck('id')->map(fn ($i) => (int) $i);
        $this->assertTrue($ids->contains($mine->appointment_id));
        $this->assertFalse($ids->contains($theirs->appointment_id));

        $this->getJson("/api/v1/appointments/{$theirs->appointment_id}", $h)->assertStatus(403);
        $this->getJson("/api/v1/patients/{$other->patient_id}/appointments", $h)->assertStatus(403);
        $this->getJson("/api/v1/patients/{$me->patient_id}/appointments", $h)->assertOk();
    }

    public function test_patient_sees_only_own_account_in_users(): void
    {
        $clinic = Clinic::factory()->create();
        $meUser = User::factory()->create(['role' => 'patient', 'clinic_id' => $clinic->clinic_id]);
        $staff = User::factory()->create(['role' => 'receptionist', 'clinic_id' => $clinic->clinic_id]);
        $h = $this->headers($meUser);

        $ids = collect($this->getJson('/api/v1/users', $h)->assertOk()->json('data'))->pluck('id')->map(fn ($i) => (int) $i);
        $this->assertEquals([$meUser->id], $ids->all());
        $this->getJson("/api/v1/users/{$staff->id}", $h)->assertStatus(403);
    }
}
