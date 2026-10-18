@php
    $story = $incident->story();
    $attempt = $incident->latestRecovery();
    $repairGuidance = \App\Support\RepairGuidance::for($incident);
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
        <div class="case-card-head">
            <h2>{{ $attempt ? 'Latest repair request and result' : 'Repair request and result' }}</h2>
            @if($needsResultCheck)
                <form method="POST" action="{{ route('incidents.retest', $incident) }}" class="js-busy-form case-repair-action" data-busy-text="Checking this incident's adapter again…">
                    @csrf
                    <button class="button button-primary" type="submit">Check result</button>
                </form>
            @elseif($incident->canRepair())
                <x-repair-form :incident="$incident" class="case-repair-action" />
            @endif
        </div>
        <p class="case-fact" data-story-repair>{{ $story['repair'] }}</p>
        @if($needsResultCheck)
            <p class="quiet">Check result reads this incident's adapter again without repeating the repair command.</p>
        @elseif($incident->canRepair())
            <p class="quiet">{{ $repairGuidance['purpose'] }}</p>
            <p class="quiet">{{ $repairGuidance['limitation'] }}</p>
            <p class="quiet">{{ $repairGuidance['consequence'] }}</p>
            <p class="quiet">Available action: <code>{{ $incident->repairCommand() }}</code></p>
            <p class="quiet">{{ $repairGuidance['is_follow_up'] ? 'Another attempt is optional and requires confirmation.' : 'Checks first and runs this action only if still needed.' }} Verification is automatic. Recovery is not guaranteed; scheduled checks may stay paused.</p>
        @elseif($incident->is_active)
            <p class="quiet">No repair command is available for this condition.</p>
        @endif
        @if($incident->is_active && $incident->nextStep())
            <p class="next-step"><b>Next step:</b> {{ $incident->nextStep() }}</p>
        @endif
    </article>

    <article class="case-card">
        <h2>{{ $incident->is_active ? 'Current outcome' : ($closingRestatesRepair ? 'Closing check' : 'Resolution observed') }}</h2>
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
    <details class="repair-evidence">
        <summary>Latest request: measurements and command details</summary>
        <p class="case-when">Request recorded {{ $attempt->started_at?->format('M j, g:i:s A') }}</p>
        <p class="quiet">Command: {{ $attempt->executionLabel() }}. Outcome: {{ $attempt->verificationLabel() }}.</p>
        @if($attempt->command_exit_code !== null)<p class="quiet">Command exit code: {{ $attempt->command_exit_code }}</p>@endif
        @if($verification = data_get($attempt->afterHealthCheck?->probe_meta, 'recovery_verification'))
            @if(($verification['retry_policy'] ?? null) === 'reconnect_window')
                <p class="quiet">Automatic verification: {{ $verification['attempt'] }} readings; reconnect window up to {{ number_format($verification['reconnect_window_seconds'], 0) }} seconds. The repair command was not repeated.</p>
                <p class="quiet">The window limits retries; a check already running is allowed to finish.</p>
            @else
                <p class="quiet">Automatic verification: {{ $verification['attempt'] }} of {{ $verification['maximum_attempts'] }} allowed readings. The repair command was not repeated.</p>
            @endif
            @if(($verification['stop_reason'] ?? null) === 'reconnect_window_elapsed')
                <p class="reading-retry-note">The reconnect window ended before a settled result was obtained. Recovery remains unverified.</p>
            @endif
            @if(is_numeric($elapsed = $verification['elapsed_ms'] ?? null))
                <p class="quiet">Verification elapsed: {{ number_format($elapsed / 1000, 2) }} seconds.</p>
            @endif
            @if(is_numeric($allowance = $verification['command_timeout_seconds'] ?? null))
                <p class="quiet">Command time limit: {{ number_format($allowance, 0) }} seconds.</p>
            @endif
            @if(is_numeric($commandMs = $verification['command_ms'] ?? null))
                <p class="quiet">Command duration: {{ number_format($commandMs / 1000, 2) }} seconds.</p>
            @endif
        @endif
        @foreach(['Before' => $attempt->beforeHealthCheck, 'After' => $attempt->afterHealthCheck] as $stage => $evidence)
            @if($stage === 'After')
                @include('incidents.verification-readings', ['recovery' => $attempt])
            @endif
            @if($evidence)
                <p><b>{{ $stage }}:</b> {{ $evidence->checked_at->format('g:i:s A') }} · {{ $evidence->diagnosis_title ?: $evidence->diagnosis_code }} · IPv4 {{ $evidence->local_ip ?: 'None' }} · Gateway {{ $evidence->gateway_ip ?: 'None' }} · {{ $evidence->interface_name ?: 'Unknown adapter' }}</p>
                @if(is_numeric($duration = data_get($evidence->probe_meta, 'timings_ms.total')))
                    <p class="quiet">Reading duration: {{ number_format($duration / 1000, 2) }} seconds.</p>
                @endif
                @if(filled($windowsStatus = data_get($evidence->probe_meta, 'windows_adapter_status')))
                    <p class="quiet">Windows adapter status: {{ $windowsStatus }}</p>
                @endif
                @if(! $evidence->readingFinished())
                    @foreach((array) data_get($evidence->probe_meta, 'errors', []) as $readingError)
                        <p class="command-error">Reading error: {{ $readingError }}</p>
                    @endforeach
                @endif
            @endif
        @endforeach
        @if($attempt->error_message)<p class="command-error">{{ $attempt->error_message }}</p>@endif
        @if($attempt->output_excerpt)<p class="quiet"><b>Stored command output (excerpt)</b></p><pre class="command-output">{{ $attempt->output_excerpt }}</pre>@endif
    </details>
@endif
@if($previousAttempts->isNotEmpty())
    <details class="repair-evidence repair-history">
        <summary>Previous repair requests ({{ $previousAttempts->count() }})</summary>
        @foreach($previousAttempts as $previous)
            <article class="repair-request-record" data-recovery-id="{{ $previous->id }}">
                <p class="case-when"><b>{{ $previous->started_at?->format('M j, g:i:s A') }} · {{ $previous->action_label }}</b></p>
                <p>{{ $previous->outcomeSummary() }}</p>
                <p class="quiet">Command: {{ $previous->execution_status === 'pending' || $previous->execution_status === null ? 'Command result not recorded' : $previous->executionLabel() }}. Outcome: {{ $previous->verification_status === 'pending' || $previous->verification_status === null ? 'Verification result not recorded' : $previous->verificationLabel() }}.</p>
                @if($previous->command_exit_code !== null)<p class="quiet">Command exit code: {{ $previous->command_exit_code }}</p>@endif
                @foreach(['Before' => $previous->beforeHealthCheck, 'After' => $previous->afterHealthCheck] as $stage => $evidence)
                    @if($stage === 'After')
                        @include('incidents.verification-readings', ['recovery' => $previous])
                    @endif
                    @if($evidence)
                        <p><b>{{ $stage }}:</b> {{ $evidence->checked_at->format('M j, g:i:s A') }} · {{ $evidence->diagnosis_title ?: $evidence->diagnosis_code }} · IPv4 {{ $evidence->local_ip ?: 'None' }} · {{ $evidence->interface_name ?: 'Unknown adapter' }}</p>
                        @if(! $evidence->readingFinished())
                            @foreach((array) data_get($evidence->probe_meta, 'errors', []) as $readingError)
                                <p class="command-error">Reading error: {{ $readingError }}</p>
                            @endforeach
                        @endif
                    @endif
                @endforeach
                @if($previous->error_message)<p class="command-error">{{ $previous->error_message }}</p>@endif
                @if($previous->output_excerpt)<p class="quiet"><b>Stored command output (excerpt)</b></p><pre class="command-output">{{ $previous->output_excerpt }}</pre>@endif
            </article>
        @endforeach
    </details>
@endif
