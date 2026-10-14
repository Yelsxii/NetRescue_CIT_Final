<?php

namespace Tests\Feature;

use App\Models\EnrollmentCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_enroll_and_submit_real_style_health_payload(): void
    {
        $enrollment = EnrollmentCode::issue();

        $register = $this->postJson('/api/agent/register', [
            'enrollment_code' => $enrollment->code,
            'device_uuid' => '11111111-2222-4333-8444-555555555555',
            'hostname' => 'LAB-PC-07',
            'display_name' => 'LAB-PC-07',
            'operating_system' => 'Microsoft Windows 11 Pro',
            'os_version' => '10.0.26100',
            'agent_version' => '2.1.0',
        ]);

        $register->assertCreated()->assertJsonStructure(['token', 'device_id']);
        $token = $register->json('token');

        $health = $this->withToken($token)->postJson('/api/agent/health', [
            'platform_supported' => true,
            'adapter_available' => true,
            'interface_name' => 'Ethernet',
            'local_ip' => '192.168.1.21',
            'gateway_ip' => '192.168.1.1',
            'dns_servers' => ['192.168.1.1'],
            'gateway_reachable' => true,
            'gateway_latency_ms' => 4.2,
            'internet_reachable' => true,
            'internet_latency_ms' => 21.7,
            'internet_target' => '1.1.1.1:443',
            'dns_operational' => true,
            'dns_latency_ms' => 30.5,
            'dns_addresses' => ['93.184.216.34'],
            'probe_errors' => [],
        ]);

        $health->assertOk()
            ->assertJsonPath('health_score', 100)
            ->assertJsonPath('diagnosis_code', 'healthy');

        $this->assertDatabaseHas('devices', [
            'hostname' => 'LAB-PC-07',
            'last_ip' => '192.168.1.21',
            'status' => 'healthy',
        ]);
    }
}
