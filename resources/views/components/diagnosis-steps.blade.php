@props(['check' => null, 'note' => null])

@if($check)
    @if($note)<p class="panel-note">{{ $note }}</p>@endif
    <div class="diagnostic-steps">
        <div class="diagnostic-row {{ $check->local_ip ? 'pass' : 'fail' }}"><span>1</span><div><strong>Computer has a usable network address</strong><small>{{ $check->local_ip ? 'Address detected' : 'No usable IPv4 address was recorded' }}</small></div><b>{{ $check->local_ip ? 'PASS' : 'FAIL' }}</b></div>
        <div class="diagnostic-row {{ $check->gateway_reachable ? 'pass' : 'fail' }}"><span>2</span><div><strong>Local network / gateway responds</strong><small>{{ $check->gateway_latency_ms !== null ? number_format($check->gateway_latency_ms, 1).' ms measured response' : 'No response time recorded' }}</small></div><b>{{ $check->gateway_reachable ? 'PASS' : 'FAIL' }}</b></div>
        <div class="diagnostic-row {{ $check->internet_reachable ? 'pass' : 'fail' }}"><span>3</span><div><strong>Internet path can be reached</strong><small>{{ $check->internet_latency_ms !== null ? number_format($check->internet_latency_ms, 1).' ms connection time' : 'Not reachable or test was not completed' }}</small></div><b>{{ $check->internet_reachable ? 'PASS' : 'FAIL' }}</b></div>
        <div class="diagnostic-row {{ $check->dns_operational ? 'pass' : 'fail' }}"><span>4</span><div><strong>Website names can be resolved</strong><small>{{ $check->dns_latency_ms !== null ? number_format($check->dns_latency_ms, 1).' ms lookup time' : 'No successful lookup time recorded' }}</small></div><b>{{ $check->dns_operational ? 'PASS' : 'FAIL' }}</b></div>
    </div>
@else
    <div class="empty-list">No measured network check is stored for this computer yet.</div>
@endif
