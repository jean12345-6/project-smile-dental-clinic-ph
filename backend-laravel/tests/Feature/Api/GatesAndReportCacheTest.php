<?php

namespace Tests\Feature\Api;

use App\Models\Clinic;
use App\Models\User;
use App\Support\ReportCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Gates (WST criterion 3) and report caching (WST criterion 18).
 */
class GatesAndReportCacheTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $clinic = Clinic::factory()->create();

        return User::factory()->create(['role' => $role, 'clinic_id' => $clinic->clinic_id, 'status' => 'active']);
    }

    public function test_gates_follow_least_privilege(): void
    {
        $admin = $this->user('clinic_admin');
        $reception = $this->user('receptionist');
        $patient = $this->user('patient');

        $this->assertTrue(Gate::forUser($admin)->allows('manage-settings'));
        $this->assertTrue(Gate::forUser($admin)->allows('view-finance'));
        $this->assertTrue(Gate::forUser($reception)->allows('view-reports'));
        $this->assertFalse(Gate::forUser($reception)->allows('view-revenue'));
        $this->assertFalse(Gate::forUser($reception)->allows('manage-settings'));
        $this->assertFalse(Gate::forUser($patient)->allows('view-reports'));
    }

    public function test_receptionist_is_refused_settings_through_the_gate(): void
    {
        $token = Auth::guard('api')->login($this->user('receptionist'));

        $this->getJson('/api/v1/app-settings', ['Authorization' => "Bearer $token"])->assertStatus(403);
    }

    public function test_cached_report_is_reused_until_data_changes(): void
    {
        $runs = 0;
        $compute = function () use (&$runs) { $runs++; return ['total' => $runs]; };

        ReportCache::remember('test', ['a' => 1], $compute);
        ReportCache::remember('test', ['a' => 1], $compute);
        $this->assertSame(1, $runs, 'second view should come from the cache');

        ReportCache::bump(); // what the middleware does after a successful write
        ReportCache::remember('test', ['a' => 1], $compute);
        $this->assertSame(2, $runs, 'after a change the report is computed fresh');
    }

    public function test_a_successful_write_marks_reports_out_of_date(): void
    {
        $before = ReportCache::version();
        $token = Auth::guard('api')->login($this->user('clinic_admin'));

        $this->putJson('/api/v1/app-settings', ['reminder_hours' => 24], ['Authorization' => "Bearer $token"])->assertOk();

        $this->assertGreaterThan($before, ReportCache::version());
    }
}
