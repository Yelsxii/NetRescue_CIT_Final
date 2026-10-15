> Historical troubleshooting note. The current application monitors this computer only. See README.md and docs/LOCAL_ONLY_MONITORING.md for current startup/scope; old device/agent instructions no longer apply.

# Fault 2: command and target-reading correction

## Evidence from NR-00028

The command ran for 35.99 seconds before its deadline was reported. Check 480
read disabled Wi-Fi in 4.992 seconds. Check 481 could not read configuration
and ended after 21.005 seconds with the 20-second PowerShell timeout recorded.
Check 482 read healthy Wi-Fi in 20.104 seconds: configuration 14.008, gateway
1.890, Internet 0.021 and DNS 4.185 seconds.

The command and two follow-ups account for approximately 77.1 seconds after
the precheck, before the retry delay, record updates and page rendering. The
healthy follow-up proves automatic recovery observation on the correct adapter.
It does not prove confirmed command completion. Existing incident history is
retained as observed recovery, not retrospectively converted to verified repair.

## Focused correction

1. Restore Enable-NetAdapter for local enable, with confirmation suppressed and
   terminating errors. This command completed with exit 0 in the earlier actual
   installed-service diagnostic (24.39 seconds); manual execution took 6.62
   seconds in a different run. Those observations are not a speed guarantee.
   The displayed available action is updated to match the executed command.
2. For explicitly requested adapters, take a fresh .NET snapshot before loading
   networking modules. An exact target already Up with zero or one IPv4 gateway
   can use that snapshot for IP, gateway, DNS and DHCP data. No gateway remains
   missing configuration, not a fabricated healthy result. Multiple gateways
   still use route metrics. A Down or absent adapter still needs an actual
   administrative-state query; it is never inferred to be Disabled.
3. Record target_snapshot or route_and_adapter as configuration_reader in each
   health check's probe_meta. This makes actual use of the path inspectable.

The 35-second command and 20-second configuration deadlines are unchanged.
Automatic retries, same-adapter checks, paused-schedule behavior, truthful
timeout attribution and incident evidence links are retained. General Central
Checks still use default routes to choose their adapter. No CSS, migrations,
credentials, agent commands, existing records or dependencies are changed.

## Install

This small overlay requires the previously installed command-and-verification
correction used for NR-00028. It is not a full application or a database.

1. Finish any active request. Stop the Laravel server with Ctrl+C.
2. Back up the files named in PATCH_MANIFEST.json. Merge the archive's app,
   resources, tests and docs folders into C:\xampp\htdocs\NetRescue_CIT_Final,
   replacing only included files. There are no public files or assets to build.
3. In Administrator PowerShell:

   ```powershell
   cd "C:\xampp\htdocs\NetRescue_CIT_Final"
   & "C:\xampp\php\php.exe" artisan optimize:clear
   & "C:\xampp\php\php.exe" artisan serve --no-reload --host=127.0.0.1 --port=8000
   ```

4. Open http://127.0.0.1:8000/ directly. Keep scheduled checks paused during
   controlled fault testing. There is no migration to run.

## Read-only Windows check first

Keep Wi-Fi enabled. In a second Administrator PowerShell window:

```powershell
cd "C:\xampp\htdocs\NetRescue_CIT_Final"
& "C:\xampp\php\php.exe" artisan tinker
```

At the Tinker prompt, paste the following as one statement:

```php
$t = microtime(true); $c = app(App\Services\Network\NetworkProbeService::class)->collectConfiguration('Wi-Fi'); dump(['seconds' => round(microtime(true) - $t, 2), 'configuration' => $c]);
```

This reads configuration only; it does not run a repair, disconnect Wi-Fi or
write health checks/incidents. On connected Wi-Fi with one gateway, inspect
reader = target_snapshot, interface_name = Wi-Fi, adapter_state = up, the actual
IP/gateway/DNS and error = null. Report elapsed time and the complete returned
configuration. This CLI check does not establish HTTP repair speed.

An optional dependency-free selection test is available:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\tests\windows\target-snapshot.test.ps1
```

It loads only the production selection function and uses synthetic interfaces;
it makes no network changes and is not a repair test.

## Subsequent end-to-end acceptance

After the read-only result is assessed, use one new disabled-adapter incident.
Central Check detects it; Run repair is clicked once. Its command, verification
and result update must finish automatically while scheduled checks remain
paused. Do not press another check during the operation.

Record command completion/exit code, automatic verification count, total wall
time, before/after adapter, reading duration and error. A clean Fault 2 pass
requires command exit 0 and the same target observed enabled by its exact
linked follow-up. Independent network problems must remain visible. Timeout
plus a healthy observation remains observed recovery, not confirmed repair.

If Wi-Fi remains disabled, restore it in Administrator PowerShell:

```powershell
Enable-NetAdapter -Name "Wi-Fi" -Confirm:$false -ErrorAction Stop
```

## Validation scope

PHP regression tests mock Windows execution. The PowerShell selection tests
exercise production selection code with synthetic objects. Neither proves
Windows module timing, driver behavior or actual repair success. The final
Windows acceptance test is still required. No fixed completion time is promised.

API references:
- https://learn.microsoft.com/en-us/powershell/module/netadapter/enable-netadapter
- https://learn.microsoft.com/en-us/dotnet/api/system.net.networkinformation.networkinterface.getipproperties?view=netframework-4.8.1
- https://learn.microsoft.com/en-us/dotnet/api/system.net.networkinformation.ipinterfaceproperties.gatewayaddresses?view=netframework-4.8.1
