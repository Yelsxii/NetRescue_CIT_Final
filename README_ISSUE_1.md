# Issue 1 correction: accurate repair evidence and readable records

This patch is based on the NetRescue_CIT_Final(1).zip uploaded in this conversation.
It addresses the DHCP demonstration and its repair/resolution presentation.
The other fault demonstrations should be examined separately after this retest.

## What changed

- A fresh pre-repair check that is already healthy now closes the incident and
  skips the command. The request is recorded as execution_status=skipped,
  verification_status=already_recovered, success=null.
- An unreadable precheck, a changed diagnosis, or a different selected adapter
  also prevents an inappropriate command. A changed diagnosis is described as
  a changed condition, not automatically described as a successful repair.
- Execution status, verification status and command exit code are recorded
  separately for new local repair attempts. Failure to verify an improvement
  no longer becomes the absolute claim that the command did not clear it.
- DHCP release/renew now targets the measured adapter instead of every adapter.
- New incidents store the exact opening and closing health-check IDs. Old records
  use their recorded latest check only when its timestamp exactly matches their
  resolution timestamp; no nearby check is selected as substitute evidence.
- One shared IncidentStory provides both the outcome and repair statements to
  the table, modal and full incident page. Both statements wrap in the table.
- The modal uses its available height and one scrolling body; cards have natural
  height. The Now configuration and repair block no longer share shrinking space.
- Gateway/Internet/DNS probes skipped by an earlier prerequisite appear as
  Not tested rather than FAIL / Did not answer. Historic readings are inferred
  using the original probe pipeline; new local readings store explicit states.
- Stored next-step guidance is displayed, and a later check of the same open
  fault preserves the existing repair escalation instead of resetting it.
- Reporting / Not reporting replaces Online / Offline in dashboard totals to
  clarify that device contact is separate from Internet availability.
- Command errors/output and before/after measurements are available under
  Measurements and command details. Times include seconds.

The success flag of old records, including the reported NR-00019, is not rewritten.
Its healthy pre-repair and post-repair measurements are explained honestly.
The new audit columns remain nullable for legacy and existing endpoint records.
Endpoint execution/reporting behavior is outside this first local repair correction.

## Apply to your Windows project

1. In Tinker type `exit` to return to PowerShell. Stop the Laravel server and
   scheduler windows with Ctrl+C.
2. Back up your project folder and export the current database from phpMyAdmin
   (select the database, Export, Quick, SQL). This preserves the original code
   and recorded incidents for comparison.
3. Extract this ZIP outside the project. Open its `files` folder. Copy its app,
   database, public, resources and tests folders into
   `C:\xampp\htdocs\NetRescue_CIT_Final`, merging folders and replacing matching
   files. These are partial folders: keep the rest of your existing project.
   If you changed a listed file in Cursor after uploading the original ZIP,
   compare/merge that file in Cursor before replacing it.
4. In PowerShell in your project folder run:

   ```powershell
   php artisan migrate --path=database/migrations/2026_10_14_000010_add_recovery_evidence.php
   php artisan optimize:clear
   ```

   This adds audit columns. Do not run migrate:fresh or reseed your working
   database. Your previous incidents and measurements should stay available.
5. Start MySQL as usual. Start Laravel from an administrator PowerShell:

   ```powershell
   php artisan serve --host=0.0.0.0 --port=8000
   ```

   Keep automatic checks paused for this first demonstration. The scheduler can
   be restarted later with `php artisan schedule:work`.
6. Open http://127.0.0.1:8000 and press Ctrl+F5 to reload the stylesheet.

## Retest issue 1 before proceeding

1. Open Incidents and NR-00019. Its table row and modal should show the same
   outcome and repair summaries. The stored false success flag remains false,
   but the explanation should say the connection was already healthy before
   the attempt, rather than claim that the action failed to clear the fault.
2. Check the dashboard at your normal browser size and at 125% zoom. DNS values
   should wrap without covering the repair block. Long incident/resolution text
   should wrap; the table can scroll horizontally on a narrow viewport. The
   modal body can scroll without clipping the text inside individual cards.
3. With a healthy DHCP Wi-Fi baseline, run `ipconfig /release "Wi-Fi"`, then
   immediately Run Central Check. A 169.254 address should produce Possible
   DHCP Configuration Issue. Missing IPv4 is a separate possible observation.
   Gateway/Internet/name lookup should show Not tested when their prerequisites
   are unavailable.
4. Click Run repair. If Windows already reacquired an address before the fresh
   precheck, expect Already recovered - no command run. That is the correct
   outcome; do not label it as a verified application repair.
5. If the fault is still present at the precheck and the command restores it,
   expect command status completed, verification verified and success=true.
6. If a command fails, its execution result and error should be visible.
   A usable address returning separately is observed recovery, not proof that
   the failed command caused it. A continuing fault stays open.
7. Capture the table and opened modal plus Measurements and command details.
   Compare the actual result against those cases before trying the next fault.

Fallback restoration: `ipconfig /renew "Wi-Fi"` in administrator PowerShell.
Use the exact adapter name reported by Now if it differs from Wi-Fi.

## Validation

The patch includes 12 regression tests in RecoveryEvidenceTest covering the
precheck guard, real before/after improvement, command errors, independent
recovery, unreadable readings, adapter changes, changed conditions, legacy
NR-00019-style reporting, table/modal consistency, exact closing evidence,
skipped probes and preservation of the next repair step.

All 45 PHPUnit tests passed (162 assertions) on PHP 8.3 with an in-memory SQLite
test database. Windows network commands were mocked; they must still be tested
on your laptop. A test-only stub supplied an empty network-interface inventory
because this container blocks getifaddrs(); production access-control code was
not changed. PHP syntax checks passed for the changed application/test files. Real Blade
views were rendered and checked in headless Chromium at 1920x1080, 1366x768,
1093x614 (a smaller zoom-equivalent viewport), and 390x844. The checks confirmed
matching table/modal statements, wrapped table text, no Now/repair overlap,
and no clipping inside modal cards. Windows command execution is still a local
retest, not something this browser check can prove.

To run the targeted tests locally in a disposable project copy with the supplied
phpunit.xml using SQLite :memory:, clear cached configuration in that copy and run:

```powershell
php artisan config:clear
php artisan test --filter=RecoveryEvidenceTest
```

The SQLite PHP extension must be enabled for the test database. Running these
logic tests does not replace the Windows network demonstration above.

## Changed files

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/IncidentController.php`
- `app/Http/Controllers/RecoveryController.php`
- `app/Models/HealthCheck.php`
- `app/Models/Incident.php`
- `app/Models/RecoveryAction.php`
- `app/Services/Network/IncidentManager.php`
- `app/Services/Network/NetworkHealthService.php`
- `app/Services/Network/NetworkProbeService.php`
- `app/Services/Network/RecoveryCommandRunner.php`
- `app/Services/Network/RecoveryService.php`
- `app/Support/CheckStory.php`
- `app/Support/IncidentStory.php`
- `database/migrations/2026_10_14_000010_add_recovery_evidence.php`
- `public/css/app.css`
- `resources/views/components/check-table.blade.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/devices/show.blade.php`
- `resources/views/history/index.blade.php`
- `resources/views/incidents/case.blade.php`
- `resources/views/incidents/panel.blade.php`
- `resources/views/incidents/show.blade.php`
- `tests/Feature/RecoveryEvidenceTest.php`
