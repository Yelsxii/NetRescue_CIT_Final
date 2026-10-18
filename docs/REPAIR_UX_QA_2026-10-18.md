# Repair labels and follow-up confirmation — 18 October 2026

The generic Run repair button did not tell the user which command would be tried, and a second button after an ineffective renewal could look like a repeated promise of recovery. The experiment branch now names each attempt, explains its purpose and limits, and requires a review before every subsequent request for the same incident.

| Action | Button |
| --- | --- |
| Enable adapter | Try enabling adapter |
| DHCP renewal | Try renewing address |
| APIPA release and renewal | Try release and renew |
| DNS cache flush | Try clearing DNS cache |
| Adapter restart | Try restarting adapter |

The dashboard, full incident page, and fetched incident panel share one form component and the same guidance. They distinguish command completion from verified recovery. A completed but ineffective action names the remaining condition and says the attempt did not clear it. Unread, timed-out, changed-condition, and already-recovered results retain their distinct meanings. Existing database records and action labels are not rewritten.

Every follow-up button, including retries of the same command, opens a dialog showing the previous request's result, the proposed action's purpose and limitations, and any interruption to connectivity. Another attempt is optional. Cancel, Escape, and closing the dialog authorize no command. Confirmation submits the original form once, preserving its CSRF token and dashboard return destination.

The server also checks the submitted action and latest request ID under the existing repair lock. Missing or stale follow-up consent returns an explicit review page without starting a measurement or repair. Confirming a current action still uses the normal precheck and skips commands if the connection has already recovered. Diagnosis and Windows command execution behavior are unchanged.

## Validation

| Check | Result |
| --- | --- |
| Full PHP suite, SQLite `:memory:` with mocked Windows/network boundaries | 155 tests, 1,262 assertions passed |
| JavaScript syntax | `node --check public/js/app.js` passed |
| First-action labels and guidance | All five button variants covered by feature tests |
| Second request and same-command retry | Confirmation required on dashboard, incident page, and fetched panel |
| Missing, outdated action, or outdated request confirmation | Server returns review; no health reading or command started |
| Confirmed request with healthy precheck | Command skipped; already-recovered result retained |
| Incomplete verification | Check result remains available without repeating a repair |
| Browser: incident popup followed by confirmation | Correct stacking; underlying dialog inert; focus returns to repair button on cancellation |
| Browser: Escape and Cancel | No preview POST received |
| Browser: keyboard Enter and Shift+Tab | Enter opens review; focus stays inside the dialog |
| Browser: confirmed same-action retry | Exactly one preview POST; correct action, prior request ID, confirmation and CSRF retained |
| Browser: confirmed dashboard escalation | Exactly one preview POST; dashboard return destination retained |
| Browser: first action | Exactly one preview POST without a follow-up dialog |
| Appearance | Readable in light and dark mode at the in-app browser's 478 × 599 viewport; long text wraps and modal body can scroll |

Browser verification used synthetic pages rendered by the production views with SQLite in memory, served on loopback port 8001. The preview router never bootstraps the live application and never runs repair commands; its POST handler only records preview fields. These checks establish interface behavior, not a new physical fault-repair acceptance run.

Screenshots are retained in the session's visualization artifacts as `repair-follow-up-dark.png` and `repair-follow-up-light.png`. Preview pages, submission logs, and preservation digests are ignored runtime artifacts and excluded from the commit. Main is not merged or modified.

Read-only before/after digests matched for `.env` and all rows in the live devices (1), health checks (595), incidents (56), recovery actions (43), and monitor settings (1) tables. No real fault, Check, repair, migration, reset, or history deletion was performed against the live application during this change. Existing working-tree session/log changes were retained and excluded from the commit.
