<?php

namespace Tests\Unit;

use App\Services\Network\DiagnosticEngine;
use Tests\TestCase;

class DiagnosticEngineTest extends TestCase
{
    private function baseMetrics(): array
    {
        return [
            'platform_supported' => true,
            'adapter_available' => true,
            'ip_assignment' => 'valid',
            'local_ip' => '192.168.1.10',
            'gateway_ip' => '192.168.1.1',
            'gateway_reachable' => true,
            'gateway_latency_ms' => 10.0,
            'internet_reachable' => true,
            'dns_operational' => true,
        ];
    }

    public function test_it_identifies_dns_failure_only_after_internet_passes(): void
    {
        $metrics = $this->baseMetrics();
        $metrics['dns_operational'] = false;

        $diagnosis = app(DiagnosticEngine::class)->diagnose($metrics, 100);

        $this->assertSame('dns_failure', $diagnosis->code);
        $this->assertSame('flush_dns', $diagnosis->recoveryActionCode);
    }

    public function test_it_prioritizes_gateway_failure_over_secondary_external_checks(): void
    {
        $metrics = $this->baseMetrics();
        $metrics['gateway_reachable'] = false;
        $metrics['internet_reachable'] = false;
        $metrics['dns_operational'] = false;

        $diagnosis = app(DiagnosticEngine::class)->diagnose($metrics, 100);

        $this->assertSame('gateway_unreachable', $diagnosis->code);
        $this->assertSame('renew_dhcp', $diagnosis->recoveryActionCode);
    }

    public function test_an_unfinished_windows_read_is_not_a_missing_adapter(): void
    {
        $metrics = $this->baseMetrics();
        $metrics['adapter_available'] = false;
        $metrics['configuration_unreadable'] = true;
        $metrics['local_ip'] = null;
        $metrics['gateway_ip'] = null;

        $diagnosis = app(DiagnosticEngine::class)->diagnose($metrics, 100);

        $this->assertSame('configuration_unreadable', $diagnosis->code);
        $this->assertNull($diagnosis->recoveryActionCode);
    }

    public function test_a_completed_read_with_no_adapter_stays_a_missing_adapter(): void
    {
        $metrics = $this->baseMetrics();
        $metrics['adapter_available'] = false;
        $metrics['configuration_unreadable'] = false;

        $diagnosis = app(DiagnosticEngine::class)->diagnose($metrics, 100);

        $this->assertSame('adapter_unavailable', $diagnosis->code);
    }

    public function test_a_disabled_adapter_can_be_enabled(): void
    {
        $metrics = $this->baseMetrics();
        $metrics['adapter_available'] = false;
        $metrics['adapter_state'] = 'disabled';
        $metrics['interface_name'] = 'Wi-Fi';
        $metrics['local_ip'] = null;
        $metrics['gateway_ip'] = null;

        $diagnosis = app(DiagnosticEngine::class)->diagnose($metrics, 100);

        $this->assertSame('adapter_disabled', $diagnosis->code);
        $this->assertSame('enable_adapter', $diagnosis->recoveryActionCode);
    }

    public function test_a_disconnected_adapter_is_left_for_a_person(): void
    {
        $metrics = $this->baseMetrics();
        $metrics['adapter_available'] = false;
        $metrics['adapter_state'] = 'disconnected';
        $metrics['interface_name'] = 'Ethernet';
        $metrics['local_ip'] = null;
        $metrics['gateway_ip'] = null;

        $diagnosis = app(DiagnosticEngine::class)->diagnose($metrics, 100);

        $this->assertSame('adapter_disconnected', $diagnosis->code);
        $this->assertNull($diagnosis->recoveryActionCode);
    }

    public function test_a_manual_address_is_not_offered_a_renew(): void
    {
        $metrics = $this->baseMetrics();
        $metrics['address_source'] = 'static';
        $metrics['gateway_reachable'] = false;
        $metrics['internet_reachable'] = false;

        $diagnosis = app(DiagnosticEngine::class)->diagnose($metrics, 100);

        $this->assertSame('static_configuration', $diagnosis->code);
        $this->assertNull($diagnosis->recoveryActionCode);
    }

    public function test_a_dhcp_gateway_failure_still_offers_renew(): void
    {
        $metrics = $this->baseMetrics();
        $metrics['address_source'] = 'dhcp';
        $metrics['gateway_reachable'] = false;
        $metrics['internet_reachable'] = false;

        $diagnosis = app(DiagnosticEngine::class)->diagnose($metrics, 100);

        $this->assertSame('gateway_unreachable', $diagnosis->code);
        $this->assertSame('renew_dhcp', $diagnosis->recoveryActionCode);
    }

    public function test_it_identifies_apipa_as_possible_dhcp_issue(): void
    {
        $metrics = $this->baseMetrics();
        $metrics['local_ip'] = '169.254.10.20';
        $metrics['ip_assignment'] = 'apipa';

        $diagnosis = app(DiagnosticEngine::class)->diagnose($metrics, 100);

        $this->assertSame('dhcp_issue', $diagnosis->code);
        $this->assertSame('renew_dhcp', $diagnosis->recoveryActionCode);
    }
}
