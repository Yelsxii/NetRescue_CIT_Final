<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitorSetting;
use App\Models\RecoveryAction;
use App\Services\Network\NetworkHealthService;
use App\Services\Network\NetworkProbeService;
use App\Services\Network\RecoveryCommandRunner;
use App\Services\Network\RecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FaultMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MonitorSetting::current()->update(['enabled' => false]);
        config(['netrescue.verification.attempts' => 1, 'netrescue.verification.retry_delay_seconds' => 0]);
    }

    private function frame(string $fault = 'healthy'): array
    {
        $frame = ['configuration' => ['supported' => true, 'adapter_available' => true, 'adapter_state' => 'up',
            'interface_name' => 'Wi-Fi', 'local_ip' => '192.168.1.5', 'gateway_ip' => '192.168.1.1',
            'dns_servers' => ['192.168.1.1'], 'address_source' => 'dhcp', 'dns_source' => 'dhcp', 'error' => null],
            'gateway' => true, 'internet' => true, 'dns' => true, 'latency' => 2];
        switch ($fault) {
            case 'adapter_disabled': case 'adapter_disconnected': case 'adapter_unavailable':
                $frame['configuration'] = array_replace($frame['configuration'], [
                    'adapter_available' => false, 'adapter_state' => match ($fault) {
                        'adapter_disabled' => 'disabled', 'adapter_disconnected' => 'disconnected', default => 'missing'},
                    'local_ip' => null, 'gateway_ip' => null]);
                break;
            case 'dhcp_issue': $frame['configuration']['local_ip'] = '169.254.12.34'; $frame['configuration']['gateway_ip'] = null; break;
            case 'ip_configuration_missing': $frame['configuration']['local_ip'] = null; $frame['configuration']['gateway_ip'] = null; break;
            case 'gateway_missing': $frame['configuration']['gateway_ip'] = null; break;
            case 'gateway_unreachable': $frame['gateway'] = false; $frame['internet'] = false; break;
            case 'gateway_ping_unanswered': $frame['gateway'] = false; break;
            case 'wan_unreachable': $frame['internet'] = false; break;
            case 'dns_failure': $frame['dns'] = false; break;
            case 'high_latency': $frame['latency'] = 150; break;
            case 'static_configuration': $frame['configuration']['address_source'] = 'static'; $frame['configuration']['gateway_ip'] = null; break;
            case 'configuration_unreadable': $frame['configuration']['error'] = 'Fixture timeout'; break;
            case 'unsupported_platform': $frame['configuration']['supported'] = false; break;
        }
        return $frame;
    }

    private function probe(array ...$frames): FaultScenarioProbe
    {
        $probe = new FaultScenarioProbe($frames);
        $this->app->instance(NetworkProbeService::class, $probe);
        return $probe;
    }

    public static function repairCases(): iterable
    {
        $actions = ['adapter_disabled' => 'enable_adapter', 'dhcp_issue' => 'renew_dhcp',
            'ip_configuration_missing' => 'renew_dhcp', 'gateway_missing' => 'renew_dhcp',
            'gateway_unreachable' => 'renew_dhcp', 'dns_failure' => 'flush_dns'];
        foreach ($actions as $fault => $action) {
            foreach (['completed', 'failed', 'timed_out', 'persistent'] as $outcome) {
                yield $fault.' / '.$outcome => [$fault, $action, $outcome];
            }
        }
    }

    #[DataProvider('repairCases')]
    public function test_fault_detection_command_verification_history_and_ui(string $fault, string $action, string $outcome): void
    {
        $probe = $this->probe($this->frame($fault), $this->frame($fault), $this->frame($outcome === 'persistent' ? $fault : 'healthy'));
        $incident = app(NetworkHealthService::class)->run()['incident'];
        $this->assertSame($fault, $incident->code);
        $this->assertSame($action, $incident->recovery_action_code);
        $runner = Mockery::mock(RecoveryCommandRunner::class);
        $runner->shouldReceive('run')->once()->with($action, $fault, 'Wi-Fi')->andReturn([
            'successful' => in_array($outcome, ['completed', 'persistent'], true),
            'exit_code' => $outcome === 'timed_out' ? null : ($outcome === 'failed' ? 5 : 0),
            'timed_out' => $outcome === 'timed_out', 'output' => 'Fixture command output',
            'error' => $outcome === 'failed' ? 'Access denied' : ($outcome === 'timed_out' ? 'Fixture timeout' : ''),
        ]);
        $this->app->instance(RecoveryCommandRunner::class, $runner);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame($outcome === 'completed', $result->success);
        $this->assertSame(match ($outcome) {
            'completed' => 'verified', 'persistent' => 'not_verified', default => 'observed_recovery',
        }, $result->verification_status);
        $this->assertSame($outcome === 'persistent' ? 'active' : 'resolved', $incident->fresh()->status);
        $this->assertSame('Wi-Fi', $result->beforeHealthCheck->interface_name);
        $this->assertSame('Wi-Fi', $result->afterHealthCheck->interface_name);
        $this->assertSame('Wi-Fi', $probe->targets[1]);
        $this->assertSame('Wi-Fi', $probe->targets[2]);
        $this->assertSame(1, RecoveryAction::count());
        $this->assertFalse(MonitorSetting::current()->enabled);
        $this->get('/incidents/'.$incident->id)->assertOk()->assertSeeText($result->executionLabel())
            ->assertSeeText($result->verificationLabel());
        $this->get('/')->assertOk();
        $this->get('/status')->assertJsonPath('latest_check_id', $result->after_health_check_id);
    }

    public static function manualCases(): iterable
    {
        foreach (['adapter_disconnected', 'adapter_unavailable', 'wan_unreachable', 'high_latency',
            'static_configuration', 'configuration_unreadable', 'unsupported_platform', 'gateway_ping_unanswered'] as $fault) {
            yield $fault => [$fault];
        }
    }

    #[DataProvider('manualCases')]
    public function test_manual_and_measurement_conditions_never_offer_a_repair(string $fault): void
    {
        $probe = $this->probe($this->frame($fault));
        $result = app(NetworkHealthService::class)->run();
        $this->assertSame($fault, $result['check']->diagnosis_code);
        $this->assertNull($result['diagnosis']->recoveryActionCode);
        if ($result['incident']) $this->assertFalse($result['incident']->canRepair());
        if (in_array($fault, ['adapter_disconnected', 'adapter_unavailable', 'static_configuration', 'configuration_unreadable', 'unsupported_platform'], true)) {
            $this->assertSame([], $probe->probes);
        }
        $this->assertSame(0, RecoveryAction::count());
    }

    public function test_failed_ping_does_not_prevent_tcp_and_dns_measurements(): void
    {
        $probe = $this->probe($this->frame('gateway_ping_unanswered'));
        $result = app(NetworkHealthService::class)->run();
        $this->assertSame(['gateway', 'internet', 'dns'], $probe->probes);
        $this->assertSame('gateway_ping_unanswered', $result['check']->diagnosis_code);
        $this->assertTrue($result['check']->internet_reachable);
        $this->assertTrue($result['check']->dns_operational);
        $this->assertSame('degraded', $result['check']->health_status);
        $this->assertFalse($result['incident']->canRepair());
    }

    public function test_dns_handoff_stops_repeating_a_completed_but_ineffective_renewal(): void
    {
        $bad = $this->frame('dns_failure');
        $this->probe($bad, $bad, $bad, $bad, $bad, $bad);
        $incident = app(NetworkHealthService::class)->run()['incident'];
        $runner = Mockery::mock(RecoveryCommandRunner::class);
        foreach (['flush_dns', 'renew_dhcp'] as $action) {
            $runner->shouldReceive('run')->once()->with($action, 'dns_failure', 'Wi-Fi')
                ->andReturn(['successful' => true, 'exit_code' => 0, 'output' => '', 'error' => '']);
        }
        $this->app->instance(RecoveryCommandRunner::class, $runner);
        app(RecoveryService::class)->attempt($incident);
        $this->assertSame('renew_dhcp', $incident->fresh()->recovery_action_code);
        app(RecoveryService::class)->attempt($incident->fresh());
        $this->assertNull($incident->fresh()->recovery_action_code);
        $this->assertFalse($incident->fresh()->canRepair());
        $this->assertStringContainsString('DNS', $incident->fresh()->recommended_action);
        app(NetworkHealthService::class)->run();
        $this->assertNull($incident->fresh()->recovery_action_code, 'Monitoring must preserve the manual handoff.');
    }

    public function test_partial_repair_is_explicit_about_the_remaining_fault_in_the_toast_and_history(): void
    {
        $this->probe($this->frame('adapter_disabled'), $this->frame('adapter_disabled'), $this->frame('dns_failure'));
        $incident = app(NetworkHealthService::class)->run()['incident'];
        $runner = Mockery::mock(RecoveryCommandRunner::class);
        $runner->shouldReceive('run')->once()->andReturn(['successful' => true, 'exit_code' => 0, 'output' => '', 'error' => '']);
        $this->app->instance(RecoveryCommandRunner::class, $runner);
        $this->post('/incidents/'.$incident->id.'/recover')->assertSessionHas('toast.type', 'warning')
            ->assertSessionHas('toast.title', 'Target repaired; another condition remains');
        $this->assertSame('dns_failure', Incident::where('status', 'active')->sole()->code);
        $this->assertStringContainsString('DNS Resolution Failure', RecoveryAction::sole()->outcomeSummary());
        $this->get('/incidents/'.$incident->id)->assertSeeText('DNS Resolution Failure');
    }

    public function test_unavailable_gateway_or_dns_measurement_does_not_offer_a_network_repair(): void
    {
        foreach (['gateway', 'dns'] as $unavailable) {
            $frame = $this->frame();
            $frame[$unavailable.'_measured'] = false;
            $this->probe($this->frame('dns_failure'), $frame);
            $original = app(NetworkHealthService::class)->run()['incident'];
            $result = app(NetworkHealthService::class)->run();
            $this->assertSame('probe_unavailable', $result['check']->diagnosis_code);
            $this->assertFalse($result['check']->probeWasRun($unavailable));
            $this->assertSame('Not measured', \App\Support\CheckStory::probeMark($result['check'], $unavailable, 100)['label']);
            $this->assertNull($result['diagnosis']->recoveryActionCode);
            $this->assertNull($result['incident']);
            $this->assertFalse($result['check']->readingFinished());
            $this->assertSame('unread', $result['check']->health_status);
            $this->assertSame('active', $original->fresh()->status, 'An incomplete connectivity test cannot close the existing fault.');
        }
    }

    public function test_gateway_failure_escalates_once_to_restart_then_stops_for_manual_help(): void
    {
        $bad = $this->frame('gateway_unreachable');
        $this->probe($bad, $bad, $bad, $bad, $bad, $bad);
        $incident = app(NetworkHealthService::class)->run()['incident'];
        $runner = Mockery::mock(RecoveryCommandRunner::class);
        foreach (['renew_dhcp', 'restart_adapter'] as $action) {
            $runner->shouldReceive('run')->once()->with($action, 'gateway_unreachable', 'Wi-Fi')
                ->andReturn(['successful' => true, 'exit_code' => 0, 'output' => '', 'error' => '']);
        }
        $this->app->instance(RecoveryCommandRunner::class, $runner);
        app(RecoveryService::class)->attempt($incident);
        $this->assertSame('restart_adapter', $incident->fresh()->recovery_action_code);
        app(RecoveryService::class)->attempt($incident->fresh());
        $this->assertNull($incident->fresh()->recovery_action_code);
        $this->assertStringContainsString('cable', $incident->fresh()->recommended_action);
        app(NetworkHealthService::class)->run();
        $this->assertFalse($incident->fresh()->canRepair());
        $this->assertSame(0, RecoveryAction::where('success', true)->count());
    }

    public function test_static_dns_failure_goes_to_manual_correction_after_one_cache_flush(): void
    {
        $bad = $this->frame('dns_failure');
        $bad['configuration']['dns_source'] = 'static';
        $this->probe($bad, $bad, $bad);
        $incident = app(NetworkHealthService::class)->run()['incident'];
        $runner = Mockery::mock(RecoveryCommandRunner::class);
        $runner->shouldReceive('run')->once()->with('flush_dns', 'dns_failure', 'Wi-Fi')
            ->andReturn(['successful' => true, 'exit_code' => 0, 'output' => '', 'error' => '']);
        $this->app->instance(RecoveryCommandRunner::class, $runner);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertFalse($result->success);
        $this->assertNull($incident->fresh()->recovery_action_code);
        $this->assertStringContainsString('manually', $incident->fresh()->recommended_action);
        $this->get('/incidents/'.$incident->id)->assertSeeText('No repair command is available for this condition.');
    }

    public function test_malformed_apipa_text_is_not_a_real_dhcp_fallback_address(): void
    {
        $frame = $this->frame();
        $frame['configuration']['local_ip'] = '169.254.invalid';
        $probe = $this->probe($frame);
        $result = app(NetworkHealthService::class)->run();
        $this->assertSame('ip_configuration_missing', $result['check']->diagnosis_code);
        $this->assertSame([], $probe->probes);
    }
}

class FaultScenarioProbe extends NetworkProbeService
{
    private array $current;
    public array $targets = [];
    public array $probes = [];

    public function __construct(private array $frames) {}
    public function collectConfiguration(?string $interfaceName = null, ?float $configurationTimeout = null): array
    {
        $this->current = array_shift($this->frames) ?? throw new \RuntimeException('Unexpected extra fault reading.');
        $this->targets[] = $interfaceName;
        return $this->current['configuration'];
    }
    public function probeGateway(?string $gatewayIp): array
    {
        $this->probes[] = 'gateway';
        return ['measured' => $this->current['gateway_measured'] ?? true, 'reachable' => $this->current['gateway'], 'latency_ms' => $this->current['gateway'] ? $this->current['latency'] : null, 'error' => null];
    }
    public function probeInternet(array $targets): array
    {
        $this->probes[] = 'internet';
        return ['reachable' => $this->current['internet'], 'latency_ms' => 20, 'target' => '1.1.1.1:443', 'error' => null];
    }
    public function probeDns(string $domain): array
    {
        $this->probes[] = 'dns';
        return ['measured' => $this->current['dns_measured'] ?? true, 'operational' => $this->current['dns'], 'latency_ms' => 30, 'addresses' => $this->current['dns'] ? ['93.184.215.14'] : [], 'error' => null];
    }
}
