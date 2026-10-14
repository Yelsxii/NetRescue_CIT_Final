<?php

namespace Tests\Feature;

use App\Services\Agent\LanPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanPairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_router_keeps_the_key_and_a_new_router_changes_it(): void
    {
        $lan = app(LanPresenceService::class);

        $first = $lan->reconcile(null, '192.168.1.7', '192.168.1.1');
        $again = $lan->reconcile($first, '192.168.1.22', '192.168.1.1');
        $moved = $lan->reconcile($again, '10.0.0.8', '10.0.0.1');

        $this->assertSame($first['pairing_key'], $again['pairing_key']);
        $this->assertSame('192.168.1', $again['prefix']);
        $this->assertNotSame($again['pairing_key'], $moved['pairing_key']);
        $this->assertSame('10.0.0.1', $moved['gateway_ip']);
    }

    public function test_discovery_announces_this_laptop_without_revealing_the_key(): void
    {
        $this->getJson('/api/agent/discover')
            ->assertOk()
            ->assertJsonPath('service', 'netrescue')
            ->assertJsonPath('gateway', '203.0.113.1')
            ->assertJsonMissingPath('pairing_key');
    }

    public function test_public_pairing_route_is_not_exposed_in_the_revised_interface(): void
    {
        $this->post('/pair', ['pairing_key' => 't3st-k3y1'])->assertNotFound();
    }

    public function test_the_agent_can_enroll_with_the_wifi_key_only_from_the_same_router(): void
    {
        $payload = [
            'enrollment_code' => 'T3ST-K3Y1',
            'device_uuid' => '11111111-2222-4333-8444-555555555555',
            'hostname' => 'LIZHARY-CLIENT',
            'display_name' => 'LIZHARY-CLIENT',
            'operating_system' => 'Microsoft Windows 11',
            'os_version' => '10.0.26200',
            'agent_version' => '2.3.0',
            'gateway_ip' => '203.0.113.1',
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
            ->postJson('/api/agent/register', $payload)
            ->assertCreated()
            ->assertJsonStructure(['token']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.8'])
            ->postJson('/api/agent/register', array_merge($payload, [
                'device_uuid' => '11111111-2222-4333-8444-666666666666',
            ]))
            ->assertStatus(422);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
            ->postJson('/api/agent/register', array_merge($payload, [
                'device_uuid' => '11111111-2222-4333-8444-777777777777',
                'gateway_ip' => '10.0.0.1',
            ]))
            ->assertStatus(422);
    }
}
