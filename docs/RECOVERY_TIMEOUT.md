> Historical troubleshooting note. The current application monitors this computer only. See README.md and docs/LOCAL_ONLY_MONITORING.md for current startup/scope; old device/agent instructions no longer apply.

# Repair request timeout correction

A live enable-adapter repair brought Wi-Fi back, but the HTTP request ended with PHP's “Maximum execution time of 60 seconds exceeded”. The operation had not completed its evidence and verification flow. This correction addresses that request failure; the live Windows repair still needs a retest.

## Changes

- Prepare a finite PHP request allowance before local repair or manual network measurement. Recovery gets at least 180 seconds, expanded when the configured probe/command deadlines require it. Manual checks account for all configured Internet targets. Individual process and socket deadlines remain in place.
- Persist command execution status, exit code, output and error immediately when the runner returns, before reconnect delay and the follow-up measurement.
- Keep command completion separate from verified recovery. An interrupted or unreadable follow-up keeps the incident open and asks for a fresh Central Check.
- Describe missing evidence accurately. Existing interrupted records are not rewritten as verified repairs.
- If serving PHP cannot accommodate the required allowance, return a clear message before starting repair.

## Multiple repair requests

Refreshing a failed POST can submit another request. If its fresh pre-check is healthy, that request is skipped. The latest request now explicitly says “No command was run for this request”; this does not erase an earlier interrupted or failed command. The modal and full incident page include Previous repair requests with retained status, errors and measurements. Missing evidence remains unknown. No historical result is rewritten.

## Install the correction ZIP

1. Stop the Laravel server with Ctrl+C. Preserve any newer local edits to the matching files.
2. Extract the ZIP outside the project. Copy the folders inside its files folder directly into C:\xampp\htdocs\NetRescue_CIT_Final, merging folders and replacing matching files. Copy the contents of files, not the files folder itself.
3. Open administrator PowerShell in the project and run:

    php artisan optimize:clear

4. Start Laravel from that same administrator PowerShell:

    & "C:\xampp\php\php.exe" artisan serve --host=127.0.0.1 --port=8000

5. Open http://127.0.0.1:8000/ directly and press Ctrl+F5.

No migration or database reset is required. The ZIP also includes the previously reviewed repair-button placement from draft PR #1. The ZIP was prepared against main commit 037cfdfad43d9e68dc79fcc974e065c4d65a002f. GitHub publication of the timeout correction is pending authorization; the empty fix/recovery-request-timeout branch does not contain the correction.

## Finish the interrupted attempt

Leave Wi-Fi enabled. Open the dashboard directly, then run Central Check. Inspect the current incident. A fresh healthy reading can close the incident as observed recovery; a past request without retained completion evidence must not become a verified repair.

## Retest the disabled-adapter repair

1. Pause automatic checks. Run Central Check and confirm Network Operational using Wi-Fi.
2. Save the fallback command locally:

    Enable-NetAdapter -Name "Wi-Fi" -Confirm:$false

3. In administrator PowerShell disable Wi-Fi:

    Disable-NetAdapter -Name "Wi-Fi" -Confirm:$false

4. Run Central Check. Expect Network Adapter Disabled and a Wi-Fi enable action.
5. Click Run repair once. Let the request and verification finish; Wi-Fi can reconnect before the loading overlay closes.
6. Inspect the new incident's expanded measurements and command details, then run another full Central Check after reconnection.

Verify the command result and target-condition change separately from full Internet/DNS health. If the fallback command is needed, record that the restoration was manual.

## Validation

58 automated PHP tests passed, with 253 assertions. Windows probes/repair commands are mocked; the container's interface enumeration is substituted only in the test bootstrap. Regression coverage includes request allowance before execution, changed configured deadlines, stored command evidence before verification, verification interruption and honest table/modal descriptions. Browser checks of the pending-verification layout passed four screen sizes in both light and dark mode. Actual Windows repair execution must be verified on the laptop.
