<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Device;
use App\Models\MonitorSetting;
use App\Services\Network\NetworkHealthService;
use App\Services\Network\NetworkRequestTimeout;
use Illuminate\Http\RedirectResponse;
use Throwable;

class HealthCheckController extends Controller
{
    public function index()
    {
        $checks = Device::localHost()->healthChecks()->paginate(40);
        $warningMs = (int) MonitorSetting::current()->latency_warning_ms;
        return view('checks.index', compact('checks', 'warningMs'));
    }

    public function store(NetworkHealthService $healthService, NetworkRequestTimeout $requestTimeout): RedirectResponse
    {
        try {
            $requestTimeout->prepareCheck();
        } catch (Throwable $e) {
            return back()->with('toast', ['type' => 'warning', 'title' => 'Check could not start', 'message' => $e->getMessage()]);
        }
        $result = $healthService->run('manual', true);
        $check = $result['check'];

        $unread = ! $check->readingFinished();
        $type = $check->diagnosis_code === 'healthy' ? 'success' : ($unread || $check->severity === 'low' ? 'warning' : 'danger');

        return back()->with('toast', [
            'type' => $type,
            'title' => match (true) {
                $check->diagnosis_code === 'healthy' => 'Check completed',
                $unread => 'Reading did not finish',
                default => 'Condition detected during check',
            },
            'message' => $unread
                ? $check->diagnosis_summary
                : $check->diagnosis_title.' · '.$check->health_score.'% health score',
        ]);
    }

    public function retest(Incident $incident, NetworkHealthService $healthService, NetworkRequestTimeout $requestTimeout): RedirectResponse
    {
        abort_unless((int) $incident->device_id === Device::localHost()->id, 404);

        try {
            $requestTimeout->prepareCheck();
        } catch (Throwable $e) {
            return back()->with('toast', ['type' => 'warning', 'title' => 'Re-test could not start', 'message' => $e->getMessage()]);
        }
        $result = $healthService->run('manual', true, $incident->latestHealthCheck?->interface_name);
        $check = $result['check'];

        return redirect()->route('incidents.show', $incident)->with('toast', [
            'type' => $check->diagnosis_code === 'healthy' ? 'success' : 'warning',
            'title' => $check->readingFinished() ? 'Re-test completed' : 'Reading did not finish',
            'message' => $check->readingFinished()
                ? $check->diagnosis_title.' · '.$check->health_score.'% health score' : $check->diagnosis_summary,
        ]);
    }
}
