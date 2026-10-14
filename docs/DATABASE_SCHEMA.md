# Database Schema Summary — NetRescue Central 2.1

## devices
One row per monitored endpoint. Stores endpoint identity, connection type (`local` or `agent`), latest reported network identity, last health score/status, heartbeat timestamps, and the hashed agent token.

## health_checks
Actual local or agent-submitted network measurements. Each row belongs to a device and stores adapter/IP/gateway/DNS details, probe outcomes, latencies, health score, diagnosis, and limited probe metadata.

## incidents
Automatically generated primary network conditions per device. Stores first/last detection, severity, active/resolved status, observation count, recommended action, and optional safe recovery code.

## recovery_actions
Stores approved recovery attempts and the linked before/after health checks. Remote recovery records remain pending until the endpoint agent returns verification evidence.

## monitor_settings
Central monitoring configuration: local-host scheduler interval, latency warning threshold, DNS test domain, and external Internet targets.

## enrollment_codes
Temporary pairing codes used to authorize new Windows endpoint agents.

## agent_commands
Queue of approved remote recovery commands for endpoint agents. The queue only stores predefined command codes, not arbitrary shell commands.
