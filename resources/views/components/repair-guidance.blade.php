@props([
    'incident',
    'mode' => 'dashboard',
    'needsResultCheck' => false,
    'differentAdapter' => false,
    'readingAdapter' => null,
    'storyRepair' => null,
])
@php
    $guidance = \App\Support\RepairGuidance::for($incident);
    $previous = $incident->latestRecovery();
    $targetAdapter = $incident->latestHealthCheck?->interface_name;
    $reviewResult = $mode === 'dashboard' && ($differentAdapter || ($previous && in_array($previous->verification_status, ['pending', 'unread', 'adapter_changed'], true)));
    $canRepair = $incident->canRepair() && ! $needsResultCheck && ! $reviewResult;
    $heading = $reviewResult ? 'Repair result needs review'
        : ($needsResultCheck ? 'Verify the current condition'
        : ($canRepair ? $guidance['action_title']
        : ($incident->is_active ? $guidance['manual_title'] : 'Latest repair result')));
    $hasDetails = $canRepair || filled($storyRepair);
@endphp
<div {{ $attributes->class(['repair-card']) }} data-repair-target="{{ $targetAdapter }}">
    <header class="repair-card-heading">
        <p class="repair-card-eyebrow">{{ $mode === 'incident' ? ($previous ? 'Latest repair request and result' : 'Repair guidance') : ($canRepair ? ($previous ? 'Another repair option' : 'Repair option') : 'Next step') }}</p>
        @if($mode === 'incident')
            <h2>{{ $heading }}</h2>
        @else
            <h3>{{ $heading }}</h3>
        @endif
    </header>

    @if($previous)
        <div class="repair-card-result">
            <strong>{{ $guidance['previous_status'] }}</strong>
            <p>{{ $guidance['previous_summary'] }}</p>
        </div>
    @endif

    @if($differentAdapter)
        <p class="repair-card-notice">The latest completed reading is for {{ $readingAdapter ?: 'another adapter' }}. It does not verify {{ $targetAdapter ? 'this '.$targetAdapter.' incident' : 'this incident' }}.</p>
        <p class="repair-card-summary">{{ $incident->reference }} · {{ $targetAdapter ?: 'Unknown adapter' }}</p>
    @elseif($needsResultCheck)
        <p class="repair-card-summary">Check this incident's adapter again without repeating the repair command.</p>
    @elseif($reviewResult)
        <p class="repair-card-summary">Review the saved result before requesting another repair.</p>
    @elseif($canRepair)
        <p class="repair-card-summary">{{ $guidance['purpose'] }}</p>
        @if(filled($guidance['attention']))
            <p class="repair-card-notice">{{ $guidance['attention'] }}</p>
        @endif
    @elseif($incident->is_active)
        <p class="repair-card-summary">{{ $guidance['manual_step'] }}</p>
    @elseif(! $previous)
        <p class="repair-card-summary">This incident is closed. No repair was requested.</p>
    @endif

    @if($hasDetails)
        <section class="repair-card-details repair-card-context" aria-label="{{ $canRepair ? 'Action details' : 'Result details' }}">
            @if($canRepair)
                <p>{{ $guidance['limitation'] }}</p>
                <p>NetRescue checks the condition first, runs this action only if needed, and verifies the result automatically. Recovery is not guaranteed.</p>
                @if($mode === 'incident')
                    <p>Command: <code>{{ $incident->repairCommand() }}</code></p>
                @endif
            @endif
            @if(filled($storyRepair))
                <p data-story-repair>{{ $storyRepair }}</p>
            @endif
        </section>
    @endif

    @if($reviewResult || $needsResultCheck || $canRepair || $mode === 'dashboard')
        <footer class="repair-card-actions">
            @if($mode === 'dashboard' && ! $reviewResult)
                <a href="{{ route('incidents.show', $incident) }}">View incident</a>
            @endif
            @if($reviewResult)
                <a class="button button-primary repair-review" href="{{ route('incidents.show', $incident) }}">Review result</a>
            @elseif($needsResultCheck)
                <form method="POST" action="{{ route('incidents.retest', $incident) }}" class="js-busy-form case-repair-action" data-busy-text="Checking this incident's adapter again…">
                    @csrf
                    <button class="button button-primary" type="submit">Check result</button>
                </form>
            @elseif($canRepair)
                <x-repair-form :incident="$incident" :return-to="$mode === 'dashboard' ? 'dashboard' : null" class="case-repair-action" />
            @endif
        </footer>
    @endif
</div>
