<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_devices_page_emphasizes_agent_free_administrator_monitoring(): void
    {
        $response = $this->get('/devices');

        $response->assertOk()
            ->assertSee('Agent-free, administrator-first monitoring')
            ->assertSee('Device List')
            ->assertDontSee('Download agent installer');
    }

    public function test_agent_installer_download_routes_are_not_exposed_in_the_revised_ui(): void
    {
        $this->get('/downloads/netrescue-agent/setup')->assertNotFound();
        $this->get('/downloads/netrescue-agent/install-script')->assertNotFound();
        $this->get('/downloads/netrescue-agent/agent-script')->assertNotFound();
    }
}
