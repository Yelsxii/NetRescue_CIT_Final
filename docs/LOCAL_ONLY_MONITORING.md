# Local-only monitoring update

## Scope

The application monitors only the computer running NetRescue. It still reads
that computer's interfaces and tests its gateway, Internet and DNS connection.
It does not scan, discover, register, poll or repair other computers.

Removed: Devices pages/filters/search, fleet reporting/offline statistics,
neighbor watching, recognition, pairing/enrollment, agent deployment scripts,
agent API ingestion/commands, remote repair dispatch and stale-heartbeat jobs.
The LAN server/firewall launch helpers are removed. `tools/windows/START_NETRESCUE.bat` binds
to 127.0.0.1 and starts the local scheduler. Other computers' software/services
are not uninstalled by this source update.

Kept: Dashboard, local Checks, local Incidents, Settings, diagnosis, predefined
local repairs, paused-schedule automatic verification, before/after evidence,
earlier reading visibility and the existing timeout policy. Run Central Check
is now labeled Run Check. The local-only change introduces no new fault logic.

## Data

The fixed internal local host ID and all associated records stay intact. The
update does not delete remote rows, drop legacy tables or modify .env. Those
old rows are inaccessible through the UI and excluded from summaries, polling,
history and repair endpoints. An ID belonging to another computer returns 404,
even if its old is_local flag is true. Database backups/data are not packaged.

## Apply the update

Finish active operations. Exit Tinker and stop Laravel/schedule:work windows.
Extract NetRescue_Local_Only_Update.zip. Its Apply-LocalOnly.ps1 script verifies
payload hashes, backs up files under storage/app/private, applies changed
source and removes the obsolete feature files listed in PATCH_MANIFEST.json.
On a copy/removal failure it restores originals. It does not run network
commands, migrate the database or start/stop Windows services.

From Administrator PowerShell, invoke the script from its extracted folder:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\Apply-LocalOnly.ps1 -ProjectPath "C:\xampp\htdocs\NetRescue_CIT_Final"
```

Then from the project directory:

```powershell
composer dump-autoload
php artisan optimize:clear
php artisan serve --no-reload --host=127.0.0.1 --port=8000
```

If scheduled checks are wanted, start php artisan schedule:work in another
project terminal. Do not start duplicate scheduler windows. Use .env and the
existing database; no migration or database reset is needed for this update.

## Review without injecting faults

Open http://127.0.0.1:8000/. The sidebar contains Dashboard, Checks, Incidents
and Settings. There is no Devices menu, search or fleet reporting count.
The summary identifies This computer, its local 24-hour check rate and local
open incidents. All checks opens /checks. Review NR-00029 at /incidents/29;
its original closed/verified result and 20.68-second reading remain unchanged.

The obsolete /devices and /api/agent URLs must return 404. With existing
remote rows, they must not appear anywhere in local summaries/history. A
normal Run Check is enough to exercise the local reader; no fault injection
or repair is necessary to review this scope change.
