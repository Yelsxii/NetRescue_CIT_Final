# NetRescue CIT — Final Examination Alignment

## Application type

Utility web application: network/system monitoring + diagnostics + automation.

## Problem addressed

The CIT administrator needs one safe place to monitor managed Windows computers without giving ordinary users access to router/admin tools or requiring repetitive manual troubleshooting on each workstation.

## Core processing / automation

- real network probes and endpoint reports
- weighted health-score computation
- dependency-aware diagnostic decision tree
- automatic incident creation, grouping, and resolution
- stale heartbeat detection
- safe recovery action selection
- before/after recovery verification
- fleet summary computation and 24-hour analytics

## MVC implementation

### Models

- `Device`
- `HealthCheck`
- `Incident`
- `MonitorSetting`
- `RecoveryAction`
- `AgentCommand`
- `EnrollmentCode`

### Views

- dashboard overview
- monitored computer list
- computer detail
- incident history
- incident detail
- reusable Blade components

### Controllers

- `DashboardController`
- `DeviceController`
- `IncidentController`
- `HealthCheckController`
- `RecoveryController`
- `MonitorSettingController`
- API controllers for endpoint agents

### Service classes

Services contain reusable processing so controllers remain focused:

- `FleetSummaryService`
- `NetworkHealthService`
- `NetworkProbeService`
- `HealthScoreCalculator`
- `DiagnosticEngine`
- `IncidentManager`
- `RecoveryService`

## Validation and error handling

Laravel Form Requests / request validation protect settings and agent payloads. Safe recovery is restricted to predefined action codes. Unsupported or unverifiable conditions do not trigger destructive actions.

## UI / usability

- administrator-only console
- plain-language problem summaries
- action queue instead of raw technical logs on the dashboard
- technical network details kept inside device pages
- cards, trend chart, status distribution, incident history
- dark/light themes
- collapsible and responsive navigation

## Why this is beyond CRUD

The database records support the utility, but the main functionality is processing and automation: the application measures, scores, diagnoses, groups, compares, monitors, verifies, and reacts to changing network conditions.
