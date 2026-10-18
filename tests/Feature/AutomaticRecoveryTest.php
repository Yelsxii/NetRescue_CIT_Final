<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\MonitorSetting;
use App\Models\RecoveryAction;
use App\Services\Network\NetworkHealthService;
use App\Services\Network\NetworkProbeService;
use App\Services\Network\RecoveryCommandRunner;
use App\Services\Network\RecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AutomaticRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['netrescue.verification.attempts' => 3, 'netrescue.verification.retry_delay_seconds' => 0]);
        MonitorSetting::current()->update(['enabled' => false]);
        $this->freezeTime(); // Also exercise readings with identical timestamps.
    }

    private function disabled(): array
    {
        return ['supported' => true, 'adapter_available' => false, 'adapter_state' => 'disabled',
            'interface_name' => 'Wi-Fi', 'local_ip' => null, 'gateway_ip' => null, 'dns_servers' => [],
            'address_source' => 'unknown', 'dns_source' => 'unknown', 'error' => null];
    }

    private function healthy(array $extra = []): array
    {
        return array_merge($this->disabled(), ['adapter_available' => true, 'adapter_state' => 'up',
            'local_ip' => '192.168.1.5', 'gateway_ip' => '192.168.1.1',
            'dns_servers' => ['192.168.1.1', '2001:db8::53'], 'address_source' => 'dhcp', 'dns_source' => 'dhcp'], $extra);
    }

    private function unread(): array
    {
        return array_merge($this->disabled(), ['adapter_state' => null, 'interface_name' => null, 'error' => 'Simulated configuration timeout']);
    }

    private function readings(array ...$configurations): void
    {
        $probe = Mockery::mock(NetworkProbeService::class);
        $probe->shouldReceive('collectConfiguration')->times(count($configurations))->andReturn(...$configurations);
        $probe->shouldReceive('probeGateway')->andReturn(['reachable' => true, 'latency_ms' => 2, 'error' => null]);
        $probe->shouldReceive('probeInternet')->andReturn(['reachable' => true, 'latency_ms' => 20, 'target' => '1.1.1.1:443', 'error' => null]);
        $probe->shouldReceive('probeDns')->andReturn(['operational' => true, 'latency_ms' => 30, 'addresses' => ['93.184.215.14'], 'error' => null]);
        $this->app->instance(NetworkProbeService::class, $probe);
    }

    private function opened(): Incident
    {
        return app(NetworkHealthService::class)->run('manual')['incident'];
    }

    private function command(bool $success = true, bool $timedOut = false): void
    {
        $runner = Mockery::mock(RecoveryCommandRunner::class);
        $runner->shouldReceive('run')->once()->with('enable_adapter', 'adapter_disabled', 'Wi-Fi')
            ->andReturn(['successful' => $success, 'output' => '',
                'error' => $timedOut ? 'The process "netsh.exe interface set interface" exceeded the timeout of 35 seconds.' : ($success ? '' : 'Access denied'),
                'exit_code' => $timedOut ? null : ($success ? 0 : 1), 'timed_out' => $timedOut]);
        $this->app->instance(RecoveryCommandRunner::class, $runner);
    }

    public function test_one_dashboard_post_retries_an_unread_followup_and_verifies_with_monitoring_paused(): void
    {
        $this->readings($this->disabled(), $this->disabled(), $this->unread(), $this->healthy(['reader' => 'target_snapshot']));
        $incident = $this->opened(); $this->command();
        $this->get('/')->assertSee('name="return_to" value="dashboard"', false);
        $this->post('/incidents/'.$incident->id.'/recover', ['return_to' => 'dashboard'])
            ->assertRedirect('/')->assertSessionHas('toast.title', 'Recovery verified');
        $action = RecoveryAction::sole();
        $this->assertTrue($action->success);
        $this->assertSame('completed', $action->execution_status);
        $this->assertSame('verified', $action->verification_status);
        $this->assertSame(0, $action->command_exit_code);
        $this->assertSame('verified_repair', $incident->fresh()->resolution_kind);
        $this->assertSame($action->after_health_check_id, $incident->fresh()->closing_health_check_id);
        $this->assertSame(2, data_get($action->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertSame('target_snapshot', data_get($action->afterHealthCheck->probe_meta, 'configuration_reader'));
        $this->assertCount(1, data_get($action->afterHealthCheck->probe_meta, 'recovery_verification.previous_check_ids'));
        $this->assertSame(1, HealthCheck::where('diagnosis_code', 'configuration_unreadable')->count());
        $this->assertFalse(MonitorSetting::current()->enabled);
        $this->get('/')->assertViewHas('localLatest', fn ($check) => $check->id === $action->after_health_check_id)
            ->assertSeeText('Network Operational');
        $this->get('/status')->assertJsonPath('latest_check_id', $action->after_health_check_id);
        $this->assertSame($action->after_health_check_id, Device::localHost()->latestHealthCheck->id);
        $this->get('/incidents/'.$incident->id)->assertSeeText('Verification readings')
            ->assertSeeText('of 3 allowed')->assertSeeText('Follow-up readings do not repeat the repair command.');
    }

    public function test_reconnecting_address_is_rechecked_automatically_until_a_usable_address_arrives(): void
    {
        $this->readings($this->disabled(), $this->disabled(),
            $this->healthy(['local_ip' => null, 'gateway_ip' => null]), $this->healthy());
        $incident = $this->opened(); $this->command();
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertTrue($result->success);
        $this->assertSame('healthy', $result->afterHealthCheck->diagnosis_code);
        $this->assertSame(2, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertSame(1, RecoveryAction::count());
    }

    public function test_three_unread_followups_stop_without_repeating_the_command_or_claiming_recovery(): void
    {
        $this->readings($this->disabled(), $this->disabled(), $this->unread(), $this->unread(), $this->unread());
        $incident = $this->opened(); $this->command();
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertFalse($result->success);
        $this->assertSame('completed', $result->execution_status);
        $this->assertSame('unread', $result->verification_status);
        $this->assertSame(3, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertCount(2, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.previous_check_ids'));
        $this->assertSame('active', $incident->fresh()->status);
        $this->assertNull($incident->fresh()->closing_health_check_id);
        $this->assertSame(3, HealthCheck::where('source', 'recovery_after')->count());
        $this->assertStringContainsString('exhausted', $incident->fresh()->recommended_action);
    }

    public function test_access_denied_with_a_readable_disabled_adapter_finishes_without_reconnect_retries(): void
    {
        $this->readings($this->disabled(), $this->disabled(), $this->disabled());
        $incident = $this->opened(); $this->command(false);
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->execution_status);
        $this->assertSame(1, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertSame('Access denied', $result->error_message);
    }

    public function test_a_different_adapter_never_verifies_the_target_or_triggers_extra_readings(): void
    {
        $this->readings($this->disabled(), $this->disabled(), $this->healthy(['interface_name' => 'Ethernet']));
        $incident = $this->opened(); $this->command();
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertFalse($result->success);
        $this->assertSame('adapter_changed', $result->verification_status);
        $this->assertSame('active', $incident->fresh()->status);
        $this->get('/')->assertSeeText('Repair result needs review')
            ->assertSeeText('It does not verify this Wi-Fi incident.')
            ->assertSee('data-repair-target="Wi-Fi"', false)
            ->assertDontSee('action="'.route('incidents.recover', $incident).'"', false)
            ->assertSeeText('Review result');
    }

    public function test_both_repair_readings_explicitly_request_the_incident_adapter(): void
    {
        $probe = Mockery::mock(NetworkProbeService::class);
        $probe->shouldReceive('collectConfiguration')->once()->withNoArgs()->andReturn($this->disabled());
        $probe->shouldReceive('collectConfiguration')->twice()->with('Wi-Fi')->andReturn($this->disabled(), $this->healthy());
        $probe->shouldReceive('probeGateway')->once()->andReturn(['reachable' => true, 'latency_ms' => 2, 'error' => null]);
        $probe->shouldReceive('probeInternet')->once()->andReturn(['reachable' => true, 'latency_ms' => 20, 'target' => '1.1.1.1:443', 'error' => null]);
        $probe->shouldReceive('probeDns')->once()->andReturn(['operational' => true, 'latency_ms' => 30, 'addresses' => ['93.184.215.14'], 'error' => null]);
        $this->app->instance(NetworkProbeService::class, $probe);
        $incident = $this->opened(); $this->command();
        $result = app(RecoveryService::class)->attempt($incident);
        $this->assertTrue($result->success);
        $this->assertSame('Wi-Fi', data_get($result->beforeHealthCheck->probe_meta, 'requested_interface_name'));
        $this->assertSame('Wi-Fi', data_get($result->afterHealthCheck->probe_meta, 'requested_interface_name'));
        $this->assertSame('Wi-Fi', data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.target_adapter'));
    }

    public function test_an_existing_repair_lock_blocks_a_second_command(): void
    {
        $this->readings($this->disabled()); $incident = $this->opened();
        $runner = Mockery::mock(RecoveryCommandRunner::class); $runner->shouldNotReceive('run');
        $this->app->instance(RecoveryCommandRunner::class, $runner);
        $lock = Cache::lock('netrescue:repair:'.$incident->device_id, 60); $this->assertTrue($lock->get());
        try {
            $this->post('/incidents/'.$incident->id.'/recover')
                ->assertSessionHas('toast.message', fn ($text) => str_contains($text, 'already running'));
            $this->assertSame(0, RecoveryAction::count());
        } finally { $lock->release(); }
    }

    public function test_the_repair_returns_to_incident_details_and_does_not_accept_an_external_return_url(): void
    {
        $this->readings($this->disabled(), $this->disabled(), $this->healthy());
        $incident = $this->opened(); $this->command();
        $this->post('/incidents/'.$incident->id.'/recover', ['return_to' => 'https://example.com'])
            ->assertRedirect('/incidents/'.$incident->id);
        $this->assertSame(1, data_get(RecoveryAction::sole()->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
    }

    public function test_a_timed_out_command_retains_unknown_completion_and_automatically_observes_later_recovery(): void
    {
        $this->readings($this->disabled(), $this->disabled(), $this->unread(), $this->healthy());
        $incident = $this->opened(); $this->command(false, true);
        $this->post('/incidents/'.$incident->id.'/recover')
            ->assertRedirect('/incidents/'.$incident->id)
            ->assertSessionHas('toast.title', 'Recovery observed; repair not verified');
        $result = RecoveryAction::sole();
        $this->assertSame('timed_out', $result->execution_status);
        $this->assertNull($result->command_exit_code);
        $this->assertFalse($result->success);
        $this->assertSame('observed_recovery', $result->verification_status);
        $this->assertSame(2, data_get($result->afterHealthCheck->probe_meta, 'recovery_verification.attempt'));
        $this->assertSame('observed_recovery', $incident->fresh()->resolution_kind);
        $this->assertSame($result->after_health_check_id, $incident->fresh()->closing_health_check_id);
        $this->assertStringContainsString('completion was not confirmed', $result->outcomeSummary());
        $this->assertStringContainsString('not verified as its cause', $result->outcomeSummary());
        $this->assertEquals(20.0, data_get($result->earlierVerificationChecks()->sole()->probe_meta, 'recovery_verification.configuration_timeout_seconds'));
        $storedAction = $result->getRawOriginal();
        $storedIncident = $incident->fresh()->getRawOriginal();
        foreach ([[], ['X-Panel' => '1']] as $headers) {
            $this->get('/incidents/'.$incident->id, $headers)->assertOk()
                ->assertSeeText('A later reading observed recovery; the repair remains unverified.')
                ->assertSeeText('Command timed out; completion unconfirmed')
                ->assertDontSeeText('A later automatic reading verified recovery');
        }
        $this->assertSame($storedAction, $result->fresh()->getRawOriginal());
        $this->assertSame($storedIncident, $incident->fresh()->getRawOriginal());
    }

    public function test_the_existing_nr27_timeout_is_described_as_uncertain_without_rewriting_it(): void
    {
        $this->readings($this->disabled()); $incident = $this->opened();
        $before = $incident->latestHealthCheck;
        $after = $before->replicate();
        $after->fill(['interface_name' => 'Ethernet', 'diagnosis_code' => 'adapter_disconnected',
            'diagnosis_title' => 'No Cable or Wi-Fi Link', 'probe_meta' => ['adapter_state' => 'disconnected']]);
        $after->save();
        $action = RecoveryAction::create(['device_id' => $incident->device_id, 'incident_id' => $incident->id,
            'action_code' => 'enable_adapter', 'action_label' => 'Enable the adapter',
            'before_health_check_id' => $before->id, 'after_health_check_id' => $after->id,
            'started_at' => now(), 'completed_at' => now(), 'success' => false,
            'execution_status' => 'failed', 'verification_status' => 'adapter_changed', 'command_exit_code' => null,
            'error_message' => 'The process "C:\\WINDOWS\\System32\\netsh.exe interface set interface "name=Wi-Fi" "admin=enabled"" exceeded the timeout of 35 seconds.']);
        $this->assertTrue($action->commandTimedOut());
        $this->assertStringContainsString('completion unconfirmed', $action->executionLabel());
        $this->assertStringContainsString('current condition has not been verified', $incident->fresh()->resolutionLabel());
        $this->get('/incidents/'.$incident->id)->assertOk()->assertSeeText('Check result')
            ->assertSeeText('command completion was not confirmed')
            ->assertSeeText('without repeating the repair command')
            ->assertDontSee('action="'.route('incidents.recover', $incident).'"', false);
        $this->assertSame('failed', $action->fresh()->execution_status);
        $this->assertFalse($action->fresh()->success);
    }

    public function test_check_result_measures_the_incident_adapter_without_running_a_command(): void
    {
        $probe = Mockery::mock(NetworkProbeService::class);
        $probe->shouldReceive('collectConfiguration')->once()->withNoArgs()->andReturn($this->disabled());
        $probe->shouldReceive('collectConfiguration')->once()->with('Wi-Fi')->andReturn($this->healthy());
        $probe->shouldReceive('probeGateway')->once()->andReturn(['reachable' => true, 'latency_ms' => 2, 'error' => null]);
        $probe->shouldReceive('probeInternet')->once()->andReturn(['reachable' => true, 'latency_ms' => 20, 'target' => '1.1.1.1:443', 'error' => null]);
        $probe->shouldReceive('probeDns')->once()->andReturn(['operational' => true, 'latency_ms' => 30, 'addresses' => ['93.184.215.14'], 'error' => null]);
        $this->app->instance(NetworkProbeService::class, $probe);
        $incident = $this->opened();
        $runner = Mockery::mock(RecoveryCommandRunner::class); $runner->shouldNotReceive('run');
        $this->app->instance(RecoveryCommandRunner::class, $runner);
        $this->post('/incidents/'.$incident->id.'/retest')->assertRedirect('/incidents/'.$incident->id);
        $this->assertSame(0, RecoveryAction::count());
        $this->assertSame('observed_recovery', $incident->fresh()->resolution_kind);
    }

    public function test_every_post_enable_read_uses_the_normal_allowance_even_with_a_stale_quick_setting(): void
    {
        config(['netrescue.verification.initial_configuration_seconds' => 5]);
        $probe = Mockery::mock(NetworkProbeService::class);
        $probe->shouldReceive('collectConfiguration')->once()->withNoArgs()->ordered()->andReturn($this->disabled());
        $probe->shouldReceive('collectConfiguration')->once()->with('Wi-Fi')->ordered()->andReturn($this->disabled());
        $probe->shouldReceive('collectConfiguration')->once()->with('Wi-Fi')->ordered()->andReturn($this->unread());
        $probe->shouldReceive('collectConfiguration')->once()->with('Wi-Fi')->ordered()->andReturn($this->healthy());
        $probe->shouldReceive('probeGateway')->once()->andReturn(['reachable' => true, 'latency_ms' => 2, 'error' => null]);
        $probe->shouldReceive('probeInternet')->once()->andReturn(['reachable' => true, 'latency_ms' => 20, 'target' => '1.1.1.1:443', 'error' => null]);
        $probe->shouldReceive('probeDns')->once()->andReturn(['operational' => true, 'latency_ms' => 30, 'addresses' => [], 'error' => null]);
        $this->app->instance(NetworkProbeService::class, $probe);
        $incident = $this->opened(); $this->command();
        $action = app(RecoveryService::class)->attempt($incident);
        $this->assertTrue($action->success);
        $first = $action->earlierVerificationChecks()->sole();
        $this->assertSame('configuration_unreadable', $first->diagnosis_code);
        $this->assertEquals(20.0, data_get($first->probe_meta, 'recovery_verification.configuration_timeout_seconds'));
        $this->assertEquals(20.0, data_get($action->afterHealthCheck->probe_meta, 'recovery_verification.configuration_timeout_seconds'));
        $this->assertSame(20, config('netrescue.timeouts.powershell_seconds'));
        $this->assertSame(35, config('netrescue.timeouts.recovery_seconds'));
        $this->assertSame('verified_repair', $incident->fresh()->resolution_kind);
    }

    public function test_a_single_allowed_read_keeps_the_full_budget(): void
    {
        config(['netrescue.verification.attempts' => 1]);
        $probe = Mockery::mock(NetworkProbeService::class);
        $probe->shouldReceive('collectConfiguration')->once()->withNoArgs()->andReturn($this->disabled());
        $probe->shouldReceive('collectConfiguration')->twice()->with('Wi-Fi')->andReturn($this->disabled(), $this->healthy());
        $probe->shouldReceive('probeGateway')->once()->andReturn(['reachable' => true, 'latency_ms' => 2, 'error' => null]);
        $probe->shouldReceive('probeInternet')->once()->andReturn(['reachable' => true, 'latency_ms' => 20, 'target' => '1.1.1.1:443', 'error' => null]);
        $probe->shouldReceive('probeDns')->once()->andReturn(['operational' => true, 'latency_ms' => 30, 'addresses' => [], 'error' => null]);
        $this->app->instance(NetworkProbeService::class, $probe);
        $incident = $this->opened(); $this->command();
        $action = app(RecoveryService::class)->attempt($incident);
        $this->assertTrue($action->success);
        $this->assertEquals(20.0, data_get($action->afterHealthCheck->probe_meta, 'recovery_verification.configuration_timeout_seconds'));
    }

    public function test_a_prior_twenty_second_timeout_is_visible_without_changing_verified_history(): void
    {
        $this->readings($this->disabled(), $this->disabled(), $this->unread(), $this->healthy());
        $incident = $this->opened(); $this->command();
        $action = app(RecoveryService::class)->attempt($incident);
        $first = $action->earlierVerificationChecks()->sole();
        $meta = $first->probe_meta;
        $meta['timings_ms'] = ['configuration' => 20676.2, 'total' => 20676.22];
        $meta['errors'] = ['The configuration process exceeded the timeout of 20 seconds.'];
        unset($meta['recovery_verification']['configuration_timeout_seconds']);
        $first->update(['probe_meta' => $meta]);
        $storedCheck = $first->getRawOriginal();
        $storedAction = $action->getRawOriginal();
        foreach ([[], ['X-Panel' => '1']] as $headers) {
            $this->get('/incidents/'.$incident->id, $headers)->assertOk()
                ->assertSeeText('Earlier automatic reading 1:')
                ->assertSeeText('Reading duration: 20.68 seconds.')
                ->assertSeeText('Measured adapter: Not obtained')
                ->assertSeeText('Requested adapter: Wi-Fi')
                ->assertSeeText('Reading error: The configuration process exceeded the timeout of 20 seconds.')
                ->assertSeeText('A later automatic reading verified recovery; this warning does not change the verified result.')
                ->assertSee('<p class="quiet">Reading error:', false)
                ->assertSeeInOrder(['Before: initial check', 'After: final check', 'Earlier automatic reading 1:']);
        }
        $this->assertTrue($action->fresh()->success);
        $this->assertSame(0, $action->fresh()->command_exit_code);
        $this->assertSame('verified', $action->fresh()->verification_status);
        $this->assertSame('verified_repair', $incident->fresh()->resolution_kind);
        $this->assertSame($storedCheck, $first->fresh()->getRawOriginal());
        $this->assertSame($storedAction, $action->fresh()->getRawOriginal());
    }

    public function test_earlier_readings_use_exact_ids_and_exclude_another_computer(): void
    {
        $this->readings($this->disabled(), $this->disabled(), $this->unread(), $this->healthy());
        $incident = $this->opened(); $this->command();
        $action = app(RecoveryService::class)->attempt($incident);
        $first = $action->earlierVerificationChecks()->sole();
        $neighbor = $first->replicate(); $neighbor->save();
        $otherDevice = Device::localHost()->replicate();
        $otherDevice->fill(['device_uuid' => 'other-verification-fixture', 'is_local' => false]); $otherDevice->save();
        $foreign = $first->replicate(); $foreign->device_id = $otherDevice->id; $foreign->save();
        $after = $action->afterHealthCheck;
        $meta = $after->probe_meta;
        $meta['recovery_verification']['previous_check_ids'] = [$first->id, (string) $first->id, $foreign->id, $after->id, 'bad', -1];
        $after->update(['probe_meta' => $meta]);
        $this->assertSame([$first->id], $action->fresh()->earlierVerificationChecks()->modelKeys());
        $this->assertFalse($action->fresh()->earlierVerificationChecks()->contains('id', $neighbor->id));
    }
}
