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

## Layout refinement after the manual demonstrations

The repair card now uses one action heading and purpose, a concise previous result when present, and a visible interruption notice when relevant. The action sits in a separate bottom-right footer. Network configuration, action limits, full stored result text, and command evidence remain available through closed disclosures rather than repeating in the main card. Dashboard, incident page, and fetched incident panel share the same guidance component. Manual DNS, external-path, ping-only, and incomplete-reading states retain distinct instructions.

The follow-up dialog separates the previous result, proposed action, and interruption notice. Limits and verification details are expandable. Cancel and confirmation remain visible while the body scrolls on a short screen. The server-side action/request confirmation checks and repair commands are unchanged.

- Full regression suite: **161 tests, 1,418 assertions passed**, using SQLite in memory and isolated compiled views.
- JavaScript syntax and scoped whitespace checks passed; independent code review found no confirmed regression.
- Production views rendered 12 synthetic layouts, covering the seven simulation scenarios plus escalation, retry, manual handoff, and uncertain verification.
- Browser checks covered light/dark themes, desktop, 375 × 812 phone layout, and an expanded dialog at 320 × 568. No document-level horizontal clipping was found in the checked phone cards; action buttons fit their footers.
- Escape/Cancel sent no preview POST. One confirmed follow-up sent exactly one POST with the correct action, prior request ID, confirmation, and CSRF. The first DNS action submitted directly once and retained its dashboard return destination.
- The fetched incident panel opened the follow-up dialog above it; cancellation returned focus to its originating repair button. Keyboard focus stayed within the active dialog.

These browser checks used a static fixture server on loopback port 8001 that never boots the live app or executes a repair. They are interface verification, not another physical fault test. Screenshots include `repair-organized-card-dark.png`, `repair-organized-popup-light.png`, and `repair-organized-popup-phone-dark.png` in the session visualization artifacts. Fresh read-only before/after digests again matched for the live `.env`, settings, database records, and history. Existing runtime changes were preserved. No live application Check, repair, fault, Restore, or database mutation was performed for this refinement.

## Final evidence layout and wording

Cards and diagnosis highlights now use a simple container without a left accent bar. Routine interruption notes use colored text without a second container. Table row left highlights are retained. The repair action remains in the bottom-right footer, and the follow-up popup keeps the previous result, proposed action and optional details in separate levels.

The incident page shows the main finding, latest repair result and current status or resolution first. **Latest repair details** is closed initially. Inside it, command completion and recovery verification are separate results, followed by a labeled **Before: initial check / After: final check** comparison and separate command/verification durations. Earlier automatic readings, technical configuration and command output are expandable. **Earlier repair attempts** contains one closed disclosure per request; each keeps its own measurements and output, rather than mixing evidence from different attempts.

Resolution text now uses complete sentences. For example: “The pre-repair check confirmed that the condition had already cleared. No repair command was run.” Findings distinguish a failed test from one that was not performed, and a closing reading describes the actual recovered adapter/address rather than repeating the opening fault. No stored incident or repair text is rewritten.

- Regression coverage checks request identity, before/after associations, skipped versus failed probes, missing measurements, zero exit codes, separate durations, closed disclosures and preserved raw records.
- Browser checks covered desktop, 375 × 812 and 320 × 568 layouts, light/dark themes, the comparison, earlier attempts and follow-up popup. Checked phone layouts had no document-level horizontal overflow; measurements stack vertically.
- Escape/Cancel does not submit a repair. The existing server confirmation guards and Windows repair commands are unchanged.
- Screenshots retained locally: `repair-card-final-dark.png`, `repair-popup-final-dark.png`, `incident-evidence-dark.png` and `incident-evidence-phone-light.png`. These are synthetic previews of production templates, not physical fault-repair evidence.

## Automatic monitoring investigation and verification

At the time of investigation, the web server was running on port 8000 without `artisan schedule:work`. The user had enabled automatic checks at a one-minute interval after the earlier UI snapshot; that current setting was preserved. Starting the web server alone does not run scheduled checks. The existing Windows launcher already starts both processes, so its command sequence did not require a change.

A lightweight cached scheduler tick now distinguishes **Paused**, **Scheduler not detected**, and a recently responding scheduler. The tick performs no network measurement and works while checks are paused. It becomes stale after 150 seconds; it confirms recent scheduler activity rather than guaranteeing completion of a future check. The dashboard withholds its countdown when no recent tick is available, shows launcher guidance, and refreshes when scheduler status changes without interrupting an active repair or modal. No schema, `.env`, interval, diagnosis or repair behavior changes are required.

The missing scheduler was started on the actual Windows host using the existing PHP installation. Read-only database verification recorded these automatic results, in local time on 18 October 2026:

| Check | Recorded time | Result |
| --- | --- | --- |
| 693 | 9:42:19 PM | Network Operational; gateway, external connectivity and DNS passed |
| 694 | 9:44:06 PM | Network Operational; gateway, external connectivity and DNS passed |
| 695 | 9:45:07 PM | Network Operational; gateway, external connectivity and DNS passed |
| 696 | 9:46:05 PM | Network Operational; gateway, external connectivity and DNS passed |

The live dashboard displayed **Every 1 min**, **This computer is connected**, and zero open incidents. DNS responses exceeded the existing 100 ms warning; connectivity was available. The scheduler remains running for the user's enabled monitoring. The temporary fixture server is separate and is stopped after UI verification. Future sessions should use `START_NETRESCUE.bat` or keep `php artisan schedule:work` running in a second project terminal, without launching duplicate schedulers.

Final validation: **173 tests, 1,510 assertions passed** against SQLite in memory with mocked network boundaries. This includes missing/stale/future/malformed scheduler ticks, paused checks, interval gates, status JSON and dashboard states. JavaScript syntax and scoped whitespace checks passed. A separate read-only review found no confirmed UI or monitoring regression. Browser checks verified the missing-scheduler message in both themes at 320 pixels and the live responding state. The live screenshot is retained locally as `automatic-monitoring-live.png`.

Preservation was checked against a fresh 9:41 PM baseline, after the user's intervening manual checks/settings changes. All 620 pre-existing health-check rows, 59 incidents, 46 repair requests and the enabled one-minute settings retained matching raw-row digests. Normal automatic checks were appended; the device's latest status was updated by monitoring. `.env` retained SHA-256 `42A83834DFE747DE1C06143AD33FF0C446C9CF308A4F6E2188454C27DAEBA168`. No network settings, fault injection, repair, database reset, migration or history deletion was performed during this investigation. Existing unrelated working-tree changes and runtime files are retained and excluded from the commit.
