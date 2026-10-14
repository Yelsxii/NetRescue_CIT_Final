# NetRescue CIT 3.1

**A Laravel MVC network monitoring, diagnosis, incident, and safe-recovery dashboard for a single CIT administration unit.**

NetRescue CIT is designed as a **single administrator dashboard**. It focuses on clear monitoring results, understandable incidents, and a compact modern interface that fits the browser screen better. End users do **not** need accounts, dashboards, or extra interaction.

## What changed in this revision

This revision was redesigned to better match the requested dashboard direction:

- OfficeSphere-inspired layout and dark/light modes
- more compact browser-fit dashboard
- blue-accent charts and cleaner card layout
- simpler, agent-free presentation in the UI
- useful graphs based on stored database values
- clearer device list, device details, and incident summaries
- sample seeded data for demonstration after `migrate:fresh --seed`

## Main features

### 1. Single CIT administration console

- Dashboard is restricted to the Central computer.
- Only the CIT administrator uses the browser interface.
- Devices and incidents are reviewed centrally.

### 2. Useful dashboard analytics

The dashboard now emphasizes operational information that is actually helpful:

- online devices vs total devices
- average health score
- healthy-check percentage over the last 24 hours
- active incident count
- fleet health trend
- current device distribution
- average latency bars for gateway / Internet / DNS
- recent incidents
- most common causes
- priority devices that need attention first

### 3. Device-centered monitoring

Each device page shows:

- latest diagnosis
- health score and reliability
- measured trend
- recent issues
- technical details such as IP, gateway, DNS, and adapter

### 4. Automatic incident tracking

NetRescue can:

- open incidents when a condition appears
- group repeated observations
- store first detected, last detected, occurrence count, severity, and guidance
- show incident history in a clean list

### 5. Controlled safe recovery

The project keeps the safe-recovery design:

- only predefined recovery actions are allowed
- no arbitrary remote administration commands are exposed in the UI
- recovery attempts are verified using fresh health checks

## Laravel MVC structure

Important files:

```text
routes/web.php
routes/api.php

app/Models/
  Device.php
  HealthCheck.php
  Incident.php
  MonitorSetting.php
  RecoveryAction.php

app/Http/Controllers/
  DashboardController.php
  DeviceController.php
  IncidentController.php
  HealthCheckController.php
  RecoveryController.php
  MonitorSettingController.php

app/Services/Network/
  FleetSummaryService.php
  NetworkHealthService.php
  NetworkProbeService.php
  HealthScoreCalculator.php
  DiagnosticEngine.php
  IncidentManager.php
  RecoveryService.php

resources/views/
  layouts/app.blade.php
  dashboard/index.blade.php
  devices/index.blade.php
  devices/show.blade.php
  history/index.blade.php
  incidents/show.blade.php
```

## Windows / XAMPP setup

Requirements:

- PHP 8.2+
- Composer
- MySQL / MariaDB
- Laravel requirements enabled in PHP

Create a database:

```text
netrescue_db
```

Then in the project folder:

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan test
```

Start the Central utility in LAN mode:

```text
START_NETRESCUE_CENTRAL_LAN.bat
```

Open the administrator console on the Central computer:

```text
http://127.0.0.1:8000
```

## Demo data note

This revised version seeds sample devices, measurements, and incidents so the dashboard immediately shows meaningful charts and cards after a fresh migration.

If you already have existing data and want to keep it, use `php artisan db:seed` carefully. If you want the full redesigned sample dashboard, use:

```powershell
php artisan migrate:fresh --seed
```

## Safe scope

This project is a monitoring and limited recovery utility, not a full remote-administration tool. It deliberately avoids arbitrary remote shell execution, router reconfiguration, firewall changes, and other sensitive controls that the application cannot safely validate.
