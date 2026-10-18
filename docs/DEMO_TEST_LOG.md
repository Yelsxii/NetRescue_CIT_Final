# NetRescue running demo test log

This is the running record of demonstrated commands and procedures. Append new demonstrations as Demo 002, Demo 003, and so on. Keep earlier successes, failed attempts, and unresolved cleanup items visible. The [fault simulation testing guide](FAULT_SIMULATION_TESTING_GUIDE.md) contains the complete scenario list.

Dates and times below use **Asia/Manila (UTC+08:00)**. A successful repair requires command completion and successful automatic verification on the same adapter. Record teardown confirmation separately from repair success.

## Demonstration index

| Demo | Date | Scenario | Stored incident / repair | Repair result | Teardown confirmation |
| --- | --- | --- | --- | --- | --- |
| 001 | 18 October 2026 | Wi-Fi adapter disabled | NR-00056 / request 35 | Verified; exit 0; Network Operational | QA task/rules absent and host connectivity confirmed at 12:35–12:36 PM; separate post-cleanup app Check not captured |

## Demo 001 — disabled Wi-Fi adapter repaired successfully

**Result: successful repair, verified in the stored application evidence.**

The user reported that the repeated test repaired the fault. A read-only evidence query at **12:26:15 PM on 18 October 2026** confirmed request **35**, incident **NR-00056**, command completion, exit code **0**, `success: true`, and `verification_status: verified`. The automatic follow-up was check **626**, source `recovery_after`, reporting **Network Operational** on **Wi-Fi**. No new fault, Check, repair, or Restore was executed while preparing this entry.

### Setup that made the demonstration work

1. MySQL was running with the existing database.
2. The application server needed administrator rights to execute the repair. An earlier attempt under a non-elevated PHP server failed with **Access is denied**; that attempt remains part of the history.
3. The runner was used from a second Administrator Windows PowerShell window.
4. Automatic checks were paused; the selected adapter was `Wi-Fi`, with DHCP enabled.
5. `www.google.com` was the configured DNS test domain; the slow-response warning was 100 ms.
6. The PowerShell session policy was changed to `RemoteSigned`, scope `Process`. The supplied transcript shows confirmation with **Y** and a successful Inspect afterward.

### Repeatable demo procedure

Run this server setup in **Administrator PowerShell window 1** when the server needs to be started. First stop any existing non-elevated NetRescue server through its own terminal; keep MySQL running.

```powershell
Set-Location C:\xampp\htdocs\NetRescue_CIT_Final
$serverPrincipal = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $serverPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Open Windows PowerShell using Run as administrator, then retry.'
}
& 'C:\xampp\php\php.exe' artisan serve --no-reload --host=127.0.0.1 --port=8000
```

Leave window 1 running. In **Administrator PowerShell window 2**, prepare the runner:

```powershell
Set-Location C:\xampp\htdocs\NetRescue_CIT_Final
Set-ExecutionPolicy -Scope Process -ExecutionPolicy RemoteSigned

$qaRunner = '.\tools\qa\Invoke-NetRescueFault.ps1'
$qaOptions = @{
    InterfaceAlias = 'Wi-Fi'
    PhpPath        = 'C:\xampp\php\php.exe'
    BaseUrl        = 'http://127.0.0.1:8000'
}

& $qaRunner @qaOptions -Mode Inspect
```

If prompted about execution policy, answer **Y**. Inspect should identify the intended adapter and display the existing settings. This policy change applies only to that terminal session.

Open **http://127.0.0.1:8000**, keep Automatic checks paused, and click **Run Check**. Require **Network Operational** before injection.

Inject one disabled-adapter fault:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault AdapterDisabled
```

**Copy the new `StatePath` printed by this invocation.** The runner saves the baseline and arms its default 10-minute rollback before disabling Wi-Fi.

In the browser:

1. Click **Run Check**. Expect **Adapter turned off**, with `Wi-Fi` identified.
2. Open the incident and show the detected condition.
3. Click **Run repair** once and let automatic verification finish.
4. Show command completion, exit code 0, verified recovery, and the successful follow-up measurements. Scheduling can remain paused throughout.

The Windows repair command executed by NetRescue is:

```powershell
Enable-NetAdapter -Name "Wi-Fi" -Confirm:$false -ErrorAction Stop
```

This line describes the application's repair action. Use the **Run repair** button during the demonstration so the command result, before/after checks, and verification are recorded together.

After showing the repair, restore the QA run even if Wi-Fi is already connected:

```powershell
# Replace this placeholder with the exact path from THIS injection.
$demoStatePath = 'C:\ProgramData\NetRescueQA\<new-run-id>\state.json'
& $qaRunner -Mode Restore -StatePath $demoStatePath
```

Then click **Run Check** again and require **Network Operational**. Check that no QA task or firewall rule remains:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both queries should show no matching QA entries after successful teardown. Do not begin another injection until restoration and the closing Check are confirmed. Record a repeat as a new run with its own state path and evidence.

### Exact command trail supplied for this session

The transcript first shows a successful session-policy change and Inspect. It then shows these commands and outputs:

| Stage | Recorded evidence |
| --- | --- |
| Previous injection | `AdapterDisabled`; state path `C:\ProgramData\NetRescueQA\0bd58cc8aeb54e7095fc3621bd8a081c\state.json` |
| Previous Restore | Printed **Restored QA changes for Wi-Fi** |
| Repeat injection associated with the reported success | `AdapterDisabled`; state path `C:\ProgramData\NetRescueQA\14a951b5eaf14a2e89aff236a34ebe58\state.json` |
| Repair confirmation | Stored request 35 / NR-00056 verified the adapter recovery |
| Repeat-run teardown | No Restore output was supplied; later read-only checks at 12:35–12:36 PM found the task absent, zero QA rules, and working connectivity |

The exact Restore command for the repeated run is:

```powershell
& $qaRunner -Mode Restore -StatePath 'C:\ProgramData\NetRescueQA\14a951b5eaf14a2e89aff236a34ebe58\state.json'
```

This path belongs only to the recorded run. Future demonstrations must use their newly printed path. The protected state file was not readable from the documentation process, so no `RestoredAt` value or exact cleanup mechanism is asserted. Subsequent checks below confirmed the task/rules were gone and connectivity was working. **No additional Restore invocation is needed now for this recorded run.** The command above remains the historical/manual cleanup reference.

### Confirmed application evidence

| Item | Recorded result |
| --- | --- |
| Scenario / action | `AdapterDisabled` / `enable_adapter` |
| Incident | NR-00056 |
| Repair request | 35 |
| Before / automatic after check | 625 / 626 |
| Repair record started | 12:22:24 PM, 18 October 2026 |
| Repair record completed | 12:23:22 PM, 18 October 2026 |
| Command status / exit code | `completed` / `0` |
| Verification / success | `verified` / `true` |
| Automatic verification readings | 1 of 3 allowed; settled; no earlier retained readings or reading errors |
| Follow-up adapter / diagnosis | `Wi-Fi` / `healthy` — Network Operational |
| Active incident at evidence capture | None |
| Gateway result | Passed, 4.50 ms |
| Internet result | Passed, 43.37 ms |
| DNS result | Answered, 1,283.02 ms; above the 100 ms display-warning threshold |

Recorded outcome:

> Enable the adapter completed; the follow-up check verified that the target condition cleared.

The slow DNS measurement does not change this run's verified adapter repair into a DNS lookup failure. It remains a displayed speed warning.

### Measured timings for this run

| Measurement | Recorded value | Meaning |
| --- | ---: | --- |
| Complete command phase | 37.787 s | Application's `command_ms`; includes command/process overhead |
| Automatic verification phase | 20.602 s | `elapsed_ms` 20,601.94; includes the follow-up reading |
| Follow-up reading | 20.550 s | Check 626's total measurement duration; part of verification, not an additional phase to add |
| Repair-record timestamp span | Approximately 58 s | 12:22:24–12:23:22; second-resolution timestamps, beginning after the precheck |
| Enable call stage interval | 34.223 s | Diagnostic marker difference: `command_returned` 36,612 ms minus `command_start` 2,389 ms |
| Command time limit | 60 s | Configured maximum; not the actual duration |

The browser click-to-response/HTTP duration and the precheck duration were not supplied for this run. Do not label the approximately 58-second record span as the complete browser repair-request time. Do not add the reading duration to verification again. Earlier October 16 averages describe separate runs; this demonstration has its own timings above.

### Follow-up cleanup confirmation — 18 October 2026, 12:35–12:36 PM

The supplied screenshot at 12:34 PM shows the successful adapter incident **Closed** with the result that automatic follow-up verified recovery after the repair. It also retains the earlier access-denied attempt as a separate closed incident whose repair was not verified as the cause of recovery.

Read-only checks then confirmed:

| Check | Observed result |
| --- | --- |
| Wi-Fi adapter | Up |
| Exact task `NetRescueQA-14a951b5eaf14a2e89aff236a34ebe58` | Absent; exact-name query returned ObjectNotFound |
| Matching QA tasks | No entries returned |
| Matching `NetRescueQA-*` firewall rules | 0 |
| Fresh host DNS lookup for `www.google.com` | Answered |
| Fresh host TCP connection to `1.1.1.1:443` | Connected |
| Latest stored application Check | Still 626, `recovery_after`, healthy |
| Latest stored repair | Still 35, verified; no active incident |

Wi-Fi recovery alone does not prove the runner's teardown is complete; the task/rule checks supplied that additional evidence here. The exact mechanism that removed the task was not established, so this log does not credit either a manual Restore or the watchdog without its output. The successful application repair and the later cleanup confirmation are separate observations.

No new application Check, repair, injection, or Restore was executed during this confirmation. A separate post-cleanup application Check is not recorded; the fresh independent host checks establish connectivity at the later time. Capture a fresh application Check if completing the full acceptance worksheet or before another injection.

### Status and remaining evidence

- [x] Local script loading succeeded with session-scoped `RemoteSigned`.
- [x] Inspect succeeded and reported the intended adapter/settings.
- [x] Previous injection's Restore completion was shown in the transcript.
- [x] Repeated disabled-adapter injection was recorded with a new state path.
- [x] User reported successful repair; stored request 35 independently confirms completion and verified recovery.
- [x] Automatic follow-up passed gateway, Internet, and DNS; no active incident at capture.
- [x] Follow-up confirmed the exact QA task absent and zero QA firewall rules; no repeat Restore command needed for this run.
- [x] Fresh independent host DNS/TCP checks confirmed connectivity after the demonstrated repair and cleanup.
- [ ] Separate post-cleanup application Check ID not captured; add one for a complete formal acceptance worksheet or before the next injection.
- [ ] Restore the previous Automatic checks setting when the demonstration session ends.

**Verdict:** repair demonstration passed; QA cleanup and subsequent host connectivity are confirmed. A dedicated post-cleanup application Check and terminal Restore output were not captured, so this entry does not claim a complete formal acceptance evidence set.

## Append the next demonstration here

Add a new entry after each new successful procedure; do not overwrite Demo 001. Repeats of the same scenario should retain distinct run/state paths and record IDs. A useful entry contains:

| Field | Fill in for the next demonstration |
| --- | --- |
| Demo number / date / scenario | |
| Administrator server and runner confirmed? | |
| Exact successful setup and injection commands | |
| Run ID and StatePath | |
| Healthy baseline and detected check IDs | |
| Incident reference and repair request ID | |
| Expected result and observed result | |
| Command completion / exit code / verification | |
| Command, verification, and request timings with their measurement scope | |
| Exact Restore command and resulting output | |
| Closing Check ID and connectivity measurements | |
| QA tasks/rules cleared? | |
| Final verdict and any pending evidence | |

Normal test history is retained in the existing database. Updating this document does not require a new fault, repair, database reset, migration, or `.env` change.
