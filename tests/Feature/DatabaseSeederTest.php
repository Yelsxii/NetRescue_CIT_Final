<?php

namespace Tests\Feature;

use App\Models\Device;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_does_not_create_demo_computers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Device::query()->count());
        $this->assertTrue(Device::query()->first()->is_local);
        $this->assertDatabaseMissing('devices', ['display_name' => 'LAB-PC-01']);
        $this->assertDatabaseMissing('devices', ['display_name' => 'FACULTY-PC-01']);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('health_checks', 0);
    }
}
