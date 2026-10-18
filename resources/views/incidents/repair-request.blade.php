@php
    $verification = data_get($recovery->afterHealthCheck?->probe_meta, 'recovery_verification', []);
    $commandMs = $verification['command_ms'] ?? null;
    $verificationMs = $verification['elapsed_ms'] ?? null;
@endphp
<div class="request-evidence" data-recovery-id="{{ $recovery->id }}">
    <header class="request-evidence-head">
        <h3>{{ $recovery->action_label ?: 'Repair request' }}</h3>
        <p class="evidence-time">Request {{ $recovery->id }} · {{ $recovery->started_at?->format('M j, g:i:s A') ?: 'Time not recorded' }}</p>
    </header>
    <dl class="request-results">
        <div><dt>Command result</dt><dd>{{ in_array($recovery->execution_status, ['pending', null], true) ? 'Command result not recorded' : $recovery->executionLabel() }}</dd></div>
        <div><dt>Recovery result</dt><dd>{{ in_array($recovery->verification_status, ['pending', null], true) ? 'Verification result not recorded' : $recovery->verificationLabel() }}</dd></div>
    </dl>
    @if($recovery->error_message)
        <p class="command-error">The command reported an error. The recorded message is available under Technical details.</p>
    @endif

    <section class="request-comparison" aria-label="Before and after measurements">
        <h3>Measurement comparison</h3>
        <div class="reading-comparison">
            <x-reading-summary :check="$recovery->beforeHealthCheck" label="Before: initial check" :warning="$warningMs" />
            <x-reading-summary :check="$recovery->afterHealthCheck" label="After: final check" :warning="$warningMs" />
        </div>
    </section>

    @if(is_numeric($commandMs) || is_numeric($verificationMs))
        <section class="request-timing">
            <h3>Recorded durations</h3>
            <dl class="request-results">
                @if(is_numeric($commandMs))<div><dt>Command duration</dt><dd>{{ number_format($commandMs / 1000, 2) }} seconds</dd></div>@endif
                @if(is_numeric($verificationMs))<div><dt>Verification duration</dt><dd>{{ number_format($verificationMs / 1000, 2) }} seconds</dd></div>@endif
            </dl>
            <p class="quiet">Verification includes the follow-up measurements. The initial check is separate.</p>
        </section>
    @endif

    @include('incidents.verification-readings', ['recovery' => $recovery])

    <details class="evidence-technical">
        <summary>Technical details</summary>
        <dl class="technical-facts">
            <div><dt>Command exit code</dt><dd>{{ $recovery->command_exit_code ?? 'Not recorded' }}</dd></div>
            @if(is_numeric($limit = $verification['command_timeout_seconds'] ?? null))<div><dt>Command time limit</dt><dd>{{ number_format($limit) }} seconds</dd></div>@endif
            @if(isset($verification['attempt']))
                <div><dt>Verification readings</dt><dd>
                    {{ $verification['attempt'] }}
                    @if(is_numeric($verification['maximum_attempts'] ?? null))
                        of {{ $verification['maximum_attempts'] }} allowed
                    @endif
                </dd></div>
            @endif
            @if(($verification['retry_policy'] ?? null) === 'reconnect_window')<div><dt>Reconnect window</dt><dd>Up to {{ number_format($verification['reconnect_window_seconds'] ?? 0) }} seconds</dd></div>@endif
        </dl>
        @if(isset($verification['attempt']))<p class="quiet">Follow-up readings do not repeat the repair command.</p>@endif
        @if(($verification['stop_reason'] ?? null) === 'reconnect_window_elapsed')<p class="reading-retry-note">The reconnect window ended before a settled result was obtained. Recovery remains unverified.</p>@endif
        <div class="reading-comparison configuration-comparison">
            @foreach(['Before' => $recovery->beforeHealthCheck, 'After' => $recovery->afterHealthCheck] as $stage => $measurement)
                <section class="configuration-stage">
                    <h4>{{ $stage }} configuration</h4>
                    @if($measurement)
                        <dl class="technical-facts">
                            <div><dt>IPv4 address</dt><dd>{{ $measurement->local_ip ?: 'Not reported' }}</dd></div>
                            <div><dt>Default gateway</dt><dd>{{ $measurement->gateway_ip ?: 'Not reported' }}</dd></div>
                            @if(filled($windowsStatus = data_get($measurement->probe_meta, 'windows_adapter_status')))<div><dt>Windows adapter status</dt><dd>{{ $windowsStatus }}</dd></div>@endif
                            @if(is_numeric($duration = data_get($measurement->probe_meta, 'timings_ms.total')))<div><dt>Reading duration</dt><dd>{{ number_format($duration / 1000, 2) }} seconds</dd></div>@endif
                        </dl>
                        @if(! $measurement->readingFinished())
                            @foreach((array) data_get($measurement->probe_meta, 'errors', []) as $readingError)<p class="command-error">Reading error: {{ $readingError }}</p>@endforeach
                        @endif
                    @else
                        <p class="quiet">Configuration was not recorded.</p>
                    @endif
                </section>
            @endforeach
        </div>
        @if($recovery->error_message)<p class="command-error">{{ $recovery->error_message }}</p>@endif
        @if($recovery->output_excerpt)
            <details class="evidence-output"><summary>Recorded command output</summary><pre class="command-output">{{ $recovery->output_excerpt }}</pre></details>
        @endif
    </details>
</div>
