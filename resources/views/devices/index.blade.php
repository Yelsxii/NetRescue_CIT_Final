@extends('layouts.app', ['title' => 'NetRescue CIT · Devices', 'pageTitle' => 'Managed Devices'])

@section('content')
<div class="page-fit">
    <header class="page-head">
        <div>
            <h1>Device List</h1>
            <p>Agent-free, administrator-first monitoring. Status comes from the latest stored check.</p>
        </div>
        <form method="GET" class="filter-row">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Name or address" aria-label="Search devices">
            <select name="status" aria-label="Filter by status">
                <option value="">All states</option>
                <option value="healthy" @selected(request('status') === 'healthy')>Healthy</option>
                <option value="degraded" @selected(request('status') === 'degraded')>Degraded</option>
                <option value="critical" @selected(request('status') === 'critical')>Critical</option>
                <option value="offline" @selected(request('status') === 'offline')>Offline</option>
                <option value="unknown" @selected(request('status') === 'unknown')>Waiting</option>
            </select>
            <button class="button button-secondary" type="submit">Filter</button>
        </form>
    </header>

    @if($unrecognized->isNotEmpty())
        <section class="seen-row">
            <h2>Seen, not recognized</h2>
            @foreach($unrecognized as $device)
                <form method="POST" action="{{ route('devices.recognize', $device) }}" class="seen-item">
                    @csrf
                    <span>{{ $device->last_ip }}</span>
                    <button class="button button-secondary" type="submit">Recognize</button>
                </form>
            @endforeach
        </section>
    @endif

    <section class="table-panel log-panel">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Computer</th>
                        <th>Status</th>
                        <th>Problem</th>
                        <th>Last seen</th>
                        <th>Address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                        <tr data-href="{{ route('devices.show', $device) }}">
                            <td>
                                <strong>{{ $device->display_name }}</strong>
                                @if($device->is_local)<em class="row-tag">Central</em>@endif
                            </td>
                            <td><x-status-pill :status="$device->status" :label="match($device->status) { 'offline' => 'Offline', 'unknown' => 'Waiting', 'degraded' => 'Degraded', 'critical' => 'Critical', 'healthy' => 'Healthy', default => ucfirst($device->status) }" /></td>
                            <td>{{ $device->attentionLine() }}</td>
                            <td>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                            <td>{{ $device->last_ip ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No computer matches this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
