# Database schema — local monitoring

## Active data

- devices: the existing fixed local UUID identifies this installation's
  computer. Its row retains current network facts and links existing history.
- health_checks: local adapter/configuration, prerequisite-aware probe results,
  timings, diagnosis and score. UI/summary queries require the local device ID.
- incidents: local detected conditions with exact opening and closing checks.
- recovery_actions: local command status, verification outcome and before/after
  check IDs. Intermediate verification IDs/timings live in linked probe_meta.
- monitor_settings: this computer's interval, enabled flag, warning threshold,
  DNS test name and Internet test targets.

## Preserved legacy schema

Existing enrollment_codes, agent_commands and old device columns/rows are not
used. Their models, controllers, services, routes and schedulers are removed.
They remain only to avoid destructive changes to an existing database and keep
historical migrations coherent. The local-only update executes no SQL, drops
no tables and rewrites no incident or recovery outcomes.

Existing local records remain associated with the same Device::LOCAL_UUID and
IDs. Old remote rows are excluded even if their recognized/is_local flags are
incorrect; public queries and actions use the actual local identity, not a
collection of rows tagged local or recognized.
