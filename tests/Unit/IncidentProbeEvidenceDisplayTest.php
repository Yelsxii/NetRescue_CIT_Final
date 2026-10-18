<?php

namespace Tests\Unit;

use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\RecoveryAction;
use App\Services\Network\DiagnosticEngine;
use App\Support\CheckStory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IncidentProbeEvidenceDisplayTest extends TestCase
{
    private function activeIncident(string $code, HealthCheck $check): Incident
    {
        $incident = new Incident(['code' => $code, 'status' => 'active']);
        $incident->setRelation('latestHealthCheck', $check);
        $incident->setRelation('closingHealthCheck', null);
        $incident->setRelation('recoveryActions', (new RecoveryAction)->newCollection());

        return $incident;
    }

    public function test_dns_outcome_does_not_claim_the_unanswered_gateway_responded(): void
    {
        $metrics = [
            'platform_supported' => true, 'adapter_available' => true, 'adapter_state' => 'up',
            'ip_assignment' => 'valid', 'local_ip' => '192.168.1.5', 'gateway_ip' => '192.168.1.1',
            'address_source' => 'dhcp', 'gateway_reachable' => false,
            'internet_reachable' => true, 'dns_operational' => false,
            'probe_states' => ['gateway' => 'failed', 'internet' => 'passed', 'dns' => 'failed'],
        ];
        $diagnosis = (new DiagnosticEngine)->diagnose($metrics, 100);
        $this->assertSame('dns_failure', $diagnosis->code);
        $check = new HealthCheck($metrics + [
            'diagnosis_code' => $diagnosis->code, 'probe_meta' => ['probe_states' => $metrics['probe_states']],
        ]);

        $outcome = $this->activeIncident($diagnosis->code, $check)->story()['resolution'];

        $this->assertStringContainsString('External TCP connectivity passed', $outcome);
        $this->assertStringContainsString('name lookup failed', $outcome);
        $this->assertStringNotContainsString('router and the Internet answered', $outcome);
    }

    public static function historicalGatewayMetadata(): iterable
    {
        yield 'explicit skipped TCP probe' => [['probe_states' => [
            'gateway' => 'failed', 'internet' => 'skipped', 'dns' => 'skipped',
        ]]];
        yield 'legacy reading without per-probe state' => [[]];
    }

    #[DataProvider('historicalGatewayMetadata')]
    public function test_historical_gateway_outcome_does_not_invent_a_failed_tcp_test(array $meta): void
    {
        $check = new HealthCheck([
            'diagnosis_code' => 'gateway_unreachable', 'platform_supported' => true,
            'local_ip' => '192.168.1.5', 'gateway_ip' => '192.168.1.1',
            'gateway_reachable' => false, 'internet_reachable' => false, 'probe_meta' => $meta,
        ]);
        $this->assertFalse($check->probeWasRun('internet'));
        $this->assertSame('Not tested', CheckStory::probeMark($check, 'internet', 100)['label']);

        $outcome = $this->activeIncident('gateway_unreachable', $check)->story()['resolution'];

        $this->assertStringContainsString('Gateway ping did not answer', $outcome);
        $this->assertStringNotContainsString('external TCP tests failed', $outcome);
    }

    public static function recoveredFocusCases(): iterable
    {
        yield 'enabled adapter' => ['adapter_disabled', 'Wi-Fi was enabled.'];
        yield 'restored network link' => ['adapter_disconnected', 'A network link was available.'];
        yield 'restored IPv4' => ['ip_configuration_missing', 'IPv4 address 192.168.1.5.'];
        yield 'restored gateway' => ['gateway_missing', 'default IPv4 gateway 192.168.1.1.'];
    }

    #[DataProvider('recoveredFocusCases')]
    public function test_formal_closing_finding_describes_the_recovered_reading(string $code, string $expected): void
    {
        $check = new HealthCheck(['diagnosis_code' => 'healthy', 'interface_name' => 'Wi-Fi',
            'local_ip' => '192.168.1.5', 'gateway_ip' => '192.168.1.1',
            'probe_meta' => ['adapter_state' => 'up']]);
        $this->assertStringContainsString($expected, CheckStory::focus($code, $check, 100));
    }

    public function test_dns_finding_distinguishes_a_failed_lookup_from_a_skipped_lookup(): void
    {
        $check = new HealthCheck(['diagnosis_code' => 'dns_failure', 'platform_supported' => true,
            'dns_operational' => false, 'dns_test_domain' => 'example.com',
            'probe_meta' => ['probe_states' => ['dns' => 'failed']]]);
        $this->assertSame('Windows could not resolve example.com.', CheckStory::focus('dns_failure', $check, 100));
        $check->probe_meta = ['probe_states' => ['dns' => 'skipped']];
        $this->assertSame('Name resolution was not tested in this reading.', CheckStory::focus('dns_failure', $check, 100));
    }

    public function test_recovery_without_a_repair_does_not_imply_a_repair_was_recorded(): void
    {
        $check = new HealthCheck(['device_id' => 1, 'diagnosis_code' => 'healthy']);
        $incident = $this->activeIncident('wan_unreachable', $check);
        $incident->fill(['device_id' => 1, 'status' => 'resolved']);
        $incident->setRelation('closingHealthCheck', $check);
        $this->assertSame('A subsequent check confirmed recovery. No repair command was run.', $incident->resolutionLabel());
    }
}
