@extends('layouts.app', ['title' => 'NetRescue CIT · Dashboard', 'pageTitle' => 'Dashboard'])

@section('content')
<div class="page-fit dashboard">
    <header class="dash-head">
        <div class="dash-copy">
            <h1>{{ $summary['fleet_state']['title'] }}</h1>
            <p>{{ $summary['fleet_state']['message'] }}</p>
        </div>
        <div class="dash-actions">
            @if($settings->enabled && $nextCheckAt)
                <span class="next-chip">Next check <b data-next-check="{{ $nextCheckAt->toIso8601String() }}">…</b></span>
            @endif
            <form action="{{ route('health-checks.store') }}" method="POST" class="js-busy-form" data-busy-text="Checking the Central computer network…">
                @csrf
                <button class="button button-primary" type="submit"><x-icon name="refresh" size="15" /> Run Central Check</button>
            </form>
        </div>
    </header>

    <section class="stat-row" aria-label="Current totals">
        <article class="stat-card stat-a">
            <span class="stat-mark"><x-icon name="online" size="22" /></span>
            <div class="stat-copy">
                <span>Reporting</span>
                <strong>{{ $summary['stats']['online'] }}</strong>
                <small>out of {{ $summary['stats']['total'] }}</small>
            </div>
        </article>
        <article class="stat-card stat-b">
            <span class="stat-mark"><x-icon name="offline" size="22" /></span>
            <div class="stat-copy">
                <span>Not reporting</span>
                <strong>{{ $summary['stats']['offline'] }}</strong>
                <small>out of {{ $summary['stats']['total'] }}</small>
            </div>
        </article>
        <article class="stat-card stat-c">
            <span class="stat-mark"><x-icon name="shield" size="22" /></span>
            <div class="stat-copy">
                <span>Healthy · 24h</span>
                <strong>{{ $summary['stats']['healthy_check_rate_24h'] !== null ? $summary['stats']['healthy_check_rate_24h'].'%' : '—' }}</strong>
                <small>out of {{ $summary['stats']['checks_24h'] }} checks</small>
            </div>
        </article>
        <article class="stat-card stat-d">
            <span class="stat-mark"><x-icon name="incident" size="22" /></span>
            <div class="stat-copy">
                <span>Open incidents</span>
                <strong>{{ $summary['stats']['active_incidents'] }}</strong>
                <small>{{ $summary['problem_groups']->isEmpty() ? 'None open' : $summary['problem_groups']->count().' grouped '.\Illuminate\Support\Str::plural('problem', $summary['problem_groups']->count()) }}</small>
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
                    <span>Run Central Check to compare this computer with the warning.</span>
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
                    <p class="case-fact">{{ $localLatest->diagnosis_title }}</p>
                    @if(is_numeric($duration = data_get($localLatest->probe_meta, 'timings_ms.total')))
                        <p class="quiet">Check duration: {{ number_format($duration / 1000, 2) }} seconds.</p>
                    @endif
                    @if($readingIncomplete)
                        <p class="quiet">{{ $localLatest->diagnosis_summary }}</p>
                    @endif
                    @if($measuredCheck)
                        <p class="now-line"><span>Address</span> <b>{{ $shown->local_ip ?: 'None' }}</b></p>
                        <p class="now-line"><span>Gateway</span> <b>{{ $shown->gateway_ip ?: 'None' }}</b></p>
                        <p class="now-line"><span>Adapter</span> <b>{{ $shown->interface_name ?: 'Unknown' }}</b></p>
                        <p class="now-line"><span>DNS</span> <b>{{ is_array($shown->dns_servers) && $shown->dns_servers ? implode(', ', $shown->dns_servers) : 'None recorded' }}</b></p>
                        @if($readingIncomplete)
                            <p class="quiet">Last completed reading {{ $measuredCheck->checked_at->diffForHumans() }}.</p>
                        @endif
                    @else
                        <p class="quiet">No completed reading is stored yet.</p>
                    @endif
                </div>
                @forelse($openHere as $incident)
                    @php
                        $repairAdapter = $incident->latestHealthCheck?->interface_name;
                        $differentAdapter = filled($repairAdapter) && $repairAdapter !== $shown->interface_name;
                        $lastAttempt = $incident->latestRecovery();
                        $unverifiedAttempt = $lastAttempt && in_array($lastAttempt->verification_status, ['pending', 'unread', 'adapter_changed'], true);
                    @endphp
                    <div class="repair-slot" data-repair-target="{{ $repairAdapter }}">
                        <strong>{{ $differentAdapter || $unverifiedAttempt ? 'Repair result needs review' : 'Available repair' }}</strong>
                        @if($differentAdapter || $unverifiedAttempt)
                            <span>{{ $incident->reference }} · {{ $repairAdapter ?: 'Unknown adapter' }} · {{ $incident->plainProblem() }}</span>
                            <a class="button button-primary repair-review" href="{{ route('incidents.show', $incident) }}">Review result</a>
                            @if($differentAdapter)
                                <p class="quiet">The latest completed reading is for {{ $shown->interface_name ?: 'another adapter' }}. It does not verify this {{ $repairAdapter }} incident.</p>
                            @endif
                            @if($lastAttempt)<p class="quiet">{{ $lastAttempt->outcomeSummary() }}</p>@endif
                        @elseif($incident->canRepair())
                            <span>{{ $incident->repairCommand() }}</span>
                            <form method="POST" action="{{ route('incidents.recover', $incident) }}" class="js-busy-form" data-busy-text="Running the repair and measuring again…">
                                @csrf
                                <input type="hidden" name="return_to" value="dashboard">
                                <button class="button button-primary" type="submit">Run repair</button>
                            </form>
                        @else
                            <span>No repair command is available for this condition.</span>
                        @endif
                        @if(! $differentAdapter && ! $unverifiedAttempt && $incident->nextStep())<p class="quiet">{{ $incident->nextStep() }}</p>@endif
                    </div>
                @empty
                @endforelse
            @else
                <p class="quiet">Run Central Check to measure this computer.</p>
            @endif
        </article>

        <article class="table-panel log-panel">
            <div class="panel-title compact">
                <div><h2>Latest checks</h2></div>
                <a href="{{ route('devices.checks', $localDevice) }}">All checks</a>
            </div>
            <x-check-table :checks="$recentChecks" :warning="$settings->latency_warning_ms" />
        </article>
    </section>
</div>
@endsection

