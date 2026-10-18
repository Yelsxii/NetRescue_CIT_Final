@php
    $laterReadingFinished = (bool) $recovery->afterHealthCheck?->readingFinished();
    $earlierReadings = $recovery->earlierVerificationChecks();
@endphp
@if($earlierReadings->isNotEmpty())
<details class="verification-history">
    <summary>Earlier verification readings ({{ $earlierReadings->count() }})</summary>
    <p class="quiet">These readings belong to this request. The final measurement is shown in the comparison above.</p>
    <ol class="verification-list">
@foreach($earlierReadings as $index => $earlier)
    <li><details class="repair-request-record verification-reading" data-verification-check-id="{{ $earlier->id }}">
        <summary>Earlier automatic reading {{ data_get($earlier->probe_meta, 'recovery_verification.attempt', $index + 1) }}: {{ $earlier->checked_at->format('g:i:s A') }} · {{ $earlier->diagnosis_title ?: $earlier->diagnosis_code }}</summary>
        @if(! $earlier->readingFinished() && $laterReadingFinished)
            <p class="reading-retry-note">
                @if($recovery->verification_status === 'verified')
                    This earlier reading did not finish. A later automatic reading verified recovery; this warning does not change the verified result.
                @elseif($recovery->verification_status === 'observed_recovery')
                    This earlier reading did not finish. A later reading observed recovery; the repair remains unverified.
                @else
                    This earlier reading did not finish. A later reading completed; see the final outcome below.
                @endif
            </p>
        @endif
        <p class="quiet">Measured adapter: {{ $earlier->interface_name ?: 'Not obtained' }} · Requested adapter: {{ data_get($earlier->probe_meta, 'requested_interface_name') ?: 'Not recorded' }}</p>
        @if(filled($windowsStatus = data_get($earlier->probe_meta, 'windows_adapter_status')))
            <p class="quiet">Windows adapter status: {{ $windowsStatus }}</p>
        @endif
        @if(is_numeric($duration = data_get($earlier->probe_meta, 'timings_ms.total')))
            <p class="quiet">Reading duration: {{ number_format($duration / 1000, 2) }} seconds.</p>
        @endif
        @if(is_numeric($budget = data_get($earlier->probe_meta, 'recovery_verification.configuration_timeout_seconds')))
            <p class="quiet">Configuration time limit: {{ number_format($budget, 2) }} seconds.</p>
        @endif
        @foreach((array) data_get($earlier->probe_meta, 'errors', []) as $readingError)
            <p class="{{ $laterReadingFinished ? 'quiet' : 'command-error' }}">Reading error: {{ $readingError }}</p>
        @endforeach
    </details></li>
@endforeach
    </ol>
</details>
@endif
