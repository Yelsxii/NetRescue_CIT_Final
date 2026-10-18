<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\RecoveryAction;
use App\Services\Network\IncidentManager;
use App\Services\Network\NetworkHealthService;
use App\Services\Network\RecoveryCommandRunner;
use App\Services\Network\RecoveryService;
use App\Support\CheckStory;
use App\Support\NetworkDiagnosis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecoveryEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These tests exercise one-reading evidence semantics. Retry behaviour is
        // tested separately with real before/unread/healthy reading sequences.
        config(['netrescue.verification.attempts' => 1, 'netrescue.verification.retry_delay_seconds' => 0]);
    }

    private function check(string $code, array $extra = []): HealthCheck
    {
        $healthy = $code === 'healthy';
        return HealthCheck::create(array_merge([
            'device_id' => Device::localHost()->id, 'checked_at' => now(), 'source' => 'manual',
            'platform_supported' => true, 'interface_name' => 'Wi-Fi',
            'local_ip' => $healthy ? '192.168.1.5' : '169.254.100.197',
            'gateway_ip' => $healthy ? '192.168.1.1' : null, 'dns_servers' => [],
            'gateway_reachable' => $healthy, 'internet_reachable' => $healthy, 'dns_operational' => $healthy,
            'gateway_latency_ms' => $healthy ? 2 : null, 'health_score' => $healthy ? 100 : 0,
            'health_status' => $healthy ? 'healthy' : 'critical', 'diagnosis_code' => $code,
            'diagnosis_title' => $healthy ? 'Network Operational' : 'Possible DHCP Configuration Issue',
            'severity' => $healthy ? 'none' : 'high', 'probe_meta' => ['adapter_state' => 'up'],
        ], $extra));
    }

    private function incident(HealthCheck $check): Incident
    {
        return Incident::create([
            'device_id' => $check->device_id, 'code' => 'dhcp_issue', 'title' => 'Possible DHCP Configuration Issue',
            'severity' => 'high', 'status' => 'active', 'first_detected_at' => $check->checked_at,
            'last_detected_at' => $check->checked_at, 'latest_health_check_id' => $check->id,
            'opening_health_check_id' => $check->id, 'recovery_action_code' => 'renew_dhcp',
        ]);
    }

    private function measurements(HealthCheck ...$checks): void
    {
        $mock = Mockery::mock(NetworkHealthService::class);
        foreach ($checks as $index => $check) {
            $diagnosis = new NetworkDiagnosis($check->diagnosis_code, $check->diagnosis_title, $check->severity, 'Test reading.');
            $mock->shouldReceive('run')->once()->with($index === 0 ? 'recovery_before' : 'recovery_after', false, 'Wi-Fi')
                ->andReturn(['check' => $check, 'diagnosis' => $diagnosis]);
        }
        $this->app->instance(NetworkHealthService::class, $mock);
    }

    private function command(?bool $success): void
    {
        $mock = Mockery::mock(RecoveryCommandRunner::class);
        if ($success === null) $mock->shouldNotReceive('run');
        else $mock->shouldReceive('run')->once()->with('renew_dhcp', 'dhcp_issue', 'Wi-Fi')
            ->andReturn(['successful' => $success, 'output' => 'Wi-Fi command output',
                'error' => $success ? '' : 'Access denied', 'exit_code' => $success ? 0 : 1]);
        $this->app->instance(RecoveryCommandRunner::class, $mock);
    }

    public function test_healthy_precheck_skips_commands_and_uses_that_exact_resolution_check(): void
    {
        $incident = $this->incident($this->check('dhcp_issue'));
        $before = $this->check('healthy', ['source' => 'recovery_before']);
        $this->measurements($before); $this->command(null);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame('skipped', $result->execution_status);
        $this->assertSame('already_recovered', $result->verification_status);
        $this->assertNull($result->success);
        $this->assertNull($result->after_health_check_id);
        $this->assertSame($before->id, $incident->fresh()->closing_health_check_id);
        $this->assertSame('already_recovered', $incident->fresh()->resolution_kind);
    }

    public function test_real_before_after_recovery_is_verified(): void
    {
        $before = $this->check('dhcp_issue'); $incident = $this->incident($before);
        $after = $this->check('healthy', ['source' => 'recovery_after']);
        $this->measurements($before, $after); $this->command(true);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertTrue($result->success);
        $this->assertSame('completed', $result->execution_status);
        $this->assertSame(0, $result->command_exit_code);
        $this->assertSame('verified_repair', $incident->fresh()->resolution_kind);
        $this->assertSame($after->id, $incident->fresh()->closing_health_check_id);
    }

    public function test_command_failure_is_separate_from_the_network_remaining_faulty(): void
    {
        $before = $this->check('dhcp_issue'); $incident = $this->incident($before);
        $this->measurements($before, $this->check('dhcp_issue')); $this->command(false);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->execution_status);
        $this->assertSame('Access denied', $result->error_message);
        $this->assertSame('active', $incident->fresh()->status);
        $this->assertSame('renew_dhcp', $incident->fresh()->recovery_action_code);
    }

    public function test_recovery_observed_after_a_command_error_is_not_claimed_as_a_verified_repair(): void
    {
        $before = $this->check('dhcp_issue'); $incident = $this->incident($before);
        $this->measurements($before, $this->check('healthy')); $this->command(false);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertFalse($result->success);
        $this->assertSame('observed_recovery', $incident->fresh()->resolution_kind);
        $this->assertStringContainsString('not verified as the cause', $incident->fresh()->resolutionLabel());
    }

    public function test_an_unreadable_precheck_runs_no_command_and_keeps_the_incident_open(): void
    {
        $incident = $this->incident($this->check('dhcp_issue'));
        $this->measurements($this->check('configuration_unreadable')); $this->command(null);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame('unread', $result->verification_status);
        $this->assertSame('active', $incident->fresh()->status);
    }

    public function test_a_healthy_different_adapter_does_not_clear_or_repair_the_original_adapter(): void
    {
        $incident = $this->incident($this->check('dhcp_issue'));
        $this->measurements($this->check('healthy', ['interface_name' => 'Ethernet'])); $this->command(null);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame('adapter_changed', $result->verification_status);
        $this->assertSame('active', $incident->fresh()->status);
    }

    public function test_changed_condition_is_closed_as_superseded_without_a_command(): void
    {
        $incident = $this->incident($this->check('dhcp_issue'));
        $this->measurements($this->check('gateway_missing', ['local_ip' => '192.168.1.5'])); $this->command(null);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame('skipped', $result->execution_status);
        $this->assertSame('superseded', $incident->fresh()->resolution_kind);
        $this->assertStringContainsString('Recovery from this incident was not confirmed.', $incident->fresh()->resolutionLabel());
    }

    public function test_existing_healthy_before_and_after_attempt_uses_consistent_honest_text(): void
    {
        $incident = $this->incident($this->check('dhcp_issue', ['checked_at' => now()->subMinute()]));
        $before = $this->check('healthy'); $after = $this->check('healthy', ['source' => 'recovery_after']);
        RecoveryAction::create(['device_id' => $incident->device_id, 'incident_id' => $incident->id,
            'action_code' => 'renew_dhcp', 'action_label' => 'Release and renew the address',
            'before_health_check_id' => $before->id, 'after_health_check_id' => $after->id,
            'started_at' => now(), 'completed_at' => now(), 'success' => false]);
        $incident->update(['status' => 'resolved', 'resolved_at' => $after->checked_at,
            'latest_health_check_id' => $after->id]);
        $story = $incident->fresh()->story();
        foreach ([$this->get('/history'), $this->withHeaders(['X-Panel' => '1'])->get('/incidents/'.$incident->id)] as $response) {
            $response->assertOk()->assertSeeText($story['resolution'])->assertSeeText($story['repair']);
            $response->assertDontSeeText('did not clear this condition');
        }
    }

    public function test_resolution_uses_the_exact_closing_id_not_a_nearby_check(): void
    {
        $incident = $this->incident($this->check('dhcp_issue', ['checked_at' => now()->subMinute()]));
        $nearby = $this->check('healthy', ['checked_at' => now()->subSeconds(10), 'local_ip' => '10.9.8.7']);
        $closing = $this->check('healthy');
        app(IncidentManager::class)->resolve($incident, $closing);
        $this->get('/incidents/'.$incident->id)->assertOk()->assertSeeText('192.168.1.5')->assertDontSeeText($nearby->local_ip);
    }

    public function test_apipa_probes_are_not_displayed_as_failed_tests(): void
    {
        $check = $this->check('dhcp_issue');
        foreach (['gateway', 'internet', 'dns'] as $probe) {
            $this->assertSame('Not tested', CheckStory::probeMark($check, $probe, 100)['label']);
        }
        $this->get('/')->assertOk()->assertSeeText('Not tested')->assertDontSeeText('FAIL Did not answer');
    }

    public function test_gateway_failure_skips_downstream_tests_but_preserves_the_gateway_failure(): void
    {
        $check = $this->check('gateway_unreachable', ['local_ip' => '192.168.1.5', 'gateway_ip' => '192.168.1.1']);
        $this->assertSame('FAIL', CheckStory::probeMark($check, 'gateway', 100)['label']);
        $this->assertSame('Not tested', CheckStory::probeMark($check, 'internet', 100)['label']);
        $this->assertSame('Not tested', CheckStory::probeMark($check, 'dns', 100)['label']);
    }

    public function test_repeated_checks_do_not_reset_the_next_repair_action(): void
    {
        $check = $this->check('dhcp_issue'); $incident = $this->incident($check);
        RecoveryAction::create(['incident_id' => $incident->id, 'action_code' => 'renew_dhcp',
            'action_label' => 'Renew', 'started_at' => now(), 'completed_at' => now(), 'success' => false]);
        $incident->update(['recovery_action_code' => 'restart_adapter', 'recommended_action' => 'Restart next.']);
        app(IncidentManager::class)->synchronize($check, new NetworkDiagnosis('dhcp_issue', 'DHCP', 'high', 'Fault', [], 'Renew.', 'renew_dhcp'));
        $this->assertSame('restart_adapter', $incident->fresh()->recovery_action_code);
        $this->assertSame('Restart next.', $incident->fresh()->recommended_action);
    }

    public function test_command_result_is_retained_when_follow_up_verification_errors(): void
    {
        $before = $this->check('dhcp_issue'); $incident = $this->incident($before);
        $health = Mockery::mock(NetworkHealthService::class);
        $health->shouldReceive('run')->once()->with('recovery_before', false, 'Wi-Fi')->andReturn(['check' => $before]);
        $health->shouldReceive('run')->once()->with('recovery_after', false, 'Wi-Fi')->andReturnUsing(function () use ($incident) {
            $saved = RecoveryAction::where('incident_id', $incident->id)->firstOrFail();
            $this->assertSame('completed', $saved->execution_status);
            $this->assertSame(0, $saved->command_exit_code);
            $this->assertNull($saved->success);
            $this->assertNull($saved->completed_at);
            $this->assertStringContainsString('follow-up verification has not finished', $saved->outcomeSummary());
            throw new \RuntimeException('Simulated follow-up interruption');
        });
        $this->app->instance(NetworkHealthService::class, $health); $this->command(true);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame('completed', $result->execution_status);
        $this->assertSame('unread', $result->verification_status);
        $this->assertSame(0, $result->command_exit_code);
        $this->assertNull($result->after_health_check_id);
        $this->assertFalse($result->success);
        $this->assertNotNull($result->completed_at);
        $this->assertStringContainsString('Simulated follow-up interruption', $result->error_message);
        $this->assertSame('active', $incident->fresh()->status);
        $this->assertStringContainsString('current condition has not been verified', $incident->fresh()->resolutionLabel());
    }

    public function test_command_error_is_retained_if_follow_up_also_errors(): void
    {
        $before = $this->check('dhcp_issue'); $incident = $this->incident($before);
        $health = Mockery::mock(NetworkHealthService::class);
        $health->shouldReceive('run')->once()->with('recovery_before', false, 'Wi-Fi')->andReturn(['check' => $before]);
        $health->shouldReceive('run')->once()->with('recovery_after', false, 'Wi-Fi')->andReturnUsing(function () use ($incident) {
            $saved = RecoveryAction::where('incident_id', $incident->id)->firstOrFail();
            $this->assertSame('failed', $saved->execution_status);
            $this->assertSame('Access denied', $saved->error_message);
            throw new \RuntimeException('Verification unavailable');
        });
        $this->app->instance(NetworkHealthService::class, $health); $this->command(false);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame('failed', $result->execution_status);
        $this->assertStringContainsString('Access denied', $result->error_message);
        $this->assertStringContainsString('Verification unavailable', $result->error_message);
        $this->assertStringContainsString('follow-up reading did not finish', $result->outcomeSummary());
    }

    public function test_incomplete_verification_does_not_claim_the_adapter_is_still_disabled(): void
    {
        $before = $this->check('adapter_disabled', ['local_ip' => null, 'probe_meta' => ['adapter_state' => 'disabled']]);
        $incident = $this->incident($before);
        $incident->update(['code' => 'adapter_disabled', 'title' => 'Network Adapter Disabled']);
        RecoveryAction::create(['device_id' => $incident->device_id, 'incident_id' => $incident->id,
            'action_code' => 'enable_adapter', 'action_label' => 'Enable the adapter',
            'before_health_check_id' => $before->id, 'started_at' => now(),
            'execution_status' => 'completed', 'verification_status' => 'pending', 'command_exit_code' => 0]);
        $story = $incident->fresh()->story();
        $this->assertStringContainsString('current condition has not been verified', $story['resolution']);
        $this->assertStringContainsString('The command completed', $story['repair']);
        foreach ([$this->get('/history'), $this->withHeaders(['X-Panel' => '1'])->get('/incidents/'.$incident->id)] as $response) {
            $response->assertOk()->assertSeeText($story['resolution'])->assertSeeText($story['repair']);
        }
        $unknown = new RecoveryAction(['execution_status' => 'pending', 'verification_status' => 'pending']);
        $this->assertStringContainsString('not recorded yet', $unknown->outcomeSummary());
    }

    public function test_an_unreadable_follow_up_preserves_execution_and_requests_a_fresh_check(): void
    {
        $before = $this->check('dhcp_issue'); $incident = $this->incident($before);
        $this->measurements($before, $this->check('configuration_unreadable')); $this->command(true);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertSame('completed', $result->execution_status);
        $this->assertSame('unread', $result->verification_status);
        $this->assertFalse($result->success);
        $this->assertSame('active', $incident->fresh()->status);
        $this->assertStringContainsString('Automatic verification exhausted', $incident->fresh()->recommended_action);
        $this->assertStringContainsString('current condition has not been verified', $incident->fresh()->resolutionLabel());
    }

    public function test_a_skipped_retry_keeps_the_earlier_interrupted_request_visible(): void
    {
        $opened = $this->check('adapter_disabled', ['local_ip' => null, 'diagnosis_title' => 'Network Adapter Disabled', 'probe_meta' => ['adapter_state' => 'disabled']]);
        $incident = $this->incident($opened);
        $incident->update(['code' => 'adapter_disabled', 'title' => 'Network Adapter Disabled', 'recovery_action_code' => 'enable_adapter']);
        $earlier = RecoveryAction::create(['device_id' => $incident->device_id, 'incident_id' => $incident->id,
            'action_code' => 'enable_adapter', 'action_label' => 'Enable the adapter',
            'before_health_check_id' => $opened->id, 'started_at' => now()->subMinutes(2),
            'execution_status' => 'pending', 'verification_status' => 'pending']);
        $healthy = $this->check('healthy');
        $this->measurements($healthy); $this->command(null);
        $latest = app(RecoveryService::class)->attempt($incident);
        $this->assertSame('already_recovered', $latest->verification_status);
        $this->assertSame('pending', $earlier->fresh()->execution_status);
        $this->assertNull($earlier->fresh()->completed_at);
        $this->assertNull($earlier->fresh()->success);
        $response = $this->withHeaders(['X-Panel' => '1'])->get('/incidents/'.$incident->id);
        $response->assertOk()->assertSeeText('No command was run for this request')
            ->assertSeeText('Latest repair request and result')->assertSeeText('Earlier repair attempts (1)')
            ->assertSeeText('Command result not recorded')->assertSee('data-recovery-id="'.$earlier->id.'"', false);
        $this->assertSame('The pre-repair check confirmed that the condition had already cleared. No repair command was run.', $incident->fresh()->resolutionLabel());
    }

    public function test_a_later_skipped_request_does_not_hide_the_previous_command_error(): void
    {
        $opened = $this->check('dhcp_issue'); $incident = $this->incident($opened);
        $earlier = RecoveryAction::create(['device_id' => $incident->device_id, 'incident_id' => $incident->id,
            'action_code' => 'renew_dhcp', 'action_label' => 'Release and renew the address',
            'before_health_check_id' => $opened->id, 'after_health_check_id' => $opened->id,
            'started_at' => now()->subMinutes(2), 'completed_at' => now()->subMinute(),
            'execution_status' => 'failed', 'verification_status' => 'not_verified',
            'success' => false, 'command_exit_code' => 1, 'error_message' => 'Access is denied (Windows System Error 5).']);
        $this->measurements($this->check('healthy')); $this->command(null);
        app(RecoveryService::class)->attempt($incident);
        $this->withHeaders(['X-Panel' => '1'])->get('/incidents/'.$incident->id)->assertOk()
            ->assertSeeText('No command was run for this request')
            ->assertSeeText('Earlier repair attempts (1)')->assertSeeText('Access is denied (Windows System Error 5).')
            ->assertSeeText('Command exit code')->assertSee('data-recovery-id="'.$earlier->id.'"', false);
        $this->assertFalse($earlier->fresh()->success);
    }

    public function test_request_groups_keep_their_own_comparisons_and_hide_technical_logs_by_default(): void
    {
        $before = $this->check('dhcp_issue');
        $after = $this->check('healthy', ['probe_meta' => [
            'adapter_state' => 'up', 'probe_states' => ['gateway' => 'passed', 'internet' => 'passed', 'dns' => 'passed'],
            'recovery_verification' => ['command_ms' => 2600, 'elapsed_ms' => 1500, 'attempt' => 1, 'maximum_attempts' => 3],
        ]]);
        $incident = $this->incident($before);
        $earlier = RecoveryAction::create(['device_id' => $incident->device_id, 'incident_id' => $incident->id,
            'action_code' => 'renew_dhcp', 'action_label' => 'Renew the address',
            'before_health_check_id' => $before->id, 'after_health_check_id' => $before->id,
            'started_at' => now()->subMinutes(2), 'completed_at' => now()->subMinute(),
            'execution_status' => 'failed', 'verification_status' => 'not_verified', 'success' => false,
            'command_exit_code' => 1, 'error_message' => 'Access denied fixture']);
        $latest = RecoveryAction::create(['device_id' => $incident->device_id, 'incident_id' => $incident->id,
            'action_code' => 'renew_dhcp', 'action_label' => 'Renew the address',
            'before_health_check_id' => $before->id, 'after_health_check_id' => $after->id,
            'started_at' => now(), 'completed_at' => now(), 'execution_status' => 'completed',
            'verification_status' => 'verified', 'success' => true, 'command_exit_code' => 0,
            'output_excerpt' => 'Stored output fixture']);
        $stored = [$incident->fresh()->getRawOriginal(), $latest->fresh()->getRawOriginal(), $earlier->fresh()->getRawOriginal()];
        foreach ([[], ['X-Panel' => '1']] as $headers) {
            $response = $this->get('/incidents/'.$incident->id, $headers)->assertOk();
            $document = new \DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $latestGroup = $xpath->query('//div[@data-recovery-id="'.$latest->id.'"]')->item(0);
            $olderGroup = $xpath->query('//div[@data-recovery-id="'.$earlier->id.'"]')->item(0);
            $this->assertNotNull($latestGroup);
            $this->assertNotNull($olderGroup);
            $stages = $xpath->query('.//section[@class="reading-stage"]', $latestGroup);
            $this->assertCount(2, $stages);
            $this->assertStringContainsString('Before: initial check', $stages->item(0)->textContent);
            $this->assertStringContainsString('Possible DHCP Configuration Issue', $stages->item(0)->textContent);
            $this->assertStringContainsString('Not tested', $stages->item(0)->textContent);
            $this->assertStringContainsString('After: final check', $stages->item(1)->textContent);
            $this->assertStringContainsString('Network Operational', $stages->item(1)->textContent);
            $this->assertStringContainsString('Passed', $stages->item(1)->textContent);
            $this->assertStringContainsString('Access denied fixture', $olderGroup->textContent);
            $this->assertStringNotContainsString('Access denied fixture', $latestGroup->textContent);
            $this->assertSame('0', trim($xpath->query('.//dt[text()="Command exit code"]/following-sibling::dd', $latestGroup)->item(0)->textContent));
            $this->assertSame('1', trim($xpath->query('.//dt[text()="Command exit code"]/following-sibling::dd', $olderGroup)->item(0)->textContent));
            $this->assertSame(0, $xpath->query('//details[contains(@class,"evidence-technical") or contains(@class,"evidence-output") or contains(@class,"repair-request-record")][@open]')->length);
            $this->assertStringContainsString('2.60 seconds', $latestGroup->textContent);
            $this->assertStringContainsString('1.50 seconds', $latestGroup->textContent);
        }
        $this->assertSame($stored, [$incident->fresh()->getRawOriginal(), $latest->fresh()->getRawOriginal(), $earlier->fresh()->getRawOriginal()]);
    }
}
