# NetRescue CIT — current project scope

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
recognition/enrollment, endpoint agents, heartbeats, deployment packages and
remote command queues are removed. This document describes the current
implementation; it does not change the instructor's examination requirements.

The database supports the utility. Its main work is measurement, processing,
comparison and automation rather than manual CRUD entry of monitoring results.
