@extends('layouts.app', ['title' => 'NetRescue CIT · Review repair', 'pageTitle' => 'Review repair attempt'])

@section('content')
@php($guidance = \App\Support\RepairGuidance::for($incident))
<div class="page-fit incident-page">
    <article class="case-card repair-confirmation-page">
        <h1>{{ $guidance['confirmation_title'] }}</h1>
        <p class="quiet">The repair options changed or this request needs your confirmation. No repair command ran for this request.</p>
        <p>{{ $incident->reference }} · {{ $incident->plainProblem() }}</p>
        @if($guidance['previous_result'])
            <h2>Previous attempt</h2>
            <p>{{ $guidance['previous_result'] }}</p>
        @endif
        @if($incident->canRepair())
            <h2>What this attempt tries</h2>
            <p>{{ $guidance['purpose'] }}</p>
            <p class="quiet">{{ $guidance['limitation'] }}</p>
            <p>{{ $guidance['consequence'] }}</p>
            <p class="quiet">Another attempt is optional. NetRescue checks first, skips the command if already recovered, and verifies afterward. Recovery is not guaranteed.</p>
            <div class="modal-actions">
                <a class="button button-ghost" href="{{ $returnTo === 'dashboard' ? route('dashboard') : route('incidents.show', $incident) }}">Cancel</a>
                <x-repair-form :incident="$incident" :return-to="$returnTo" :confirmed="true" />
            </div>
        @else
            <p>No repair is currently available. Review the incident's latest result.</p>
            <a class="button button-ghost" href="{{ route('incidents.show', $incident) }}">Review incident</a>
        @endif
    </article>
</div>
@endsection
