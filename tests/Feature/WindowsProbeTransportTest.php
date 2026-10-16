<?php

namespace Tests\Feature;

use App\Services\Network\NetworkProbeService;
use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use Tests\TestCase;

class WindowsProbeTransportTest extends TestCase
{
    public function test_incomplete_json_is_unreadable_instead_of_a_fabricated_missing_adapter(): void
    {
        foreach (['{}', '[]', '{"adapterAvailable":true}', '{"adapterAvailable":false,"adapterState":"garbage"}'] as $json) {
            $configuration = $this->probe(['successful' => true, 'output' => $json, 'error' => '', 'exit_code' => 0])->collectConfiguration();
            $this->assertNotEmpty($configuration['error'], $json);
            $this->assertNull($configuration['adapter_state']);
        }
    }

    public function test_missing_or_contradictory_probe_results_are_unavailable_not_network_failures(): void
    {
        foreach (['{}', 'not-json', '{"operational":true,"addresses":[]}'] as $json) {
            $probe = $this->probe(['successful' => true, 'output' => $json, 'error' => '', 'exit_code' => 0]);
            $gateway = $probe->probeGateway('192.168.1.1');
            $dns = $probe->probeDns('example.com');
            $this->assertFalse($gateway['measured']);
            $this->assertFalse($dns['measured']);
            $this->assertFalse($dns['operational']);
            $this->assertNotEmpty($gateway['error']);
            $this->assertNotEmpty($dns['error']);
        }
    }

    private function probe(array $reply): NetworkProbeService
    {
        return new class($reply) extends NetworkProbeService {
            public array $commands = [];
            public function __construct(private array $reply) {}
            public function isSupportedPlatform(): bool { return true; }
            protected function runProcess(array $command, float $timeout): array
            {
                $this->commands[] = compact('command', 'timeout');
                return $this->reply;
            }
        };
    }

    public function test_enable_uses_the_confirmed_powershell_command_and_displays_the_same_action(): void
    {
        $probe = $this->probe(['successful' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);
        $probe->enableAdapter('Wi-Fi 2');
        $command = $probe->commands[0]['command'];
        $this->assertStringEndsWith('\\System32\\WindowsPowerShell\\v1.0\\powershell.exe', $command[0]);
        $this->assertSame(['-File', resource_path('powershell/change-adapter.ps1'), '-Action', 'Enable', '-InterfaceName', 'Wi-Fi 2'], array_slice($command, -6));
        $script = file_get_contents(resource_path('powershell/change-adapter.ps1'));
        $this->assertStringContainsString('Enable-NetAdapter -Name $InterfaceName -Confirm:$false -ErrorAction Stop', $script);
        $this->assertStringContainsString("Write-Stage 'module_import_finished'", $script);
        $this->assertStringContainsString("Write-Stage 'command_returned'", $script);
        $this->assertSame(1, substr_count($script, 'Enable-NetAdapter -Name'));
        $this->assertSame(60.0, $probe->commands[0]['timeout']);
        $incident = new Incident(['recovery_action_code' => 'enable_adapter']);
        $incident->setRelation('device', new Device(['is_local' => true]));
        $incident->setRelation('latestHealthCheck', new HealthCheck(['interface_name' => 'Wi-Fi 2']));
        $this->assertSame('Enable-NetAdapter -Name "Wi-Fi 2" -Confirm:$false -ErrorAction Stop', $incident->repairCommand());
        $this->expectException(\RuntimeException::class);
        $probe->enableAdapter('Wi-Fi" & other-command');
    }

    public function test_configuration_uses_a_local_script_file_and_preserves_both_dns_families(): void
    {
        $payload = ['adapterAvailable' => true, 'adapterState' => 'up', 'interfaceName' => 'Wi-Fi',
            'localIp' => '192.168.1.5', 'gatewayIp' => '192.168.1.1',
            'dnsServers' => ['192.168.1.1', '2001:db8::53'], 'addressSource' => 'dhcp', 'dnsSource' => 'static', 'reader' => 'target_snapshot'];
        $probe = $this->probe(['successful' => true, 'output' => json_encode($payload), 'error' => '', 'exit_code' => 0]);
        $configuration = $probe->collectConfiguration();
        $this->assertSame($payload['dnsServers'], $configuration['dns_servers']);
        $this->assertSame('static', $configuration['dns_source']);
        $this->assertSame('target_snapshot', $configuration['reader']);
        $this->assertNull($configuration['error']);
        $command = $probe->commands[0]['command'];
        $this->assertSame('-File', $command[count($command) - 2]);
        $this->assertSame(resource_path('powershell/read-configuration.ps1'), end($command));
    }

    public function test_enable_timeout_is_retained_as_unconfirmed_with_partial_output(): void
    {
        $probe = $this->probe(['successful' => false, 'output' => 'NetRescue command stage: command_returned; elapsed_ms=25000',
            'error' => 'The process exceeded the timeout of 35 seconds.', 'exit_code' => null, 'timed_out' => true]);
        $result = $probe->enableAdapter('Wi-Fi');
        $this->assertFalse($result['successful']);
        $this->assertTrue($result['timed_out']);
        $this->assertNull($result['exit_code']);
        $this->assertSame('NetRescue command stage: command_returned; elapsed_ms=25000', $result['output']);
        $this->assertStringContainsString('35 seconds', $result['error']);
    }

    public function test_small_probe_scripts_use_encoded_transport_without_progress_output(): void
    {
        $probe = $this->probe(['successful' => true, 'output' => '{"reachable":true,"latencyMs":2}', 'error' => '', 'exit_code' => 0]);
        $this->assertTrue($probe->probeGateway('192.168.1.1')['reachable']);
        $command = $probe->commands[0]['command'];
        $this->assertSame('-EncodedCommand', $command[count($command) - 2]);
        $script = mb_convert_encoding(base64_decode(end($command), true), 'UTF-8', 'UTF-16LE');
        $this->assertStringContainsString("\$ProgressPreference = 'SilentlyContinue'", $script);
    }

    public function test_a_reader_error_is_unreadable_instead_of_becoming_a_missing_adapter(): void
    {
        $probe = $this->probe(['successful' => true, 'output' => '{"error":"Snapshot failed"}', 'error' => '', 'exit_code' => 0]);
        $configuration = $probe->collectConfiguration();
        $this->assertSame('Snapshot failed', $configuration['error']);
        $this->assertNull($configuration['adapter_state']);
        $this->assertTrue($configuration['supported']);
    }

    public function test_an_explicit_target_is_passed_as_an_argument_and_an_unrelated_reply_is_rejected(): void
    {
        $probe = $this->probe(['successful' => true,
            'output' => '{"interfaceName":"Ethernet","adapterAvailable":true,"adapterState":"up"}',
            'error' => '', 'exit_code' => 0]);
        $result = $probe->collectConfiguration('Wi-Fi');
        $this->assertSame(['-InterfaceName', 'Wi-Fi'], array_slice($probe->commands[0]['command'], -2));
        $this->assertStringContainsString('different adapter cannot verify', $result['error']);
        $this->assertNull($result['adapter_state']);
        $this->assertFalse($result['adapter_available']);
    }

    public function test_a_short_configuration_budget_does_not_change_later_default_budgets(): void
    {
        $probe = $this->probe(['successful' => true, 'output' => '{}', 'error' => '', 'exit_code' => 0]);
        $probe->collectConfiguration('Wi-Fi', 5.0);
        $probe->collectConfiguration('Wi-Fi');
        $probe->collectConfiguration();
        $probe->collectConfiguration('Wi-Fi', 100.0);
        $this->assertSame([5.0, 20.0, 20.0, 20.0], array_column($probe->commands, 'timeout'));
        $this->assertSame(20, config('netrescue.timeouts.powershell_seconds'));
    }

    public function test_invalid_configuration_budget_is_rejected_before_starting_a_process(): void
    {
        foreach ([0.0, -1.0, INF, NAN] as $invalid) {
            $probe = $this->probe(['successful' => true, 'output' => '{}', 'error' => '', 'exit_code' => 0]);
            try {
                $probe->collectConfiguration('Wi-Fi', $invalid);
                $this->fail('An invalid budget was accepted.');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame([], $probe->commands);
            }
        }
    }

    public function test_transition_and_process_timeout_have_distinct_machine_readable_reasons(): void
    {
        $probe = $this->probe(['successful' => true, 'output' => json_encode([
            'interfaceName' => 'Wi-Fi', 'error' => 'Windows has no stable link state.',
            'errorCode' => 'adapter_transitioning', 'windowsAdapterStatus' => 'Unknown',
        ]), 'error' => '', 'exit_code' => 0]);
        $reading = $probe->collectConfiguration('Wi-Fi');
        $this->assertSame('adapter_transitioning', $reading['error_code']);
        $this->assertSame('Unknown', $reading['windows_adapter_status']);
        $this->assertNull($reading['adapter_state']);
        $probe = $this->probe(['successful' => false, 'output' => '', 'error' => 'Timed out', 'timed_out' => true]);
        $this->assertSame('process_timed_out', $probe->collectConfiguration('Wi-Fi')['error_code']);
    }

    public function test_restart_uses_the_same_bounded_adapter_path(): void
    {
        $probe = $this->probe(['successful' => false, 'output' => 'command_start', 'error' => 'Access denied', 'exit_code' => 1]);
        $result = $probe->restartAdapter('Wi-Fi');
        $this->assertFalse($result['successful']);
        $this->assertSame(['-Action', 'Restart', '-InterfaceName', 'Wi-Fi'], array_slice($probe->commands[0]['command'], -4));
        $this->assertSame(60.0, $probe->commands[0]['timeout']);
        $this->assertSame(1, $result['exit_code']);
    }
}
