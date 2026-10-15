<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\MonitorSetting;
use App\Services\Network\NetworkProbeService;
use App\Services\Network\RecoveryCommandRunner;
use App\Services\Network\RecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class LocalOnlyMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function check(Device $device, array $extra = []): HealthCheck
    {
        return HealthCheck::create(array_merge([
            'device_id' => $device->id, 'checked_at' => now()->subMinute(), 'source' => 'manual',
            'platform_supported' => true, 'interface_name' => 'Wi-Fi', 'local_ip' => '192.168.1.5',
            'gateway_ip' => '192.168.1.1', 'dns_servers' => ['192.168.1.1'],
            'gateway_reachable' => true, 'internet_reachable' => true, 'dns_operational' => true,
            'gateway_latency_ms' => 3, 'internet_latency_ms' => 20, 'dns_latency_ms' => 30,
            'health_score' => 100, 'health_status' => 'healthy', 'diagnosis_code' => 'healthy',
            'diagnosis_title' => 'LOCAL-MEASUREMENT', 'severity' => 'none',
            'probe_meta' => ['adapter_state' => 'up'],
        ], $extra));
    }

    private function foreign(): Device
    {
        // Even an old, incorrectly flagged is_local row is not this host's UUID.
        return Device::create(['device_uuid' => 'retained-foreign-host', 'display_name' => 'REMOTE-COMPUTER-HIDDEN',
            'hostname' => 'REMOTE-COMPUTER-HIDDEN', 'is_local' => true, 'recognized' => true, 'status' => 'critical']);
    }

    private function incident(Device $device, HealthCheck $check): Incident
    {
        return Incident::create(['device_id' => $device->id, 'code' => 'adapter_disabled',
            'title' => 'REMOTE-INCIDENT-HIDDEN', 'status' => 'active', 'severity' => 'critical',
            'first_detected_at' => now()->subMinute(), 'last_detected_at' => now(),
            'latest_health_check_id' => $check->id, 'opening_health_check_id' => $check->id,
            'recovery_action_code' => 'enable_adapter']);
    }

    public function test_remote_records_do_not_enter_local_dashboard_checks_history_or_status(): void
    {
        $host = Device::localHost(); $local = $this->check($host);
        $other = $this->foreign();
        $remote = $this->check($other, ['checked_at' => now(), 'health_score' => 0, 'health_status' => 'critical',
            'diagnosis_code' => 'adapter_disabled', 'diagnosis_title' => 'REMOTE-MEASUREMENT-HIDDEN',
            'gateway_latency_ms' => 9999]);
        $incident = $this->incident($other, $remote);
        $this->get('/')->assertOk()->assertSeeText('100%')->assertSeeText('LOCAL-MEASUREMENT')
            ->assertDontSee('REMOTE-')->assertDontSee('Reporting')->assertDontSee('Not reporting')
            ->assertDontSee('Search a computer')->assertDontSee('href="'.url('/devices').'"', false)
            ->assertSee('data-latest-check-id="'.$local->id.'"', false)
            ->assertViewHas('summary', fn ($s) => $s['stats']['checks_24h'] === 1 && $s['stats']['active_incidents'] === 0);
        $this->get('/checks')->assertOk()->assertSeeText('LOCAL-MEASUREMENT')->assertDontSee('REMOTE-');
        $this->get('/history?device='.$other->id)->assertOk()->assertDontSee('REMOTE-')->assertDontSee('All computers');
        $this->get('/status')->assertOk()->assertJsonPath('latest_check_id', $local->id)->assertJsonPath('active_incident_id', null);
        $this->assertDatabaseHas('devices', ['id' => $other->id]);
        $this->assertDatabaseHas('health_checks', ['id' => $remote->id]);
        $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'status' => 'active']);
    }

    public function test_remote_incident_urls_cannot_view_repair_retest_or_queue_anything(): void
    {
        Device::localHost(); $other = $this->foreign(); $incident = $this->incident($other, $this->check($other));
        $commands = Mockery::mock(RecoveryCommandRunner::class); $commands->shouldNotReceive('run');
        $this->app->instance(RecoveryCommandRunner::class, $commands);
        $this->get('/incidents/'.$incident->id)->assertNotFound();
        $this->get('/incidents/'.$incident->id, ['X-Panel' => '1'])->assertNotFound();
        $this->post('/incidents/'.$incident->id.'/recover')->assertNotFound();
        $this->post('/incidents/'.$incident->id.'/retest')->assertNotFound();
        $this->assertFalse($incident->canRepair());
        $this->assertDatabaseCount('recovery_actions', 0);
        $this->assertDatabaseCount('agent_commands', 0);
        $this->expectException(\RuntimeException::class);
        app(RecoveryService::class)->attempt($incident);
    }

    public function test_removed_device_and_agent_endpoints_are_not_registered(): void
    {
        Device::localHost();
        foreach (['/devices', '/devices/1', '/devices/1/checks', '/api/agent/discover', '/api/agent/commands', '/agent/install.ps1'] as $path) {
            $this->get($path)->assertNotFound();
        }
        foreach (['/devices/1/recognize', '/api/agent/register', '/api/agent/heartbeat', '/api/agent/health', '/api/agent/commands/1/result'] as $path) {
            $this->post($path, [])->assertNotFound();
        }
        $this->assertDatabaseCount('agent_commands', 0);
        $this->assertDatabaseCount('enrollment_codes', 0);
        $this->assertArrayNotHasKey('netrescue:mark-offline', Artisan::all());
    }

    public function test_automatic_monitor_records_only_this_computer_and_does_not_touch_old_remote_rows(): void
    {
        $host = Device::localHost(); $this->check($host, ['source' => 'automatic', 'checked_at' => now()->subMinutes(10)]);
        $other = $this->foreign(); $remote = $this->check($other, ['source' => 'automatic', 'checked_at' => now()]);
        $incident = $this->incident($other, $remote); $before = $other->fresh()->getAttributes();
        MonitorSetting::current()->update(['enabled' => true, 'interval_minutes' => 5]);
        $probe = Mockery::mock(NetworkProbeService::class);
        $probe->shouldReceive('collectConfiguration')->once()->withNoArgs()->andReturn([
            'supported' => true, 'adapter_available' => true, 'adapter_state' => 'up', 'interface_name' => 'Wi-Fi',
            'local_ip' => '192.168.1.5', 'gateway_ip' => '192.168.1.1', 'dns_servers' => ['192.168.1.1'], 'error' => null,
        ]);
        $probe->shouldReceive('probeGateway')->once()->andReturn(['reachable' => true, 'latency_ms' => 3, 'error' => null]);
        $probe->shouldReceive('probeInternet')->once()->andReturn(['reachable' => true, 'latency_ms' => 20, 'target' => '1.1.1.1:443', 'error' => null]);
        $probe->shouldReceive('probeDns')->once()->andReturn(['operational' => true, 'latency_ms' => 30, 'addresses' => [], 'error' => null]);
        $this->app->instance(NetworkProbeService::class, $probe);
        $this->artisan('netrescue:monitor')->assertSuccessful();
        $this->assertSame(2, HealthCheck::where('device_id', $host->id)->count());
        $this->assertSame(1, HealthCheck::where('device_id', $other->id)->count());
        $this->assertSame($before, $other->fresh()->getAttributes());
        $this->assertSame('active', $incident->fresh()->status);
    }
}
