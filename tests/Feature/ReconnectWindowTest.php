<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\MonitorSetting;
use App\Models\RecoveryAction;
use App\Services\Network\NetworkHealthService;
use App\Services\Network\NetworkProbeService;
use App\Services\Network\RecoveryCommandRunner;
use App\Services\Network\RecoveryService;
use App\Services\Network\RecoveryWaitClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReconnectWindowTest extends TestCase
{
    use RefreshDatabase;

    private RecoveryWaitClock $clock;
    private array $configurationArguments = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        MonitorSetting::current()->update(['enabled' => false]);
        config(['netrescue.verification.retry_delay_seconds' => 0,
            'netrescue.verification.reconnect_window_seconds' => 60,
            'netrescue.verification.reconnect_poll_seconds' => 2]);
        $this->clock = new class extends RecoveryWaitClock {
            public float $seconds = 0;
            public array $waits = [];
            public function now(): float { return $this->seconds; }
            public function wait(float $seconds): void { $this->waits[] = $seconds; $this->seconds += $seconds; }
        };
        $this->app->instance(RecoveryWaitClock::class, $this->clock);
    }

    private function disabled(): array
    {
        return ['supported' => true, 'adapter_available' => false, 'adapter_state' => 'disabled',
            'interface_name' => 'Wi-Fi', 'local_ip' => null, 'gateway_ip' => null, 'dns_servers' => [],
            'address_source' => 'unknown', 'dns_source' => 'unknown', 'error' => null];
    }

    private function transition(string $reason = 'adapter_transitioning', ?string $name = 'Wi-Fi'): array
    {
        return array_merge($this->disabled(), ['adapter_state' => null, 'interface_name' => $name,
            'error' => 'The requested adapter has no stable link state. Windows status: Unknown.',
            'error_code' => $reason, 'windows_adapter_status' => 'Unknown']);
    }

    private function healthy(): array
    {
        return array_merge($this->disabled(), ['adapter_available' => true, 'adapter_state' => 'up',
            'local_ip' => '192.168.1.5', 'gateway_ip' => '192.168.1.1', 'dns_servers' => ['192.168.1.1']]);
    }

    private function readings(array $sequence, ?array $repeat = null): void
    {
        $probe = Mockery::mock(NetworkProbeService::class);
        $index = 0;
        $probe->shouldReceive('collectConfiguration')->andReturnUsing(function (...$arguments) use ($sequence, $repeat, &$index) {
            $this->configurationArguments[] = $arguments;
            [$configuration, $seconds] = $sequence[$index++] ?? $repeat ?? throw new \RuntimeException('Unexpected extra configuration read');
            $this->clock->seconds += $seconds;
            return $configuration;
        });
        $probe->shouldReceive('probeGateway')->andReturn(['reachable' => true, 'latency_ms' => 2, 'error' => null]);
        $probe->shouldReceive('probeInternet')->andReturn(['reachable' => true, 'latency_ms' => 20, 'target' => '1.1.1.1:443', 'error' => null]);
        $probe->shouldReceive('probeDns')->andReturn(['operational' => true, 'latency_ms' => 30, 'addresses' => ['93.184.215.14'], 'error' => null]);
        $this->app->instance(NetworkProbeService::class, $probe);
    }

    private function command(string $result = 'completed'): void
    {
        $runner = Mockery::mock(RecoveryCommandRunner::class);
        $runner->shouldReceive('run')->once()->with('enable_adapter', 'adapter_disabled', 'Wi-Fi')
            ->andReturn(['successful' => $result === 'completed',
                'output' => 'NetRescue command stage: powershell_started',
                'error' => $result === 'completed' ? '' : ($result === 'timed_out' ? 'Process exceeded the timeout of 60 seconds.' : 'Access denied'),
                'exit_code' => $result === 'completed' ? 0 : ($result === 'timed_out' ? null : 1),
                'timed_out' => $result === 'timed_out']);
        $this->app->instance(RecoveryCommandRunner::class, $runner);
    }

    public function test_transitions_continue_past_three_reads_and_verify_automatically_while_paused(): void
    {
        $this->readings([[$this->disabled(), 0], [$this->disabled(), 0],
            [$this->transition(), 7.79], [$this->transition(), 8.07], [$this->transition(), 1.44],
            [$this->transition(), 3], [$this->healthy(), 1.08]]);
        $incident = app(NetworkHealthService::class)->run('manual')['incident'];
        $this->command();
        $this->post('/incidents/'.$incident->id.'/recover', ['return_to' => 'dashboard'])
            ->assertRedirect('/')->assertSessionHas('toast.title', 'Recovery verified');
        $action = RecoveryAction::sole();
        $this->assertTrue($action->success);
        $this->assertSame(0, $action->command_exit_code);
        $this->assertSame('verified_repair', $incident->fresh()->resolution_kind);
        $this->assertSame('Wi-Fi', $action->afterHealthCheck->interface_name);
        $meta = $action->afterHealthCheck->probe_meta['recovery_verification'];
        $this->assertSame(5, $meta['attempt']);
        $this->assertNull($meta['maximum_attempts']);
        $this->assertSame('reconnect_window', $meta['retry_policy']);
        $this->assertSame('settled', $meta['stop_reason']);
        $this->assertEqualsWithDelta(29380, $meta['elapsed_ms'], .1);
        $this->assertCount(4, $action->earlierVerificationChecks());
        $this->assertFalse(MonitorSetting::current()->enabled);
        $this->get('/')->assertViewHas('localLatest', fn ($check) => $check->id === $action->after_health_check_id);
        $this->get('/status')->assertJsonPath('latest_check_id', $action->after_health_check_id);
        foreach ([[], ['X-Panel' => '1']] as $headers) {
            $this->get('/incidents/'.$incident->id, $headers)
                ->assertSeeText('Verification readings')->assertSeeText('Reconnect window')->assertSeeText('Up to 60 seconds')
                ->assertSeeText('Windows adapter status: Unknown')
                ->assertSeeText('Command time limit');
        }
    }

    public function test_fast_transition_errors_use_elapsed_time_then_stop_without_claiming_recovery(): void
    {
        $this->readings([[$this->disabled(), 0], [$this->disabled(), 0]], [$this->transition(), 0]);
        $incident = app(NetworkHealthService::class)->run('manual')['incident'];
        $this->command();
        $result = app(RecoveryService::class)->attempt($incident);
        $meta = $result->afterHealthCheck->probe_meta['recovery_verification'];
        $this->assertSame(30, $meta['attempt']);
        $this->assertSame('reconnect_window_elapsed', $meta['stop_reason']);
        $this->assertEquals(60000, $meta['elapsed_ms']);
        $this->assertFalse($result->success);
        $this->assertSame('completed', $result->execution_status);
        $this->assertSame('unread', $result->verification_status);
        $this->assertSame('active', $incident->fresh()->status);
        $this->assertNull($incident->fresh()->closing_health_check_id);
        $this->get('/incidents/'.$incident->id)->assertSeeText('The reconnect window ended before a settled result was obtained.')
            ->assertSeeText('After:')->assertSeeText('Reading error: The requested adapter has no stable link state. Windows status: Unknown.')
            ->assertDontSeeText('Windows did not return the adapter in time');
    }

    public function test_timed_out_command_stays_unconfirmed_even_after_extended_verification_observes_recovery(): void
    {
        $this->readings([[$this->disabled(), 0], [$this->disabled(), 0],
            [$this->transition(), 1], [$this->transition(), 1], [$this->transition(), 1], [$this->healthy(), 1]]);
        $incident = app(NetworkHealthService::class)->run('manual')['incident'];
        $this->command('timed_out');
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame(4, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertFalse($result->success);
        $this->assertNull($result->command_exit_code);
        $this->assertSame('timed_out', $result->execution_status);
        $this->assertSame('observed_recovery', $result->verification_status);
        $this->assertSame('observed_recovery', $incident->fresh()->resolution_kind);
    }

    public function test_access_denied_does_not_activate_the_reconnect_window(): void
    {
        $this->readings([[$this->disabled(), 0], [$this->disabled(), 0]], [$this->transition(), 0]);
        $incident = app(NetworkHealthService::class)->run('manual')['incident'];
        $this->command('failed');
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame(3, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertSame('attempts', data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.retry_policy'));
        $this->assertSame('Access denied', $result->error_message);
        $this->assertEquals(0, $this->clock->seconds);
    }

    public function test_another_adapters_transition_cannot_extend_or_verify_the_target(): void
    {
        $this->readings([[$this->disabled(), 0], [$this->disabled(), 0]], [$this->transition('adapter_transitioning', 'Ethernet'), 0]);
        $incident = app(NetworkHealthService::class)->run('manual')['incident'];
        $this->command();
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame(3, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertSame('attempts', data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.retry_policy'));
        $this->assertSame('active', $incident->fresh()->status);
        $this->assertFalse($result->success);
    }

    public function test_the_last_configuration_read_is_bounded_by_remaining_window_time(): void
    {
        $this->readings([[$this->disabled(), 0], [$this->disabled(), 0],
            [$this->transition('process_timed_out', null), 20], [$this->transition('process_timed_out', null), 20],
            [$this->transition('process_timed_out', null), 16]]);
        $incident = app(NetworkHealthService::class)->run('manual')['incident'];
        $this->command();
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame(['Wi-Fi', 16.0], $this->configurationArguments[4]);
        $this->assertSame('reconnect_window_elapsed', data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.stop_reason'));
        $this->assertSame(20, config('netrescue.timeouts.powershell_seconds'));
    }

    public function test_unrelated_reader_failure_keeps_the_standard_attempt_limit(): void
    {
        $this->readings([[$this->disabled(), 0], [$this->disabled(), 0]], [$this->transition('configuration_read_failed'), 0]);
        $incident = app(NetworkHealthService::class)->run('manual')['incident'];
        $this->command();
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame(3, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertSame('reading_limit', data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.stop_reason'));
        $this->assertEquals(0, $this->clock->seconds);
    }

    public function test_an_explicit_single_read_setting_does_not_silently_enable_more_reads(): void
    {
        config(['netrescue.verification.attempts' => 1]);
        $this->readings([[$this->disabled(), 0], [$this->disabled(), 0]], [$this->transition(), 0]);
        $incident = app(NetworkHealthService::class)->run('manual')['incident'];
        $this->command();
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame(1, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertEquals(0, $this->clock->seconds);
    }

    public function test_a_later_manual_closing_read_does_not_rewrite_failed_automatic_verification(): void
    {
        config(['netrescue.verification.reconnect_window_seconds' => 0]);
        $this->readings([[$this->disabled(), 0], [$this->disabled(), 0]], [$this->transition(), 0]);
        $incident = app(NetworkHealthService::class)->run('manual')['incident'];
        $this->command('timed_out');
        $action = app(RecoveryService::class)->attempt($incident);
        $this->assertSame(3, data_get($action->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $stored = $action->getRawOriginal();
        $this->readings([[$this->healthy(), 0]]);
        $closing = app(NetworkHealthService::class)->run('manual')['check'];
        $this->assertSame('observed_recovery', $incident->fresh()->resolution_kind);
        $this->assertSame($closing->id, $incident->fresh()->closing_health_check_id);
        $this->assertSame($stored, $action->fresh()->getRawOriginal());
        foreach ([[], ['X-Panel' => '1']] as $headers) {
            $this->get('/incidents/'.$incident->id, $headers)->assertOk()
                ->assertSeeText('The separate closing measurement is shown below.')
                ->assertSeeText('Manual check')
                ->assertSeeText('Reading error: The requested adapter has no stable link state. Windows status: Unknown.');
        }
        $this->assertSame($stored, $action->fresh()->getRawOriginal());
    }
}
