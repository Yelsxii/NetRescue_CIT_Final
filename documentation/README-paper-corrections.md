# Corrections for NetRescue_Updated (1).docx

Checked against the current NetRescue CIT code and screens. Most of the technical body matches the system. The interface section does not. It describes one incomplete reading and one timed-out repair as if those examples were the screens, and it tells the reader that a finished screenshot set is still missing.

Write the student lines, course, and instructor on the title page if they are not already filled. The names in this copy are already present.

## Keep as written

These statements match the system. They do not need to be softened.

- Checks run in order: adapter and configuration, then gateway, external TCP, then DNS. A failed prerequisite means the later tests are not counted as separate failures.
- An unfinished configuration read is stored and does not open or close an incident.
- Health points are gateway 30, Internet 30, DNS 20, and response 20. Response points require a reachable gateway and a numeric time at or below the warning setting.
- The 24-hour figure is the share of finished checks in that period whose stored health status is healthy.
- Repairs are limited to clearing the DNS cache, renewing DHCP, enabling the adapter, and restarting the adapter. A DHCP problem uses release and renew on the measured adapter. Other address repairs renew only.
- A repair is confirmed by the user, then a fresh reading of the same adapter must still match before the command runs. The command runs once. Follow-up readings do not repeat it.
- A completed command and a verified recovery are stored separately. A timed-out or failed command can still be followed by observed recovery, and that recovery is not stored as a verified repair.
- Scheduled checks follow the saved on/off switch and interval, and only while the scheduler is running. A manual check and a confirmed repair still run when automatic checks are paused.
- Settings accept intervals of 1, 5, 10, 15, 30, or 60 minutes, a warning from 10 to 1000 ms, and a DNS test domain. Invalid values are rejected.
- Routes, controllers, and the five models in the tables match `routes/web.php`.
- A healthy finished check can close an open incident as observed recovery. Closure by itself does not mean a repair was verified.
- A disconnected link, a missing adapter, an upstream failure, and a manual address are guidance cases. The application does not pretend a repair command can fix them.
- Manual DNS is not corrected by flushing the cache. The next step says a person must correct that server or set DNS to automatic, and another repair still needs its own confirmation.

## Change the interface section

The section “User Interface and Screenshots” is the part that misleads. Figures 3 and 4 are described as development captures of an unfinished reading and an unverified command. The text then says the final screens for Checks, Settings, repair confirmation, and a verified repair are still needed, and that the screenshots do not show that the build passed its repair tests.

That describes one moment in testing. It does not describe what those screens are.

Replace that section with a description of the screen and the record it keeps. Do not quote the words, times, addresses, or error text visible in a picture. A caption may name the screen. It should not retell the example.

Use this wording:

**Local dashboard.** The dashboard is the current summary for the computer running NetRescue. It records whether the latest finished check needs attention, how many finished checks in the last 24 hours were classified healthy, and how many incidents are open. It shows the latest response times for the gateway, the external path, and name lookup, the last completed address, gateway, adapter, and DNS servers, and a short list of recent checks with each result. Run Check starts a new measurement.

**Incidents.** The incident list records each detected condition, when it started, how long it remained open, the repair outcome, and whether the record is open or closed. Status and severity filters limit that list. One incident record keeps the condition found at the start, the latest repair request and its result, and the closing or current outcome. Measurements, command status, and command output stay in a separate details section so the summary does not depend on the log.

**Checks.** The Checks page is the measurement history. Each row records the time of the reading and the gateway, Internet, and name-lookup result, including a result that was not tested because an earlier requirement failed. The diagnosis for that reading is stored with the row.

**Settings.** The monitoring window records whether automatic checks are on, how often they run, the slow-response warning, and the domain used for the name-lookup test.

Do not write that the dashboard “shows an incomplete reading” or that the incident page “shows a request that timed out.” Those can be true of one saved record. They are not what the screen is for. If a picture happens to contain a slow reading or a closed incident, let the picture carry the example. The paragraph should still describe the record.

Also remove these sentences:

- “Figures 3 and 4 are supplied development captures…”
- “They do not establish that the final build passed all repair tests.”
- “A complete final screenshot set should also show…”
- “The supplied screenshots show earlier error handling.”
- “final-run outcomes remain pending.”



Use them as figures of the interface. Captions should follow the wording above, not the text inside the image.

The Check result button is not part of every incident. It appears only when an open incident still needs another reading and the repair command should not be repeated. Say that only if you describe actions, and say when it is offered.

The third card on an incident is not always labeled “current outcome.” An open incident shows the current outcome. A closed incident shows the closing check or the observed resolution. Describe the record, not one heading.

## Fix these system sentences

**“A different diagnosis can replace the earlier condition.”**  
That is not what happens. A new condition closes the older open incident as superseded and opens its own incident. The older record keeps its original condition. Replace that sentence with: a different diagnosis closes the earlier open incident and starts a separate incident for the new condition.

**Occurrence count.**  
The count is stored and increases when the same open condition is measured again. No screen displays that number. Do not describe it as something the user reviews. You may say the system counts repeated detections of the same open incident.

**“Opening, latest, and closing readings.”**  
The incident screen shows the opening condition and, when the incident is closed, the closing measurement. A latest reading is stored, but it is not presented as a third reading on that page. The Checks page is the list of measurements. Say “opening measurement and closing measurement,” not three linked readings.

**“Charts.”**  
The technology table says the interface provides charts. The current screens do not show a trend chart. They show response-time bars. Change “charts” to “response-time bars.”

**Development tools in the technology table.**  
Cursor, Git, GitHub, Laravel Tinker, and Pint are editing and testing tools. They are not part of the running utility. Keep them only if the table is clearly split into “application” and “development.” Leaving them under “Use in the Application” makes them look like system features.

**PowerShell 5.1.**  
The program starts Windows PowerShell. The source does not require version 5.1. Say “Windows PowerShell” unless you have confirmed that version on the demonstration computer.

**Internet test targets.**  
They are stored in monitoring settings and used by the external TCP check. The settings window does not edit them. Do not add them to the list of fields the user changes in that window.

## Test-case table

Table 7 matches the rules in the code. Keep the procedures and expected results.

Do not attach those rows to the screenshots, and do not say the cases are still pending unless that is the status you want the instructor to read. If a case was not run on the Windows computer, leave it as an expected result and do not imply that a screenshot proves it. If it was run, say that in one line of your own. Do not invent a pass.

## What not to add

Do not copy sample sentences from the screens into the paper. That includes a particular latency, a computer name, an incident number, “Network Adapter Disabled,” a timeout message, or a command excerpt. Those are examples of stored records. The paper should name the kind of record.
