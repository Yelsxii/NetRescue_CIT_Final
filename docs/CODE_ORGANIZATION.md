# Source organization and MVC reading guide

The active application follows Laravel's standard directory structure. The
organization and formatting cleanup preserves the UI, routes, validation and
diagnostic/repair behavior. PHP source changes are limited to whitespace and
layout, with non-whitespace tokens checked against a saved baseline.

## Directory map

| Location | Responsibility |
| --- | --- |
| `app/Models/` | Eloquent records, relationships, casts and record-level behavior. |
| `app/Http/Controllers/` | HTTP request coordination, queries, views and redirects. |
| `app/Http/Requests/` | Validated monitoring input and normalization. |
| `app/Http/Middleware/` | Local-console access checks. |
| `app/Services/Network/` | Probes, diagnostic decisions, scoring, incident management, recovery and verification. |
| `app/Services/MonitorSchedulerStatus.php` | Recent scheduler availability from its cached tick. |
| `app/Support/` | Shared result presentation, repair guidance and small value helpers. |
| `app/Console/Commands/` | Artisan entry point for scheduled checks. |
| `resources/views/` | Blade layouts, pages and shared components. |
| `resources/powershell/` | Windows OS bridge used by the PHP network services. |
| `routes/web.php` | Named local-console HTTP routes. |
| `routes/console.php` | Scheduler tick and automatic monitor schedule. |
| `database/migrations/` | Schema definitions and compatible historical upgrades. |
| `database/seeders/` | Fresh-install initial settings and local identity. |
| `config/` | Application and framework configuration. |
| `public/` | Laravel front controller and browser assets. |
| `tests/` | Unit, feature and Windows-script tests. |
| `tools/windows/` | Optional setup/start wrappers, separate from application logic. |
| `tools/qa/` | Guarded fault injection, restoration and evidence helpers. |
| `documentation/` | Written report, program flow, screenshots and source artwork. |
| `docs/` | Architecture, rubric review, testing guides and measured QA records. |
| `docs/archive/` | Clearly labeled old reports and patch copies, outside the active source tree. |
| `tools/archive/diagnostics/` | Unchanged temporary troubleshooting scripts, outside the web root. |
| `tools/archive/agents/` | Preserved legacy agent package from the removed remote feature; not part of the current application. |
| `storage/` | Runtime sessions, logs, cache and private artifacts; not application source. |
| `vendor/` | Composer dependencies; do not manually edit. |

## Read one simple MVC path

For comparison with the teacher's Book example, start with monitoring settings:

1. `routes/web.php` maps the named `settings.update` route to
   `MonitorSettingController::update()`.
2. `UpdateMonitorSettingRequest` validates and normalizes the submitted fields.
3. The controller calls `MonitorSetting::current()` and updates only validated
   attributes through Eloquent.
4. The controller redirects with a result message. The Blade settings form uses
   named routes, CSRF protection, method spoofing, old input and validation errors.

The teacher's example validates directly in its controller. A dedicated Laravel
Form Request provides the same validation boundary while keeping the controller
shorter. This is an organizational difference, not a departure from MVC.

## Read the diagnostic and recovery paths

`HealthCheckController` delegates a check to `NetworkHealthService`. That service
uses `NetworkProbeService`, `HealthScoreCalculator`, `DiagnosticEngine` and
`IncidentManager` to measure, classify, compute and retain the outcome. Models
store the evidence and Blade presents it.

`RecoveryController` delegates a confirmed request to `RecoveryService`. The
service checks the selected adapter and current condition, chooses a predefined
action, calls the Windows command boundary, and verifies the result. Execution
status and observed recovery remain separate evidence.

`RunNetworkMonitor` exposes the check workflow as an Artisan command. Laravel's
scheduler invokes the command every minute; the command checks whether
monitoring is enabled and due. Batch files merely start these Laravel processes;
they do not implement their decisions. PowerShell remains necessary to access
Windows adapter information and cmdlets; Laravel owns the workflow around it.

## Readability review

Existing strengths include descriptive domain names, namespace-based folders,
separate HTTP validation, reusable Blade components, shared presentation helpers
and tests separated from production code. Source paths follow Composer's PSR-4
mapping; archived patch copies are not autoloaded or discovered by PHPUnit.

Compact model relationship methods now use multiline bodies. Spacing, argument
continuations, array indentation, method separation and statements sharing a
line have been made consistent across active PHP source and tests. Existing
names, import order, guards, expressions, comments and string contents are
preserved. No class/API renames, schema changes or movement of active PHP
classes was needed.

Some display calculations still live in controllers, models and Blade `@php`
blocks. Moving them would be a separate structural refactor, so their location
and behavior remain unchanged. Blade, CSS, JavaScript, images and the Windows
PowerShell adapters are excluded from this PHP formatting pass.

## Repeating the style check

`pint.json` uses the installed Laravel Pint formatter with an empty preset and
explicit whitespace/layout rules. It excludes syntax conversion, import
reordering, added control braces, string rewriting and heredoc indentation.
Archived files and runtime folders are excluded.

From the project root, check the active PHP scope without changing it:

```powershell
php vendor/bin/pint --test app config database/migrations database/seeders routes tests bootstrap/app.php bootstrap/providers.php
```

The regression and token/content preservation evidence is recorded in
[the organization QA notes](ORGANIZATION_QA_2026-10-18.md).
