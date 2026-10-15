<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalConsoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_another_computer_cannot_open_the_cit_admin_console(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'SERVER_ADDR' => '192.0.2.20',
        ])->get('/')->assertForbidden();

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'SERVER_ADDR' => '192.0.2.20',
        ])->get('/checks')->assertForbidden();

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'SERVER_ADDR' => '192.0.2.20',
        ])->get('/history')->assertForbidden();

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'SERVER_ADDR' => '192.0.2.20',
        ])->patch('/settings', [
            'enabled' => true,
            'interval_minutes' => 5,
            'latency_warning_ms' => 100,
            'dns_test_domain' => 'example.com',
        ])->assertForbidden();
    }

    public function test_this_computer_can_open_its_console(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.50',
            'SERVER_ADDR' => '203.0.113.50',
        ])->get('/')->assertOk()->assertSee('Dashboard');
    }
}
