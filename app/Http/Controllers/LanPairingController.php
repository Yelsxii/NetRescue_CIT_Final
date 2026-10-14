<?php

namespace App\Http\Controllers;

use App\Services\Agent\AgentDeploymentService;
use App\Services\Agent\LanPresenceService;
use Illuminate\Http\Request;

class LanPairingController extends Controller
{
    public function connect(Request $request, LanPresenceService $lan, AgentDeploymentService $deployment)
    {
        $key = (string) $request->input('pairing_key', '');
        $remote = trim((string) $request->server('REMOTE_ADDR', ''));

        if (! $lan->keyMatches($key)) {
            return response('That key does not match the Wi-Fi this Central laptop is using now.', 422);
        }

        if (! $lan->sameLan($remote)) {
            return response('This computer is not on the same router as the Central laptop, so this key cannot connect it.', 422);
        }

        $snapshot = $lan->snapshot();
        $batch = $deployment->installerBatch(
            $deployment->centralUrl($request),
            $snapshot['pairing_key']
        );

        return response($batch, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="NetRescueConnect.bat"',
        ]);
    }

    public function rotate(LanPresenceService $lan)
    {
        $lan->rotateKey();

        return redirect()->route('devices.index');
    }
}
