<?php

namespace App\Http\Controllers;

use App\Models\AgentCommand;
use App\Models\Incident;
use App\Models\RecoveryAction;
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

        if ($incident->device && !$incident->device->is_local) {
            try {
                $recovery = RecoveryAction::create([
                    'device_id' => $incident->device_id,
                    'incident_id' => $incident->id,
                    'action_code' => $incident->recovery_action_code,
                    'action_label' => $this->labelFor($incident->recovery_action_code),
                    'before_health_check_id' => $incident->latest_health_check_id,
                    'started_at' => now(),
                ]);

                AgentCommand::create([
                    'device_id' => $incident->device_id,
                    'incident_id' => $incident->id,
                    'recovery_action_id' => $recovery->id,
                    'command_code' => $incident->recovery_action_code,
                    'payload' => [
                        'requested_from' => 'central_dashboard',
                        'release' => $incident->code === 'dhcp_issue',
                        'interface_name' => $incident->latestHealthCheck?->interface_name,
                    ],
                    'status' => 'queued',
                    'queued_at' => now(),
                    'expires_at' => now()->addMinutes((int) config('netrescue.agent.command_expiry_minutes', 10)),
                ]);
            } catch (Throwable $e) {
                return back()->with('toast', ['type' => 'danger', 'title' => 'Remote recovery could not queue', 'message' => $e->getMessage()]);
            }

            return back()->with('toast', [
                'type' => 'success',
                'title' => 'Repair sent',
                'message' => $incident->device?->display_name.' will run '.$this->labelFor($incident->recovery_action_code).' and report the measurement.',
            ]);
        }

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

    private function labelFor(?string $code): string
    {
        return match ($code) {
            'flush_dns' => 'Clear Windows DNS resolver cache',
            'renew_dhcp' => 'Renew Windows DHCP configuration',
            'enable_adapter' => 'Enable the network adapter',
            'restart_adapter' => 'Restart the network adapter',
            default => 'Approved NetRescue recovery action',
        };
    }
}
