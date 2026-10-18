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
                ->assertSeeText('This attempt did not clear the condition.');
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
}
