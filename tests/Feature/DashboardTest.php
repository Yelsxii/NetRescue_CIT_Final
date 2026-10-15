<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_without_fake_measurements(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Waiting for network measurements');
        $response->assertSee('Run Check');
    }

    public function test_dashboard_displays_real_stored_measurement(): void
    {
        $device = Device::localHost();

        HealthCheck::create([
            'device_id' => $device->id,
            'checked_at' => now(),
            'source' => 'manual',
            'platform_supported' => true,
            'interface_name' => 'Wi-Fi',
            'local_ip' => '192.168.1.10',
            'gateway_ip' => '192.168.1.1',
            'dns_servers' => ['192.168.1.1'],
            'gateway_reachable' => true,
            'gateway_latency_ms' => 3.2,
            'internet_reachable' => true,
            'internet_latency_ms' => 25.1,
            'dns_operational' => true,
            'dns_latency_ms' => 31.7,
            'dns_test_domain' => 'example.com',
            'health_score' => 100,
            'health_status' => 'healthy',
            'diagnosis_code' => 'healthy',
            'diagnosis_title' => 'Network Operational',
            'severity' => 'none',
            'diagnosis_summary' => 'All configured NetRescue checks passed.',
            'diagnosis_details' => [],
            'probe_meta' => [],
        ]);

        $device->update(['status' => 'healthy', 'health_score' => 100, 'last_health_check_at' => now(), 'last_seen_at' => now()]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('100%');
        $response->assertSee('This computer is connected');
        $response->assertSee('Response times');
        $response->assertSee('100 ms warning threshold');
        $response->assertSee('Slow or failed checks');
        $response->assertSee('width: 3.2%', false);
        $response->assertSee('width: 25.1%', false);
        $response->assertSee('0 ms');
        $response->assertSee('100 ms');
    }
}

