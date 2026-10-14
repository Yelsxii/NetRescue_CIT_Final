<?php

namespace App\Http\Controllers\Api;

use App\Services\Agent\AgentDeploymentService;
use App\Services\Agent\LanPresenceService;
use Illuminate\Http\Request;

class AgentDiscoveryController extends AgentApiController
{
    public function show(Request $request, LanPresenceService $lan, AgentDeploymentService $deployment)
    {
        $snapshot = $lan->snapshot();

        return response()->json([
            'service' => 'netrescue',
            'url' => $deployment->centralUrl($request),
            'gateway' => $snapshot['gateway_ip'],
            'hostname' => $snapshot['hostname'],
        ]);
    }
}
