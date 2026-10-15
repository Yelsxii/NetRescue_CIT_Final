<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Incident;
use App\Services\Network\NetworkRequestTimeout;
use App\Services\Network\RecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class RecoveryController extends Controller
{
    public function store(Incident $incident, RecoveryService $recoveryService, NetworkRequestTimeout $requestTimeout, Request $request): RedirectResponse
    {
        $incident->loadMissing(['device', 'latestHealthCheck']);

        abort_unless((int) $incident->device_id === Device::localHost()->id, 404);

        try {
            $requestTimeout->prepareRecovery((string) $incident->recovery_action_code, $incident->code);
            $recovery = $recoveryService->attempt($incident);
        } catch (Throwable $e) {
            return back()->with('toast', ['type' => 'danger', 'title' => 'Recovery could not start', 'message' => $e->getMessage()]);
        }

        $skipped = $recovery->execution_status === 'skipped';
        $already = $recovery->verification_status === 'already_recovered';
        $observed = $recovery->verification_status === 'observed_recovery';
        $destination = $request->input('return_to') === 'dashboard'
            ? route('dashboard') : route('incidents.show', $incident);
        return redirect($destination)->with('toast', [
            'type' => $recovery->success || $already ? 'success' : 'warning',
            'title' => $already ? 'Already recovered - no command run'
                : ($skipped ? 'Repair was not run' : ($recovery->success ? 'Recovery verified'
                    : ($observed ? 'Recovery observed; repair not verified' : 'Repair outcome needs review'))),
            'message' => $recovery->outcomeSummary(),
        ]);
    }

}
