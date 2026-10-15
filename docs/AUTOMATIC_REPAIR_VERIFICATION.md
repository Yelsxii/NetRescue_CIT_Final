> Historical troubleshooting note. The current application monitors this computer only. See README.md and docs/LOCAL_ONLY_MONITORING.md for current startup/scope; old device/agent instructions no longer apply.

# Automatic repair verification and Windows probe overhead

Prepared against Yelsxii/NetRescue_CIT_Final main commit
569caa5cbd780ab367c516140d1f991f29678006 (15 October 2026).

## What the screenshots establish

The installed repair already performed one follow-up reading. That reading
timed out, so recovery was not verified and the dashboard requested a separate
Central Check. Pausing scheduled checks did not cause that timeout.

The separate installed-service diagnostic demonstrated a disabled-to-enabled
transition: precheck 4.51 s, enable 24.39 s with exit 0, follow-up 10.72 s. It did
not establish why an earlier attempt exceeded its deadline, or verify incident
closure. Manual PowerShell enable took 6.62 s and a later configuration read
took 1.02 s. These are different runs, not a controlled speed comparison.

## Changes

- A local repair performs a fresh precheck, the permitted command if still
  appropriate, and automatic verification in the same request. The scheduled
  monitoring toggle does not control this flow.
- Follow-up verification allows at most three readings. It retries unreadable
  results and relevant reconnecting states, waiting one second only between
  retries. It finishes immediately on a completed stable result. It never
  repeats the repair command as part of verification.
- Access denied plus a readable unchanged condition ends promptly. A different
  measured adapter cannot verify the original adapter. Persistent unreadable
  results remain unverified after the bounded attempts.
- Each follow-up is retained as a health check. The final exact check is linked
  to the repair and any incident resolution. Metadata retains earlier reading
  IDs, attempt count, command duration and measurement timings.
- The repair retains command execution evidence before verification starts.
  Cache locks prevent concurrent local repairs for the same device.
- Local enable uses
  `Enable-NetAdapter -Name "Wi-Fi" -Confirm:$false -ErrorAction Stop`, substituting only
  the validated measured adapter name. The UI displays that actual command.
  This restores the command that completed in the earlier installed-service
  diagnostic; native netsh repeatedly timed out in later full repair tests.
  Remote-agent command text remains unchanged. Administrator privileges are
  still required. Restart and DHCP commands retain their existing scope.
- Configuration uses Windows' .NET interface snapshot for IP, DNS and DHCP
  data instead of Get-NetIPConfiguration's many CIM calls. Route selection
  remains based on the IPv4 default route and combined route/interface metric.
  When no route exists, Get-NetAdapter identifies physical disabled or
  disconnected adapters; Down is never assumed to mean Disabled.
- A reading explicitly pinned to an adapter first takes a fresh .NET snapshot.
  If that exact adapter is Up and has at most one IPv4 gateway, its configuration
  can be read without loading NetAdapter/NetTCPIP. Down, absent or ambiguous
  targets retain the administrative-state/route fallback. Unpinned Central
  Checks retain their route-based adapter selection. The reader path is saved
  in probe_meta.configuration_reader for diagnosis.
- The configuration reader runs from the included local PowerShell file, which
  avoids putting a large encoded script into cmd.exe's command line. Small
  PowerShell probes use UTF-16LE encoded commands and suppress progress output.
- Gateway ICMP uses .NET Ping with two bounded requests and no fixed one-second
  inter-request pause. Internet/DNS prerequisites remain in order; skipped
  tests do not become failures. IPv4 and IPv6 DNS addresses remain visible.
- The unconditional 2.5-second reconnect sleep is removed. If the first fresh
  reading is already ready, there is no additional wait.
- The dashboard repair returns to the refreshed dashboard. Incident-page and
  modal repair forms use the same backend and return updated incident details.
  No separate Central Check click is part of the normal successful repair flow.
- The loading overlay explains automatic verification and shows actual elapsed
  time. Status polling cannot navigate away during an active operation, and
  browser history restore resets disabled buttons.
- Fast readings that share a database timestamp are ordered by ID as well as
  time, so the dashboard, device and status endpoint show the latest result.
- The latest graph scale, labels and proportional bar widths from main are
  preserved. This patch does not replace them with an earlier dashboard.

## Install

This is an overlay for the existing project with the recovery-evidence migration
already installed. There is NO new migration, database replacement, dependency
installation or .env change.

1. Stop `php artisan serve` in its current terminal with Ctrl+C. Do not copy
   files during an active repair.
2. Extract NetRescue_Automatic_Verification_and_Speed_Correction.zip. Inside
   its top-level folder, copy the `app`, `config`, `public`, `resources`, `tests`
   and `docs` folders into `C:\xampp\htdocs\NetRescue_CIT_Final`, merging
   folders and replacing the included files. Preserve any newer edits not in
   the referenced GitHub commit; CHANGESET.patch and PATCH_MANIFEST.json make
   the complete patch reviewable.
3. Confirm this NEW file exists:
   `resources\powershell\read-configuration.ps1`.
4. In Administrator PowerShell:

   ```powershell
   cd "C:\xampp\htdocs\NetRescue_CIT_Final"
   & "C:\xampp\php\php.exe" artisan optimize:clear
   & "C:\xampp\php\php.exe" artisan serve --host=127.0.0.1 --port=8000
   ```

5. Open `http://127.0.0.1:8000/` directly and press Ctrl+F5. Do not refresh an
   old `/recover` POST page or accept a form-resubmission prompt.

## One-click Fault 2 acceptance test

Keep scheduled checks paused. Run Central Check once for the healthy baseline.
Use Administrator PowerShell to inject the fault:

```powershell
Disable-NetAdapter -Name "Wi-Fi" -Confirm:$false -ErrorAction Stop
```

Run Central Check to detect Network Adapter Disabled and open a NEW incident.
Press Run repair ONCE, from either the dashboard or that incident. Do not press
another repair or Central Check while the request is running. Let automatic
verification finish and the page update.

A pass requires recorded command completion with exit 0, a fresh follow-up
showing the SAME Wi-Fi adapter enabled, and the incident linked to that exact
follow-up as verified_repair. Address/gateway/Internet/DNS results must remain
honest and separately visible. A cleared adapter fault is not a guarantee that
an independent DNS or upstream fault was also repaired.

Expand Latest request: measurements and command details to read the automatic
verification count, command duration, final reading and error/output evidence.
Now shows the latest check duration. Capture those values to measure the actual
speed on this laptop. If the command succeeds but all readings fail, keep the
incident unverified; investigate the retained error instead of marking success.

If Wi-Fi remains disabled, manual restoration in Administrator PowerShell is:

```powershell
Enable-NetAdapter -Name "Wi-Fi" -Confirm:$false -ErrorAction Stop
```

Delete the two temporary diagnostic PHP files from `public` after testing:
netrescue-process-diagnostic.php and netrescue-adapter-repair-diagnostic.php.
Neither is needed for the application's normal repair flow.

## Validation and limits

The local automated suite exercises paused-monitoring verification, first-read
failure followed by recovery, reconnecting address acquisition, bounded failure,
command errors, wrong-adapter safety, duplicate requests, dashboard return and
same-second reading order. The Windows process boundary is mocked in these
tests; this environment does not provide Windows hardware.

Browser checks use the actual Blade views, JavaScript and CSS at desktop and
mobile sizes. They cover the repair button position, loading text/timer,
polling suppression and history restore. Synthetic fixtures are not evidence
that Windows repaired an actual adapter.

The implementation reduces redundant Windows query/module work and fixed
waiting. It does not promise a fixed number of seconds: DHCP, DNS, driver
reconnection and failed network requests can legitimately take longer. A
Windows end-to-end acceptance run is still required before Fault 2 is passed.

No previous incident is rewritten or retrospectively labeled verified. This
download is prepared source; it is not an automatically deployed GitHub change.

Primary API references:
- https://learn.microsoft.com/en-us/powershell/module/netadapter/enable-netadapter
- https://learn.microsoft.com/en-us/dotnet/api/system.net.networkinformation.ipinterfaceproperties.gatewayaddresses?view=netframework-4.8.1
- https://learn.microsoft.com/en-us/dotnet/api/system.net.networkinformation.networkinterface.getipproperties?view=netframework-4.8.1
- https://learn.microsoft.com/en-us/dotnet/api/system.net.networkinformation.ipv4interfaceproperties.isdhcpenabled?view=netframework-4.8.1
- https://learn.microsoft.com/en-us/dotnet/api/system.net.networkinformation.ping.send?view=netframework-4.8.1
