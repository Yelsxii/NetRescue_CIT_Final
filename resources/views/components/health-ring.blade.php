@props(['score' => null, 'status' => 'unknown'])
@php
    $value = is_numeric($score) ? max(0, min(100, (int) $score)) : 0;
    $circumference = 2 * pi() * 52;
    $offset = $circumference * (1 - ($value / 100));
    $tone = match ($status) {
        'healthy' => 'ring-success',
        'degraded' => 'ring-warning',
        'critical' => 'ring-danger',
        default => 'ring-muted',
    };
@endphp
<div class="health-ring {{ $tone }}">
    <svg viewBox="0 0 120 120" aria-hidden="true">
        <circle class="health-ring-track" cx="60" cy="60" r="52"/>
        <circle class="health-ring-value" cx="60" cy="60" r="52" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"/>
    </svg>
    <div class="health-ring-copy">
        <strong>{{ is_numeric($score) ? $value.'%' : '—' }}</strong>
        <span>{{ is_numeric($score) ? strtoupper($status) : 'NO DATA' }}</span>
    </div>
</div>
