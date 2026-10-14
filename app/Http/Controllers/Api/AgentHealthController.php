<?php

namespace App\Http\Controllers\Api;

use App\Services\Agent\RemoteHealthIngestService;
use Illuminate\Http\Request;

class AgentHealthController extends AgentApiController
{
    public function store(Request $request, RemoteHealthIngestService $ingest)
    {
        $device = $this->authenticatedDevice($request);
        if (!$device) return $this->unauthenticated();

        $metrics = $request->validate([
            'platform_supported' => ['required', 'boolean'],
            'adapter_available' => ['required', 'boolean'],
            'adapter_state' => ['nullable', 'string', 'in:up,disabled,disconnected,missing'],
            'address_source' => ['nullable', 'string', 'in:dhcp,static,unknown'],
            'dns_source' => ['nullable', 'string', 'in:dhcp,static,unknown'],
            'interface_name' => ['nullable', 'string', 'max:255'],
            'local_ip' => ['nullable', 'ip'],
            'gateway_ip' => ['nullable', 'ip'],
            'dns_servers' => ['array'],
            'dns_servers.*' => ['string', 'max:64'],
            'gateway_reachable' => ['required', 'boolean'],
            'gateway_latency_ms' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'internet_reachable' => ['required', 'boolean'],
            'internet_latency_ms' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'internet_target' => ['nullable', 'string', 'max:255'],
            'dns_operational' => ['required', 'boolean'],
            'dns_latency_ms' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'dns_addresses' => ['array'],
            'dns_addresses.*' => ['string', 'max:64'],
            'probe_errors' => ['array'],
            'probe_errors.*' => ['string', 'max:1000'],
        ]);

        $result = $ingest->ingest($device, $metrics, 'agent', true);

        return response()->json([
            'ok' => true,
            'health_score' => $result['check']->health_score,
            'health_status' => $result['check']->health_status,
            'diagnosis_code' => $result['diagnosis']->code,
            'diagnosis_title' => $result['diagnosis']->title,
        ]);
    }
}
