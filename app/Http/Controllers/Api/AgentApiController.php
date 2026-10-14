<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

abstract class AgentApiController extends Controller
{
    protected function authenticatedDevice(Request $request): ?Device
    {
        $token = $request->bearerToken();
        if (!$token) return null;

        return Device::query()
            ->where('auth_token_hash', hash('sha256', $token))
            ->where('connection_type', 'agent')
            ->first();
    }

    protected function unauthenticated()
    {
        return response()->json(['message' => 'Invalid or missing agent token.'], 401);
    }
}
