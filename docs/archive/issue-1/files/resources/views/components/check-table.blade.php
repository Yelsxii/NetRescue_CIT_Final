@props(['checks', 'warning' => 100])
<div class="table-scroll">
    <table class="data-table check-table">
        <colgroup><col style="width:12%"><col style="width:17%"><col style="width:17%"><col style="width:22%"><col style="width:32%"></colgroup>
        <thead>
            <tr>
                <th>Time</th>
                <th>Gateway</th>
                <th>Internet</th>
                <th>Name lookup</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            @forelse($checks as $check)
                @php
                    $unmeasured = ['code' => 'none', 'label' => '—', 'detail' => 'Not measured'];
                    if ($check->readingFinished()) {
                        $gateway = \App\Support\CheckStory::probeMark($check, 'gateway', (int) $warning);
                        $internet = \App\Support\CheckStory::probeMark($check, 'internet', (int) $warning);
                        $dns = \App\Support\CheckStory::probeMark($check, 'dns', (int) $warning);
                    } else {
                        $gateway = $internet = $dns = $unmeasured;
                    }
                @endphp
                <tr>
                    <td>{{ $check->checked_at->format('g:i A') }}</td>
                    <td><b class="mark-{{ $gateway['code'] }}">{{ $gateway['label'] }}</b> {{ $gateway['detail'] }}</td>
                    <td><b class="mark-{{ $internet['code'] }}">{{ $internet['label'] }}</b> {{ $internet['detail'] }}</td>
                    <td><b class="mark-{{ $dns['code'] }}">{{ $dns['label'] }}</b> {{ $dns['detail'] }}</td>
                    <td>{{ $check->diagnosis_title }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No check is stored yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
