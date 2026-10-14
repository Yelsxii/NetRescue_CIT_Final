<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Incident;
use App\Models\HealthCheck;
use App\Models\RecoveryAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentHistoryOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_repair_uses_its_exact_linked_after_check_without_claiming_repair_success(): void
    {
        $device = Device::localHost();
        $incident = Incident::create(['device_id' => $device->id, 'code' => 'dhcp_issue',
            'title' => 'DHCP', 'severity' => 'high', 'status' => 'resolved',
            'first_detected_at' => now()->subMinute(), 'last_detected_at' => now(), 'resolved_at' => now()]);
        $after = HealthCheck::create(['device_id' => $device->id, 'checked_at' => $incident->resolved_at,
            'source' => 'recovery_after', 'platform_supported' => true, 'local_ip' => '192.168.1.5',
            'gateway_ip' => '192.168.1.1', 'health_score' => 100, 'health_status' => 'healthy',
            'diagnosis_code' => 'healthy', 'diagnosis_title' => 'Network Operational', 'severity' => 'none']);
        RecoveryAction::create(['device_id' => $device->id, 'incident_id' => $incident->id,
            'action_code' => 'renew_dhcp', 'action_label' => 'Renew the address',
            'after_health_check_id' => $after->id, 'started_at' => now(), 'completed_at' => now(), 'success' => false]);
        $this->assertSame($after->id, $incident->fresh()->closingEvidence()?->id);
        $this->assertFalse($incident->fresh()->latestRecovery()->success);
        $this->assertStringContainsString('not verified as the cause', $incident->fresh()->resolutionLabel());
    }

    public function test_open_priority_is_preserved_while_closed_records_are_newest_first(): void
    {
        $device = Device::localHost();
        foreach ([
            ['Old closed critical', 'resolved', 'critical', now()->subHours(3)],
            ['New closed high', 'resolved', 'high', now()->subMinute()],
            ['Open high', 'active', 'high', now()],
            ['Open critical', 'active', 'critical', now()->subMinutes(2)],
        ] as $index => [$title, $status, $severity, $time]) {
            Incident::create(['device_id' => $device->id, 'code' => 'history_order_'.$index,
                'title' => $title, 'status' => $status, 'severity' => $severity,
                'first_detected_at' => $time, 'last_detected_at' => $time,
                'resolved_at' => $status === 'resolved' ? $time : null]);
        }
        $this->get('/history')->assertOk()
            ->assertSeeTextInOrder(['Open critical', 'Open high', 'New closed high', 'Old closed critical']);
    }
}
