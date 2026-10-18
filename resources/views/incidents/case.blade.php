@php
    $story = $incident->story();
    $attempt = $incident->latestRecovery();
    $needsResultCheck = $incident->is_active && $attempt
        && in_array($attempt->verification_status, ['unread', 'adapter_changed'], true);
    $previousAttempts = $attempt
        ? $incident->recoveryActions()->where('id', '!=', $attempt->id)->with(['beforeHealthCheck', 'afterHealthCheck'])->get()
        : collect();
    $closingRestatesRepair = ! $incident->is_active
        && $closingCheck
        && (bool) $attempt?->success
        && ! in_array($incident->resolution_kind, ['already_recovered', 'superseded'], true)
        && $attempt->beforeHealthCheck?->diagnosis_code !== 'healthy';
    $closingFact = $closingCheck
        ? \App\Support\CheckStory::focus($incident->code, $closingCheck, $warningMs)
        : null;
    $closingFactIsGeneric = $closingCheck && $closingFact === $closingCheck->diagnosis_summary;
@endphp
<section class="detail-stack record-grid">
    <article class="case-card">
        <h2>Detected condition</h2>
        @if($openingCheck)
            <p class="case-when">{{ \App\Support\CheckStory::sourceLabel($openingCheck) }} · {{ $openingCheck->checked_at->format('g:i:s A') }}</p>
            <p class="case-fact">{{ $openedFact }}</p>
            @if($incident->code === 'dhcp_issue')
                <p class="quiet">This is an automatic fallback address. No normal DHCP address was obtained in this reading.</p>
            @endif
            <p class="quiet">Gateway {{ $openingCheck->gateway_ip ?: 'not reported' }} · Adapter {{ $openingCheck->interface_name ?: 'not reported' }}</p>
        @else
            <p class="case-fact">{{ $incident->plainDetail() }}</p>
            <p class="quiet">The exact opening measurement is not available for this record.</p>
        @endif
    </article>

    <article class="case-card case-repair-card">
        <x-repair-guidance :incident="$incident" mode="incident" :needs-result-check="$needsResultCheck" :story-repair="$story['repair']" />
    </article>

    <article class="case-card">
        <h2>{{ $incident->is_active ? 'Current status' : ($closingRestatesRepair ? 'Verified outcome' : 'Resolution') }}</h2>
        @unless($closingRestatesRepair)
            <p class="case-fact" data-story-resolution>{{ $story['resolution'] }}</p>
        @endunless
        @if(! $incident->is_active && $attempt?->verification_status === 'unread' && $attempt->afterHealthCheck && $closingCheck && $attempt->after_health_check_id !== $closingCheck->id)
            <p class="quiet">Automatic verification ended at {{ $attempt->afterHealthCheck->checked_at->format('g:i:s A') }} without a completed reading. The separate closing measurement is shown below.</p>
        @endif
        @if(! $incident->is_active && $attempt?->beforeHealthCheck?->diagnosis_code === 'healthy' && $attempt->before_health_check_id !== $closingCheck?->id)
            <p class="case-when">Check before repair · {{ $attempt->beforeHealthCheck->checked_at->format('g:i:s A') }}</p>
            <p class="quiet">Already healthy: IPv4 {{ $attempt->beforeHealthCheck->local_ip ?: 'not reported' }} · Gateway {{ $attempt->beforeHealthCheck->gateway_ip ?: 'not reported' }}.</p>
        @endif
        @if(! $incident->is_active && $closingCheck)
            <p class="case-when">{{ \App\Support\CheckStory::sourceLabel($closingCheck) }} · {{ $closingCheck->checked_at->format('g:i:s A') }}</p>
            @if($closingRestatesRepair)
                <p class="case-fact">{{ $closingCheck->diagnosis_title ?: 'Closing measurement recorded' }}</p>
            @endif
            @if($closingFact && ! $closingFactIsGeneric)
                <p class="quiet">{{ $closingFact }}</p>
            @endif
            <p class="quiet">Gateway {{ $closingCheck->gateway_ip ?: 'not reported' }}@if(! $closingRestatesRepair && filled($closingCheck->diagnosis_title)) · {{ $closingCheck->diagnosis_title }}@endif</p>
        @endif
    </article>
</section>
@if($attempt)
    @php($latestResult = \App\Support\RepairGuidance::result($attempt))
    <details class="repair-evidence latest-request">
        <summary><span>Latest repair details</span><small>{{ $attempt->action_label }} · {{ $latestResult['status'] }}</small></summary>
        @include('incidents.repair-request', ['recovery' => $attempt])
    </details>
@endif
@if($previousAttempts->isNotEmpty())
    <details class="repair-evidence repair-history">
        <summary>Earlier repair attempts ({{ $previousAttempts->count() }})</summary>
        @foreach($previousAttempts as $previous)
            @php($previousResult = \App\Support\RepairGuidance::result($previous))
            <details class="repair-request-record">
                <summary>{{ $previous->action_label }} · {{ $previousResult['status'] }}<small>{{ $previous->started_at?->format('M j, g:i:s A') ?: 'Time not recorded' }}</small></summary>
                @include('incidents.repair-request', ['recovery' => $previous])
            </details>
        @endforeach
    </details>
@endif
