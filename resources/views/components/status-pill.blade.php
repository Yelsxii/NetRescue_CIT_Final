@props(['status' => 'neutral', 'label' => null])
@php
    $normalized = strtolower((string) $status);
    $class = match ($normalized) {
        'healthy', 'resolved', 'success', 'none' => 'pill pill-success',
        'degraded', 'warning', 'moderate', 'low' => 'pill pill-warning',
        'critical', 'danger', 'high', 'active', 'offline' => 'pill pill-danger',
        default => 'pill pill-neutral',
    };
@endphp
<span {{ $attributes->merge(['class' => $class]) }}><span class="pill-dot"></span>{{ $label ?? ucfirst($normalized) }}</span>
