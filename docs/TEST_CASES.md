# NetRescue CIT Suggested Test Cases

| Test | Expected result |
|---|---|
| Open dashboard on Central computer | Administrator dashboard loads |
| Open dashboard from another LAN computer | HTTP 403; no monitoring data is shown |
| Run Central health check | A real `health_checks` record is stored and the dashboard updates |
| Healthy gateway + Internet + DNS | Healthy diagnosis and high score |
| Internet works but DNS fails | DNS-specific diagnosis; approved DNS-cache recovery can be offered |
| APIPA / 169.254 address | Possible DHCP issue; approved DHCP renewal can be offered |
| Gateway fails | One gateway/local-network root condition; downstream failures are not separately treated as root causes |
| Endpoint heartbeat becomes stale | Device becomes not reporting/offline and an automatic incident is created |
| Same problem repeats | Existing active incident occurrence count increases instead of creating alert spam |
| Condition recovers | Active incident resolves automatically after a healthy/new diagnosis supersedes it |
| Approved recovery succeeds | Before/after checks are saved and success is verified |
| Invalid settings | Laravel validation displays field errors |
| Theme toggle | Light/dark preference persists across page reloads |
| Sidebar collapse | Desktop menu collapses and preference persists |
| Mobile viewport | Menu becomes a drawer and cards/lists remain readable |
