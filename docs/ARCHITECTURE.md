# NetRescue CIT Architecture

## Purpose

NetRescue CIT is a single-administrator Laravel utility. The Central computer owns the web console. Managed Windows endpoints run only the lightweight agent and report measurements to the API.

## Request flow

```text
CIT Administrator Browser
        |
        v
routes/web.php
        |
        v
Controller
        |
        +---- Models / Eloquent ----> MySQL
        |
        +---- Network Services ------> Processing / diagnosis / automation
        |
        v
Blade View
        |
        v
Administrator Browser
```

## Endpoint flow

```text
Managed Windows Computer
        |
        | heartbeat + measured health payload
        v
routes/api.php
        |
        v
Agent API Controller
        |
        v
RemoteHealthIngestService
        |
        +--> HealthScoreCalculator
        +--> DiagnosticEngine
        +--> IncidentManager
        |
        v
MySQL
        |
        v
CIT dashboard updates
```

## Responsibility split

### Models

Represent stored application data and relationships.

### Views

Blade templates display only the data needed by the administrator. Technical details are kept in drill-down pages.

### Controllers

Receive requests, validate/coordinate actions, and select responses. Heavy network logic does not live in controllers.

### Services

Perform network processing, calculations, diagnosis, incident synchronization, fleet analytics, and safe recovery.

## Access model

- `/`, `/devices`, `/history`, settings, recovery, and Central actions use `central.admin` middleware.
- The middleware permits only requests originating from the Central computer itself.
- Endpoint agents use token/enrollment-protected API routes and read-only bootstrap script routes.
- End users do not receive an endpoint dashboard.
