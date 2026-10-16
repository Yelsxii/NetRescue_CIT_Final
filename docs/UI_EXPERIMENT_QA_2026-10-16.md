# Settings, themes, and responsive UI experiment

Date: 2026-10-16, on the Windows development laptop.

Branch: `codex/ui-settings-responsive-experiment`, based on main at
`7e9b03ac7c0588393e8db65917d5c8e87a5e0a36`. This experiment is separate from
the merged Windows fault-QA work. No network faults or repairs were run during
this design pass.

## Changes

- Settings uses the same dropdown design as incident filters. The original
  select still submits its value; the enhanced control supports arrows,
  Home/End, Enter/Space, Escape, and Tab.
- Replaced the native millisecond spinner with minus/plus buttons and a clear
  `ms` suffix. Direct number entry, integer steps, and the 10–1000 range remain.
- Dark mode uses a deep navy canvas, opaque slate sidebar, distinct panel and
  control surfaces, and visible focus indicators. The light menu icon now
  inherits a dark foreground instead of hardcoded white.
- Mobile navigation has an explicit close button, focus containment, and an
  inactive background while open. Settings and incident dialogs return focus
  when closed.
- Filters wrap on narrow screens. Tables retain all columns inside focusable
  scroll regions with visible scrollbars and an overflow-only hint above them.
  Incident names are real links usable with the keyboard or a new tab.
- Short dialogs scroll; Settings dropdowns stay within the viewport and can
  open above their trigger. The mobile Now card exposes all of its content.

## Verification

Actual browser interactions used the Codex in-app Chromium browser on Windows.
Viewport overrides emulate screen sizes; this is not a physical phone test.

| Page | Viewport widths | Themes | Result |
| --- | --- | --- | --- |
| Dashboard | 320, 375, 768, 1024, 1440 | Light and dark | No page/content horizontal overflow; narrow Now card has no height cap |
| Checks | Same five widths | Light and dark | Every column retained inside the table scroll region |
| Incidents | Same five widths | Light and dark | Filters fit; every column retained inside the table scroll region |
| Incident detail | Same five widths | Light and dark | Cards and text fit without horizontal clipping |

Additional interaction checks passed:

- Settings at 320×640, 375×812, 1440×900, and short landscape 844×390;
  light and dark landscape dialogs stayed inside the viewport.
- At 844×320 the dropdown flipped upward, with its top clamped to 12px.
- Dropdown selection, arrow navigation, Home/End, Enter, Escape, and Tab.
  Escape dismissed only the open list; uncommitted navigation did not change
  the selected value. Fixed an opening-focus defect found during this pass.
- Modal Tab loop and focus return; mobile menu open/close and background
  isolation; collapsed desktop navigation in both themes.
- Direct number entry, +1 increment, and disabled minus/plus controls at
  10 and 1000 respectively.
- Saved interval 5 and warning 100 in the isolated copy; success feedback and
  the saved interval persisted across reload. Live settings remained unchanged.
- Incident status filter submitted `resolved`; keyboard Enter on an incident
  link opened its details modal. The phone details modal had no horizontal
  overflow. Keyboard ArrowRight scrolled a table by 80px in the manual check.
- Overflow hint disappears when all columns fit and returns at narrow widths.
- No browser console errors or warnings were observed on the checked pages.
- PHP regression suite: **149 tests, 1,168 assertions passed** using SQLite
  in memory. JavaScript syntax and scoped Git whitespace checks passed.

## Data and branch preservation

Preview ran only on `127.0.0.1:8001`, using a separate SQLite copy under ignored
`storage/app/private/ui-experiment`. Process-only configuration provided a
separate cookie name, compiled-view directory, and log output. The existing
port-8000 server, Apache, and MySQL were left running. No live migrations,
seeding, repair calls, or network setting changes were performed.

At 23:14:04 +08:00, read-only SHA-256 comparisons confirmed `.env` and all rows
of the following live tables matched the pre-preview snapshot:

| Table | Rows | Comparison |
| --- | ---: | --- |
| devices | 1 | Unchanged |
| health_checks | 542 | Unchanged |
| incidents | 46 | Unchanged |
| recovery_actions | 33 | Unchanged |
| monitor_settings | 1 | Unchanged |
| enrollment_codes | 1 | Unchanged |
| agent_commands | 0 | Unchanged |

Existing unrelated local changes and runtime files were excluded from the
experiment commit. Main remains at the original commit above. Screenshots,
the viewport matrix, preview database, and preservation hashes are local QA
artifacts; they are not committed.

## Assessment

The design is consistent enough for user evaluation on the experiment branch.
The reported hidden-menu, clipped-dialog, filter-overflow, and table-overflow
problems are addressed in the tested browser. Dense tables intentionally scroll
horizontally on phones rather than dropping information. Physical touch-device
and other browser-engine testing remain useful before calling the appearance
final. This pass changes presentation and interaction only; the prior Windows
fault-QA report remains the evidence for network diagnosis and repair behavior.
