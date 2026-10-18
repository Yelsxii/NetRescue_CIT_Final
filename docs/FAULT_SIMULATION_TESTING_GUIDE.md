# NetRescue fault simulation testing guide

Use this file to test diagnosis, repair, displayed results, and restored connectivity on the Windows laptop.

**Run one fault at a time: healthy check → inject → Run Check → inspect → one offered repair → Restore → healthy check.**

The seven guarded scenarios below passed three times each during the real laptop QA on 16 October 2026. Some tests deliberately leave a fault in place after a repair command: reporting **not verified** is the expected, correct result. Additional lab-only coverage is listed at the end.

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
& 'C:\xampp\php\php.exe' artisan serve --no-reload --host=127.0.0.1 --port=8000
```

Leave that window running. Open **http://127.0.0.1:8000** in your browser.

## 2. Prepare the second PowerShell window

Open a second **Administrator Windows PowerShell** window. Run this setup once in that window:

```powershell
Set-Location C:\xampp\htdocs\NetRescue_CIT_Final

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

Inspect displays the adapter/DHCP/DNS configuration and latest application evidence without injecting a fault. Click **Run Check** in NetRescue and confirm **Network Operational** before proceeding. A successful DNS lookup above the warning threshold can still be shown as slow.

## 3. Follow this cycle for every fault

1. Choose **one** command from section 4. The runner also performs a fresh baseline Check before injection and refuses an unhealthy baseline.
2. Read the printed message **Rollback armed. StatePath: ...**. Copy that run's exact `state.json` path before continuing.
3. In NetRescue, click **Run Check**. Compare the diagnosis and available repair with the scenario's expected result below.
4. If a repair is offered, request it **once**, let verification finish, and inspect the displayed result and incident details. Record the incident ID and timings.
5. Run **Restore** using section 5, even if NetRescue already repaired the condition. This also clears the QA task.
6. Click **Run Check** again. Require **Network Operational**, successful gateway/Internet/DNS results, and no remaining QA task before starting another fault.

These browser-driven commands leave the injected condition active until repair/restoration or the watchdog. The default watchdog attempts restoration after **10 minutes**, with retries if its execution fails. It is a fallback; explicitly restore after each test.

If a fault disappears before your diagnosis check, record **not reproduced** and restore it. DHCP can reacquire an address or route quickly. Do not count that run as a pass or inject a second fault over it.

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

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault GatewayPingBlocked
```

- **Change:** adds a temporary outbound gateway ICMP echo block.
- **Diagnosis:** gateway ping unanswered while Internet and DNS remain available; code `gateway_ping_unanswered`.
- **Repair:** none offered.
- **Pass:** the display explains the ping limitation without treating it as a proven Internet outage.
- **Next:** Restore removes the QA rule; run a closing Check.

### E. Gateway and external targets blocked — ineffective repair must stay unverified

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault GatewayTestsBlocked
```

- **Change:** adds a temporary block to the gateway and configured external test target IPs.
- **Diagnosis:** possible local gateway/path issue; code `gateway_unreachable`.
- **Repair:** request the offered DHCP renewal once.
- **Pass:** while the block remains, a command can complete successfully but its repair must remain **not verified**. The application must not claim that renewal cleared the block.
- **Next:** Restore removes the QA rule; run a closing Check. Later observed recovery must not be falsely credited to the earlier unverified repair.

### F. External test targets blocked — detection and manual guidance

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault WanTargetsBlocked
```

- **Change:** temporarily blocks the configured external target IPs while retaining the local gateway path.
- **Diagnosis:** possible WAN/external path or target issue; code `wan_unreachable`.
- **Repair:** none offered; manual upstream/firewall/target guidance is expected.
- **Pass:** the result describes the failed observations without claiming a proven ISP outage.
- **Next:** Restore removes the QA rule; run a closing Check.

### G. Unreachable DNS servers — cache flush cannot fix the injected settings

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault DnsServer
```

- **Change:** sets unreachable documentation-range IPv4/IPv6 DNS servers on the selected adapter and clears the cache.
- **Diagnosis:** DNS resolution failure; code `dns_failure`.
- **Repair:** request one cache flush. It can complete but cannot correct the injected manual DNS addresses.
- **Pass:** the repair remains **not verified**, followed by manual DNS correction guidance. Repeating the same ineffective repair should not be offered.
- **Next:** use Restore to recover the saved DNS settings, including each family's automatic/manual mode and ordered manual server list. Run a closing Check.

The firewall cases simulate the test observations. They do not establish a real router or ISP failure. These seven cases exercise successful repair, appropriate refusal to repair, and truthful reporting of an ineffective repair.

## 5. Restore and verify before continuing

Replace the placeholder with the **exact StatePath printed by the current run**:

```powershell
$faultStatePath = 'C:\ProgramData\NetRescueQA\<actual-run-id>\state.json'
& $qaRunner -Mode Restore -StatePath $faultStatePath
```

Use the saved state rather than replacing DNS with a generic automatic setting. The laptop may have manual IPv4 DNS and automatic IPv6 DNS; Restore uses the original per-family configuration.

Check for remaining QA tasks and firewall rules:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both should return no QA entries after successful restoration. Then click **Run Check** in NetRescue and confirm gateway, Internet, and DNS succeed. A slow DNS warning alone is different from a failed lookup.

**If restoration fails:** stop testing. Keep the state file and task. Read `restore-errors.txt` in that run's directory, address the reported issue, and retry the same Restore command. Do not remove the task to bypass the next-test guard. Successful command completion alone does not prove connectivity is restored.

DHCP renewal can legitimately produce a different leased IPv4 address. Automatic DNS server lists can also change with DHCP. Preservation means retaining the original configuration modes and restoring saved manual settings, not guaranteeing the same future lease.

## 6. Optional: let the runner perform the full test

This mode runs diagnosis, requests at most one eligible repair, captures evidence, restores in `finally`, and performs a closing Check. Use it when you want automated execution instead of clicking through each phase.

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault AdapterDisabled -ExerciseApp -RollbackSeconds 1800
```

Change only `AdapterDisabled` to another supported fault from section 4. Run one invocation, review its closing result, and confirm restoration before the next. The 1800-second watchdog matches the supervised laptop QA runs. The runner normally restores as soon as its work finishes; it does not wait 30 minutes.

If the expected diagnosis is not reproduced, the automated runner reports that mismatch, sends no repair request, and attempts restoration. Record **not reproduced**, not pass. A warning that the closing diagnosis is not healthy requires investigation before continuing.

## 7. Evidence, timings, and a reusable results sheet

Each injection stores its state and baseline in:

```text
C:\ProgramData\NetRescueQA\<run-id>\
```

With `-ExerciseApp`, the directory also contains detected/restored evidence and rendered pages, repair evidence when a repair was requested, and `http-timings.json`. DNS restoration records `dns-restored.json` after successful verification. In browser-driven mode, record your observations/screenshots yourself; it does not automatically capture those later browser actions.

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
