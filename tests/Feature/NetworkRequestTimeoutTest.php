<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\MonitorSetting;
use App\Models\RecoveryAction;
use App\Services\Network\NetworkHealthService;
use App\Services\Network\NetworkRequestTimeout;
use App\Services\Network\RecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NetworkRequestTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function incident(): Incident
    {
        return Incident::create([
            'device_id' => Device::localHost()->id, 'code' => 'adapter_disabled', 'title' => 'Network Adapter Disabled',
            'severity' => 'critical', 'status' => 'active', 'first_detected_at' => now(),
            'last_detected_at' => now(), 'recovery_action_code' => 'enable_adapter',
        ]);
    }

    public function test_recovery_allowance_covers_the_complete_default_pipeline(): void
    {
        $timeout = app(NetworkRequestTimeout::class);
        // Default readings can each use 36 seconds; DHCP has two 35-second commands.
        $this->assertGreaterThan(144, $timeout->recoverySeconds('renew_dhcp', 'dhcp_issue'));
        $this->assertGreaterThan(109, $timeout->recoverySeconds('enable_adapter', 'adapter_disabled'));
        $this->assertGreaterThan(60, $timeout->checkSeconds());
    }

    public function test_custom_probe_limits_and_all_configured_targets_are_accounted_for(): void
    {
        MonitorSetting::current()->update(['internet_targets' => ['1.1.1.1:443', '8.8.8.8:53', '9.9.9.9:443']]);
        config(['netrescue.timeouts.powershell_seconds' => 40, 'netrescue.timeouts.ping_seconds' => 10,
            'netrescue.timeouts.socket_seconds' => 5, 'netrescue.timeouts.dns_seconds' => 10,
            'netrescue.timeouts.recovery_seconds' => 40]);
        $timeout = app(NetworkRequestTimeout::class);
        // Two 75-second readings plus two 40-second commands exceed the usual 180-second minimum.
        $this->assertGreaterThan(232, $timeout->recoverySeconds('renew_dhcp', 'dhcp_issue'));
        $this->assertGreaterThan($timeout->recoverySeconds('enable_adapter', 'adapter_disabled'),
            $timeout->recoverySeconds('renew_dhcp', 'dhcp_issue'));
    }

    public function test_repair_request_extends_php_allowance_before_the_service_starts(): void
    {
        $incident = $this->incident();
        $mock = Mockery::mock(RecoveryService::class);
        $mock->shouldReceive('attempt')->once()->andReturnUsing(function () {
            $this->assertGreaterThanOrEqual(180, (int) ini_get('max_execution_time'));

            return new RecoveryAction(['execution_status' => 'skipped', 'verification_status' => 'already_recovered']);
        });
        $this->app->instance(RecoveryService::class, $mock);
        $old = (int) ini_get('max_execution_time');
        try {
            set_time_limit(60);
            $this->post('/incidents/'.$incident->id.'/recover')->assertRedirect('/incidents/'.$incident->id);
        } finally {
            set_time_limit($old);
        }
    }

    public function test_check_and_retest_prepare_the_allowance_before_measurement(): void
    {
        $incident = $this->incident();
        $check = new HealthCheck(['diagnosis_code' => 'healthy', 'diagnosis_title' => 'Network Operational', 'severity' => 'none', 'health_score' => 100]);
        $mock = Mockery::mock(NetworkHealthService::class);
        $mock->shouldReceive('run')->twice()->with('manual', true)->andReturnUsing(function () use ($check) {
            $this->assertGreaterThan(60, (int) ini_get('max_execution_time'));

            return ['check' => $check];
        });
        $this->app->instance(NetworkHealthService::class, $mock);
        $old = (int) ini_get('max_execution_time');
        try {
            foreach (['/health-checks', '/incidents/'.$incident->id.'/retest'] as $url) {
                set_time_limit(60);
                $this->post($url)->assertRedirect();
            }
        } finally {
            set_time_limit($old);
        }
    }

    public function test_an_unadjustable_request_limit_blocks_repair_before_execution(): void
    {
        $incident = $this->incident();
        $timeout = Mockery::mock(NetworkRequestTimeout::class);
        $timeout->shouldReceive('prepareRecovery')->once()->with('enable_adapter', 'adapter_disabled')
            ->andThrow(new RuntimeException('Serving PHP limit cannot be increased.'));
        $this->app->instance(NetworkRequestTimeout::class, $timeout);
        $service = Mockery::mock(RecoveryService::class);
        $service->shouldNotReceive('attempt');
        $this->app->instance(RecoveryService::class, $service);
        $this->from('/history')->post('/incidents/'.$incident->id.'/recover')
            ->assertRedirect('/history')->assertSessionHas('toast.title', 'Recovery could not start');
        $this->assertDatabaseCount('recovery_actions', 0);
    }
}
