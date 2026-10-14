<?php

namespace Tests\Feature;

use App\Models\MonitorSetting;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonitorScheduleTest extends TestCase
{
    public function test_a_one_minute_setting_is_due_on_the_next_scheduler_minute(): void
    {
        $settings = new MonitorSetting(['interval_minutes' => 1, 'enabled' => true]);
        $last = Carbon::parse('2026-10-11 22:09:04');

        $this->assertFalse($settings->isDue($last, Carbon::parse('2026-10-11 22:09:30')));
        $this->assertTrue($settings->isDue($last, Carbon::parse('2026-10-11 22:10:00')));
        $this->assertSame(
            '2026-10-11 22:10:00',
            $settings->nextAutomaticAt($last, Carbon::parse('2026-10-11 22:09:20'))->format('Y-m-d H:i:s')
        );
    }

    public function test_a_five_minute_setting_waits_until_that_interval(): void
    {
        $settings = new MonitorSetting(['interval_minutes' => 5, 'enabled' => true]);
        $last = Carbon::parse('2026-10-11 22:00:04');

        $this->assertFalse($settings->isDue($last, Carbon::parse('2026-10-11 22:04:00')));
        $this->assertTrue($settings->isDue($last, Carbon::parse('2026-10-11 22:05:00')));
    }
}
