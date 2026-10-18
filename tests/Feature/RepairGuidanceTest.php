<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\RecoveryAction;
use App\Services\Network\NetworkHealthService;
use App\Services\Network\RecoveryCommandRunner;
use App\Support\NetworkDiagnosis;
use App\Support\RepairGuidance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RepairGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function incident(string $action = 'renew_dhcp', string $code = 'gateway_unreachable'): Incident
    {
        $check = HealthCheck::create([
            'device_id' => Device::localHost()->id, 'checked_at' => now(), 'source' => 'manual',
            'platform_supported' => true, 'interface_name' => 'Wi-Fi',
            'local_ip' => '192.168.1.5', 'gateway_ip' => '192.168.1.1', 'dns_servers' => [],
            'gateway_reachable' => false, 'internet_reachable' => false, 'dns_operational' => false,
            'health_score' => 0, 'health_status' => 'critical', 'diagnosis_code' => $code,
            'diagnosis_title' => 'Possible Local Gateway / Path Issue', 'severity' => 'high',
            'probe_meta' => ['adapter_state' => 'up', 'probe_states' => ['gateway' => 'failed', 'internet' => 'failed', 'dns' => 'skipped']],
        ]);
        return Incident::create([
            'device_id' => $check->device_id, 'code' => $code, 'title' => $check->diagnosis_title,
            'severity' => 'high', 'status' => 'active', 'first_detected_at' => $check->checked_at,
            'last_detected_at' => $check->checked_at, 'opening_health_check_id' => $check->id,
            'latest_health_check_id' => $check->id, 'recovery_action_code' => $action,
        ]);
    }

    private function previous(Incident $incident, string $action = 'renew_dhcp', string $execution = 'completed', string $verification = 'not_verified'): RecoveryAction
    {
        return RecoveryAction::create([
            'device_id' => $incident->device_id, 'incident_id' => $incident->id,
            'action_code' => $action, 'action_label' => $action === 'enable_adapter' ? 'Enable the adapter' : 'Renew the address',
            'before_health_check_id' => $incident->latest_health_check_id,
            'after_health_check_id' => $incident->latest_health_check_id,
            'started_at' => now()->subMinute(), 'completed_at' => now(), 'success' => false,
            'execution_status' => $execution, 'verification_status' => $verification,
            'command_exit_code' => $execution === 'completed' ? 0 : 1,
        ]);
    }

    private function noNetworkActions(): void
    {
        $health = Mockery::mock(NetworkHealthService::class);
        $health->shouldNotReceive('run');
        $this->app->instance(NetworkHealthService::class, $health);
        $commands = Mockery::mock(RecoveryCommandRunner::class);
        $commands->shouldNotReceive('run');
        $this->app->instance(RecoveryCommandRunner::class, $commands);
    }

    public function test_first_buttons_describe_each_action_and_its_limits(): void
    {
        foreach ([
            ['enable_adapter', 'adapter_disabled', 'Try enabling adapter', 'missing driver'],
            ['renew_dhcp', 'gateway_unreachable', 'Try renewing address', 'firewall block'],
            ['renew_dhcp', 'dhcp_issue', 'Try release and renew', 'temporarily interrupt'],
            ['flush_dns', 'dns_failure', 'Try clearing DNS cache', 'DNS server settings'],
            ['restart_adapter', 'gateway_unreachable', 'Try restarting adapter', 'briefly disconnects'],
        ] as [$action, $code, $label, $detail]) {
            $incident = $this->incident($action, $code);
            $guidance = RepairGuidance::for($incident);
            $this->assertSame($label, $guidance['button_label']);
            $this->assertNotEmpty($guidance['action_title']);
            $this->assertSame('', $guidance['previous_summary']);
            $this->assertSame('', $guidance['previous_status']);
            $this->assertStringContainsString($detail, $guidance['limitation'].' '.$guidance['consequence']);
            $this->get('/incidents/'.$incident->id)->assertOk()->assertSeeText($label)
                ->assertSee('data-repair-confirmation="initial"', false);
        }
    }

    public function test_escalation_requires_review_on_dashboard_page_and_fetched_panel(): void
    {
        $incident = $this->incident('restart_adapter');
        $previous = $this->previous($incident);
        foreach ([['/', []], ['/incidents/'.$incident->id, []], ['/incidents/'.$incident->id, ['X-Panel' => '1']]] as [$url, $headers]) {
            $this->get($url, $headers)->assertOk()->assertSeeText('Try restarting adapter')
                ->assertSee('data-repair-confirmation="required"', false)
                ->assertSee('name="previous_request_id" value="'.$previous->id.'"', false)
                ->assertSee('data-repair-previous=', false)
                ->assertSeeText('The command completed, but the follow-up still found the target condition.');
        }
    }

    public function test_retry_of_the_same_failed_action_also_requires_review(): void
    {
        $incident = $this->incident('enable_adapter', 'adapter_disabled');
        $previous = $this->previous($incident, 'enable_adapter', 'failed');
        $this->get('/incidents/'.$incident->id)->assertOk()
            ->assertSeeText('Try enabling adapter')->assertSee('data-repair-confirmation="required"', false)
            ->assertSee('name="previous_request_id" value="'.$previous->id.'"', false);
    }

    public function test_unconfirmed_or_stale_follow_up_never_starts_readings_or_commands(): void
    {
        $incident = $this->incident('restart_adapter');
        $previous = $this->previous($incident);
        $this->noNetworkActions();
        foreach ([
            [],
            ['repair_action' => 'restart_adapter', 'previous_request_id' => $previous->id, 'repair_confirmed' => 0],
            ['repair_action' => 'renew_dhcp', 'previous_request_id' => $previous->id, 'repair_confirmed' => 1],
            ['repair_action' => 'restart_adapter', 'previous_request_id' => $previous->id + 1, 'repair_confirmed' => 1],
        ] as $payload) {
            $this->post('/incidents/'.$incident->id.'/recover', $payload + ['return_to' => 'dashboard'])
                ->assertStatus(409)->assertSeeText('Review another repair attempt')
                ->assertSeeText('Previous attempt')->assertSeeText('Recovery is not guaranteed.')
                ->assertSee('data-repair-confirmation="reviewed"', false)
                ->assertSee('name="return_to" value="dashboard"', false);
            $this->assertSame(1, RecoveryAction::count());
            $this->assertSame(1, HealthCheck::count());
        }
    }

    public function test_confirmed_follow_up_still_skips_commands_if_precheck_finds_recovery(): void
    {
        $incident = $this->incident('restart_adapter');
        $previous = $this->previous($incident);
        $healthy = $incident->latestHealthCheck->replicate();
        $healthy->fill(['checked_at' => now(), 'source' => 'recovery_before', 'diagnosis_code' => 'healthy',
            'diagnosis_title' => 'Network Operational', 'gateway_reachable' => true, 'internet_reachable' => true,
            'dns_operational' => true, 'health_score' => 100, 'health_status' => 'healthy', 'severity' => 'none']);
        $healthy->save();
        $health = Mockery::mock(NetworkHealthService::class);
        $health->shouldReceive('run')->once()->with('recovery_before', false, 'Wi-Fi')->andReturn([
            'check' => $healthy, 'diagnosis' => new NetworkDiagnosis('healthy', 'Network Operational', 'none', 'Connected.'),
        ]);
        $this->app->instance(NetworkHealthService::class, $health);
        $commands = Mockery::mock(RecoveryCommandRunner::class);
        $commands->shouldNotReceive('run');
        $this->app->instance(RecoveryCommandRunner::class, $commands);
        $this->post('/incidents/'.$incident->id.'/recover', [
            'repair_action' => 'restart_adapter', 'previous_request_id' => $previous->id,
            'repair_confirmed' => 1, 'return_to' => 'dashboard',
        ])->assertRedirect('/')->assertSessionHas('toast.title', 'Already recovered - no command run');
        $this->assertSame(2, RecoveryAction::count());
        $latest = $incident->fresh()->latestRecovery();
        $this->assertSame('skipped', $latest->execution_status);
        $this->assertSame('already_recovered', $latest->verification_status);
    }

    public function test_uncertain_verification_remains_a_check_instead_of_a_repeat_repair(): void
    {
        $incident = $this->incident();
        $previous = $this->previous($incident, 'renew_dhcp', 'completed', 'unread');
        $this->get('/incidents/'.$incident->id)->assertOk()->assertSeeText('Check result')
            ->assertDontSee('class="js-busy-form js-repair-form', false);
        $this->assertStringContainsString('follow-up reading did not finish', $previous->outcomeSummary());
        $this->assertStringNotContainsString('did not clear', $previous->outcomeSummary());
    }

    public function test_short_previous_results_preserve_the_difference_between_failure_and_missing_evidence(): void
    {
        foreach ([
            ['completed', 'not_verified', 'Condition remains', 'still found the target condition'],
            ['completed', 'unread', 'Reading incomplete', 'did not finish'],
            ['completed', 'adapter_changed', 'Adapter changed', 'different adapter'],
            ['completed', 'changed_condition', 'Condition changed', 'different condition'],
            ['failed', 'not_verified', 'Command unsuccessful', 'did not complete successfully'],
            ['failed', 'unread', 'Command unsuccessful', 'reading was incomplete'],
            ['failed', 'adapter_changed', 'Command unsuccessful', 'different adapter'],
            ['failed', 'observed_recovery', 'Recovery observed', 'repair was not verified as its cause'],
            ['skipped', 'already_recovered', 'Already recovered', 'No command was run'],
            ['skipped', 'unread', 'Reading incomplete', 'pre-repair reading did not finish'],
            ['skipped', 'adapter_changed', 'Adapter changed', 'Windows selected a different adapter'],
            ['skipped', 'changed_condition', 'Condition changed', 'pre-repair check found a different condition'],
        ] as [$execution, $verification, $status, $summary]) {
            $incident = $this->incident('restart_adapter');
            $previous = $this->previous($incident, 'renew_dhcp', $execution, $verification);
            $guidance = RepairGuidance::for($incident);
            $this->assertSame($status, $guidance['previous_status']);
            $this->assertStringContainsString($summary, $guidance['previous_summary']);
            $this->assertSame($previous->outcomeSummary(), $guidance['previous_result']);
            $this->assertSame($previous->id, $guidance['previous_request_id']);
            $this->assertTrue($guidance['is_follow_up']);
            if ($verification !== 'not_verified') {
                $this->assertStringNotContainsString('still found', $guidance['previous_summary']);
                $this->assertStringNotContainsString('did not clear', $guidance['previous_summary']);
            }
        }
    }

    public function test_timeout_and_pending_summaries_do_not_claim_command_failure_or_network_failure(): void
    {
        foreach (['timed_out', 'failed'] as $execution) {
            $incident = $this->incident();
            $previous = $this->previous($incident, 'renew_dhcp', $execution, 'unread');
            $previous->update(['command_exit_code' => null,
                'error_message' => 'The process "ipconfig" exceeded the timeout of 60 seconds.']);
            $guidance = RepairGuidance::for($incident);
            $this->assertSame('Command timed out', $guidance['previous_status']);
            $this->assertStringContainsString('completion was not confirmed', $guidance['previous_summary']);
            $this->assertStringContainsString('reading was incomplete', $guidance['previous_summary']);
            $this->assertStringNotContainsString('did not complete successfully', $guidance['previous_summary']);
        }

        foreach (['completed', 'failed', 'pending'] as $execution) {
            $incident = $this->incident();
            $previous = $this->previous($incident, 'renew_dhcp', $execution, 'pending');
            $previous->update(['completed_at' => null]);
            $guidance = RepairGuidance::for($incident);
            $this->assertStringContainsString('pending', strtolower($guidance['previous_status']));
            $this->assertStringNotContainsString('still found', $guidance['previous_summary']);
            $this->assertSame('Verification pending', $guidance['manual_title']);
            $this->assertStringContainsString('Wait for the current request', $guidance['manual_step']);
        }
    }

    public function test_verified_target_recovery_does_not_claim_that_every_condition_cleared(): void
    {
        $incident = $this->incident();
        $previous = $this->previous($incident, 'renew_dhcp', 'completed', 'verified');
        $after = $incident->latestHealthCheck->replicate();
        $after->fill(['diagnosis_code' => 'dns_failure', 'diagnosis_title' => 'DNS Resolution Failure', 'gateway_reachable' => true]);
        $after->save();
        $previous->update(['success' => true, 'after_health_check_id' => $after->id]);
        $guidance = RepairGuidance::for($incident);
        $this->assertSame('Recovery verified', $guidance['previous_status']);
        $this->assertSame('The target condition cleared after repair; another condition remains.', $guidance['previous_summary']);

        $previous->beforeHealthCheck->update(['diagnosis_code' => 'healthy']);
        $guidance = RepairGuidance::for($incident->fresh());
        $this->assertSame('Already healthy', $guidance['previous_status']);
        $this->assertStringContainsString('recovery was not established', $guidance['previous_summary']);
    }

    public function test_manual_handoff_is_specific_without_inventing_an_outage_or_overriding_manual_settings(): void
    {
        foreach ([
            ['gateway_ping_unanswered', 'No repair required', 'Internet and DNS tests passed'],
            ['high_latency', 'No repair required', 'slow response persists'],
            ['probe_unavailable', 'Check required', 'does not confirm a network outage'],
            ['configuration_unreadable', 'Check required', 'does not confirm a network outage'],
            ['wan_unreachable', 'Manual action required', 'router WAN status and firewall policy'],
            ['gateway_unreachable', 'Manual action required', 'router connection and firewall policy'],
            ['static_configuration', 'Manual action required', 'approved manual IPv4 address'],
            ['adapter_disconnected', 'Manual action required', 'Reconnect the Ethernet cable or join the Wi-Fi network'],
            ['adapter_unavailable', 'Manual action required', 'adapter installation and driver'],
            ['adapter_disabled', 'Manual action required', 'administrator permissions'],
        ] as [$code, $title, $step]) {
            $guidance = RepairGuidance::for($this->incident('', $code));
            $this->assertSame($title, $guidance['manual_title']);
            $this->assertStringContainsString($step, $guidance['manual_step']);
        }

        $incident = $this->incident('', 'dns_failure');
        $incident->latestHealthCheck->update(['probe_meta' => ['dns_source' => 'static']]);
        $guidance = RepairGuidance::for($incident->fresh());
        $this->assertStringContainsString('manually configured DNS servers', $guidance['manual_step']);
        $this->assertStringContainsString('approved settings', $guidance['manual_step']);
        $this->assertStringNotContainsString('automatic', $guidance['manual_step']);
        $this->assertStringNotContainsString('renew', $guidance['manual_step']);
    }

    public function test_incomplete_or_different_adapter_follow_up_requires_a_check_instead_of_manual_configuration_changes(): void
    {
        foreach (['unread', 'adapter_changed'] as $verification) {
            $incident = $this->incident('', 'dns_failure');
            $this->previous($incident, 'flush_dns', 'completed', $verification);
            $guidance = RepairGuidance::for($incident);
            $this->assertSame('Check required', $guidance['manual_title']);
            $this->assertStringContainsString('adapter again', $guidance['manual_step']);
            $this->assertStringNotContainsString('DNS servers', $guidance['manual_step']);
        }
    }

    public function test_attention_emphasizes_only_connection_interruptions(): void
    {
        foreach ([['enable_adapter', 'adapter_disabled'], ['flush_dns', 'dns_failure']] as [$action, $code]) {
            $this->assertSame('', RepairGuidance::for($this->incident($action, $code))['attention']);
        }
        $restart = RepairGuidance::for($this->incident('restart_adapter'));
        $renew = RepairGuidance::for($this->incident('renew_dhcp'));
        $release = RepairGuidance::for($this->incident('renew_dhcp', 'dhcp_issue'));
        $this->assertStringContainsString('briefly disconnects Wi-Fi', $restart['attention']);
        $this->assertStringContainsString('Connectivity may pause', $renew['attention']);
        $this->assertStringContainsString('temporarily interrupt connectivity', $release['attention']);
    }
}
