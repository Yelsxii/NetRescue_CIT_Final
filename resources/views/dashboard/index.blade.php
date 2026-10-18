@extends('layouts.app', ['title' => 'NetRescue CIT · Dashboard', 'pageTitle' => 'Dashboard'])

@section('content')
@php
    $dashboardMonitor = app(\App\Services\MonitorSchedulerStatus::class)->status($settings);
@endphp
<div class="page-fit dashboard">
    <header class="dash-head">
        <div class="dash-copy">
            <h1>{{ $summary['state']['title'] }}</h1>
            <p>{{ $summary['state']['message'] }}</p>
        </div>
        <div class="dash-actions">
            @if($dashboardMonitor['state'] === 'ready' && $nextCheckAt)
                <span class="next-chip">Next check <b data-next-check="{{ $nextCheckAt->toIso8601String() }}">…</b></span>
            @endif
            <form action="{{ route('health-checks.store') }}" method="POST" class="js-busy-form" data-busy-text="Checking this computer's network…">
                @csrf
                <button class="button button-primary" type="submit"><x-icon name="run-check" size="15" /> Run Check</button>
            </form>
        </div>
    </header>

    @if($dashboardMonitor['state'] === 'scheduler_unconfirmed')
        <p class="monitor-notice" role="status">{{ $dashboardMonitor['message'] }} Start NetRescue with <strong>START_NETRESCUE.bat</strong> and allow up to one minute for automatic checks to begin.</p>
    @endif

    <section class="stat-row local-stats" aria-label="This computer's monitoring summary">
        <article class="stat-card stat-a">
            <span class="stat-sheen" aria-hidden="true"></span>
            <span class="stat-sheen-high" aria-hidden="true"></span>
            <span class="stat-mark"><x-icon name="computer" size="22" /></span>
            <div class="stat-copy">
                <span>This computer</span>
                <strong class="host-name">{{ gethostname() ?: $localDevice->display_name }}</strong>
                <small>Local network monitoring</small>
            </div>
        </article>
        <article class="stat-card stat-c">
            <span class="stat-sheen" aria-hidden="true"></span>
            <span class="stat-sheen-high" aria-hidden="true"></span>
            <span class="stat-mark"><x-icon name="shield" size="22" /></span>
            <div class="stat-copy">
                <span>Healthy · 24h</span>
                <strong>{{ $summary['stats']['healthy_check_rate_24h'] !== null ? $summary['stats']['healthy_check_rate_24h'].'%' : '—' }}</strong>
                <small>out of {{ $summary['stats']['checks_24h'] }} checks</small>
            </div>
        </article>
        <article class="stat-card stat-d">
            <span class="stat-sheen" aria-hidden="true"></span>
            <span class="stat-sheen-high" aria-hidden="true"></span>
            <span class="stat-mark"><x-icon name="incident" size="22" /></span>
            <div class="stat-copy">
                <span>Open incidents</span>
                <strong>{{ $summary['stats']['active_incidents'] }}</strong>
                <small>On this computer</small>
            </div>
        </article>
    </section>

    @php
        $window = $summary['response_points']->slice(-48)->values();
        $warn = (int) $settings->latency_warning_ms;
        $marker = $networkScale > 0 ? min(100, ($warn / $networkScale) * 100) : 100;
        $scaleEnd = rtrim(rtrim(number_format((float) $networkScale, 1, '.', ''), '0'), '.');
        $outside = function ($ok, $ms) use ($warn) {
            return $ok === false || (is_numeric($ms) && (float) $ms > $warn);
        };
        $tally = [
            ['label' => 'Gateway', 'slow' => $window->filter(fn ($row) => $outside($row->gateway_ok ?? null, $row->gateway ?? null))->count()],
            ['label' => 'Internet', 'slow' => $window->filter(fn ($row) => $outside($row->internet_ok ?? null, $row->internet ?? null))->count()],
            ['label' => 'Name lookup', 'slow' => $window->filter(fn ($row) => $outside($row->dns_ok ?? null, $row->dns ?? null))->count()],
        ];
    @endphp
    <section class="workspace">
        <article class="chart-panel">
            <div class="panel-title">
                <div>
                    <h2>Response times</h2>
                    <p>{{ $warn }} ms warning threshold</p>
                </div>
            </div>
            @if($measuredCheck)
                @if($readingIncomplete)
                    <p class="quiet">The latest check did not finish. These bars are the last completed reading.</p>
                @endif
                <div class="chart-fill">
                <div class="meter-list">
                    @foreach($networkNow as $row)
                        @php
                            $mark = \App\Support\CheckStory::mark($row['ok'], $row['ms'], $warn);
                        @endphp
                        <div class="meter">
                            <div class="meter-top">
                                <span>{{ $row['label'] }}</span>
                                <b class="mark-{{ $mark['code'] }}">{{ $mark['label'] }} {{ $mark['detail'] }}</b>
                            </div>
                            <div class="meter-track">
                                <i class="{{ $mark['code'] === 'fail' ? 'is-fail' : ($mark['code'] === 'slow' ? 'is-slow' : ($mark['code'] === 'none' ? 'is-none' : '')) }}" style="width: {{ $row['width'] }}%"></i>
                                <em class="meter-warn" style="left: {{ $marker }}%"></em>
                            </div>
                        </div>
                    @endforeach
                    <div class="meter-scale">
                        <span>0 ms</span>
                        <span>{{ $scaleEnd }} ms</span>
                    </div>
                </div>
                <div class="tally" aria-label="Slow or failed checks">
                    <p class="tally-label">Slow or failed checks</p>
                    @foreach($tally as $item)
                        <article>
                            <span>{{ $item['label'] }}</span>
                            <strong class="{{ $item['slow'] > 0 ? 'mark-slow' : 'mark-pass' }}">{{ $item['slow'] }}</strong>
                            <small>of {{ $window->count() }} {{ \Illuminate\Support\Str::plural('check', $window->count()) }}</small>
                        </article>
                    @endforeach
                </div>
                </div>
            @else
                <div class="empty-chart">
                    <strong>No measurement yet</strong>
                    <span>Run Check to measure this computer's network connection.</span>
                </div>
            @endif
        </article>

        <article class="now-panel">
            <div class="panel-title compact">
                <div>
                    <h2>Now</h2>
                    <p>{{ $localLatest ? $localLatest->checked_at->diffForHumans() : 'No measurement yet' }}</p>
                </div>
            </div>
            @if($localLatest)
                @php
                    $openHere = $summary['active_incidents']->where('device_id', $localDevice->id);
                    $shown = $measuredCheck ?: $localLatest;
                @endphp
                <div class="now-facts">
                    <p class="case-fact now-finding">{{ $localLatest->diagnosis_title }}</p>
                    @if($readingIncomplete)
                        <p class="repair-card-notice">{{ $localLatest->diagnosis_summary }}</p>
                    @endif
                    <p class="now-adapter"><span>Adapter</span> <b>{{ $localLatest->interface_name ?: 'Not reported in the latest check' }}</b></p>
                    <section class="now-network-details" aria-label="Network details">
                        @if(is_numeric($duration = data_get($localLatest->probe_meta, 'timings_ms.total')))
                            <p class="quiet">Latest check duration: {{ number_format($duration / 1000, 2) }} seconds.</p>
                        @endif
                        @if($measuredCheck)
                            @if($readingIncomplete)
                                <p class="quiet">Address, gateway and DNS below are from the last completed reading {{ $measuredCheck->checked_at->diffForHumans() }}.</p>
                                <p class="now-line"><span>Measured adapter</span> <b>{{ $shown->interface_name ?: 'Unknown' }}</b></p>
                            @endif
                            <p class="now-line"><span>Address</span> <b>{{ $shown->local_ip ?: 'None' }}</b></p>
                            <p class="now-line"><span>Gateway</span> <b>{{ $shown->gateway_ip ?: 'None' }}</b></p>
                            <p class="now-line"><span>DNS</span> <b>{{ is_array($shown->dns_servers) && $shown->dns_servers ? implode(', ', $shown->dns_servers) : 'None recorded' }}</b></p>
                        @else
                            <p class="quiet">No completed reading is stored yet.</p>
                        @endif
                    </section>
                </div>
                @forelse($openHere as $incident)
                    @php
                        $repairAdapter = $incident->latestHealthCheck?->interface_name;
                        $differentAdapter = filled($repairAdapter) && $repairAdapter !== $shown->interface_name;
                    @endphp
                    <x-repair-guidance :incident="$incident" mode="dashboard" :different-adapter="$differentAdapter" :reading-adapter="$shown->interface_name" />
                @empty
                @endforelse
            @else
                <p class="quiet">Run Check to measure this computer.</p>
            @endif
        </article>

        <article class="table-panel log-panel">
            <div class="panel-title compact">
                <div><h2>Latest checks</h2></div>
                <a href="{{ route('checks.index') }}">All checks</a>
            </div>
            <x-check-table :checks="$recentChecks" :warning="$settings->latency_warning_ms" />
        </article>
    </section>
</div>
@endsection

