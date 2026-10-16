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
}
