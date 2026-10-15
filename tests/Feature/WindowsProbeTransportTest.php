<?php

namespace Tests\Feature;

use App\Services\Network\NetworkProbeService;
use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use Tests\TestCase;

class WindowsProbeTransportTest extends TestCase
{
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
        $this->assertSame('-EncodedCommand', $command[count($command) - 2]);
        $script = mb_convert_encoding(base64_decode(end($command), true), 'UTF-8', 'UTF-16LE');
        $this->assertStringContainsString("Enable-NetAdapter -Name 'Wi-Fi 2' -Confirm:\$false -ErrorAction Stop", $script);
        $this->assertSame(35.0, $probe->commands[0]['timeout']);
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
        $probe = $this->probe(['successful' => false, 'output' => 'Command started',
            'error' => 'The process exceeded the timeout of 35 seconds.', 'exit_code' => null, 'timed_out' => true]);
        $result = $probe->enableAdapter('Wi-Fi');
        $this->assertFalse($result['successful']);
        $this->assertTrue($result['timed_out']);
        $this->assertNull($result['exit_code']);
        $this->assertSame('Command started', $result['output']);
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
}
