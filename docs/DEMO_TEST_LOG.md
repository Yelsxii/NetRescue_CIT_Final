# NetRescue running demo test log

This is the running record of demonstrated commands and procedures. Append the next demonstration as Demo 003, then continue numbering. Keep earlier successes, failed attempts, and unresolved cleanup items visible. The [fault simulation testing guide](FAULT_SIMULATION_TESTING_GUIDE.md) contains the complete scenario list.

Dates and times below use **Asia/Manila (UTC+08:00)**. A successful repair requires command completion and successful automatic verification on the same adapter. Record teardown confirmation separately from repair success.

**Preferred live demo procedure:** inject one fault in PowerShell without `-ExerciseApp`, click **Run Check** in the browser, inspect the incident, click **Run repair** once if offered, and let the application verify it. Restore the QA run, then click a closing **Run Check** and confirm task/rule cleanup. The runner's prerequisite baseline Check and safety rollback still run automatically. Demo 001 used the browser procedure; Demo 002 below retains an automated success. **A successful browser-operated NoIpv4 repeat has not yet been recorded.**

## Demonstration index

| Demo | Date | Scenario | Stored incident / repair | Repair result | Teardown confirmation |
| --- | --- | --- | --- | --- | --- |
| 001 | 18 October 2026 | Wi-Fi adapter disabled — browser-operated | NR-00056 / request 35 | Verified; exit 0; Network Operational | QA task/rules absent and host connectivity confirmed at 12:35–12:36 PM; separate post-cleanup app Check not captured |
| 002 | 18 October 2026 | DHCP IPv4 lease released (`NoIpv4`) — automated | NR-00057 / request 36 | Verified renewal; exit 0; Network Operational | Runner printed Restore completed; post-Restore Check 634 healthy |

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

## Demo 002 — missing DHCP IPv4 repaired successfully (automated run)

**Historical automated result: successful diagnosis, verified DHCP renewal, restoration, and a healthy closing application Check. This was not a browser-operated demonstration.**

The user supplied the successful automated run's terminal output. Read-only queries of the stored application evidence at **12:58 PM on 18 October 2026** confirmed incident **NR-00057**, repair request **36**, detected Check **631**, pre-repair Check **632**, verified follow-up **633**, and post-Restore closing Check **634**. This entry was documented without executing another fault, repair, Check, or Restore.

### Historical automated command and setup

Keep the NetRescue PHP server running in **Administrator PowerShell window 1**, MySQL running, and Automatic checks paused. Use one active IPv4 path. The setup and administrator check in Demo 001 apply here too.

In **Administrator PowerShell window 2**, the successful automated run used this sequence. It is retained as evidence of what actually ran, rather than the procedure for the next manual demo:

```powershell
Set-Location C:\xampp\htdocs\NetRescue_CIT_Final
Set-ExecutionPolicy -Scope Process -ExecutionPolicy RemoteSigned

$qaRunner = '.\tools\qa\Invoke-NetRescueFault.ps1'
$qaOptions = @{
    InterfaceAlias = 'Wi-Fi'
    PhpPath        = 'C:\xampp\php\php.exe'
    BaseUrl        = 'http://127.0.0.1:8000'
}

& $qaRunner @qaOptions -Mode Apply -Fault NoIpv4 -ExerciseApp
```

Require the previous QA run to be restored before invoking this command. The runner checks a fresh healthy baseline, arms its default 10-minute watchdog, releases Wi-Fi's DHCP IPv4 lease, immediately proceeds to diagnosis, requests one eligible repair, restores in `finally`, and records a closing Check. It normally finishes and restores well before the watchdog deadline.

This mode uses the normal application Check and Repair routes. The runner starts those requests automatically; no browser click is needed to initiate them. The stored Check source still reads `manual` for baseline, detection, and the closing Check because those use the same on-demand route as **Run Check**. Repair precheck/follow-up use `recovery_before` and `recovery_after`.

This run records backend and rendered-response evidence; a screenshot of a browser presentation was not supplied. For the next live demonstration, use the manual NoIpv4 procedure in [section 4B of the testing guide](FAULT_SIMULATION_TESTING_GUIDE.md#b-missing-dhcp-ipv4-address--renewal-should-recover-it): omit `-ExerciseApp`, then click **Run Check** and **Run repair** yourself. Record that repeat separately after it succeeds; retain this automated result and its original IDs.

### Exact successful terminal output

```text
Rollback armed. StatePath: C:\ProgramData\NetRescueQA\18fd7d27141446eda9c4897619bb4b8c\state.json
Applied NoIpv4 to Wi-Fi. Open http://127.0.0.1:8000 to observe the incident.
Renew the address completed; the follow-up check verified that the target condition cleared.
Restored QA changes for Wi-Fi. Run NetRescue Check to verify connectivity; rollback command completion alone is not proof.
Closing diagnosis: healthy. Evidence: C:\ProgramData\NetRescueQA\18fd7d27141446eda9c4897619bb4b8c
```

The closing line follows the runner's actual post-Restore Check. Together with the stored verified repair, this establishes more than command completion alone.

### Recorded sequence and expected results

All times are on **18 October 2026, Asia/Manila**.

| Stage | Check / time | Recorded condition |
| --- | --- | --- |
| Fresh baseline | 630 / 12:56:06 PM | Wi-Fi, usable IPv4 `192.168.1.5`, gateway `192.168.1.1`, healthy |
| Fault detected | 631 / 12:56:11 PM | Wi-Fi remained Up with DHCP; IPv4 and gateway absent; `ip_configuration_missing` |
| Repair precheck | 632 / 12:56:14 PM | Same Wi-Fi adapter; IPv4 and gateway still absent; fault confirmed before repair |
| Automatic follow-up | 633 / 12:56:18 PM | IPv4 and gateway returned; gateway/Internet/DNS passed; healthy |
| After Restore | 634 / 12:56:25 PM | Healthy, gateway/Internet/DNS passed; no active incident at evidence capture |

The gateway, Internet, and DNS probes in checks 631 and 632 were **skipped** because usable IPv4 configuration was missing. Their stored false boolean fields do not represent three independently measured probe failures; `probe_states` records the actual skipped states.

| Repair evidence | Recorded result |
| --- | --- |
| Incident / diagnosis | NR-00057 / `ip_configuration_missing` — IPv4 Configuration Missing |
| Incident opened / resolved | 12:56:11 PM / 12:56:18 PM |
| Incident status / resolution kind | `resolved` (Closed) / `verified_repair` |
| Repair request / action | 36 / `renew_dhcp` — Renew the address |
| Command | Targeted `ipconfig.exe /renew Wi-Fi` |
| Command status / exit code | `completed` / `0` |
| Verification / success | `verified` / `true` |
| Verification policy | 1 of 3 allowed readings; settled; no earlier retained readings or reading errors |
| Automatic after Check | 633: gateway 2.00 ms, Internet 99.71 ms, DNS answered in 237.11 ms |
| Closing Check | 634: gateway 3.50 ms, Internet 17.67 ms, DNS answered in 288.37 ms |

DNS answered successfully in both readings but exceeded the configured 100 ms display-warning threshold. This remains a speed warning, not a failed lookup or a failed IPv4 repair.

### Measured timings

| Measurement | Recorded value | Scope |
| --- | ---: | --- |
| Initial fault-detection reading | 1.638 s | Check 631's total reading duration |
| Repair precheck reading | 0.650 s | Check 632's total reading duration; before the repair record's started timestamp |
| Complete command phase | 2.726 s | `command_ms` 2,726.20; includes process overhead |
| Automatic verification phase | 1.803 s | `elapsed_ms` 1,803.38 |
| Automatic after reading | 1.789 s | Check 633's reading duration; already included in verification |
| Repair-record timestamp span | Approximately 4 s | 12:56:14–12:56:18, second-resolution timestamps; excludes precheck and HTTP overhead |
| Closing reading | 2.140 s | Check 634's total reading duration, after Restore |
| Command time limit | 35 s | Configured maximum, not the actual duration |

These phase timings are read from the stored checks and repair record. The runner records HTTP repair-request timing in `http-timings.json` under the protected run directory; that file was not read by the documentation process. No browser click-to-response duration is claimed. Do not add the after-reading duration to verification again.

### Restore and teardown

Run directory and evidence:

```text
C:\ProgramData\NetRescueQA\18fd7d27141446eda9c4897619bb4b8c\
```

For this successful invocation, `-ExerciseApp` already executed Restore and a healthy closing Check. **An extra Restore command is not required merely to complete the sequence shown above.** If cleanup needs to be retried for this exact run, its idempotent saved-state command is:

```powershell
& $qaRunner -Mode Restore -StatePath 'C:\ProgramData\NetRescueQA\18fd7d27141446eda9c4897619bb4b8c\state.json'
```

Future repetitions receive a new state path. The runner's Restore requests removal of the rollback task after restoration. A separate Administrator-terminal task/rule listing was not supplied for this entry; retain one if completing the full acceptance worksheet or before another injection:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Expect no matching QA entries. Perform these checks from the Administrator runner window; results from a non-elevated process may not reveal protected tasks. This NoIpv4 test creates no firewall block.

### Earlier attempts retained as context

- An earlier attempt could not reach the stopped PHP server on port 8000. It failed before injection and was not a fault test pass.
- The browser-driven run `6a2e551c72b648019da27f3bf0cf30c0` printed that NoIpv4 was applied, and a second injection was correctly refused while its rollback was armed. The later observed check 629 was healthy with a usable DHCP IPv4 address. That attempt did not demonstrate a captured missing-address diagnosis or a DHCP repair. Its exact reason for returning healthy was not established; no automatic-reacquisition or watchdog explanation is asserted.
- The successful run `18fd7d27141446eda9c4897619bb4b8c` used `-ExerciseApp` to capture the actual missing-address condition and verified renewal before restoration. The earlier attempt is not counted as this success.

### Automated run verdict

- [x] A fresh healthy baseline was recorded.
- [x] Missing IPv4 and gateway were detected while Wi-Fi remained Up.
- [x] The repair precheck independently confirmed that the same fault persisted.
- [x] Targeted DHCP renewal completed with exit code 0.
- [x] Automatic verification confirmed recovery on Wi-Fi; incident closed as verified repair.
- [x] Restore completion was printed, followed by healthy closing Check 634.
- [ ] Successful browser-operated NoIpv4 repeat pending; use a new run/state path and record its own evidence.
- [ ] Separate Administrator-terminal task/rule listing not captured for this entry.
- [ ] Restore the previous Automatic checks setting when the demonstration session ends.

**Verdict:** Demo 002's automated diagnosis, repair, verification, restoration, and closing connectivity Check passed. Administrative task-list evidence was not captured. A browser-operated NoIpv4 repeat remains pending and is not counted as a manual demo pass.

## Append the next demonstration here

Add Demo 003 after the next new successful procedure; retain Demo 001 and Demo 002. Repeats of the same scenario should retain distinct run/state paths and record IDs. A useful entry contains:

| Field | Fill in for the next demonstration |
| --- | --- |
| Demo number / date / scenario | |
| Execution mode: browser-operated or automated | |
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
