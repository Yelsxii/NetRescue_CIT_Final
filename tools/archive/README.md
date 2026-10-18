# Historical support tools

These artifacts are retained for traceability and are not part of the current
local-only application's runtime.

| Directory | Contents |
| --- | --- |
| `agents/` | Unchanged legacy agent ZIP from the removed remote-device feature. It has no active route or deployment flow. Do not install it for the current local-only version. |
| `diagnostics/` | Unchanged temporary development diagnostics, preserved outside the web root. See that directory's README before inspecting them. |

Normal application processing belongs to the root `app/`, `routes/` and
`resources/` directories. Current QA helpers remain in `tools/qa/` and optional
Windows launchers in `tools/windows/`.
