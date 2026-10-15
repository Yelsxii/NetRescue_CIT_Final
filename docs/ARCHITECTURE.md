# NetRescue CIT architecture

NetRescue runs on one Windows computer. Local browser requests enter Laravel
controllers, services read that computer's adapter and connectivity, and
Eloquent retains checks, incidents and recovery evidence in the configured
database. Blade renders the dashboard, checks, incidents and settings.

## Local processing

NetworkHealthService calls NetworkProbeService, then HealthScoreCalculator,
DiagnosticEngine and IncidentManager. LocalSummaryService queries only the
fixed local identity's records. Status polling and all history pages use the
same identity. Windows probes do not discover or monitor other LAN computers.

RecoveryService permits only a local incident, takes a fresh target-pinned
precheck, runs a predefined command if still needed and verifies automatically.
It retains command execution separately from measurement and closure evidence.
Earlier verification readings remain visible. Concurrent local repairs are
locked. Pausing the scheduler does not pause verification within a repair.

## Routes and access

The local.console middleware protects /, /checks, /history, /incidents, /status,
settings and repair/check POST routes. It accepts requests from this computer.
Loopback requests are checked directly, without hostname lookup. The provided
launcher binds the web server to 127.0.0.1.

Devices routes and all agent registration/reporting/command routes are removed.
There are no discovery, enrollment, deployment, heartbeat or remote recovery
services. The scheduler has one task: netrescue:monitor. It measures this host
only and honors its automatic-check settings.

## Data compatibility

The Device model identifies the local host internally with its existing fixed
UUID. This preserves the IDs and foreign keys of saved local checks/incidents.
It is not a managed-device feature or an editable computer directory. Old
remote data and legacy tables are retained but have no active application path.
Historical migrations remain available for compatible upgrades/fresh installs.
