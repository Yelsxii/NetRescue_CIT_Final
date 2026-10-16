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
        // Budget one precheck and up to three follow-ups, with no unbounded retry.
        $this->assertGreaterThan(144, $timeout->recoverySeconds('renew_dhcp', 'dhcp_issue'));
        $this->assertGreaterThan(109, $timeout->recoverySeconds('enable_adapter', 'adapter_disabled'));
        $this->assertGreaterThan(60, $timeout->checkSeconds());
        $this->assertSame(80, $timeout->checkSeconds());
        $this->assertSame(292, $timeout->recoverySeconds('enable_adapter', 'adapter_disabled'));
        config(['netrescue.verification.attempts' => 100, 'netrescue.verification.retry_delay_seconds' => 100]);
        $this->assertSame(300, $timeout->recoverySeconds('enable_adapter', 'adapter_disabled'));
    }

    public function test_dns_allowance_is_included_in_every_check_and_automatic_follow_up(): void
    {
        $timeout = app(NetworkRequestTimeout::class);
        config(['netrescue.timeouts.dns_seconds' => 6]);
        $oldCheck = $timeout->checkSeconds();
        $oldRecovery = $timeout->recoverySeconds('flush_dns', 'dns_failure');
        config(['netrescue.timeouts.dns_seconds' => 20]);

        $this->assertSame($oldCheck + 14, $timeout->checkSeconds());
        // One precheck and three verification readings each need the DNS budget.
        $this->assertSame($oldRecovery + 4 * 14, $timeout->recoverySeconds('flush_dns', 'dns_failure'));
    }

    public function test_custom_probe_limits_and_all_configured_targets_are_accounted_for(): void
    {
        MonitorSetting::current()->update(['internet_targets' => ['1.1.1.1:443', '8.8.8.8:53', '9.9.9.9:443']]);
        config(['netrescue.timeouts.powershell_seconds' => 40, 'netrescue.timeouts.ping_seconds' => 10,
            'netrescue.timeouts.socket_seconds' => 5, 'netrescue.timeouts.dns_seconds' => 10,
            'netrescue.timeouts.recovery_seconds' => 40]);
        $timeout = app(NetworkRequestTimeout::class);
        // All configured target deadlines and each allowed follow-up are covered.
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
        $reading = function () use ($check) {
            $this->assertGreaterThan(60, (int) ini_get('max_execution_time'));

            return ['check' => $check];
        };
        $mock->shouldReceive('run')->once()->with('manual', true)->andReturnUsing($reading);
        $mock->shouldReceive('run')->once()->with('manual', true, null)->andReturnUsing($reading);
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

    public function test_adapter_limits_and_reconnect_window_share_the_request_and_lock_allowance(): void
    {
        $timeout = app(NetworkRequestTimeout::class);
        $this->assertSame(60.0, $timeout->commandSeconds('enable_adapter'));
        $this->assertSame(60.0, $timeout->commandSeconds('restart_adapter'));
        $this->assertSame(35.0, $timeout->commandSeconds('flush_dns'));
        $this->assertSame(35.0, $timeout->commandSeconds('renew_dhcp'));
        config(['netrescue.timeouts.adapter_recovery_seconds' => 90, 'netrescue.verification.reconnect_window_seconds' => 120]);
        $this->assertGreaterThanOrEqual(36 + 90 + 120 + 36 + 30, $timeout->recoverySeconds('enable_adapter', 'adapter_disabled'));
        config(['netrescue.timeouts.adapter_recovery_seconds' => INF, 'netrescue.verification.reconnect_window_seconds' => NAN]);
        $this->assertSame(60.0, $timeout->commandSeconds('enable_adapter'));
        $this->assertSame(60.0, $timeout->reconnectSeconds());
    }
}
