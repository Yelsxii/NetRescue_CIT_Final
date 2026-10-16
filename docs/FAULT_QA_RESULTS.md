# NetRescue QA results — 16 October 2026

This file records the initial software-only session. Subsequent actual Windows
laptop fault testing is documented in
[Windows laptop QA results](WINDOWS_LAPTOP_QA_RESULTS_2026-10-16.md).

**Repository/software QA completed. Real Windows laptop fault injection has not
been executed in this session.** The available terminal is Linux; no connection
to the user's laptop PowerShell or desktop was available. No settings on that
laptop were changed.

Base reviewed: `4a9ffca420b821635c23897786cd2d8a42861d68` (`main`). Production code
is in the repository root application; historical copies in `files/` were not
treated as the running implementation.

## Confirmed defects and corrections

| Reproduced behavior | Corrected behavior |
| --- | --- |
| Failed gateway ping prevented Internet and DNS probes, so working connectivity could be diagnosed as a router failure and offered renewal | External TCP is tested despite an unanswered ping; working TCP/DNS produces an ICMP warning with no repair |
| Empty/incomplete JSON from Windows could be interpreted as a missing adapter | Response shape/state consistency is checked; malformed configuration is unreadable evidence |
| Truncated or timed-out gateway/DNS processes could become ordinary network failures | Incomplete connectivity tests are Not measured, do not justify a repair, and cannot close a previously detected incident |
| A nonempty IP such as `0.0.0.0`, loopback, multicast, broadcast or malformed text could count as IP recovery | Shared host IPv4 validation is used by diagnosis and repair verification; genuine APIPA remains a DHCP-specific condition |
| Persistent DNS failure after DHCP renewal could keep offering renewal | Cache flush can escalate to renewal only with confirmed DHCP addressing/automatic DNS; ineffective renewal hands off to manual DNS investigation |
| DNS escalation could offer DHCP renewal with unknown DNS source or a static IP | Manual/unknown configuration is handed to a person, preserving those settings |
| Adapter repair could show an overall success toast even when another fault remained | Warning toast names partial recovery; the remaining condition appears in the outcome/history and its own active incident |
| Every untargeted configuration read loaded detailed Windows networking queries, even with one unambiguous active interface | A single-interface snapshot fast path avoids those queries; multi-interface/multi-gateway cases retain route-metric selection |
| Failure to enumerate local interfaces could produce a console HTTP 500 | Retain explicit local addresses and preserve access denial for other computers when enumeration is unavailable |

## Executed checks

Runtime: PHP 8.3.6, project dependencies installed from `composer.lock`, SQLite
in-memory test database, PowerShell 7.4.6 on Linux. Windows networking, cmdlets,
and task operations were replaced with explicit fixtures in automated tests.

| Check | Result |
| --- | --- |
| New regression tests run against the original behavior | 60 selected tests, 511 assertions, 13 reproduced failures |
| Complete final PHP suite | 140 tests, 1,128 assertions; zero failures/errors/skips |
| Production adapter snapshot/selection tests | 14 checks passed |
| Production transition-reader tests | 4 checks passed |
| Production adapter-command boundary tests | 6 checks passed |
| Fault-injection, CSRF and rollback fixture tests | 22 checks passed |
| PowerShell parsing | All 8 production/test/QA script files parsed |

The suite covers every supported diagnosis and all six repairable incident types
with completed commands, nonzero failures, timeouts and persistent faults. It
also exercises stale/healthy prechecks, changed adapters, duplicate requests,
paused scheduling, reconnect windows, intermediate reading failures, exact
before/after history, normal route responses and rendered outcome text. DNS
flush/renew/manual and gateway renew/restart/manual escalation are tested through
the real services. **These are software tests, not real adapter/ISP experiments.**

## Windows runner provided

`tools/qa/Invoke-NetRescueFault.ps1` supports one guarded local injection per run:
disabled adapter, released DHCP IPv4, removed gateway route, gateway ping block,
combined gateway/test-target block, external target block, or unreachable manual
DNS servers. It can exercise the normal CSRF-protected Check/Repair routes and
save their HTML, IDs, command/verification results and timings.

Before injection it saves state in a protected folder and registers an independent
rollback task. Restoration matches the saved adapter GUID, removes only that
run's firewall rules, preserves each DNS family's automatic/manual mode, and
uses targeted DHCP renewal rather than inventing a static gateway route. Tests
verify idempotence, missing-target handling and rollback evidence retention.

See [WINDOWS_FAULT_QA.md](WINDOWS_FAULT_QA.md) for preparation and exact commands.

## Still required before claiming laptop acceptance

- Run each guarded scenario on the actual Windows computer and inspect its saved
  evidence and final healthy Check. Task Scheduler, firewall cmdlets, DHCP and
  Windows PowerShell 5.1 execution need this real validation.
- Re-test the disabled adapter three times. Prior user tests recorded roughly
  25–36 seconds and occasional unconfirmed completion. The fast reader reduces
  avoidable query work; no new Windows enable-command speed is claimed here.
- Test cable/Wi-Fi reconnection by physical/user action. Use an isolated lab or
  disposable VM for absent adapters/drivers, genuine DHCP starvation/APIPA, bad
  manual addressing and controlled latency. Router/ISP failures require their
  actual external conditions or a suitable lab.
- Test multiple-adapter/VPN routing separately. Configuration is adapter-selected;
  external TCP follows Windows routing and DNS uses the Windows resolver. This
  runner requires one active IPv4 interface to keep controlled fault evidence
  unambiguous.

The correct release status is **automated QA passed; Windows acceptance pending**.
