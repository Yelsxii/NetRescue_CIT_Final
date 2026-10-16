# NetRescue fault QA plan

Scope: the production application in `app/`, `resources/`, and `public/`, based on
commit `4a9ffca`. `files/` contains historical copies and is not the running app.

## Acceptance rule

A successful command is not proof of recovery. Each test must capture the initial
check, incident ID, fresh precheck, exact command and exit status, all follow-up
checks, final diagnosis, and UI result. Verify only the fault that was measured on
the same adapter. If another fault remains, show it explicitly. A timeout or
permission error must never appear as a verified repair. Do not repeat a command
while waiting for Windows to reconnect.

## Coverage matrix

| Fault / condition | Controlled trigger | Expected behavior |
| --- | --- | --- |
| Adapter disabled | Disable only the selected adapter | Enable once; poll reconnect; verify target state and display remaining faults |
| No cable / Wi-Fi link | Disconnect Wi-Fi or unplug cable locally | Manual reconnect instructions; no fictitious software repair |
| Adapter absent | Synthetic reading; hardware/VM test | Manual hardware/driver guidance; no enable command without a target |
| DHCP APIPA | Synthetic reading; isolated DHCP-disabled lab | Renew only a DHCP adapter; confirm usable address and gateway; escalate once then manual help |
| Missing IPv4 | Release selected DHCP lease | Renew; reject zero, loopback, multicast, broadcast and APIPA as recovered addresses |
| Missing gateway | Temporarily remove the selected default route | Renew DHCP; restart only if still applicable; retain failure if gateway stays absent |
| Gateway not answering | Block gateway traffic locally in a guarded test | Measure Internet too; do not infer an outage from ping alone |
| Gateway ICMP blocked but Internet works | Block only gateway ICMP | Report ping limitation; no unnecessary DHCP repair |
| WAN / external targets unreachable | Block configured test target IPs | Manual upstream/firewall guidance; do not claim a proven ISP outage |
| DNS lookup failure | Set unreachable DNS on selected adapter; clear cache | Flush once; verify; static DNS needs manual correction; DHCP DNS may renew once |
| DNS remains broken after renewal | Synthetic before/after sequence | Stop offering the same ineffective renewal; give manual DNS guidance |
| Bad manual IPv4 configuration | Synthetic reading; isolated VM | Explain manual correction; never replace static settings automatically |
| High latency | Synthetic timed response; controlled lab congestion | Show measured latency and threshold; manual guidance, no reset |
| Reading unavailable / transitioning | Timeout, malformed output, changing snapshot | Keep prior evidence; no fabricated missing adapter or success |
| Gateway/DNS probe unavailable | Truncated output or process timeout | Show Not measured; no repair based on an unfinished connectivity test |
| Unsupported platform | Linux execution | Explicit unsupported state; no Windows commands |

For every repairable fault also test access denied, nonzero exit, process timeout,
stale/healthy precheck, changed fault, adapter mismatch, persistent failure,
downstream fault after partial recovery, monitoring paused, and duplicate requests.

## Timing

Record configuration, gateway, Internet, DNS, command, verification and overall
elapsed times. A click should acknowledge immediately in the browser. Do not add
fixed waits after a settled result. Windows adapter activation and DHCP can take
several seconds; retain bounded reconnect polling and truthful pending/failure
states rather than shortening timeouts to manufacture a faster result. Compare
three real laptop runs per fault; Linux mocks are not Windows timing evidence.

## Execution environments

1. Run the complete PHP suite with SQLite and mocked Windows boundaries.
2. Run PowerShell parsing and synthetic production-script tests without changing
   networking. These can run in PowerShell on Linux.
3. On the Windows laptop, capture baseline adapter/DHCP/DNS/routes, arm independent
   scheduled rollback before injecting a fault, apply one fault, run NetRescue
   Check / Repair or perform the indicated human step, then restore and recheck.
4. Never mark a hardware, router, ISP, or Windows-only test as passed from mocks.
   Real APIPA/no-adapter/latency tests need a suitable lab or hardware intervention.

The current ChatGPT execution terminal is Linux and has no local Windows desktop
or PowerShell connection to the user's laptop. Laptop tests remain unexecuted
until the local runner is run there. No laptop network settings were changed here.
