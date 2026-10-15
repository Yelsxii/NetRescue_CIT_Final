> Historical troubleshooting note. The current application monitors this computer only. See README.md and docs/LOCAL_ONLY_MONITORING.md for current startup/scope; old device/agent instructions no longer apply.

# Fault 2: bounded first verification and retained reading details

## What NR-00029 proves

Recovery action 14 completed Enable-NetAdapter with exit code 0. Its before
check 485 measured disabled Wi-Fi. Its final check 487 measured the same Wi-Fi
operational with IPv4 192.168.1.5 and gateway 192.168.1.1. The incident closed
as verified repair. Scheduled checks were paused; verification was automatic.
This is a functional pass for the tested disabled-adapter flow.

The first follow-up, check 486, did not finish. Its configuration process
exceeded the 20-second deadline and its measured duration was 20.676 seconds.
No gateway, Internet or DNS probes ran in that unreadable check. It does not
establish the adapter's state during that attempt. The error identifies the
PowerShell reader process, not the individual call inside it that stalled.

Recorded phase durations are approximately:

| Phase | Seconds |
| --- | ---: |
| Pre-repair reading | 7.69 |
| Enable command | 25.30 |
| First verification, unreadable | 20.68 |
| Retry delay | 1.00 |
| Final verification, operational | 6.25 |
| Sum | 60.92 |

This is a sum of recorded work and the configured retry delay. It is not a
measurement of all time between browser submission and page completion. Other
request/record/rendering overhead was not separately measured. The repair
record's started_at is currently written after the precheck; its duration
therefore excludes that precheck. The dashboard's 6.25-second check duration
is the final reading, not the whole repair request.

## Focused change

When an enable/restart command completed or timed out and more than one
verification attempt is available, only the FIRST target configuration attempt
uses the smaller of the normal budget and initial_configuration_seconds (5 by
default). The later attempts keep the normal 20-second configuration budget.
The shorter budget is passed as an argument; global timeouts are not changed
temporarily at runtime. Each follow-up records its effective configuration
timeout with the existing verification metadata.

Regular Central Checks, prechecks, explicit result checks, DHCP/DNS repair
verification and a sole allowed verification attempt retain their normal
configuration allowance. Explicit command failures such as Access denied do
not use the reconnect-specific short budget. The command's own 35-second
timeout remains unchanged, and the command is never repeated by verification.

A timed-out first reading is still saved as unreadable. It is not turned into
an adapter diagnosis or a successful result. Automatic retry and exact-target
verification remain required before closing an incident as verified.

The first attempt's allowed wait is reduced from 20 to 5 seconds; this does
not guarantee a particular total repair duration or remove a driver's own
enable time. Process termination and request overhead can extend the measured
duration beyond a configured deadline. Actual Windows speed must be retested.

## Evidence display

The shared incident view now displays retained earlier verification readings
between Before and After. Both the full incident and its modal use this view.
Previous repair requests use the same reading partial. It shows reading time,
duration, diagnosis, error, measured adapter, requested adapter and the saved
configuration timeout when available. An unreadable adapter is labeled Not
obtained, rather than copying the requested adapter into a measured field.

Only exact check IDs already linked in verification metadata are used. IDs
from a different computer, duplicates and the final check itself are excluded.
The display does not infer readings from nearby timestamps and does not modify
existing checks or incident/repair outcomes. NR-00029 retains its verified
result and original 20.68-second earlier reading after installation.

## Install

This overlay requires the target-read correction already used for NR-00029.

1. Exit any old Tinker session with exit. Finish any active request and stop
   the Laravel server with Ctrl+C.
2. Back up the listed files. Merge this archive's app, config, resources,
   tests and docs folders into C:\xampp\htdocs\NetRescue_CIT_Final. Replace
   included files; preserve other project files.
3. Confirm the NEW partial was copied:
   resources\views\incidents\verification-readings.blade.php.
4. In Administrator PowerShell:

   ```powershell
   cd "C:\xampp\htdocs\NetRescue_CIT_Final"
   & "C:\xampp\php\php.exe" artisan optimize:clear
   & "C:\xampp\php\php.exe" artisan serve --no-reload --host=127.0.0.1 --port=8000
   ```

No migration, database reset, dependency installation or asset build is needed.

## First review without injecting a fault

Open http://127.0.0.1:8000/incidents/29 directly. Expand Latest request:
measurements and command details. The earlier automatic reading should show
20.68 seconds and its original timeout. The request must remain verified and
the incident closed. Confirm the same details in the incident modal.

This review reads existing evidence; it does not run a network command.

## Subsequent Windows timing test

Keep scheduled checks paused and establish a healthy Wi-Fi baseline with
Central Check. In Administrator PowerShell:

```powershell
Disable-NetAdapter -Name "Wi-Fi" -Confirm:$false -ErrorAction Stop
```

Central Check must detect Network Adapter Disabled. Press Run repair once and
wait for automatic verification and the page update. Do not run a manual
restoration or another check while it is in progress. Report command duration,
the earlier reading durations/errors if any, final reading, outcome and actual
browser elapsed time. If the first configuration read times out, its error
should reference 5 seconds; later reads retain 20 seconds. If it finishes
promptly, a retry is not required. Compare actual measured results rather than
assuming a fixed improvement from the changed deadline.

If the request has finished and Wi-Fi remains disabled, restore it manually:

```powershell
Enable-NetAdapter -Name "Wi-Fi" -Confirm:$false -ErrorAction Stop
```

## Validation scope

PHP tests exercise the real recovery/health flow with a mocked Windows process
boundary. They cover a short first read followed by a normal-budget retry,
single-attempt preservation, unchanged global configuration, invalid arguments,
historical timeout visibility, exact reading links and cross-device exclusion.
Browser checks render actual Blade/CSS/JS with synthetic records. These checks
do not establish Windows performance; a laptop timing run remains necessary.
