@props(['trend'])
@php
    $points = collect($trend)->values();
    $width = 1000;
    $height = 250;
    $padX = 38;
    $padY = 28;
    $usableW = $width - ($padX * 2);
    $usableH = $height - ($padY * 2);
    $count = max(1, $points->count() - 1);
    $coords = $points->map(function ($row, $index) use ($padX, $padY, $usableW, $usableH, $count) {
        $x = $padX + (($index / $count) * $usableW);
        $score = max(0, min(100, (int) $row->health_score));
        $y = $padY + ((100 - $score) / 100 * $usableH);
        return ['x' => round($x, 2), 'y' => round($y, 2), 'score' => $score, 'row' => $row];
    });
    $line = $coords->map(fn($p) => $p['x'].','.$p['y'])->implode(' ');
    $area = $coords->isNotEmpty()
        ? $padX.','.($height-$padY).' '.$line.' '.($width-$padX).','.($height-$padY)
        : '';
@endphp
<div class="trend-chart">
    @if($points->isNotEmpty())
        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="Network health score trend">
            <defs>
                <linearGradient id="healthArea" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="currentColor" stop-opacity=".18"/>
                    <stop offset="100%" stop-color="currentColor" stop-opacity=".01"/>
                </linearGradient>
            </defs>
            @foreach([100,75,50,25,0] as $tick)
                @php($y = $padY + ((100-$tick)/100*$usableH))
                <line class="trend-grid" x1="{{ $padX }}" x2="{{ $width-$padX }}" y1="{{ $y }}" y2="{{ $y }}" />
                <text class="trend-axis" x="4" y="{{ $y+4 }}">{{ $tick }}</text>
            @endforeach
            <polygon class="trend-area" points="{{ $area }}" fill="url(#healthArea)"/>
            <polyline class="trend-line" points="{{ $line }}"/>
            @foreach($coords as $p)
                <circle class="trend-point" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="5">
                    <title>{{ $p['row']->checked_at->format('M j, g:i A') }} · {{ $p['score'] }}% · {{ $p['row']->diagnosis_title }}</title>
                </circle>
            @endforeach
        </svg>
        <div class="trend-caption">
            <span>{{ $points->first()->checked_at->format('g:i A') }}</span>
            <span>{{ $points->count() }} stored point{{ $points->count() === 1 ? '' : 's' }}</span>
            <span>{{ $points->last()->checked_at->format('g:i A') }}</span>
        </div>
    @else
        <div class="empty-chart">
            <x-icon name="activity" size="28" />
            <strong>Not enough monitoring history yet</strong>
            <span>Run at least two health checks to draw a real trend.</span>
        </div>
    @endif
</div>
