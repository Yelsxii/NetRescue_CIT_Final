@foreach($recovery->earlierVerificationChecks() as $index => $earlier)
    <article class="repair-request-record verification-reading" data-verification-check-id="{{ $earlier->id }}">
        <p><b>Earlier automatic reading {{ data_get($earlier->probe_meta, 'recovery_verification.attempt', $index + 1) }}:</b> {{ $earlier->checked_at->format('g:i:s A') }} · {{ $earlier->diagnosis_title ?: $earlier->diagnosis_code }}</p>
        <p class="quiet">Measured adapter: {{ $earlier->interface_name ?: 'Not obtained' }} · Requested adapter: {{ data_get($earlier->probe_meta, 'requested_interface_name') ?: 'Not recorded' }}</p>
        @if(is_numeric($duration = data_get($earlier->probe_meta, 'timings_ms.total')))
            <p class="quiet">Reading duration: {{ number_format($duration / 1000, 2) }} seconds.</p>
        @endif
        @if(is_numeric($budget = data_get($earlier->probe_meta, 'recovery_verification.configuration_timeout_seconds')))
            <p class="quiet">Configuration timeout: {{ number_format($budget, 2) }} seconds.</p>
        @endif
        @foreach((array) data_get($earlier->probe_meta, 'errors', []) as $readingError)
            <p class="command-error">Reading error: {{ $readingError }}</p>
        @endforeach
    </article>
@endforeach
