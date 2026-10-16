<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Support\CheckStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartialReadingDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function reading(string $code, array $extra = []): HealthCheck
    {
        return HealthCheck::create(array_merge([
            'device_id' => Device::localHost()->id, 'checked_at' => now(), 'source' => 'manual',
            'platform_supported' => true, 'interface_name' => 'Wi-Fi', 'local_ip' => '192.168.1.5',
            'gateway_ip' => '192.168.1.1', 'dns_servers' => ['192.168.1.1'],
            'gateway_reachable' => true, 'gateway_latency_ms' => 12.5,
            'internet_reachable' => true, 'internet_latency_ms' => 31,
            'dns_operational' => false, 'dns_latency_ms' => null, 'dns_test_domain' => 'example.com',
            'health_score' => 0, 'health_status' => 'unread', 'diagnosis_code' => $code,
            'diagnosis_title' => 'Connectivity Test Did Not Finish', 'severity' => 'low',
            'diagnosis_summary' => 'The DNS test did not finish.', 'diagnosis_details' => [],
            'probe_meta' => ['probe_states' => ['gateway' => 'passed', 'internet' => 'passed', 'dns' => 'unavailable']],
        ], $extra));
    }

    public function test_dashboard_uses_the_last_complete_check_for_bars_after_a_partial_dns_reading(): void
    {
        $completed = $this->reading('healthy', [
            'checked_at' => now()->subMinute(), 'gateway_latency_ms' => 2.5, 'internet_latency_ms' => 18,
            'dns_operational' => true, 'dns_latency_ms' => 40, 'health_score' => 100,
            'health_status' => 'healthy', 'diagnosis_title' => 'Network Operational',
            'probe_meta' => ['probe_states' => ['gateway' => 'passed', 'internet' => 'passed', 'dns' => 'passed']],
        ]);
        $partial = $this->reading('probe_unavailable');

        $this->get('/')->assertOk()
            ->assertViewHas('measuredCheck', fn ($check) => $check->id === $completed->id)
            ->assertViewHas('localLatest', fn ($check) => $check->id === $partial->id)
            ->assertViewHas('networkNow', fn ($rows) => $rows[0]['ms'] === 2.5 && $rows[1]['ms'] === 18.0)
            ->assertSeeText('Network reading did not finish')
            ->assertSeeText('These bars are the last completed reading.');
        $this->assertFalse($partial->readingFinished());
    }

    public function test_a_partial_reading_cannot_be_its_own_last_completed_check(): void
    {
        $this->reading('probe_unavailable');
        $this->get('/')->assertOk()->assertViewHas('measuredCheck', null)
            ->assertSeeText('No completed reading is stored yet.')
            ->assertDontSeeText('These bars are the last completed reading.');
    }

    public function test_checks_keep_completed_probe_measurements_when_another_probe_did_not_finish(): void
    {
        $partial = $this->reading('probe_unavailable');
        $this->get('/checks')->assertOk()->assertSeeTextInOrder([
            'PASS', '12.5 ms', 'PASS', '31.0 ms', 'Not measured',
            'Windows test result unavailable', 'Connectivity Test Did Not Finish',
        ]);
        $this->assertSame('Not measured', CheckStory::probeMark($partial, 'dns', 100)['label']);
        $partial->probe_meta = ['probe_states' => ['gateway' => 'unavailable', 'internet' => 'skipped', 'dns' => 'skipped']];
        $this->assertSame('Not tested', CheckStory::probeMark($partial, 'internet', 100)['label']);
        $partial->probe_meta = [];
        $this->assertSame('Not measured', CheckStory::probeMark($partial, 'gateway', 100)['label']);
    }
}
