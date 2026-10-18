@extends('layouts.app', ['title' => 'NetRescue CIT · Review repair', 'pageTitle' => 'Review repair attempt'])

@section('content')
@php($guidance = \App\Support\RepairGuidance::for($incident))
<div class="page-fit incident-page">
    <article class="case-card repair-confirmation-page">
        <h1>{{ $guidance['confirmation_title'] }}</h1>
        <p class="repair-card-notice">The repair options require review. No command was run for this request.</p>
        <p class="quiet">{{ $incident->reference }} · {{ $incident->plainProblem() }}</p>
        @if($guidance['previous_result'])
            <section class="repair-confirm-previous">
                <span class="repair-card-eyebrow">Previous attempt</span>
                <strong>{{ $guidance['previous_status'] }}</strong>
                <p>{{ $guidance['previous_summary'] }}</p>
            </section>
        @endif
        @if($incident->canRepair())
            <section class="repair-confirm-step">
                <span class="repair-card-eyebrow">Proposed action</span>
                <h2>{{ $guidance['action_title'] }}</h2>
                <p>{{ $guidance['purpose'] }}</p>
            </section>
            @if($guidance['attention'])<p class="repair-card-notice">{{ $guidance['attention'] }}</p>@endif
            <details class="repair-card-details">
                <summary>Limits and verification</summary>
                <p>{{ $guidance['limitation'] }}</p>
                <p class="quiet">Another attempt is optional. NetRescue checks first, skips the command if already recovered, and verifies afterward. Recovery is not guaranteed.</p>
            </details>
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
