# NetRescue CIT - final examination alignment

The project is a local network diagnostic and recovery utility. It addresses
repetitive troubleshooting on the computer where NetRescue runs: adapter state,
IPv4/DHCP configuration, gateway reachability, Internet connectivity and DNS.
It provides measurements, diagnoses, incident history and verified local repair.

## Processing demonstrated

Real local probes feed weighted health scoring and an ordered diagnostic tree.
The system opens/resolves incidents from evidence, guards against stale or
wrong-target repairs, executes predefined actions and verifies their outcome.
Scheduled checks and automatic repair verification are separate operations.
A command exit code alone does not establish that the target condition cleared.

## MVC

Models: HealthCheck, Incident, RecoveryAction, MonitorSetting, and the internal
local identity in Device. Controllers expose Dashboard, Checks, Incidents,
Settings and local check/repair actions. Services implement probing, diagnosis,
scoring, incident synchronization, local summary and recovery. Blade components
provide consistent tables, response bars, incident details and modals.

## Scope

Only the current computer is monitored. Remote devices, LAN discovery,
recognition/enrollment, endpoint agents, remote heartbeats, deployment packages and
remote command queues are removed. This document describes the current
implementation; it does not change the instructor's examination requirements.

The database supports the utility. Its main work is measurement, processing,
comparison and automation rather than manual CRUD entry of monitoring results.

## Examination requirements reviewed

The supplied three-page Final Examination PDF requires a functional Laravel
utility with MVC, Blade, Eloquent where applicable, input validation, error
handling, meaningful processing, readable source and a working demonstration.
It explicitly lists network/router management, system monitoring and automation
as possible projects. Applications that mainly perform basic CRUD receive lower
priority. NetRescue therefore fits the stated project type without being
converted into a Book-style CRUD application.

This is a source/evidence comparison, not a promised grade. The instructor
assesses the submitted project, documentation and live demonstration.

## Program rubric: 50 points

| Criterion | Maximum | Current evidence |
| --- | ---: | --- |
| Functionality | 12 | Manual/scheduled checks, diagnosis, incident history, supported repairs and follow-up verification. See the measured QA records and running demo log. Windows permissions, a running scheduler and available external services remain operating requirements. |
| Laravel MVC implementation | 10 | Named routes, controllers, Eloquent models, Blade layouts/components, migrations and a Form Request. Network services keep extended processing outside HTTP controllers. |
| Programming logic | 8 | Ordered probe prerequisites, weighted health scoring, diagnosis rules, incident transitions, target-pinned repair prechecks, one repair action per request and bounded verification without repeating that action. |
| User interface and usability | 6 | Completed dashboard, Checks, Incidents and settings; light/dark and phone layout checks are recorded in the UI QA notes. No UI changes were made for this review. |
| Validation and error handling | 5 | Allowed intervals, threshold range and domain validation; local-console checks; invalid/stale repair confirmation handling; explicit failed, incomplete, skipped and unverified results. |
| Code organization and readability | 5 | Standard Laravel folders, descriptive class names, dedicated requests/services/support helpers and shared Blade components. Startup/QA helpers and archived copies are now clearly separated from active source. See the code organization guide for remaining readability observations. |
| Creativity and originality | 4 | Live Windows network measurements, evidence-based diagnosis and verified recovery go beyond manually adding, editing and deleting records. |
| Total | 50 | The table identifies evidence for assessment; it does not award a score. |

## Comparison with the teacher's Laravel example

| Book example pattern | NetRescue equivalent |
| --- | --- |
| `Book` extends Eloquent `Model`, with `$fillable` | `HealthCheck`, `Incident`, `RecoveryAction` and `MonitorSetting` use Eloquent attributes, casts and relationships. |
| `Schema::create()` in migration `up()` | Timestamped migrations define checks, incidents, repair evidence and settings; existing history is retained. |
| Named routes call controller methods | `routes/web.php` connects the local console to Dashboard, HealthCheck, Incident, Recovery and MonitorSetting controllers. |
| Controller validates, saves and redirects | `MonitorSettingController::update()` uses `UpdateMonitorSettingRequest`, updates validated attributes and redirects with feedback. |
| Controller queries Eloquent and returns `view()` | `IncidentController` queries/loads the local records and returns full-page or panel Blade views. |
| `@extends`, `@section`, named `route()`, `@csrf`, `@method`, `old()` and error display | The same Blade/form conventions are used in `resources/views/`. |
| Application-specific controller processing | Controllers delegate the more complex networking work to services. Services supplement MVC rather than replace it. |

The sample demonstrates conventional Laravel patterns; it is not a requirement
to duplicate its Book entity, CRUD screens, method count or Tailwind styling.
No sample commands were run, no sample database configuration was copied, and
no application logic was rewritten to imitate it. The later formatting pass
changes PHP whitespace/layout only; it preserves executable tokens and the UI.

## Examination documentation and demonstration

The existing `documentation/NetRescue-CIT-Documentation.html` contains the title
page, introduction, objectives, application description, features, tools,
MVC explanation, program flow and interface screenshots. These correspond to
sections A-I requested in the PDF. Its student name, course and instructor
fields are still blank and must be completed with the correct details before
submission. The existing paper date and screenshots should be checked against
the final submission; this review did not rewrite or re-render that paper.

The PDF also has separate documentation and pitching/demonstration rubrics.
Prepare to explain the problem, intended users, value, technologies, actual
demonstration and possible improvements. Explain the PHP workflow and Windows
OS bridge accurately. Launch helpers are optional startup conveniences, not
the diagnostic engine; they remain visible and documented in `tools/windows/`.

During the demonstration, distinguish a completed command from verified
recovery. Some physical, upstream or manually configured conditions require
human action. The system records those limits rather than claiming that every
network failure can be repaired automatically.

## Reading and evidence links

- [Code organization and MVC reading path](CODE_ORGANIZATION.md)
- [Architecture](ARCHITECTURE.md)
- [Running manual demo log](DEMO_TEST_LOG.md)
- [Fault demonstration guide](FAULT_SIMULATION_TESTING_GUIDE.md)
- [Recent UI and monitoring QA](REPAIR_UX_QA_2026-10-18.md)
- [Examination organization checks](ORGANIZATION_QA_2026-10-18.md)
