@props(['check', 'label', 'warning' => 100])
<section class="reading-stage">
    <h4>{{ $label }}</h4>
    @if($check)
        <p class="evidence-time">{{ $check->checked_at->format('M j, g:i:s A') }}</p>
        <p class="reading-condition">{{ $check->diagnosis_title ?: $check->diagnosis_code }}</p>
        <p class="quiet">Adapter: {{ $check->interface_name ?: 'Not reported' }}</p>
        <dl class="reading-probes">
            @foreach(['gateway' => 'Gateway', 'internet' => 'Internet', 'dns' => 'Name lookup'] as $probe => $name)
                @php($mark = \App\Support\CheckStory::probeMark($check, $probe, $warning))
                <div>
                    <dt>{{ $name }}</dt>
                    <dd class="mark-{{ $mark['code'] }}">{{ match($mark['code']) { 'pass' => 'Passed', 'fail' => 'Failed', 'slow' => 'Slow', default => $mark['label'] } }}@if(in_array($mark['code'], ['pass', 'slow'], true)) · {{ $mark['detail'] }}@endif</dd>
                </div>
            @endforeach
        </dl>
    @else
        <p class="quiet">No measurement is recorded for this stage.</p>
    @endif
</section>
