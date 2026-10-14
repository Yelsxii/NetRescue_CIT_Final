@extends('layouts.app', ['title' => 'NetRescue CIT · '.$device->display_name, 'pageTitle' => $device->display_name])

@section('content')
@php
    $gateway = \App\Support\CheckStory::probeMark($latest, 'gateway', $warningMs);
    $internet = \App\Support\CheckStory::probeMark($latest, 'internet', $warningMs);
    $dns = \App\Support\CheckStory::probeMark($latest, 'dns', $warningMs);
    $recent = $trend->reverse()->take(4);
@endphp
<div class="page-fit device-page">
    <header class="identity">
        <div>
            <a class="back-link" href="{{ route('devices.index') }}">Devices</a>
            <h1>{{ $device->display_name }}</h1>
            <p>
                <x-status-pill :status="$device->status" :label="match($device->status) { 'offline' => 'Offline', 'unknown' => 'Waiting', 'degraded' => 'Degraded', 'critical' => 'Critical', 'healthy' => 'Healthy', default => ucfirst($device->status) }" />
                <span>{{ $device->is_local ? 'Central computer' : 'Recognized computer' }}</span>
                <span>{{ $latest?->local_ip ?? $device->last_ip ?? 'No address' }}</span>
                <span>{{ $device->last_seen_at ? 'Last seen '.$device->last_seen_at->diffForHumans() : 'Not seen yet' }}</span>
            </p>
        </div>
    </header>

    <section class="detail-stack">
        <article class="case-card">
            <h2>Current condition</h2>
            @if($latest && ! $latest->readingFinished())
                <p class="case-when">{{ $latest->checked_at->diffForHumans() }}</p>
                <p class="case-fact">{{ $latest->diagnosis_title }}</p>
                <p class="quiet">{{ $latest->diagnosis_summary }}</p>
                <p class="now-line"><span>Gateway</span> <b class="mark-none">Not measured</b></p>
                <p class="now-line"><span>Internet</span> <b class="mark-none">Not measured</b></p>
                <p class="now-line"><span>Name lookup</span> <b class="mark-none">Not measured</b></p>
            @elseif($latest)
                <p class="case-when">{{ $latest->checked_at->diffForHumans() }}</p>
                <p class="case-fact">{{ $latest->diagnosis_title }}</p>
                <p class="now-line"><span>Gateway</span> <b class="mark-{{ $gateway['code'] }}">{{ $gateway['detail'] }}</b></p>
                <p class="now-line"><span>Internet</span> <b class="mark-{{ $internet['code'] }}">{{ $internet['detail'] }}</b></p>
                <p class="now-line"><span>Name lookup</span> <b class="mark-{{ $dns['code'] }}">{{ $dns['detail'] }}</b></p>
            @else
                <p class="case-fact">No measurement stored.</p>
            @endif
        </article>

        <article class="case-card">
            <h2>Configuration</h2>
            <p class="now-line"><span>Address</span> <b>{{ $measured?->local_ip ?? $device->last_ip ?? 'None' }}</b></p>
            <p class="now-line"><span>Gateway</span> <b>{{ $measured?->gateway_ip ?? $device->gateway_ip ?? 'None' }}</b></p>
            <p class="now-line"><span>DNS</span> <b>{{ $measured && is_array($measured->dns_servers) && $measured->dns_servers ? implode(', ', $measured->dns_servers) : (is_array($device->dns_servers) && $device->dns_servers ? implode(', ', $device->dns_servers) : 'None recorded') }}</b></p>
            <p class="now-line"><span>Adapter</span> <b>{{ $measured?->interface_name ?? $device->adapter_name ?? 'Unknown' }}</b></p>
        </article>

        <article class="case-card">
            <h2>Repair</h2>
            @forelse($activeIncidents as $incident)
                <p class="case-fact">{{ $incident->plainProblem() }}</p>
                @if($incident->canRepair())
                    <p class="quiet">{{ $incident->repairCommand() }}</p>
                    <form method="POST" action="{{ route('incidents.recover', $incident) }}" class="js-busy-form" data-busy-text="{{ $device->is_local ? 'Running the repair and measuring again…' : 'Sending the repair to this computer…' }}">
                        @csrf
                        <button class="button button-primary" type="submit">Run repair</button>
                    </form>
                @elseif(filled($incident->repairCommand()) && ! $device->is_local)
                    <p class="quiet">{{ $incident->repairCommand() }}. This computer is not connected, so the repair cannot be sent.</p>
                @else
                    <p class="quiet">No repair command on this computer. {{ $incident->resolutionLabel() }}.</p>
                @endif
            @empty
                <p class="case-fact">No open repair.</p>
            @endforelse
        </article>
    </section>

    <article class="table-panel">
        <div class="panel-title compact">
            <div><h2>Recent results</h2></div>
            <a href="{{ route('devices.checks', $device) }}">All checks</a>
        </div>
        <div class="check-log">
            @forelse($recent as $check)
                <div class="check-log-row">
                    <span>{{ $check->checked_at->format('g:i A') }}</span>
                    <strong>{{ $check->diagnosis_title }}</strong>
                </div>
            @empty
                <p class="quiet">No checks stored yet.</p>
            @endforelse
        </div>
    </article>
</div>
@endsection
