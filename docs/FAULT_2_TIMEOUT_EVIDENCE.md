# Fault 2: timeout evidence and startup comparison

NR-00027 records a 7.98 s precheck, a 35.98 s enable process that exceeded its
35 s deadline, and an 8.67 s follow-up of Ethernet. These phases account for
about 53 seconds. The follow-up is not evidence of the Wi-Fi result.

The later read-only Windows command reports Wi-Fi Enabled / Connected. That
establishes its state at that later observation; it does not establish when
the change occurred or that the enable process completed successfully.

## Corrections included

This overlay includes the previous adapter-target correction and additionally:

- New process timeouts retain `execution_status = timed_out` with an unknown
  exit code. Existing records such as NR-00027 are described from their stored
  timeout error without rewriting their status or success flag.
- A timeout is not described as a confirmed nonzero command exit. Timeout text
  says command completion was not confirmed and reports verification separately.
- Automatic verification can retry a reconnecting target after a timeout. It
  still never repeats the command as part of verification.
- A fresh target reading that shows recovery after an unconfirmed command closes
  the incident as `observed_recovery`, not `verified_repair`.
- An active incident whose follow-up measured another adapter no longer claims
  the original adapter is currently disabled. Its current outcome is unverified.
- Ambiguous finished requests show Check result in the modal. That action reads
  the incident's named adapter without issuing a repair command. New valid fault
  records still offer Run repair, which includes automatic verification.
- Dashboard mismatch records identify the target and link to Review result.
- Prechecks and follow-ups explicitly request the incident adapter; the Windows
  reader does not substitute a disconnected Ethernet interface for Wi-Fi.
- Interface snapshots are refreshed after slow Windows calls, and a conflicting
  transition is returned as unreadable so the verifier can read again.

## What remains unproven

The reason the Windows enable process exceeded 35 seconds is not yet established.
Increasing that deadline or changing the success label would not establish it.

The installed Laravel ServeCommand filters most inherited environment variables
when a .env file exists and reload mode is enabled. With --no-reload, that branch
preserves the parent environment. The earlier browser diagnostic reported a
small inherited environment (690 UTF-16 units). This makes server startup a
reasonable comparison to perform; it does not prove that filtering caused the
command timeout.

Primary implementation reference:
https://github.com/laravel/framework/blob/12.x/src/Illuminate/Foundation/Console/ServeCommand.php

## Install this combined follow-up

Requires the Automatic_Verification_and_Speed_Correction already installed.
The separate Adapter_Target_Correction ZIP is not additionally needed; its
changes are included here. No new migration, vendor edit, database replacement
or .env edit is required.

1. Stop the current Laravel server with Ctrl+C.
2. Copy this ZIP's app, public, resources, tests and docs folders into
   C:\xampp\htdocs\NetRescue_CIT_Final. Merge folders and replace included files.
   Preserve newer local edits by reviewing CHANGESET.patch when needed.
3. Keep the PowerShell reader and its PHP callers together in this version.
4. In Administrator PowerShell, start the server with the comparison flag:

```powershell
cd "C:\xampp\htdocs\NetRescue_CIT_Final"
& "C:\xampp\php\php.exe" artisan optimize:clear
& "C:\xampp\php\php.exe" artisan serve --no-reload --host=127.0.0.1 --port=8000
```

--no-reload affects the server's .env restart watcher and environment passing.
It does not disable the dashboard's result update or automatic verification.

## Next measurement, without another injected fault

Keep Wi-Fi enabled and scheduled checks paused. Open http://127.0.0.1:8000/
directly and Ctrl+F5. Run Central Check once to obtain a current reading and its
duration with this startup. Record the Now adapter, diagnosis, address, gateway
and check duration. This is a startup diagnostic and reconciliation of the
existing incomplete record; a successful normal repair does not require another
Central Check click.

If NR-00027 closes after that fresh check, it must say recovery was observed,
without changing its earlier attempt to a verified repair. Do not disable Wi-Fi
or repeat the repair until the current reading is assessed. A single fast read
would show that startup is usable, not prove a controlled speed comparison or
the complete disabled-to-enabled repair flow. The later acceptance test must
still capture a fresh fault, command exit and exact follow-up for the same adapter.

## Validation

Local tests cover the original NR-00027 error shape, unchanged legacy flags,
timed-out command followed by automatic observed recovery, named-adapter
prechecks/follow-ups, and Check result without a second command. The Windows
process boundary is mocked. Windows command latency and real adapter repair
cannot be validated in this Linux environment.
