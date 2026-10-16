@extends('layouts.app', ['title' => 'NetRescue CIT · Incidents', 'pageTitle' => 'Incidents'])

@section('content')
<div class="page-fit">
    <header class="page-head">
        <div>
            <h1>Incidents</h1>
            <p>Incidents on this computer, with observed outcomes and repair verification.</p>
        </div>
        <form method="GET" class="filter-row">
            <select name="status" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Open</option>
                <option value="resolved" @selected(request('status') === 'resolved')>Closed</option>
            </select>
            <select name="severity" aria-label="Filter by severity">
                <option value="">Any severity</option>
                @foreach(['critical','high','moderate','low'] as $severity)
                    <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ ucfirst($severity) }}</option>
                @endforeach
            </select>
            <button class="button button-secondary" type="submit">Apply</button>
        </form>
    </header>

    <section class="table-panel log-panel">
        <p class="table-scroll-hint"><x-icon name="chevron" size="14" /> Scroll to see all columns</p>
        <div class="table-scroll" role="region" aria-label="Incident history" tabindex="0">
            <table class="data-table incident-table">
                <colgroup><col style="width:23%"><col style="width:17%"><col style="width:12%"><col style="width:38%"><col style="width:10%"></colgroup>
                <thead>
                    <tr>
                        <th scope="col">Incident</th>
                        <th scope="col">Started</th>
                        <th scope="col">Duration</th>
                        <th scope="col">Outcome and repair</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $incident)
                        @php($story = $incident->story())
                        <tr data-href="{{ route('incidents.show', $incident) }}">
                            <td><a class="incident-link" data-incident-link href="{{ route('incidents.show', $incident) }}">{{ $incident->plainProblem() }}</a></td>
                            <td>{{ $incident->first_detected_at->format('M j, g:i:s A') }}</td>
                            <td>{{ $incident->durationLabel() }}</td>
                            <td class="incident-resolution"><p data-story-resolution>{{ $story['resolution'] }}</p><p class="quiet" data-story-repair>{{ $story['repair'] }}</p></td>
                            <td><x-status-pill :status="$incident->status" :label="$incident->status === 'resolved' ? 'Closed' : 'Open'" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No incident matches this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($incidents->hasPages())
            <div class="pagination-wrap">{{ $incidents->links('partials.pagination') }}</div>
        @endif
    </section>
</div>
@endsection
