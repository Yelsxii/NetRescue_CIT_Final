@extends('layouts.app', ['title' => 'NetRescue CIT · '.$incident->reference, 'pageTitle' => 'Incident '.$incident->reference])

@section('content')
@php
    $openedFact = \App\Support\CheckStory::focus($incident->code, $openingCheck, $warningMs) ?: $incident->plainDetail();
@endphp
<div class="page-fit incident-page">
    <header class="identity">
        <div>
            <a class="back-link" href="{{ route('history.index') }}">Incidents</a>
            <h1>{{ $incident->plainProblem() }}</h1>
            <p>
                <x-status-pill :status="$incident->status" :label="$incident->status === 'resolved' ? 'Closed' : 'Open'" />
                <span>Opened {{ $incident->first_detected_at->format('g:i:s A') }}</span>
                @if($incident->resolved_at)<span>Closed {{ $incident->resolved_at->format('g:i:s A') }}</span>@endif
                <span>{{ $incident->reference }}</span>
            </p>
        </div>
    </header>

    @include('incidents.case')
</div>
@endsection
