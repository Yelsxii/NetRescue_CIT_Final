<?php

namespace Tests\Unit;

use App\Models\HealthCheck;
use App\Models\Incident;
use App\Services\Network\RecoveryPlan;
use Tests\TestCase;

class RecoveryPlanTest extends TestCase
{
    public function test_static_dns_does_not_escalate_to_renew(): void
    {
        $incident = new Incident([
            'code' => 'dns_failure',
            'recovery_action_code' => 'flush_dns',
            'recommended_action' => 'Clear the cache.',
        ]);
        $after = new HealthCheck([
            'interface_name' => 'Wi-Fi',
            'probe_meta' => ['dns_source' => 'static'],
        ]);

        [$action, $recommendation] = app(RecoveryPlan::class)->nextStep($incident, 'flush_dns', $after);

        $this->assertNull($action);
        $this->assertStringContainsString('manually', $recommendation);
    }

    public function test_dhcp_dns_failure_escalates_to_renew(): void
    {
        $incident = new Incident([
            'code' => 'dns_failure',
            'recovery_action_code' => 'flush_dns',
            'recommended_action' => 'Clear the cache.',
        ]);
        $after = new HealthCheck([
            'interface_name' => 'Wi-Fi',
            'probe_meta' => ['dns_source' => 'dhcp'],
        ]);

        [$action] = app(RecoveryPlan::class)->nextStep($incident, 'flush_dns', $after);

        $this->assertSame('renew_dhcp', $action);
    }

    public function test_a_failed_renew_escalates_to_an_adapter_restart(): void
    {
        $incident = new Incident([
            'code' => 'gateway_unreachable',
            'recovery_action_code' => 'renew_dhcp',
            'recommended_action' => 'Renew the address.',
        ]);
        $after = new HealthCheck([
            'interface_name' => 'Wi-Fi',
            'probe_meta' => [],
        ]);

        [$action] = app(RecoveryPlan::class)->nextStep($incident, 'renew_dhcp', $after);

        $this->assertSame('restart_adapter', $action);
    }

    public function test_a_failed_restart_is_left_for_a_person(): void
    {
        $incident = new Incident([
            'code' => 'gateway_unreachable',
            'recovery_action_code' => 'restart_adapter',
            'recommended_action' => 'Restart the adapter.',
        ]);
        $after = new HealthCheck(['interface_name' => 'Wi-Fi', 'probe_meta' => []]);

        [$action, $recommendation] = app(RecoveryPlan::class)->nextStep($incident, 'restart_adapter', $after);

        $this->assertNull($action);
        $this->assertStringContainsString('person', $recommendation);
    }

    public function test_enabling_the_adapter_is_verified_only_when_it_is_up(): void
    {
        $before = new HealthCheck(['probe_meta' => ['adapter_state' => 'disabled']]);
        $up = new HealthCheck(['probe_meta' => ['adapter_state' => 'up']]);
        $stillOff = new HealthCheck(['probe_meta' => ['adapter_state' => 'disabled']]);

        $plan = app(RecoveryPlan::class);

        $this->assertTrue($plan->cleared('adapter_disabled', $before, $up));
        $this->assertFalse($plan->cleared('adapter_disabled', $before, $stillOff));
    }
}
