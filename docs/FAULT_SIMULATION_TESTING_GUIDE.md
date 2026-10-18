# NetRescue fault simulation testing guide

Use this file to test diagnosis, repair, displayed results, and restored connectivity on the Windows laptop.

**Run one fault at a time: healthy check → inject → Run Check → one offered Try… action → review results → Restore → healthy check.**

**This is a browser-operated demo:** use PowerShell for fault setup and restoration, then click **Run Check** and the named **Try…** repair option yourself in NetRescue. Buttons now name the action, such as **Try enabling adapter** or **Try renewing address**, rather than promising recovery. Leave `-ExerciseApp` off every demo injection command; it would perform those application actions for you. The application's automatic verification after your repair click and the runner's safety rollback remain enabled.

**Every follow-up attempt requires review.** After any earlier repair request for the incident, clicking its next Try… button opens a confirmation dialog. It shows the previous result, the proposed action, what it cannot fix, and any temporary disconnection. Cancel runs nothing. Confirm authorizes that action once; NetRescue still checks first and skips the command if the condition has already cleared. This applies to both a different next action and a retry of the same command. A stale page must review changed repair options before another command can run.

The seven guarded scenarios below passed three times each during the real laptop QA on 16 October 2026. Some tests deliberately leave a fault in place after a repair command: reporting **not verified** is the expected, correct result. Additional lab-only coverage is listed at the end.

For successful demonstrations and their exact evidence, see the [running demo test log](DEMO_TEST_LOG.md). Add each newly demonstrated scenario there without replacing previous results.

## 1. Prepare the laptop and application

- Work locally on the laptop. These tests can temporarily disconnect its network.
- Use **Administrator Windows PowerShell** for both the application server and the fault runner. Administrator rights in the runner alone do not give PHP permission to repair.
- Have MySQL running with the existing NetRescue database.
- Keep exactly one active IPv4 adapter. Disconnect VPNs and alternate network paths during these tests.
- In NetRescue Settings, note the current **Automatic checks** setting, then turn it off. Leave the DNS test domain set to a valid public name, such as `example.com`.
- Keep this guide open locally. Preserve `.env`, the database, and existing history; no reset, reseed, or migration is needed.

If the application server is not already running with administrator rights, start it in the first Administrator PowerShell window:

```powershell
Set-Location C:\xampp\htdocs\NetRescue_CIT_Final
$serverPrincipal = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $serverPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Open Windows PowerShell using Run as administrator, then retry.'
}
& 'C:\xampp\php\php.exe' artisan serve --no-reload --host=127.0.0.1 --port=8000
```

Leave that window running. Open **http://127.0.0.1:8000** in your browser.

If port 8000 is already served by a PHP process without administrator rights, stop that application's server with Ctrl+C in its server window, then relaunch it from the administrator window. An already running PHP process does not gain administrator rights when you open another elevated terminal. Leave MySQL running.

## 2. Prepare the second PowerShell window

Open a second **Administrator Windows PowerShell** window. Run this setup once in that window:

```powershell
Set-Location C:\xampp\htdocs\NetRescue_CIT_Final

# Allow local scripts in this terminal session; answer Y if prompted.
Set-ExecutionPolicy -Scope Process -ExecutionPolicy RemoteSigned

Get-NetAdapter | Select-Object Name, Status

$qaRunner = '.\tools\qa\Invoke-NetRescueFault.ps1'
$qaOptions = @{
    InterfaceAlias = 'Wi-Fi'
    PhpPath        = 'C:\xampp\php\php.exe'
    BaseUrl        = 'http://127.0.0.1:8000'
}

& $qaRunner @qaOptions -Mode Inspect
```

If the adapter has a different name, replace `Wi-Fi` in `$qaOptions` with the exact name shown. Repeat this setup if you open a new terminal window.

The process-scoped execution policy ends when this PowerShell session closes. It permits the local runner to load; it does not grant administrator rights to PHP. If an organization-managed policy still blocks the runner, inspect `Get-ExecutionPolicy -List` before continuing.

Inspect displays the adapter/DHCP/DNS configuration and latest application evidence without injecting a fault. Click **Run Check** in NetRescue and confirm **Network Operational** before proceeding. A successful DNS lookup above the warning threshold can still be shown as slow.

## 3. Follow this cycle for every fault

1. Choose **one** command from section 4. The runner also performs a fresh baseline Check before injection and refuses an unhealthy baseline.
2. Read the printed message **Rollback armed. StatePath: ...** and keep that run's exact `state.json` path available. For the time-sensitive NoIpv4 demo, keep the terminal open and copy it after the repair rather than delaying the browser actions.
3. In NetRescue, click **Run Check** after the runner prints **Applied**. Confirm the new reading's time is after injection; the previously displayed healthy baseline is not a diagnosis of the injected fault. Compare the diagnosis and available repair with the scenario's expected result below.
4. If a repair is offered, request it **once**, let verification finish, and inspect the displayed result and incident details. Record the incident ID and timings.
5. Run **Restore** using section 5, even if NetRescue already repaired the condition. This also clears the QA task.
6. Click **Run Check** again. Require **Network Operational**, successful gateway/Internet/DNS results, and no remaining QA task before starting another fault.

These browser-driven commands leave the injected condition active until repair/restoration or the watchdog. The default watchdog attempts restoration after **10 minutes**, with retries if its execution fails. It is a fallback; explicitly restore after each test.

If a fresh diagnosis check does not observe the injected fault, record **not reproduced** and restore that run before retrying. Retain the check time and results; do not assume why the condition was absent or count the run as a pass.

## 4. Simulation commands and expected results

### A. Adapter switched off — repair should recover it

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault AdapterDisabled
```

- **Change:** disables only the selected adapter.
- **Diagnosis:** adapter turned off; code `adapter_disabled`.
- **Repair:** enable the adapter once and verify reconnection on that adapter.
- **Pass:** target recovery is verified. If another condition remains, the result must name it rather than claim full recovery.
- **Next:** Restore using section 5, then run a closing Check.

### B. Missing DHCP IPv4 address — renewal should recover it

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault NoIpv4
```

- **Change:** releases the selected DHCP adapter's IPv4 lease. DHCP must already be enabled.
- **Diagnosis:** missing IPv4 configuration (`ip_configuration_missing`), or DHCP/APIPA condition (`dhcp_issue`).
- **Repair:** targeted DHCP renewal.
- **Pass:** verification confirms a usable IPv4 address and connectivity. An APIPA address is not proof of recovery.
- **Next:** Restore using section 5, then run a closing Check.

**Timing matters on this laptop.** During the October 18 investigation, release genuinely removed IPv4, Windows then assigned an APIPA address, and a usable DHCP address returned about **35 seconds** after the confirmed release, before any NetRescue repair command. That explains why a later fresh Check can correctly report Network Operational. The 10-minute QA watchdog was still armed; it did not cause that early recovery. This observed interval is not a guaranteed window. Keep the page ready and perform the two browser actions promptly; review the incident details after repair.

The updated runner now requires a simple DHCP baseline, saves the native release result as `noipv4-release.json`, and saves a fresh adapter snapshot as `noipv4-after-release.json`. It prints **NoIpv4 confirmed ... no usable IPv4 address** before **Applied**. If release leaves a usable IPv4 address, or the adapter no longer has the intended Up/DHCP state, it reports **not reproduced** and attempts restoration instead of claiming a successful injection. Additional manual IPv4 addresses are refused before release.

**Manual NoIpv4 demonstration — follow these steps:**

1. Confirm the previous run is restored. Keep the administrator PHP server running, Automatic checks paused, and the local NetRescue page open. Click **Run Check** and confirm **Network Operational**.
2. Run the injection command above **without `-ExerciseApp`**. Wait for **NoIpv4 confirmed** and **Applied NoIpv4**. Keep the terminal open with its new **StatePath** visible. It saves the original settings and arms rollback before releasing the address. It does not request a post-injection diagnosis or repair.
3. Immediately return to NetRescue and click **Run Check**; aim to start within a few seconds of **Applied**. Confirm this is a new reading after injection. Expect **IPv4 Configuration Missing** (`ip_configuration_missing`), or **Possible DHCP Configuration Issue** (`dhcp_issue`) if Windows has assigned APIPA. Wi-Fi can still show **Up** while lacking usable IPv4 configuration. Gateway/Internet/DNS probes should be shown as not tested when the address prerequisite fails.
4. As soon as the result appears, click the offered **Try renewing address** or **Try release and renew** button on the dashboard once. Let NetRescue finish its automatic follow-up checks. Save the detailed incident walkthrough until after this step, so Windows has less time to recover before the repair precheck. A repeat request on the same incident requires the follow-up confirmation described above.
5. Now open the incident and show command completion, exit code **0**, and **verified** recovery on Wi-Fi. The follow-up must show a usable IPv4 address, gateway, and successful connectivity. A Closed label alone does not prove that the repair caused recovery. Record the incident reference, repair request, and displayed timings.
6. Restore this run using its own saved path and section 5. This clears the QA rollback even if NetRescue already renewed the address. If cleanup has already completed, follow the confirmation procedure in section 5 instead.
7. Click **Run Check** again and require **Network Operational**, successful gateway/Internet/DNS results, and no remaining QA tasks or rules. Record that closing Check separately.

If the screen still says **Network Operational**, first distinguish the old baseline from the result of your new **Run Check**. If the new reading is healthy, stop this attempt and restore its exact state path; do not inject again while rollback is armed. Preserve that result as **not reproduced** for investigation.

If the repair reports **Already recovered — no command run**, its precheck found usable configuration before executing a command. Retain that as observed recovery, restore the QA run, and repeat the prepared sequence. Do not count it as a successful repair demonstration. Do not run `ipconfig /renew` yourself before the application's repair, or repeatedly release the address while the repair is running.

The **browser-operated repeat passed** on October 18: incident **NR-00059**, request **38**, detected Check **644**, repair precheck **645**, verified follow-up **646**, and post-Restore closing Check **647**. See Demo 003 in the [running log](DEMO_TEST_LOG.md) for the procedure, timings, and cleanup. Demo 002 remains the earlier automated result.

### C. Missing default gateway — renewal should restore the route

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault GatewayMissing
```

- **Change:** removes the selected adapter's active IPv4 default route.
- **Eligibility:** one unchanged DHCP gateway, no manual/persistent default routes, and the expected route metric. The runner refuses an ambiguous configuration before removing routes.
- **Diagnosis:** missing gateway; code `gateway_missing`.
- **Repair:** targeted DHCP renewal.
- **Pass:** a usable default gateway returns and recovery is verified.
- **Next:** Restore using section 5, then run a closing Check.

### D. Gateway ping blocked — detection only

**Fault 4 manual demo.** This adds a temporary outbound ICMPv4 echo-request block to the selected adapter's gateway. It does not change DHCP, IPv4 addressing, or DNS settings. Internet access can remain usable. This test checks diagnosis and the decision to offer no repair; it does not demonstrate an application repair command.

1. **Prepare.** Use the administrator server and runner setup in sections 1–2. Keep Automatic checks paused. Complete the previous run's Restore and confirm no QA tasks/rules remain. In NetRescue, click **Run Check** and require **Network Operational**, with gateway, Internet, and DNS passing.
2. **Inject one fault.** In the prepared Administrator runner window, run the following command. Leave off `-ExerciseApp` so that you initiate the diagnosis in the browser:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault GatewayPingBlocked
```

Copy the **new StatePath** printed after **Rollback armed** and wait for **Applied GatewayPingBlocked**. The independent safety rollback is normally due in 10 minutes; perform the explicit Restore below after reviewing the result.

3. **Check in the browser.** Click **Run Check** yourself after injection and verify the fresh reading. Open the detected incident and compare the result with this table:

| Item | Expected result |
| --- | --- |
| Diagnosis | **Gateway Ping Unanswered; Internet Available** (`gateway_ping_unanswered`) |
| Adapter and configuration | Wi-Fi remains Up; usable IPv4 address and gateway remain present |
| Gateway test | Ping unanswered; no measured gateway response time |
| Internet test | External TCP connectivity passes |
| DNS test | Lookup answers; a slow-answer warning alone is acceptable |
| Available repair | **None**; no DHCP renewal or DNS flush is indicated |
| Interpretation | Ping alone does not establish a router or Internet outage |

Do not request a repair through an older incident. For this controlled simulation, Restore removes the deliberately added QA rule. A real router may intentionally decline ping responses; reviewing its ICMP configuration is only needed if gateway ping monitoring is desired.

4. **Restore this run.** Replace the placeholder below with the exact StatePath from step 2, not a path from Fault 3:

```powershell
$fault4StatePath = 'C:\ProgramData\NetRescueQA\<fault-4-run-id>\state.json'
& $qaRunner -Mode Restore -StatePath $fault4StatePath
```

Expect **Restored QA changes for Wi-Fi**. This removes the run-owned ping-block rule and clears its rollback task. If Restore reports an error, retain the state/task and follow section 5 before testing again.

5. **Verify the final state.** Click **Run Check** again in NetRescue. Require **Network Operational**, gateway ping answering, and Internet/DNS passing. In the **Administrator runner window**, check cleanup:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both queries should return no QA entries after successful cleanup. Do not infer cleanup from Wi-Fi being Up or the incident being Closed alone.

6. **Record the demonstration.** Save the baseline/detected/closing Check IDs, incident reference, new StatePath, displayed diagnosis and lack of a repair, Restore output, and cleanup results in **Demo 005** of the [running demo log](DEMO_TEST_LOG.md). The current manual demonstration is **pending** until those results are supplied. Recovery after removing the rule is observed by a Check; record the repair command and repair duration as **not applicable**, not as a verified application repair.

If Internet or DNS also fails, or a fresh reading never observes the intended ping-only condition, retain the actual result and restore before retrying. Do not count that attempt as the expected scenario passing.

### E. Gateway and external targets blocked — ineffective repair must stay unverified

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault GatewayTestsBlocked
```

- **Change:** adds a temporary block to the gateway and configured external test target IPs.
- **Diagnosis:** possible local gateway/path issue; code `gateway_unreachable`.
- **Repair:** request the offered DHCP renewal once.
- **Pass:** while the block remains, a command can complete successfully but its repair must remain **not verified**. The application must not claim that renewal cleared the block.
- **Next:** Restore removes the QA rule; run a closing Check. Later observed recovery must not be falsely credited to the earlier unverified repair.

For the manual Fault 5 demo, click **Run Check** after injection, then **Try renewing address** once. While the injected block remains, the command may complete but the follow-up should report that the attempt did not clear the condition. DNS is **not tested** when the external connectivity prerequisite fails; it is not a separately measured DNS failure.

If **Try restarting adapter** appears next, it is an optional escalation, not proof that renewal succeeded or a promise that restarting will fix the cause. Clicking it opens **Review another repair attempt**, explaining the earlier result and the brief disconnection. For this controlled firewall test, **Cancel** and use the saved-state Restore instead. After Restore, a fresh healthy Check records observed recovery; it must not credit the earlier ineffective renewal. Do not repeatedly request repairs to force a successful result.

The October 18 repeat is recorded in **Demo 006** of the [running log](DEMO_TEST_LOG.md#demo-006--fault-5-repeat-with-named-repair-buttons-and-follow-up-popup): NR-00065, requests 44 and 45. The operator proceeded with restart in that run; both commands completed but stayed unverified, followed by healthy Check 683 and observed recovery. The popup is operator-confirmed; exact Restore output and elevated cleanup evidence were not captured.

### F. External test targets blocked — detection and manual guidance

**Fault 6 manual demo.** This temporarily blocks only the configured external test target IPs on the selected adapter. The local gateway should remain reachable. It tests appropriate manual guidance and refusal to offer an unsupported repair; it does not demonstrate or prove an ISP outage.

1. Keep the administrator PHP server and MySQL running, with Automatic checks paused. In Administrator PowerShell, confirm the previous run's QA tasks and firewall rules are absent using section 5. Click **Run Check** in NetRescue and require **Network Operational**, with gateway, Internet, and DNS passing.
2. In the prepared Administrator runner window, inject one fault without `-ExerciseApp`:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault WanTargetsBlocked
```

3. Keep the **new StatePath** printed after **Rollback armed**, and wait for **Applied WanTargetsBlocked**. The default safety rollback is due after 10 minutes; complete the browser work and explicit Restore before that deadline.
4. Click **Run Check** yourself and inspect the new reading:

| Item | Expected result |
| --- | --- |
| Diagnosis | **Possible WAN / ISP Connectivity Issue** (`wan_unreachable`); the incident list may say **Internet path failed** |
| Adapter/configuration | Wi-Fi Up; usable IPv4 and gateway remain present |
| Gateway probe | Passes; a response time is measured |
| Internet probe | Configured external TCP tests fail |
| Name lookup | **Not tested** because the external connectivity prerequisite failed |
| Repair button / popup | None for this condition; manual guidance is expected |
| Meaning | The external path or targets could not be reached; these observations do not establish an ISP outage |

Do not use a repair button on an older incident. If gateway also fails, or the fresh reading stays healthy, retain that actual result and restore instead of counting it as the intended Fault 6 pass.

5. Restore this run's saved state in the Administrator runner window:

```powershell
$fault6StatePath = 'C:\ProgramData\NetRescueQA\<new-fault-6-run-id>\state.json'
& $qaRunner -Mode Restore -StatePath $fault6StatePath
```

Replace the placeholder with the exact path printed by this injection. Expect **Restored QA changes for Wi-Fi**. If restoration fails, retain the state/task and follow section 5 before another fault.

6. Click **Run Check** again; require **Network Operational** with gateway, Internet, and DNS passing. Run the section 5 task/rule queries from Administrator PowerShell; both should return no QA entries. Record the new StatePath, baseline/detection/closing Check IDs, incident reference, diagnosis, lack of repair, Restore output, and cleanup results in **Demo 007** of the running log after the test. Repair command/duration are **not applicable**; recovery is observed after cleanup.

The October 18 demonstration is recorded in **Demo 007** of the [running log](DEMO_TEST_LOG.md#demo-007--fault-6-detected-saved-state-restore-and-host-connectivity-confirmed): NR-00066, detected Check 685, explicit Restore output, and subsequent host TCP/DNS connectivity. Later healthy Check **686** closed the incident as observed recovery and supplies the next scenario's baseline; its on-demand source alone does not establish a separate browser click. A separate elevated cleanup listing was not captured. Working websites alone do not establish teardown: this fault blocks only the configured target IPs.

### G. Unreachable DNS servers — cache flush cannot fix the injected settings

**Fault 7 manual demo.** The runner saves the selected adapter's original IPv4/IPv6 DNS settings, arms rollback, sets unreachable documentation-range DNS servers (`192.0.2.53` and `2001:db8::53`), and clears the cache. This tests a DNS-specific diagnosis and honest reporting that clearing the cache cannot replace incorrect manual server addresses.

1. Keep the administrator PHP server and MySQL running, Automatic checks paused, and a valid lookup name such as the existing `www.google.com` unchanged. Finish the previous Restore and cleanup. Click **Run Check** and require **Network Operational**, with gateway, Internet, and DNS passing.
2. In the prepared Administrator runner window, inject one fault without `-ExerciseApp`:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault DnsServer
```

3. Save the **new StatePath** printed after **Rollback armed**, and wait for **Applied DnsServer**. The default safety rollback is due after 10 minutes. Complete the demo and explicit Restore before that deadline.
4. Click **Run Check** yourself and inspect the fresh reading:

| Item | Expected result |
| --- | --- |
| Diagnosis | **DNS Resolution Failure** (`dns_failure`) |
| Adapter/configuration | Wi-Fi Up; usable IPv4 and gateway retained |
| Gateway probe | Passes |
| Internet probe | Configured external TCP connectivity passes |
| Name lookup | **Fails**; this is a measured lookup failure, not **Not tested** |
| Initial action | **Try clearing DNS cache** |

5. Click **Try clearing DNS cache** once and wait for automatic verification. The command should complete, but recovery should remain **not verified** while the injected manual DNS addresses remain. Expect wording that this attempt did not clear **DNS Resolution Failure**, followed by guidance to correct the manually set DNS server. In this intended outcome, no second repair button/popup is expected: renewal cannot replace manual DNS. A command error, skipped repair, or already-recovered precheck is a different outcome and must be recorded as such.
6. Restore the exact saved DNS settings using the current run's printed path. Use the PowerShell `&` call operator:

```powershell
& $qaRunner -Mode Restore -StatePath 'C:\ProgramData\NetRescueQA\<new-fault-7-run-id>\state.json'
```

Replace the placeholder with this injection's exact StatePath; do not reuse Fault 6's path. Expect **Restored QA changes for Wi-Fi**. Restore verifies each family's original automatic/manual mode and the exact ordered manual server lists, and writes `dns-restored.json` in that run directory. Do not substitute a generic DNS reset or set everything to automatic; that could overwrite the original manual configuration.

7. Click **Run Check** again and require **Network Operational**, with gateway/Internet/DNS passing. A slow successful lookup is a warning, not a DNS failure. Run the section 5 task/rule queries from Administrator PowerShell and require no QA entries. Record the StatePath, Check IDs, incident reference, command exit code and displayed timings, Restore output, closing Check, and cleanup evidence in **Demo 008** after the test.

If the injected lookup still passes, the fault was not reproduced; restore and retain that outcome. If gateway or Internet also fails, record the different condition and restore rather than counting a DNS-specific pass. If Restore fails, retain the state/task, review `restore-errors.txt`, and retry the same Restore after addressing the error; do not continue testing.

The October 18 demonstration is recorded in **Demo 008** of the [running log](DEMO_TEST_LOG.md#demo-008--fault-7-dns-failure-detected-cache-flush-correctly-unverified-connectivity-restored): NR-00067, request 46, detected Check 687, precheck 688, unverified follow-up 689, and healthy closing Check 690. The cache flush completed in **0.272 s**; verification took **12.788 s**. The reported DNS server list returned to baseline and fresh host connectivity passed. Exact Restore output, protected per-family restore report, and a separate elevated cleanup listing were not captured; do not treat those as supplied evidence.

The firewall cases simulate the test observations. They do not establish a real router or ISP failure. These seven cases exercise successful repair, appropriate refusal to repair, and truthful reporting of an ineffective repair.

## 5. Restore and verify before continuing

Replace the placeholder with the **exact StatePath printed by the current run**:

```powershell
$faultStatePath = 'C:\ProgramData\NetRescueQA\<actual-run-id>\state.json'
& $qaRunner -Mode Restore -StatePath $faultStatePath
```

Use the saved state rather than replacing DNS with a generic automatic setting. The laptop may have manual IPv4 DNS and automatic IPv6 DNS; Restore uses the original per-family configuration.

In the **Administrator runner window**, check for remaining QA tasks and firewall rules. A non-elevated process may not reveal protected tasks:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both should return no QA entries after successful restoration. Then click **Run Check** in NetRescue and confirm gateway, Internet, and DNS succeed. A slow DNS warning alone is different from a failed lookup.

**If Wi-Fi already recovered automatically:** a verified repair confirms that the application verified its repair. A Closed incident can also mean that recovery was only observed by a later Check; read its resolution text. Also check the QA tasks/rules above. If cleanup has already happened and connectivity is confirmed, an extra Restore invocation is unnecessary; record those checks. If a QA task remains, use that run's exact saved-state Restore command. The runner's watchdog and the application's repair are separate mechanisms, so do not infer which one restored connectivity from Wi-Fi being Up alone.

**If restoration fails:** stop testing. Keep the state file and task. Read `restore-errors.txt` in that run's directory, address the reported issue, and retry the same Restore command. Do not remove the task to bypass the next-test guard. Successful command completion alone does not prove connectivity is restored.

DHCP renewal can legitimately produce a different leased IPv4 address. Automatic DNS server lists can also change with DHCP. Preservation means retaining the original configuration modes and restoring saved manual settings, not guaranteeing the same future lease.

## 6. Historical automated QA reference

The runner also supports `-ExerciseApp` for automated regression QA. It starts diagnosis, requests at most one eligible repair, captures evidence, restores in `finally`, and performs a closing Check. **Do not add this switch to the manual demo commands in section 4.**

The [measured laptop QA results](WINDOWS_LAPTOP_QA_RESULTS_2026-10-16.md) and Demo 002 in the [running log](DEMO_TEST_LOG.md) retain those automated results. They establish their recorded outcomes; they do not establish that a presenter clicked the browser buttons.

## 7. Evidence, timings, and a reusable results sheet

Each injection stores its state and baseline in:

```text
C:\ProgramData\NetRescueQA\<run-id>\
```

For this browser-operated guide, record your browser observations/screenshots, check IDs, incident reference, and displayed repair timings yourself. The runner saves the state and baseline but does not automatically capture your later browser actions. NoIpv4 additionally saves its release output/timing and immediate adapter snapshot; its state includes the safety rollback deadline. DNS restoration records `dns-restored.json` after successful verification. Historical automated runs using `-ExerciseApp` additionally save detected/restored evidence, rendered pages, repair evidence when requested, and `http-timings.json`.

HTTP repair duration includes the precheck, command, verification, and response. Command duration is only one part of that total. Historical mean repair-request timings on this laptop were 21.527 s for adapter enable, 6.923 s for released IPv4 renewal, and 4.632 s for missing-gateway renewal. These are comparison points, not deadlines or guarantees.

Copy this worksheet for each run. Repeat accepted scenarios three times if reproducing the acceptance QA:

| Item | Record |
| --- | --- |
| Date/time and fault | |
| Adapter and run/state path | |
| Healthy baseline check ID | |
| Detected check ID / incident reference | |
| Expected diagnosis / actual diagnosis | |
| Repair offered / command requested | |
| Command result / verification result | |
| Repair request / command / verification durations | |
| Displayed result matches evidence? | |
| Restore completed? | |
| Closing check ID / gateway / Internet / DNS | |
| No remaining QA tasks or rules? | |
| Verdict: pass / fail / not reproduced | |
| Evidence location and notes | |

A pass requires the expected diagnosis and repair behavior, truthful displayed results, successful restoration, and a healthy closing Check. An intentionally ineffective repair can pass the test when the app correctly labels it unverified and gives the appropriate next step.

## 8. Other conditions and software-only simulations

There are no guarded injection commands in this runner for the following real-world acceptance cases:

| Condition | Appropriate test |
| --- | --- |
| No Wi-Fi link / unplugged cable | Disconnect locally, Run Check, verify reconnect guidance, reconnect, then Run Check again |
| Missing adapter / driver | Disposable VM or spare hardware; verify hardware/driver guidance |
| Genuine APIPA from unavailable DHCP | Isolated DHCP lab; a manually assigned 169.254 address is not equivalent |
| Bad static IPv4 / gateway | Disposable VM with known lab settings; verify manual-correction guidance |
| High gateway latency | Controlled delay in a lab; compare measurements against the warning threshold |
| DHCP-provided DNS remains broken | Controlled DHCP/DNS lab; verify bounded flush/renew escalation and manual handoff |
| Multiple adapters / VPN routing | Separate routing test plan; the runner requires one active IPv4 path |

For synthetic code-path tests, run the fault matrix from the project directory:

```powershell
& 'C:\xampp\php\php.exe' artisan test --filter=FaultMatrixTest
```

The checked-in `phpunit.xml` configures SQLite `:memory:`. Use that testing configuration without a cached live configuration or overriding database environment variables. The matrix mocks Windows/network boundaries and covers detection, repairs, timeouts, command failures, persistent faults, partial recovery, and escalation. It does not inject real laptop faults or establish physical acceptance for the lab cases.

## 9. Finish the session

- Restore the final run and obtain a healthy closing Check.
- Confirm no QA tasks or firewall rules remain.
- Restore the previous **Automatic checks** setting and any VPN/alternate connection you intentionally disconnected.
- Retain the run directories and recorded results for review.

Real checks and repairs append normal database history. The runner does not edit `.env`, reset/reseed the database, or remove existing history. Monitor settings you intentionally change during preparation need to be restored by you. Stop only an application server you started specifically for testing when you finish.

References: [runner details](WINDOWS_FAULT_QA.md) and [measured laptop QA results](WINDOWS_LAPTOP_QA_RESULTS_2026-10-16.md).
