# NetRescue CIT — monitoring on this computer

NetRescue checks the Windows computer on which it is running. The dashboard,
checks, incidents, statistics and repairs all refer to that computer.

## What it does

- Reads the local adapter, IPv4 configuration, gateway and DNS servers.
- Tests that computer's gateway, Internet connectivity and DNS resolution.
- Records measured results, diagnoses conditions and keeps incident history.
- Offers predefined local repairs with fresh before/after evidence.
- Verifies automatically after a repair, including when scheduled checks are paused.
- Keeps command completion separate from observed or verified recovery.

The interface contains Dashboard, Checks, Incidents and monitoring settings.
There is no device directory, LAN discovery, pairing, agent API, heartbeat
monitor or remote repair queue. The gateway and external test targets remain
part of measuring this computer's connectivity.

## Start on Windows

Use Administrator PowerShell for Windows repair permissions:

```powershell
cd "C:\xampp\htdocs\NetRescue_CIT_Final"
php artisan serve --no-reload --host=127.0.0.1 --port=8000
```

In a second project terminal, start scheduled checks if wanted:

```powershell
php artisan schedule:work
```

Open http://127.0.0.1:8000/. START_NETRESCUE.bat starts both processes on this
computer; run it as Administrator when repairs are needed. Automatic checks
also depend on the interval and enabled setting. Run Check and Run repair
remain available when scheduled checks are paused.

## Existing installation

Apply the local-only update without replacing .env, the database or storage.
No new migration or database reset is required. Local history and exact repair
evidence are preserved. Old remote records, if any, remain in the database but
are excluded from this interface and every live statistic or operation.

## Fresh installation

Use PHP 8.2+, Composer and MySQL/MariaDB with the existing Laravel 12 project.
Create .env from .env.example, configure the database, then run:

```powershell
composer install
php artisan key:generate
php artisan migrate --seed
```

The seeder creates settings and one local identity only; it creates no demo
measurements, incidents or other computers. SETUP_WINDOWS.bat is the fresh
installation helper, not the updater for an existing project.

## Validation

```powershell
php artisan test
```

Automated tests cover local scope, removed routes, retained evidence,
diagnosis, safe repairs and automatic verification with mocked Windows calls.
Actual Windows networking and command timing require laptop testing.

See docs/LOCAL_ONLY_MONITORING.md for removal scope and data compatibility,
docs/ARCHITECTURE.md for the implementation, and docs/TEST_CASES.md for tests.
Earlier fault-specific notes document historical debugging runs; this README
and LOCAL_ONLY_MONITORING.md describe the current application scope.
