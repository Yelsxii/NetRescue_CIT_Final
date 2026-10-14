<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Services\Network\NetworkHealthService;
use Illuminate\Http\RedirectResponse;

class HealthCheckController extends Controller
{
    public function store(NetworkHealthService $healthService): RedirectResponse
    {
        $result = $healthService->run('manual', true);
        $check = $result['check'];

        $unread = $check->diagnosis_code === 'configuration_unreadable';
        $type = $check->diagnosis_code === 'healthy' ? 'success' : ($unread || $check->severity === 'low' ? 'warning' : 'danger');

        return back()->with('toast', [
            'type' => $type,
            'title' => match (true) {
                $check->diagnosis_code === 'healthy' => 'Central check completed',
                $unread => 'Reading did not finish',
                default => 'Condition detected during Central check',
            },
            'message' => $unread
                ? $check->diagnosis_summary
                : $check->diagnosis_title.' · '.$check->health_score.'% health score',
        ]);
    }

    public function retest(Incident $incident, NetworkHealthService $healthService): RedirectResponse
    {
        $incident->loadMissing('device');

        if ($incident->device && ! $incident->device->is_local) {
            return back()->with('toast', [
                'type' => 'warning',
                'title' => 'Remote device re-test is not automated in this version',
                'message' => 'This agent-free version relies on fresh observed measurements from the network. Use the device page to review the latest condition.',
            ]);
        }

        $result = $healthService->run('manual', true);
        $check = $result['check'];

        return redirect()->route('incidents.show', $incident)->with('toast', [
            'type' => $check->diagnosis_code === 'healthy' ? 'success' : 'warning',
            'title' => 'Re-test completed',
            'message' => $check->diagnosis_title.' · '.$check->health_score.'% health score',
        ]);
    }
}
