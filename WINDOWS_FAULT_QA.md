# Run real fault QA on the Windows laptop

The changes were tested in Linux with simulated Windows boundaries. These
instructions and scripts have **not yet been executed on the user's laptop**.
The scripts run locally; ChatGPT's cloud terminal has no PowerShell or desktop
connection to that laptop.

## Prepare

Use the `codex/fault-qa-repair-verification` branch in your existing project. Keep
your `.env`, database and local history. Do not reset or reseed your database.

Start NetRescue from **Administrator PowerShell**, so the app's PHP process has
the permissions required for repairs:

```powershell
php artisan serve --no-reload --host=127.0.0.1 --port=8000
```

In NetRescue Settings, pause scheduled checks during these controlled tests.
Run Check and Repair still work while scheduling is paused. Use exactly one
active IPv4 adapter and disconnect active VPNs/alternate paths for this runner.
If PHP is not on PATH, use `C:\xampp\php\php.exe` in place of `php` and supply
`-PhpPath C:\xampp\php\php.exe` to the runner.

In a second Administrator PowerShell window, change to the project directory:

```powershell
.\tools\qa\Invoke-NetRescueFault.ps1 -Mode Inspect -InterfaceAlias "Wi-Fi"
```

This displays network settings and the application's latest evidence without
injecting a fault. Substitute the exact adapter alias if yours differs. Scripts
use Windows PowerShell-compatible syntax; no Pester installation is needed.

## Execute one scenario

```powershell
.\tools\qa\Invoke-NetRescueFault.ps1 -Mode Apply -Fault DnsServer -InterfaceAlias "Wi-Fi" -ExerciseApp
```

With `-ExerciseApp`, the runner:

1. Uses the normal Check route and requires a freshly healthy baseline.
2. Saves the adapter GUID, DHCP/DNS modes and gateway information in a protected
   `%ProgramData%\NetRescueQA\<run-id>` folder.
3. Arms a SYSTEM rollback task for ten minutes later **before changing the network**.
4. Injects one fault; uses a fresh CSRF token to POST to the normal Check route.
5. Requires the expected diagnosis before requesting a repair. If repair is
   available, uses the normal Repair route **once**, then captures its result.
6. Restores the injected settings in `finally`, runs a closing Check, and saves
   evidence. A non-healthy closing diagnosis is a warning requiring review.

The independent task also attempts restoration if the terminal is closed; failed
task executions have three one-minute retries. This cannot guarantee recovery
from a real hardware, router, power, or operating system failure.

Without `-ExerciseApp`, the fault remains until you repair/reconnect/restore it or
the watchdog runs. Use that mode only when you want to click through the browser
yourself. The exact state path is printed before injection:

```powershell
.\tools\qa\Invoke-NetRescueFault.ps1 -Mode Restore -StatePath "C:\ProgramData\NetRescueQA\<run-id>\state.json"
```

Do not run a second fault until the first is restored and a new Check is healthy.
The runner refuses another injection while a QA rollback task is armed.

## Scenarios and expected outcomes

Use the same command above, changing only `-Fault`:

| `-Fault` | Actual controlled change | What NetRescue should show |
| --- | --- | --- |
| `AdapterDisabled` | Disable the selected adapter | Adapter turned off; enable once; reconnect verification; healthy or explicit remaining condition |
| `NoIpv4` | Release only the selected DHCP adapter's IPv4 lease | Missing address or APIPA; targeted renewal; usable address verified |
| `GatewayMissing` | Remove the selected adapter's active default routes; DHCP required | Missing gateway; targeted renewal; restart/manual handoff if still broken |
| `GatewayPingBlocked` | Temporary ICMP echo block to the gateway only | Gateway ping unanswered, while Internet and DNS pass; no repair |
| `GatewayTestsBlocked` | Temporary outbound block to the gateway IP and configured external target IPs | Possible local path issue; renewal must remain unverified while the block persists |
| `WanTargetsBlocked` | Temporary outbound block to configured external target IPs | External path/target failure; manual guidance; no false ISP or repair claim |
| `DnsServer` | Unreachable documentation-range IPv4/IPv6 DNS servers, then clear cache | DNS failure; cache flush may complete but cannot correct the manual server addresses; manual DNS guidance |

Firewall scenarios reproduce the **test observations**, not a proven broken
router or ISP. DHCP can reacquire an address/route before a check finishes. If the
expected fault is not reproduced, the runner records that fact and sends no
repair request; do not count that run as a pass.

The runner does not change your permanent IP configuration, remove drivers,
reset Winsock, disable DHCP on your household router, or manufacture latency by
flooding the network. These cases need additional local/lab acceptance work:

| Condition | Real acceptance test |
| --- | --- |
| No cable / Wi-Fi link | Disconnect locally; Run Check; follow the displayed reconnect step; Run Check again |
| No adapter / missing driver | Use a disposable VM or spare hardware; confirm manual guidance |
| Genuine APIPA from no DHCP response | Use an isolated lab with unavailable DHCP; distinguish from a manually assigned 169.254 address |
| Bad static IPv4 / gateway | Use a disposable VM with known lab addresses; confirm NetRescue leaves manual settings for correction |
| High gateway latency | Use controlled delay in a lab network; compare the measured latency with the configured threshold |
| DHCP-provided DNS still broken | Use a lab DHCP/DNS setup; flush, renew once, and confirm manual handoff if DNS remains broken |
| Multiple adapters / VPN routing | Use a separate routing test plan; the fault runner deliberately requires a single active IPv4 path |

## Evidence and timing

Each run saves `baseline.json`, `detected.json`, relevant `after-repair.json`,
`restored.json`, the actual rendered HTML pages, `http-timings.json`, and rollback
state. The JSON includes check and incident IDs, command completion/exit code,
verification status, before/after IDs, per-phase measurement timing, and the
application's result summary. The incident's command details retain intermediate
reconnect readings and command duration. A rollback failure writes
`restore-errors.txt` and retains the task/state.

Repeat successful scenarios three times individually. Record click-to-response,
command time, verification time, and final diagnosis. Do not treat a command exit
of zero as proof of recovery. A repaired target with another remaining fault
must produce a warning that names the remaining condition.

The faster single-interface reader avoids loading detailed routing/adapter
modules on unambiguous active-interface reads. It does not prove a faster Windows
driver enable operation; the previous 25–36 second adapter timings must be
remeasured on this laptop.

## Automated checks

```powershell
php artisan test
powershell -NoProfile -File tests\windows\target-snapshot.test.ps1
powershell -NoProfile -File tests\windows\transition-reader.test.ps1
powershell -NoProfile -File tests\windows\change-adapter.test.ps1
powershell -NoProfile -File tests\windows\fault-runner.test.ps1
```

These PowerShell tests replace operating system calls with fixtures. They do not
inject real faults. See `FAULT_QA_PLAN.md` for coverage and `FAULT_QA_RESULTS.md`
for the measured results from this development session.

Command behavior references: [ipconfig](https://learn.microsoft.com/en-us/windows-server/administration/windows-commands/ipconfig),
[Resolve-DnsName](https://learn.microsoft.com/en-us/powershell/module/dnsclient/resolve-dnsname),
[Set-DnsClientServerAddress](https://learn.microsoft.com/en-us/powershell/module/dnsclient/set-dnsclientserveraddress),
and [Register-ScheduledTask](https://learn.microsoft.com/en-us/powershell/module/scheduledtasks/register-scheduledtask).
