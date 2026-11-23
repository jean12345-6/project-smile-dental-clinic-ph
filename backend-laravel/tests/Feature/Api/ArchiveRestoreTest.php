<?php

namespace Tests\Feature\Api;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Patient records are archived, never erased, and can be restored (WST criterion 11).
 */
class ArchiveRestoreTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clinic = Clinic::factory()->create();
        $this->patient = Patient::factory()->create(['clinic_id' => $this->clinic->clinic_id, 'status' => 'active']);
    }

    private function as(string $role): array
    {
        $user = User::factory()->create(['role' => $role, 'clinic_id' => $this->clinic->clinic_id, 'status' => 'active']);

        return ['Authorization' => 'Bearer ' . Auth::guard('api')->login($user)];
    }

    public function test_archive_keeps_the_record_and_hides_it_from_the_list(): void
    {
        $h = $this->as('clinic_admin');

        $this->deleteJson("/api/v1/r/patient-records/{$this->patient->patient_id}", [], $h)->assertOk();

        $this->assertDatabaseHas('patients', ['patient_id' => $this->patient->patient_id, 'status' => 'archived']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'archive', 'record_id' => $this->patient->patient_id]);
        $this->getJson('/api/v1/r/patient-records', $h)->assertJsonMissing(['patient_id' => $this->patient->patient_id]);
        $this->getJson('/api/v1/r/patient-records?status=archived', $h)->assertJsonFragment(['patient_id' => $this->patient->patient_id]);
    }

    public function test_restore_brings_the_patient_back(): void
    {
        $h = $this->as('clinic_admin');
        $this->deleteJson("/api/v1/r/patient-records/{$this->patient->patient_id}", [], $h);

        $this->postJson("/api/v1/r/patient-records/{$this->patient->patient_id}/restore", [], $h)->assertOk();

        $this->assertDatabaseHas('patients', ['patient_id' => $this->patient->patient_id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'restore', 'record_id' => $this->patient->patient_id]);
    }

    public function test_dentist_cannot_restore(): void
    {
        $this->deleteJson("/api/v1/r/patient-records/{$this->patient->patient_id}", [], $this->as('clinic_admin'));

        $this->postJson("/api/v1/r/patient-records/{$this->patient->patient_id}/restore", [], $this->as('dentist'))->assertStatus(403);
    }

    public function test_only_archived_records_can_be_restored(): void
    {
        $this->postJson("/api/v1/r/patient-records/{$this->patient->patient_id}/restore", [], $this->as('clinic_admin'))->assertStatus(422);
    }
}
