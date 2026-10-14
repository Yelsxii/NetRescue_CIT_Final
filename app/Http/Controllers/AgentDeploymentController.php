<?php

namespace App\Http\Controllers;

use App\Models\EnrollmentCode;
use App\Services\Agent\AgentDeploymentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AgentDeploymentController extends Controller
{
    public function installer(Request $request, AgentDeploymentService $deployment): Response
    {
        $enrollment = EnrollmentCode::current();
        $centralUrl = $deployment->centralUrl($request);
        $content = $deployment->installerBatch($centralUrl, $enrollment->code);

        return response($content, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="NetRescueAgentSetup.bat"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function installScript(): BinaryFileResponse
    {
        return response()->download(
            base_path('agent/Install-Agent.ps1'),
            'Install-Agent.ps1',
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    public function agentScript(): BinaryFileResponse
    {
        return response()->download(
            base_path('agent/NetRescueAgent.ps1'),
            'NetRescueAgent.ps1',
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
