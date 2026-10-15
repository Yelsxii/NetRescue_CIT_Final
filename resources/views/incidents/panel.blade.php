@php
    $openedFact = \App\Support\CheckStory::focus($incident->code, $openingCheck, $warningMs) ?: $incident->plainDetail();
    $clearedFact = \App\Support\CheckStory::focus($incident->code, $closingCheck, $warningMs);
@endphp
<header class="record-head">
    <div>
        <p class="record-kicker">{{ $incident->reference }} · This computer</p>
        <h2 id="recordModalTitle">{{ $incident->plainProblem() }}</h2>
        <p class="record-meta">
            <x-status-pill :status="$incident->status" :label="$incident->status === 'resolved' ? 'Closed' : 'Open'" />
            <span>Opened {{ $incident->first_detected_at->format('g:i:s A') }}</span>
            @if($incident->resolved_at)<span>Closed {{ $incident->resolved_at->format('g:i:s A') }}</span>@endif
            <span>{{ $incident->durationLabel() }}</span>
        </p>
    </div>
</header>
@include('incidents.case')
