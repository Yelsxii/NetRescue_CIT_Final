<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\MonitorSetting;
use App\Services\MonitorSchedulerStatus;
use App\Services\Network\NetworkHealthService;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class MonitorSchedulerStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_enabled_setting_does_not_claim_the_scheduler_is_running(): void
    {
        $settings = MonitorSetting::current();
        $status = app(MonitorSchedulerStatus::class)->status($settings);

        $this->assertSame('scheduler_unconfirmed', $status['state']);
        $this->assertFalse($status['scheduler_ready']);
        $this->assertNull($status['last_tick_at']);
        $this->assertSame('Scheduler not detected', $status['label']);
        $this->get('/status')->assertOk()->assertJsonPath('monitoring_enabled', true)
            ->assertJsonPath('monitoring.state', 'scheduler_unconfirmed');
        $this->get('/')->assertOk()->assertSeeText('Scheduler not detected')
            ->assertSeeText('START_NETRESCUE.bat')->assertDontSee('data-next-check=', false);
        $this->assertDatabaseCount('health_checks', 0);
    }

    public function test_only_a_recent_scheduler_tick_confirms_readiness(): void
    {
        $this->travelTo(Carbon::parse('2026-10-18 18:00:00'));
        $settings = MonitorSetting::current();
        $scheduler = app(MonitorSchedulerStatus::class);
        $scheduler->heartbeat();

        $this->assertSame('ready', $scheduler->status($settings)['state']);
        $settings->update(['interval_minutes' => 1]);
        $this->get('/')->assertOk()->assertSeeText('Every 1 min')->assertSee('data-next-check=', false)
            ->assertDontSeeText('Scheduler not detected')->assertDontSeeText('START_NETRESCUE.bat');
        $this->travel(150)->seconds();
        $this->assertTrue($scheduler->status($settings)['scheduler_ready']);
        $this->travel(1)->seconds();
        $this->assertSame('scheduler_unconfirmed', $scheduler->status($settings)['state']);

        $scheduler->heartbeat();
        $this->travel(-1)->seconds();
        $this->assertFalse($scheduler->status($settings)['scheduler_ready']);
    }

    public function test_the_scheduled_heartbeat_runs_while_paused_without_creating_measurements(): void
    {
        $settings = MonitorSetting::current();
        $settings->update(['enabled' => false]);
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $events = collect(app(Schedule::class)->events());
        $heartbeat = $events->first(fn ($event) => $event->description === 'netrescue:scheduler-heartbeat');

        $this->assertInstanceOf(CallbackEvent::class, $heartbeat);
        $this->assertSame('* * * * *', $heartbeat->expression);
        $heartbeat->run($this->app);

        $status = app(MonitorSchedulerStatus::class)->status($settings);
        $this->assertSame('paused', $status['state']);
        $this->assertSame('Paused', $status['label']);
        $this->assertTrue($status['scheduler_ready']);
        $this->get('/')->assertOk()->assertSeeText('Paused')->assertDontSee('data-next-check=', false)
            ->assertDontSeeText('Scheduler not detected')->assertDontSeeText('START_NETRESCUE.bat');
        $this->assertDatabaseCount('health_checks', 0);
        $this->assertDatabaseCount('recovery_actions', 0);
        $this->assertFalse($settings->fresh()->enabled);
    }

    public function test_paused_and_not_due_monitor_commands_do_not_run_a_probe(): void
    {
        $settings = MonitorSetting::current();
        $settings->update(['enabled' => false]);
        $health = Mockery::mock(NetworkHealthService::class);
        $health->shouldNotReceive('run');
        $this->app->instance(NetworkHealthService::class, $health);

        $this->artisan('netrescue:monitor')->expectsOutput('Monitoring is disabled.')->assertSuccessful();
        $this->assertDatabaseCount('health_checks', 0);
        $this->assertFalse(app(MonitorSchedulerStatus::class)->status($settings)['scheduler_ready']);

        $settings->update(['enabled' => true, 'interval_minutes' => 5]);
        HealthCheck::create([
            'device_id' => Device::localHost()->id, 'source' => 'automatic', 'checked_at' => now(),
            'platform_supported' => true, 'gateway_reachable' => true, 'internet_reachable' => true,
            'dns_operational' => true, 'health_score' => 100, 'health_status' => 'healthy',
            'diagnosis_code' => 'healthy', 'diagnosis_title' => 'Network Operational', 'severity' => 'none',
        ]);
        $this->artisan('netrescue:monitor')->assertSuccessful();
        $this->assertDatabaseCount('health_checks', 1);
    }

    public function test_unreadable_or_malformed_heartbeat_does_not_break_status(): void
    {
        $settings = MonitorSetting::current();
        Cache::put('netrescue:scheduler:last-tick', 'invalid', 60);
        $this->assertFalse(app(MonitorSchedulerStatus::class)->status($settings)['scheduler_ready']);

        Cache::shouldReceive('get')->once()->with('netrescue:scheduler:last-tick')
            ->andThrow(new \RuntimeException('Cache is unavailable'));
        $this->assertSame('scheduler_unconfirmed', app(MonitorSchedulerStatus::class)->status($settings)['state']);
    }
}
