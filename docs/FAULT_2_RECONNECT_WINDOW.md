# Fault 2: bounded command completion and elapsed reconnect verification

The laptop's read-only test ran in cli-server under PHP 8.2.12, 64-bit,
with an elevated 64-bit PowerShell 5.1 child. NetAdapter import finished at
1.459 seconds, Get-NetAdapter at 1.993, WhatIf at 2.187 and Windows device
status at 3.031. The installed target_snapshot reader finished in 1.08 seconds.
The returned source hashes matched the source inspected for this correction.
These are healthy-state readings; they do not establish the duration or root
cause of a real enable operation while Wi-Fi is disabled/reconnecting.

## What changes

- Enable-NetAdapter and Restart-NetAdapter run once through a short -File
  invocation. Module import, command start, command return and errors have
  flushed progress markers. The actual cmdlets/target/confirmation flags are
  unchanged. Success still requires a real zero process exit plus a measured
  clearing of the same target condition.
- Adapter processes have a 60-second default allowance (capped at 120 for
  custom configuration). DHCP/DNS processes keep their prior 35-second default.
  A larger allowance is not a speed improvement or proof the Windows issue is
  fixed. No job dispatch or fabricated command completion is used.
- For enable/restart after a completed or timed-out command, recognized
  transitional readings can activate a 60-second reconnect window. The clock
  starts after the command; read durations and retry gaps count toward it.
  Poll gaps are two seconds by default. A settled result returns immediately.
  The last configuration read uses no more than the remaining window budget.
  An in-flight gateway/Internet/DNS measurement may finish after that window;
  the PHP request and repair lock allowances account for it.
- The ordinary three-attempt policy remains for other repairs and unrelated
  unreadable errors. Access-denied commands cannot activate the window. Setting
  attempts to one or reconnect_window_seconds to zero disables extra reads.
  A 60-reading safety cap also bounds an unexpectedly non-advancing clock.
- Explicit transition/snapshot-change codes and raw Windows status are retained
  in probe_meta. A quick transition error is not described as a 20-second timeout.
- Evidence shows the retry policy, elapsed verification time, actual command
  allowance and final reading errors. A later manual closing measurement is
  distinguished from an earlier unsuccessful automatic verification.

The extra window contains readings only. It never repeats the repair command.
Scheduled checks may stay paused. The local-only scope is retained. No database
migration/reset or rewrite of saved NR-00029/31/32 evidence occurs.

## Expected outcomes

Exit 0 and a completed same-adapter measurement clearing the condition means
verified repair. A process timeout remains completion unconfirmed even if
the reconnect window later observes recovery. Exhausted unreadable readings
leave the condition unverified and retain the exact error. A later manual check
can independently observe recovery without rewriting the earlier request.

Automated PHP tests use a fake monotonic clock and mocked Windows probes to
exercise more than three transient readings, deadline expiry, permissions,
foreign adapters, paused monitoring and timeout honesty. PowerShell fixture
tests execute the production command wrapper with mocked adapter cmdlets.
Native Windows timing still requires one laptop repair test after installation.
