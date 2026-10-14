@props(['label', 'status' => null, 'value' => null, 'meta' => null, 'icon' => 'activity'])
@php
    $tone = match ($status) {
        true, 'healthy' => 'metric-good',
        false, 'critical' => 'metric-bad',
        'degraded' => 'metric-warn',
        default => 'metric-neutral',
    };
@endphp
<div class="metric-card {{ $tone }}">
    <div class="metric-icon"><x-icon :name="$icon" size="20" /></div>
    <div class="metric-copy">
        <span class="metric-label">{{ $label }}</span>
        <strong>{{ $value ?? 'Not checked' }}</strong>
        @if($meta)<small>{{ $meta }}</small>@endif
    </div>
</div>
