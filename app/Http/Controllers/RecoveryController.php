<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Incident;
use App\Exceptions\RepairConfirmationRequired;
use App\Services\Network\NetworkRequestTimeout;
use App\Services\Network\RecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class RecoveryController extends Controller
{
    public function store(Incident $incident, RecoveryService $recoveryService, NetworkRequestTimeout $requestTimeout, Request $request): RedirectResponse|Response
    {
        $incident->loadMissing(['device', 'latestHealthCheck']);

        abort_unless((int) $incident->device_id === Device::localHost()->id, 404);

        try {
            $requestTimeout->prepareRecovery((string) $incident->recovery_action_code, $incident->code);
            $recovery = $recoveryService->attempt($incident, [
                'action' => $request->input('repair_action'),
                'previous_id' => $request->integer('previous_request_id'),
                'confirmed' => $request->boolean('repair_confirmed'),
            ]);
        } catch (RepairConfirmationRequired $e) {
            $incident->refresh()->load(['device', 'latestHealthCheck', 'recoveryActions.beforeHealthCheck', 'recoveryActions.afterHealthCheck']);
            return response()->view('incidents.confirm-repair', [
                'incident' => $incident,
                'returnTo' => $request->input('return_to') === 'dashboard' ? 'dashboard' : null,
            ], 409);
        } catch (Throwable $e) {
            return back()->with('toast', ['type' => 'danger', 'title' => 'Recovery could not start', 'message' => $e->getMessage()]);
        }

        $skipped = $recovery->execution_status === 'skipped';
        $already = $recovery->verification_status === 'already_recovered';
        $observed = $recovery->verification_status === 'observed_recovery';
        $partial = $recovery->success && $recovery->afterHealthCheck?->diagnosis_code !== 'healthy';
        $destination = $request->input('return_to') === 'dashboard'
            ? route('dashboard') : route('incidents.show', $incident);
        return redirect($destination)->with('toast', [
            'type' => ($recovery->success && ! $partial) || $already ? 'success' : 'warning',
            'title' => match (true) {
                $already => 'Already recovered - no command run',
                $skipped => 'Repair was not run',
                $partial => 'Target repaired; another condition remains',
                (bool) $recovery->success => 'Recovery verified',
                $observed => 'Recovery observed; repair not verified',
                $recovery->verification_status === 'not_verified' => 'This repair did not clear the condition',
                default => 'Repair outcome needs review',
            },
            'message' => $recovery->outcomeSummary(),
        ]);
    }

}
