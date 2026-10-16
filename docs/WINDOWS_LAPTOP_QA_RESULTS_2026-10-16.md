# Real Windows laptop fault QA — 16 October 2026

**Seven guarded scenarios passed three times each: 21 accepted fault runs.**
There were 22 injections in total: the first DNS attempt failed its acceptance
condition, exposed defects, and remains in the evidence. Three corrected DNS
runs then passed. This report supplements the earlier software-only results in
`FAULT_QA_RESULTS.md`.

## Host and method

Commands ran on the actual Acer Aspire A715-76G laptop, Windows `10.0.26300`,
Windows PowerShell 5.1 and PHP 8.2.12. PR #2's prepared revision was `6581d49`, on
`codex/fault-qa-repair-verification`. Existing source/document changes carried
across the branch switch. No reset, reseed, migration, or history deletion ran.

MySQL and NetRescue were initially stopped. The existing database was backed up
before injection, then the app ran locally with Windows administrator rights.
Scheduling was already paused and remained paused. One active IPv4 Wi-Fi path
was used; Ethernet was disconnected. Each test began with a fresh healthy Check.

Before changing one network condition, the runner saved the adapter state and
armed an independent SYSTEM rollback task. These supervised runs used
`-ExerciseApp -RollbackSeconds 1800`. The ordinary CSRF-protected Check route
had to reproduce the expected diagnosis before the runner requested Repair once.
The runner restored settings in `finally`, performed a closing Check, and removed
its task only after successful restoration. Each next test required a healthy
closing Check and zero armed tasks. The timed watchdog's independent scheduled
execution was not deliberately triggered; registration and direct rollback were
exercised on every injection.

## Fault results

| Scenario | Accepted runs | Diagnosis and repair behavior | Closing result |
| --- | --- | --- | --- |
| Disabled adapter | 002–004, 3/3 | `adapter_disabled`; one enable command; recovery verified on the same adapter | Healthy, task removed |
| Released DHCP IPv4 | 005–007, 3/3 | `ip_configuration_missing`; targeted renewal; usable IPv4 and connectivity verified | Healthy, task removed |
| Missing gateway | 008–010, 3/3 | `gateway_missing`; targeted renewal restored the DHCP route | Healthy, task removed |
| Gateway ping blocked | 011–013, 3/3 | `gateway_ping_unanswered`; Internet/DNS passed; no repair offered or requested | Healthy after rule removal |
| Gateway and external targets blocked | 014–016, 3/3 | `gateway_unreachable`; renewal exited 0 but stayed `not_verified` while the block persisted | Healthy after rollback; ineffective repair remained unverified |
| External targets blocked | 017–019, 3/3 | `wan_unreachable`; configured-target/manual guidance; no repair requested | Healthy after rule removal |
| Unreachable manual DNS servers | 021–023, 3/3 | `dns_failure`; one cache flush completed but stayed `not_verified`; manual DNS guidance; no further repair offered | Healthy; original IPv4/IPv6 modes and server lists verified |

Every accepted run returned HTTP 200, runner exit 0, and a healthy closing Check
with gateway, Internet and DNS passing. No QA task remained armed. Saved rendered
HTML was checked against diagnosis, repair availability and recorded outcomes.
The browser also verified adapter and DHCP outcomes, incident 46's ineffective
repair, incident 52's DNS result, the corrected historical timeout row, and a
fresh final Check.

A completed command was never accepted as proof of repair by itself. After
rollback, incident 46 correctly said recovery was observed by a check and the
repair was not verified as its cause. Firewall tests establish behavior under
blocked test observations; they do not prove a real router or ISP failure.

## Repair response timings

These are elapsed HTTP repair-request times, including precheck, Windows command,
verification and the response. They approximate click-to-response; a browser
click/animation was not separately timed. A request duration is not a claim that
repair succeeded.

| Scenario/action | Mean | Range | Verified outcome |
| --- | ---: | ---: | --- |
| Disabled adapter / enable | 21.527 s | 21.400–21.602 s | Recovered, 3/3 |
| Released IPv4 / renew | 6.923 s | 6.545–7.220 s | Recovered, 3/3 |
| Missing gateway / renew | 4.632 s | 4.425–4.798 s | Recovered, 3/3 |
| Persistent gateway block / renew | 16.182 s | 15.169–17.231 s | Correctly unverified, 3/3 |
| Bad manual DNS / cache flush | 21.064 s | 20.272–22.343 s | Correctly unverified, 3/3 |

Ping-only and external-target-only faults offered no repair.

| Disabled-adapter run | Repair response | Full command process | Verification | Readings |
| --- | ---: | ---: | ---: | ---: |
| 002 | 21.602 s | 10.838 s | 7.486 s | 2 |
| 003 | 21.400 s | 12.064 s | 6.471 s | 1 |
| 004 | 21.579 s | 11.210 s | 7.643 s | 2 |
| Mean | 21.527 s | 11.371 s | 7.200 s | — |

The actual `Enable-NetAdapter` call, excluding module/process overhead, took
8.944, 10.045 and 9.163 seconds. Intermediate disconnected readings in runs 002
and 004 were retained, then superseded by same-adapter healthy verification.
Earlier user observations were approximately 25–36 seconds; no original-code
control was rerun, so this is not a controlled speedup comparison.

## Confirmed defects fixed

- **DNS probe cut off too early.** Attempt 020 exhausted the six-second process
  limit and correctly produced `probe_unavailable`, without a repair. A separate
  real query to the unreachable servers completed in 9.089 seconds. The default
  DNS allowance and fallbacks are now 20 seconds, with matching request budgets.
  Genuine process timeouts still remain unmeasured. Runs 021–023 verified the
  completed DNS-failure/manual-handoff flow.
- **Mixed DNS modes were lost during rollback.** Resetting automatic IPv6 through
  a family-selected PowerShell object also reset manual IPv4. The addresses still
  matched, so a healthy Check alone missed the problem. Original IPv4 manual and
  IPv6 automatic settings were explicitly restored at 22:00:55. Rollback now uses
  family-specific `netsh` commands, validates both modes and ordered manual server
  lists after all commands, saves `dns-restored.json`, and retains its task on a
  verification failure. All three corrected real runs confirmed both families.
  Command reference: [Microsoft netsh interface documentation](https://learn.microsoft.com/en-us/windows-server/administration/windows-commands/netsh-interface).
- **Partial readings were displayed inconsistently.** The dashboard could label
  a partial probe result as its last completed reading, while the Checks table
  hid already completed gateway/Internet results. It now selects a completed
  check for those dashboard bars and preserves each probe's recorded state in
  the table. The real timeout check 596 now displays gateway PASS 12.5 ms,
  Internet PASS 31.0 ms, and DNS Not measured. Overall diagnosis stays incomplete.
  Controller regressions also cover having no earlier completed check.
- **Gateway injection could remove manual routes.** The runner now rejects
  manual, persistent, multiple or customized gateway routes before injection.
  Eligibility requires one unchanged DHCP gateway. The three actual gateway
  tests passed this gate and restored their route through DHCP.

Final Windows PHP suite: **146 tests, 1,156 assertions passed**, using SQLite
`:memory:` and process-only test settings. Windows PowerShell fixtures passed
14 snapshot, 4 transition-reader, 6 adapter-command and 49 runner checks
(**73 fixture checks**). Parser and scoped diff checks passed. Fixture cases
are not additional laptop injections.

## Final connectivity and preservation

The final normal browser Check, ID 613, reported **Network Operational, 100%**:
gateway 7.00 ms, Internet 22.17 ms, DNS 532.63 ms. DNS answered successfully but
remained above the configured 100 ms display warning. The final independent host
check at 22:13 confirmed adapter Up, a default gateway, external TCP and DNS
resolution, zero QA firewall rules and zero armed rollback tasks.

Original IPv4 manual DNS, automatic IPv6 DNS, server addresses, DHCP addressing
and gateway were restored. At 22:13:30, the `.env` SHA-256 and all monitoring
settings matched the pretest baseline. The backup comparison confirmed every
original row was unchanged: **436 checks, 25 incidents and 18 repair actions**.
The retained database now contains 541 checks, 46 incidents and 33 repair actions;
these increases are the normal QA history. No existing history was removed.
Application-generated log/session files were allowed to update during normal use.

The supervisor, app server and database started for QA were stopped at 22:13:34,
returning those services to their initial stopped state. This did not undo QA
history or alter `.env`. The confirmed fixes, regression tests and this report
are included in the follow-up change for PR #2; runtime artifacts remain local.

Raw JSON, rendered responses, timing summaries, screenshots, the database backup
and preservation checks are retained locally under the ignored directory
`storage/app/private/windows-qa-20261016/`. Attempt 020 is retained as a failed
attempt, with its later explicit DNS-mode correction recorded separately.

## Remaining lab coverage

The guarded matrix does not establish physical cable/Wi-Fi reconnection, missing
hardware/drivers, genuine DHCP starvation/APIPA, invalid manual addressing,
controlled high latency, DHCP-provided DNS failure, multiple-adapter/VPN routing,
or a real router/ISP outage. Those acceptance cases remain as documented in
`WINDOWS_FAULT_QA.md`.

## Final PR regression review

Review of PR #2 against `main` identified and corrected three further regressions:

- A sole Up host-only virtual interface without a gateway could hide disabled
  physical Wi-Fi. The untargeted snapshot shortcut now requires a gateway;
  otherwise route/physical-adapter selection runs. A production-reader fixture
  reproduces the virtual-interface/disabled-Wi-Fi case. Explicit targeted reads
  still retain missing-address/gateway evidence during acquisition.
- A DNS incident could claim the router answered even when gateway ping failed
  and only external TCP passed. Its displayed outcome now states the measured
  external TCP result without inventing a gateway response.
- Historical gateway incidents could claim external TCP failed when the older
  probe pipeline had skipped it. Their displayed outcome now describes the
  unanswered gateway without reinterpreting stored history as a TCP failure.

The added regression cases failed before their fixes. Final isolated Windows PHP
validation passed **149 tests and 1,168 assertions**. Windows PowerShell 5.1
fixtures passed **75 checks**: 16 snapshot, 4 transition-reader, 6 adapter-command
and 49 runner checks. This review used in-memory data, an isolated compiled-view
directory and process-only test settings; no new laptop faults were injected.
Existing local/runtime files, `.env` and database-file hashes remained unchanged.

GitHub reported no workflow runs, check runs, commit statuses or submitted
reviews for the reviewed head, and this repository has no GitHub workflow files.
CI is therefore unconfigured, not a passing CI result. The PR was mergeable and
was left as an unmerged draft. The earlier 21 accepted laptop fault runs and the
lab-coverage limits above remain the physical acceptance evidence.
