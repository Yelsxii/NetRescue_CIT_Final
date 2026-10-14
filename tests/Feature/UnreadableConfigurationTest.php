<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Services\Network\NetworkHealthService;
use App\Services\Network\NetworkProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnreadableConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_timed_out_adapter_read_does_not_open_a_missing_adapter_incident(): void
    {
        $device = Device::localHost();
        $device->update([
            'last_ip' => '192.168.1.4',
            'adapter_name' => 'Wi-Fi',
            'gateway_ip' => '192.168.1.1',
            'dns_servers' => ['192.168.1.1'],
            'status' => 'healthy',
            'health_score' => 100,
        ]);

        Incident::create([
            'device_id' => $device->id,
            'code' => 'dns_failure',
            'title' => 'DNS Resolution Failure',
            'severity' => 'moderate',
            'status' => 'active',
            'first_detected_at' => now()->subMinute(),
            'last_detected_at' => now()->subMinute(),
            'summary' => 'Name lookup failed.',
        ]);

        $this->app->instance(NetworkProbeService::class, $this->probeReturning([
            'supported' => true,
            'adapter_available' => false,
            'interface_name' => null,
            'local_ip' => null,
            'gateway_ip' => null,
            'dns_servers' => [],
            'error' => 'The process exceeded the timeout of 20 seconds.',
        ]));

        $result = app(NetworkHealthService::class)->run('automatic');

        $this->assertSame('configuration_unreadable', $result['diagnosis']->code);
        $this->assertNull($result['incident']);
        $this->assertSame(1, Incident::query()->where('status', 'active')->count());
        $this->assertSame('dns_failure', Incident::query()->where('status', 'active')->value('code'));

        $device->refresh();
        $this->assertSame('192.168.1.4', $device->last_ip);
        $this->assertSame('Wi-Fi', $device->adapter_name);
        $this->assertSame('healthy', $device->status);
    }

    public function test_windows_reporting_no_adapter_still_opens_an_incident(): void
    {
        Device::localHost();

        $this->app->instance(NetworkProbeService::class, $this->probeReturning([
            'supported' => true,
            'adapter_available' => false,
            'interface_name' => null,
            'local_ip' => null,
            'gateway_ip' => null,
            'dns_servers' => [],
            'error' => null,
        ]));

        $result = app(NetworkHealthService::class)->run('automatic');

        $this->assertSame('adapter_unavailable', $result['diagnosis']->code);
        $this->assertSame('adapter_unavailable', $result['incident']->code);
    }

    public function test_dashboard_keeps_the_last_completed_reading_when_the_newest_check_did_not_finish(): void
    {
        $device = Device::localHost();
        $device->update(['status' => 'healthy', 'health_score' => 100, 'last_seen_at' => now(), 'last_health_check_at' => now()]);

        HealthCheck::create([
            'device_id' => $device->id,
            'checked_at' => now()->subMinute(),
            'source' => 'automatic',
            'platform_supported' => true,
            'interface_name' => 'Wi-Fi',
            'local_ip' => '192.168.1.4',
            'gateway_ip' => '192.168.1.1',
            'dns_servers' => ['192.168.1.1'],
            'gateway_reachable' => true,
            'gateway_latency_ms' => 2.5,
            'internet_reachable' => true,
            'internet_latency_ms' => 18.0,
            'dns_operational' => true,
            'dns_latency_ms' => 40.0,
            'dns_test_domain' => 'example.com',
            'health_score' => 100,
            'health_status' => 'healthy',
            'diagnosis_code' => 'healthy',
            'diagnosis_title' => 'Network Operational',
            'severity' => 'none',
            'diagnosis_summary' => 'The network was measured.',
            'diagnosis_details' => [],
            'probe_meta' => [],
        ]);

        HealthCheck::create([
            'device_id' => $device->id,
            'checked_at' => now(),
            'source' => 'automatic',
            'platform_supported' => true,
            'gateway_reachable' => false,
            'internet_reachable' => false,
            'dns_operational' => false,
            'health_score' => 0,
            'health_status' => 'unread',
            'diagnosis_code' => 'configuration_unreadable',
            'diagnosis_title' => 'Network reading did not finish',
            'severity' => 'low',
            'diagnosis_summary' => 'Windows did not return the adapter in time, so this check did not measure the network.',
            'diagnosis_details' => [],
            'probe_meta' => ['errors' => ['timed out']],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Network reading did not finish');
        $response->assertSee('192.168.1.4');
        $response->assertSee('Wi-Fi');
        $response->assertSee('Not measured');
        $response->assertSee('100%');
        $response->assertDontSee('No Active Network Adapter');
        $response->assertDontSee('Did not answer');
    }

    private function probeReturning(array $configuration): NetworkProbeService
    {
        $probe = $this->createMock(NetworkProbeService::class);
        $probe->method('collectConfiguration')->willReturn($configuration);

        return $probe;
    }
}
