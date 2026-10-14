@props(['points', 'warning' => 100, 'only' => null])
@php
    $rows = collect($points)->values();
    $warning = (float) $warning;
    $catalog = [
        'gateway' => ['name' => 'Gateway', 'class' => 'series-gateway', 'swatch' => 'is-gateway', 'ok' => 'gateway_ok'],
        'internet' => ['name' => 'Internet', 'class' => 'series-internet', 'swatch' => 'is-internet', 'ok' => 'internet_ok'],
        'dns' => ['name' => 'Name lookup', 'class' => 'series-dns', 'swatch' => 'is-dns', 'ok' => 'dns_ok'],
    ];
    $keys = collect($only ?: array_keys($catalog))->filter(fn ($key) => isset($catalog[$key]))->values();
    $series = $keys->map(function ($key) use ($rows, $catalog) {
        return [
            'key' => $key,
            'name' => $catalog[$key]['name'],
            'class' => $catalog[$key]['class'],
            'values' => $rows->map(fn ($row) => is_numeric($row->{$key} ?? null) ? round((float) $row->{$key}, 2) : null)->all(),
        ];
    })->all();
    $hits = $rows->map(function ($row) use ($keys, $catalog, $warning) {
        return [
            'time' => $row->checked_at->format('g:i A'),
            'source' => \App\Support\CheckStory::sourceLabel($row->source ?? null),
            'title' => $row->title ?? 'Stored check',
            'rows' => $keys->map(function ($key) use ($row, $catalog, $warning) {
                $mark = \App\Support\CheckStory::mark($row->{$catalog[$key]['ok']} ?? null, $row->{$key} ?? null, (int) $warning);

                return [
                    'name' => $catalog[$key]['name'],
                    'label' => $mark['label'],
                    'detail' => $mark['detail'],
                    'code' => $mark['code'],
                ];
            })->all(),
        ];
    })->all();
    $latest = $rows->last();
    $latestMarks = $latest ? $keys->map(function ($key) use ($latest, $catalog, $warning) {
        return [
            'swatch' => $catalog[$key]['swatch'],
            'name' => $catalog[$key]['name'],
            'mark' => \App\Support\CheckStory::mark($latest->{$catalog[$key]['ok']} ?? null, $latest->{$key} ?? null, (int) $warning),
        ];
    }) : collect();
    $payload = [
        'warning' => $warning,
        'count' => $rows->count(),
        'series' => $series,
        'hits' => $hits,
    ];
@endphp
<div class="response-chart">
    @if($rows->isNotEmpty())
        <div class="chart-legend">
            @foreach($latestMarks as $item)
                <span><i class="{{ $item['swatch'] }}"></i> {{ $item['name'] }} <b class="mark-{{ $item['mark']['code'] }}">{{ $item['mark']['detail'] }}</b></span>
            @endforeach
        </div>
        <div class="plot-frame">
            <div class="plot-scale" aria-hidden="true"></div>
            <svg role="img" aria-label="{{ $latestMarks->pluck('name')->join(', ') }}. Hover for a check."></svg>
            <script type="application/json" class="chart-data">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        </div>
        <div class="chart-tip" hidden></div>
        <div class="trend-caption">
            <span>{{ $rows->first()->checked_at->format('g:i A') }}</span>
            <span>Hover a point</span>
            <span>{{ $rows->last()->checked_at->format('g:i A') }}</span>
        </div>
    @else
        <div class="empty-chart">
            <strong>No response times stored yet</strong>
            <span>Points appear after the Central computer is checked.</span>
        </div>
    @endif
</div>
