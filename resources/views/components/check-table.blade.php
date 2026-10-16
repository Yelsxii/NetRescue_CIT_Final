@props(['checks', 'warning' => 100])
<p class="table-scroll-hint"><x-icon name="chevron" size="14" /> Scroll to see all columns</p>
<div class="table-scroll" role="region" aria-label="Network check history" tabindex="0">
    <table class="data-table check-table">
        <colgroup><col style="width:11%"><col style="width:21%"><col style="width:21%"><col style="width:22%"><col style="width:25%"></colgroup>
        <thead>
            <tr>
                <th scope="col">Time</th>
                <th scope="col">Gateway</th>
                <th scope="col">Internet</th>
                <th scope="col">Name lookup</th>
                <th scope="col" class="result-cell">Result</th>
            </tr>
        </thead>
        <tbody>
            @forelse($checks as $check)
                @php
                    $resultTone = $check->diagnosis_code === 'healthy' ? 'ok' : (in_array($check->severity, ['none', 'low'], true) ? 'warn' : 'bad');
                    $gateway = \App\Support\CheckStory::probeMark($check, 'gateway', (int) $warning);
                    $internet = \App\Support\CheckStory::probeMark($check, 'internet', (int) $warning);
                    $dns = \App\Support\CheckStory::probeMark($check, 'dns', (int) $warning);
                @endphp
                <tr>
                    <td>{{ $check->checked_at->format('g:i A') }}</td>
                    <td><b class="mark-{{ $gateway['code'] }}">{{ $gateway['label'] }}</b> {{ $gateway['detail'] }}</td>
                    <td><b class="mark-{{ $internet['code'] }}">{{ $internet['label'] }}</b> {{ $internet['detail'] }}</td>
                    <td><b class="mark-{{ $dns['code'] }}">{{ $dns['label'] }}</b> {{ $dns['detail'] }}</td>
                    <td class="result-cell"><span class="result-text is-{{ $resultTone }}">{{ $check->diagnosis_title }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No check is stored yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
