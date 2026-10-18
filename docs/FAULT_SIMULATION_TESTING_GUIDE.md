# NetRescue: manual fault simulation guide

Follow Faults **1 to 7**, one at a time. Use PowerShell to inject and restore each fault, and use the NetRescue buttons for diagnosis and repair. **Do not add -ExerciseApp to any command.**

Each injection saves the original settings and arms a **10-minute safety rollback**. Complete the browser steps and explicit Restore before that deadline. The runner performs a healthy prerequisite Check before injection; you perform the post-injection Check and repair in the browser.

If an injection fails and printed a new StatePath, restore that run before retrying. If no new StatePath was printed, address the error first; do not use another run's path. If the new reading differs from the expected result, restore the current run before retrying. If Restore fails, keep its state and rollback task, resolve the reported error, and retry the same Restore. Do not start another fault while the previous test remains active.

## Start the application before Fault 1

1. Start **MySQL** in XAMPP. Keep the existing database and .env.
2. Use **two Windows PowerShell windows opened with Run as administrator**. Window 1 runs NetRescue; window 2 runs the fault commands.
3. If NetRescue is already served by an administrator terminal, leave it running. If it is running without administrator rights, stop that server with **Ctrl+C in its own terminal** first.
4. To start the server, run this in **Administrator PowerShell window 1**:

```powershell
Set-Location C:\xampp\htdocs\NetRescue_CIT_Final

$serverPrincipal = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $serverPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Open Windows PowerShell using Run as administrator, then retry.'
}

& 'C:\xampp\php\php.exe' artisan serve --no-reload --host=127.0.0.1 --port=8000
```

5. Leave window 1 running. Open **http://127.0.0.1:8000** in the browser.
6. Open **Settings**. Note whether **Automatic checks** was on or off, switch it **off**, and click **Save settings**. Restore that original setting when all tests are finished.
7. Keep a valid lookup domain, such as **www.google.com**, in Settings. Confirm Wi-Fi is connected. Use one active connection; disconnect any VPN or alternate connection for these tests.

## Prepare PowerShell once for Faults 1-7

In **Administrator PowerShell window 2**, run this setup **once**. The commands use **Wi-Fi**; if Get-NetAdapter shows a different adapter name, change InterfaceAlias to that name before continuing.

```powershell
Set-Location C:\xampp\htdocs\NetRescue_CIT_Final
Set-ExecutionPolicy -Scope Process -ExecutionPolicy RemoteSigned

Get-NetAdapter | Select-Object Name, Status

$qaRunner = '.\tools\qa\Invoke-NetRescueFault.ps1'
$qaOptions = @{
    InterfaceAlias = 'Wi-Fi'
    PhpPath        = 'C:\xampp\php\php.exe'
    BaseUrl        = 'http://127.0.0.1:8000'
}
```

Answer **Y** if PowerShell asks about the execution policy. Keep this window open and in the project folder for all seven faults. The per-fault Inspect commands below only check the current settings; they do not repeat this setup.

If you close this window, open a new Administrator PowerShell window and rerun this setup. If a fault is still active, restore it using its saved StatePath before starting another fault.

## Fault 1 - Wi-Fi adapter turned off

**Test:** NetRescue detects a disabled adapter, enables it, and verifies recovery.

### 1. Check the starting connection

In the same **Administrator PowerShell window 2** prepared above, run:

```powershell
& $qaRunner @qaOptions -Mode Inspect
```

Inspect should display the selected adapter and its current settings without injecting a fault.

In **NetRescue**:

1. Keep MySQL and the administrator application server running.
2. Open **Settings**, confirm **Automatic checks** is off, and save if you changed it.
3. Go to **Dashboard** and click **Run Check**.
4. Wait for the check to finish. Require **Network Operational**, with Gateway and Internet passing and Name lookup answering. A slow successful lookup is acceptable.
5. Keep the Dashboard open before running the fault command.

### 2. Inject the fault

In **Administrator PowerShell window 2**, run:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault AdapterDisabled
```

Wait for **Rollback armed. StatePath: ...** and **Applied AdapterDisabled to Wi-Fi**. The printed StatePath belongs to this test and is required for Restore.

**Keep this terminal output open. Use its newly printed StatePath when you reach the Restore step.**

### 3. Detect the fault in NetRescue

1. In **Dashboard**, click **Run Check** after the terminal prints Applied.
2. Wait for the new result. Expect **Network Adapter Disabled** in the Now panel. The incident title may be **Adapter turned off**.
3. Confirm the adapter is **Wi-Fi**. Gateway, Internet and Name lookup should be **Not tested** because the adapter is disabled.

### 4. Run the application repair

1. In the **Now** panel, click **Try enabling adapter** once.
2. Wait for NetRescue to finish enabling Wi-Fi and performing automatic verification. Do not run a separate Windows enable command.
3. Expect **Recovery verified** and a follow-up reading of **Network Operational**.

### 5. Review the recorded repair

1. Open **Incidents**, then the new **Adapter turned off** incident.
2. Confirm the status is **Closed** and the result says the target condition cleared after repair.
3. Expand **Latest repair details**. Confirm **Command result: Command completed** and a verified **Recovery result**.
4. Compare **Before: initial check** with **After: final check**. Before should show the disabled adapter; After should show **Network Operational** on Wi-Fi.
5. Expand **Technical details** and confirm **Command exit code: 0**. Note **Command duration** and **Verification duration** under Recorded durations to record the adapter-repair speed.

### 6. Restore this test

Return to **Administrator PowerShell window 2**. Copy only the full StatePath printed by **this Fault 1 injection**, starting with C:\ProgramData\NetRescueQA and ending with state.json.

Run the block below. When it asks for the StatePath, paste that path **without quotation marks**, then press **Enter**.

```powershell
$fault1StatePath = Read-Host 'Paste the StatePath printed for Fault 1'
& $qaRunner -Mode Restore -StatePath $fault1StatePath
```

Expect **Restored QA changes for Wi-Fi**. If it reports **This QA run was already restored**, continue with the closing check below.

Run Restore even if NetRescue has already repaired the connection; it also clears this test's rollback task.

### 7. Confirm recovery in the app and check cleanup

In **NetRescue**:

1. Return to **Dashboard** and click **Run Check**.
2. Wait for the new reading. Require **Network Operational**, Gateway and Internet passing, and Name lookup answering. A slow successful lookup is acceptable.
3. Open **Incidents** and the incident created by this test. Confirm it is Closed and that its verified repair result remains available.

In **Administrator PowerShell window 2**, run:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both commands should show **no QA entries**. Start Fault 2 only after the closing Check is healthy and these queries are empty.

<a id="b-missing-dhcp-ipv4-address--renewal-should-recover-it"></a>

## Fault 2 - Missing IPv4 address (NoIpv4)

**Test:** NetRescue detects a released DHCP address and renews it. **Read steps 3-4 before injecting.** Windows can obtain an address again by itself, so keep the Dashboard ready.

This test requires DHCP. In the Inspect output below, confirm **Dhcp: Enabled** before applying the fault.

### 1. Check the starting connection

In the same **Administrator PowerShell window 2** prepared above, run:

```powershell
& $qaRunner @qaOptions -Mode Inspect
```

Inspect should display the selected adapter and its current settings without injecting a fault.

In **NetRescue**:

1. Keep MySQL and the administrator application server running.
2. Open **Settings**, confirm **Automatic checks** is off, and save if you changed it.
3. Go to **Dashboard** and click **Run Check**.
4. Wait for the check to finish. Require **Network Operational**, with Gateway and Internet passing and Name lookup answering. A slow successful lookup is acceptable.
5. Keep the Dashboard open before running the fault command.

### 2. Inject the fault

In **Administrator PowerShell window 2**, run:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault NoIpv4
```

Wait for **Rollback armed. StatePath: ...** and **Applied NoIpv4 to Wi-Fi**. The printed StatePath belongs to this test and is required for Restore.

**Immediately return to the Dashboard. Leave the terminal open; copy its StatePath after the application repair so you do not lose time before Run Check.**

### 3. Detect the fault immediately

1. Click **Run Check** within a few seconds of Applied. Do not stop to take screenshots or inspect an old incident first.
2. Expect **IPv4 Configuration Missing**. If Windows has assigned a 169.254.x.x address, expect **Possible DHCP Configuration Issue** instead.
3. Gateway, Internet and Name lookup should be **Not tested** because there is no usable IPv4 configuration.
4. Wi-Fi can still be enabled while its usable IPv4 address is missing.

If the new Check already says **Network Operational**, use steps 6-7 to restore this run, then repeat Fault 2 from step 1. Do not inject again while this run is armed.

### 4. Request the repair promptly

1. As soon as the diagnosis appears, click **Try renewing address**. If the diagnosis is the DHCP/APIPA condition, click **Try release and renew** instead.
2. Wait for automatic verification without running ipconfig yourself.
3. Expect the target condition to clear and a usable IPv4 address, default gateway and connectivity to return.

If the repair says **Already recovered** and no command was run, Windows recovered before the repair precheck. Complete steps 6-7, then repeat this fault. That result does not demonstrate a successful application repair.

### 5. Review the recorded repair

1. Open **Incidents**, then the new missing-address or DHCP incident.
2. Confirm **Closed** and verified recovery after repair.
3. Expand **Latest repair details**. Confirm **Command completed** and a verified **Recovery result**.
4. Compare **Before: initial check** and **After: final check**. After must show **Network Operational**; a remaining 169.254.x.x address is not a successful recovery.
5. Expand **Technical details**. Confirm exit code **0**, a usable **After configuration** IPv4 address and gateway. Note the recorded command and verification durations.

### 6. Restore this test

Return to **Administrator PowerShell window 2**. Copy only the full StatePath printed by **this Fault 2 injection**, starting with C:\ProgramData\NetRescueQA and ending with state.json.

Run the block below. When it asks for the StatePath, paste that path **without quotation marks**, then press **Enter**.

```powershell
$fault2StatePath = Read-Host 'Paste the StatePath printed for Fault 2'
& $qaRunner -Mode Restore -StatePath $fault2StatePath
```

Expect **Restored QA changes for Wi-Fi**. If it reports **This QA run was already restored**, continue with the closing check below.

Run Restore even if NetRescue has already repaired the connection; it also clears this test's rollback task.

### 7. Confirm recovery in the app and check cleanup

In **NetRescue**:

1. Return to **Dashboard** and click **Run Check**.
2. Wait for the new reading. Require **Network Operational**, Gateway and Internet passing, and Name lookup answering. A slow successful lookup is acceptable.
3. If this run created an incident, open it in **Incidents** and confirm it is Closed. A successful application repair should retain its verified result. An **Already recovered** request should still say no command was run.
4. If Windows recovered before the fault was detected, there may be no new incident. Complete cleanup below, then repeat Fault 2; do not count that run as a successful application repair.

In **Administrator PowerShell window 2**, run:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both commands should show **no QA entries**. Start Fault 3 only after the closing Check is healthy and these queries are empty.

## Fault 3 - Missing default gateway

**Test:** NetRescue detects a missing gateway and requests DHCP configuration to restore it.

This test requires DHCP. In the Inspect output below, confirm **Dhcp: Enabled** before applying the fault. If the runner refuses the gateway configuration, stop this test instead of removing routes yourself.

### 1. Check the starting connection

In the same **Administrator PowerShell window 2** prepared above, run:

```powershell
& $qaRunner @qaOptions -Mode Inspect
```

Inspect should display the selected adapter and its current settings without injecting a fault.

In **NetRescue**:

1. Keep MySQL and the administrator application server running.
2. Open **Settings**, confirm **Automatic checks** is off, and save if you changed it.
3. Go to **Dashboard** and click **Run Check**.
4. Wait for the check to finish. Require **Network Operational**, with Gateway and Internet passing and Name lookup answering. A slow successful lookup is acceptable.
5. Keep the Dashboard open before running the fault command.

### 2. Inject the fault

In **Administrator PowerShell window 2**, run:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault GatewayMissing
```

Wait for **Rollback armed. StatePath: ...** and **Applied GatewayMissing to Wi-Fi**. The printed StatePath belongs to this test and is required for Restore.

**Keep this terminal output open. Use its newly printed StatePath when you reach the Restore step.**

### 3. Detect the fault in NetRescue

1. In **Dashboard**, click **Run Check**.
2. Wait for **Default Gateway Missing**.
3. Confirm a usable IPv4 address is still present but no default gateway is reported.
4. Gateway, Internet and Name lookup should be **Not tested** because the gateway prerequisite is missing.

### 4. Run the application repair

1. Click **Try renewing address** once in the **Now** panel.
2. Wait for the renewal and automatic verification to finish.
3. Expect verified recovery and **Network Operational** with a default gateway present.

### 5. Review the recorded repair

1. Open **Incidents**, then the new missing-gateway incident.
2. Confirm **Closed** and verified recovery after repair.
3. Expand **Latest repair details**. Confirm **Command completed** and a verified **Recovery result**.
4. Compare **Before: initial check** and **After: final check**. Before should show the missing gateway; After should show **Network Operational**.
5. Expand **Technical details**. Confirm exit code **0** and a default gateway in **After configuration**. Note the command and verification durations.

### 6. Restore this test

Return to **Administrator PowerShell window 2**. Copy only the full StatePath printed by **this Fault 3 injection**, starting with C:\ProgramData\NetRescueQA and ending with state.json.

Run the block below. When it asks for the StatePath, paste that path **without quotation marks**, then press **Enter**.

```powershell
$fault3StatePath = Read-Host 'Paste the StatePath printed for Fault 3'
& $qaRunner -Mode Restore -StatePath $fault3StatePath
```

Expect **Restored QA changes for Wi-Fi**. If it reports **This QA run was already restored**, continue with the closing check below.

Run Restore even if NetRescue has already repaired the connection; it also clears this test's rollback task.

### 7. Confirm recovery in the app and check cleanup

In **NetRescue**:

1. Return to **Dashboard** and click **Run Check**.
2. Wait for the new reading. Require **Network Operational**, Gateway and Internet passing, and Name lookup answering. A slow successful lookup is acceptable.
3. Open **Incidents** and the incident created by this test. Confirm it is Closed and that its verified repair result remains available.

In **Administrator PowerShell window 2**, run:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both commands should show **no QA entries**. Start Fault 4 only after the closing Check is healthy and these queries are empty.

<a id="d-gateway-ping-blocked--detection-only"></a>

## Fault 4 - Gateway ping blocked, Internet still available

**Test:** NetRescue detects an unanswered gateway ping while Internet and DNS still work. No application repair is needed for this ping-only condition.

### 1. Check the starting connection

In the same **Administrator PowerShell window 2** prepared above, run:

```powershell
& $qaRunner @qaOptions -Mode Inspect
```

Inspect should display the selected adapter and its current settings without injecting a fault.

In **NetRescue**:

1. Keep MySQL and the administrator application server running.
2. Open **Settings**, confirm **Automatic checks** is off, and save if you changed it.
3. Go to **Dashboard** and click **Run Check**.
4. Wait for the check to finish. Require **Network Operational**, with Gateway and Internet passing and Name lookup answering. A slow successful lookup is acceptable.
5. Keep the Dashboard open before running the fault command.

### 2. Inject the fault

In **Administrator PowerShell window 2**, run:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault GatewayPingBlocked
```

Wait for **Rollback armed. StatePath: ...** and **Applied GatewayPingBlocked to Wi-Fi**. The printed StatePath belongs to this test and is required for Restore.

**Keep this terminal output open. Use its newly printed StatePath when you reach the Restore step.**

### 3. Detect the fault in NetRescue

1. In **Dashboard**, click **Run Check**.
2. Wait for **Gateway Ping Unanswered; Internet Available**.
3. Confirm the following results:

   - **Gateway:** failed or did not answer.
   - **Internet:** passed.
   - **Name lookup:** answered, possibly with a slow warning.
   - **IPv4 address and gateway:** still present.

### 4. Review the incident without running a repair

1. Open **Incidents**, then the new **Gateway ping unanswered; Internet available** incident.
2. Confirm the detected condition matches the ping-only failure.
3. Expect **No repair required** and no Try repair button. Do not use a repair button belonging to an older incident.
4. Keep this incident open until you have run Restore and the closing Check.

### 5. Restore this test

Return to **Administrator PowerShell window 2**. Copy only the full StatePath printed by **this Fault 4 injection**, starting with C:\ProgramData\NetRescueQA and ending with state.json.

Run the block below. When it asks for the StatePath, paste that path **without quotation marks**, then press **Enter**.

```powershell
$fault4StatePath = Read-Host 'Paste the StatePath printed for Fault 4'
& $qaRunner -Mode Restore -StatePath $fault4StatePath
```

Expect **Restored QA changes for Wi-Fi**. If it reports **This QA run was already restored**, continue with the closing check below.

Restore removes the temporary gateway-ping firewall rule. It is simulation cleanup, not an application repair.

### 6. Confirm recovery in the app and check cleanup

In **NetRescue**:

1. Return to **Dashboard** and click **Run Check**.
2. Wait for the new reading. Require **Network Operational**, Gateway and Internet passing, and Name lookup answering. A slow successful lookup is acceptable.
3. Open **Incidents** and the incident created by this test. Confirm it is Closed after the healthy Check. Recovery should be observed by a check; no repair command was run.

In **Administrator PowerShell window 2**, run:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both commands should show **no QA entries**. Start Fault 5 only after the closing Check is healthy and these queries are empty.

## Fault 5 - Gateway and Internet test targets blocked

**Test:** NetRescue offers renewal, then a confirmed adapter restart. Both may complete while the deliberate firewall block remains. The correct result is **Condition remains / Recovery not verified**, followed by manual guidance.

### 1. Check the starting connection

In the same **Administrator PowerShell window 2** prepared above, run:

```powershell
& $qaRunner @qaOptions -Mode Inspect
```

Inspect should display the selected adapter and its current settings without injecting a fault.

In **NetRescue**:

1. Keep MySQL and the administrator application server running.
2. Open **Settings**, confirm **Automatic checks** is off, and save if you changed it.
3. Go to **Dashboard** and click **Run Check**.
4. Wait for the check to finish. Require **Network Operational**, with Gateway and Internet passing and Name lookup answering. A slow successful lookup is acceptable.
5. Keep the Dashboard open before running the fault command.

### 2. Inject the fault

In **Administrator PowerShell window 2**, run:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault GatewayTestsBlocked
```

Wait for **Rollback armed. StatePath: ...** and **Applied GatewayTestsBlocked to Wi-Fi**. The printed StatePath belongs to this test and is required for Restore.

**Keep this terminal output open. Use its newly printed StatePath when you reach the Restore step.**

### 3. Detect the fault in NetRescue

1. In **Dashboard**, click **Run Check**.
2. Wait for **Possible Local Gateway / Path Issue**. The incident title may be **Gateway not responding**.
3. Confirm:

   - **Gateway:** failed.
   - **Internet:** failed.
   - **Name lookup:** **Not tested**, not a separately measured DNS failure.
   - **IPv4 address, gateway and Wi-Fi adapter:** still reported.

### 4. Try the first repair - renewal

1. In the **Now** panel, click **Try renewing address** once.
2. Wait for the command and automatic verification to finish.
3. Expect the command to complete but the repair to remain **not verified**. The deliberate firewall block has not been removed.
4. Expect **Condition remains** and the next available action **Try restarting adapter**.

### 5. Open and confirm the second repair popup

1. Click **Try restarting adapter** once. This first click should open **Review another repair attempt**.
2. In the popup, read **Previous attempt**. It should explain that the previous command completed but the target condition remains.
3. Read **Proposed action: Restart adapter** and the warning that Wi-Fi will briefly disconnect.
4. To repeat the demonstrated two-attempt test, click **Try restarting adapter** at the **bottom right of the popup**. This second click confirms and starts the restart.
5. Wait for the adapter to reconnect and automatic verification to finish. Do not run another fault or repair while it is checking.
6. Expect the restart command to complete but recovery to remain **not verified**, because the injected firewall block is still present.
7. Expect **Manual action required** after the supported attempts. Do not keep requesting repairs.

### 6. Review both recorded attempts

1. Open **Incidents**, then the new **Gateway not responding** incident.
2. Before Restore, expect the incident to remain **Open** with the target condition still present.
3. Expand **Latest repair details**. It should describe **Restart the adapter**, a completed command and **Recovery not verified**.
4. Compare its Before and After readings. They should still show the gateway/path condition. Expand **Technical details** and check the command exit code; the expected completed-command result is **0**.
5. Expand **Earlier repair attempts**, then the renewal request. It should show the earlier completed renewal and its own unverified recovery result.
6. Note the command and verification durations for each request separately.

### 7. Restore this test

Return to **Administrator PowerShell window 2**. Copy only the full StatePath printed by **this Fault 5 injection**, starting with C:\ProgramData\NetRescueQA and ending with state.json.

Run the block below. When it asks for the StatePath, paste that path **without quotation marks**, then press **Enter**.

```powershell
$fault5StatePath = Read-Host 'Paste the StatePath printed for Fault 5'
& $qaRunner -Mode Restore -StatePath $fault5StatePath
```

Expect **Restored QA changes for Wi-Fi**. If it reports **This QA run was already restored**, continue with the closing check below.

Restore removes the deliberate firewall block. Neither DHCP renewal nor adapter restart removes that rule.

### 8. Confirm recovery in the app and check cleanup

In **NetRescue**:

1. Return to **Dashboard** and click **Run Check**.
2. Wait for the new reading. Require **Network Operational**, Gateway and Internet passing, and Name lookup answering. A slow successful lookup is acceptable.
3. Open **Incidents** and the incident created by this test. Confirm it is Closed and the resolution attributes recovery to a later check. The two earlier attempts must remain unverified; closure must not relabel either as a verified repair.

In **Administrator PowerShell window 2**, run:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both commands should show **no QA entries**. Start Fault 6 only after the closing Check is healthy and these queries are empty.

## Fault 6 - Internet test targets blocked, gateway reachable

**Test:** NetRescue detects failed external connectivity tests and gives manual guidance without offering an unsupported local repair.

### 1. Check the starting connection

In the same **Administrator PowerShell window 2** prepared above, run:

```powershell
& $qaRunner @qaOptions -Mode Inspect
```

Inspect should display the selected adapter and its current settings without injecting a fault.

In **NetRescue**:

1. Keep MySQL and the administrator application server running.
2. Open **Settings**, confirm **Automatic checks** is off, and save if you changed it.
3. Go to **Dashboard** and click **Run Check**.
4. Wait for the check to finish. Require **Network Operational**, with Gateway and Internet passing and Name lookup answering. A slow successful lookup is acceptable.
5. Keep the Dashboard open before running the fault command.

### 2. Inject the fault

In **Administrator PowerShell window 2**, run:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault WanTargetsBlocked
```

Wait for **Rollback armed. StatePath: ...** and **Applied WanTargetsBlocked to Wi-Fi**. The printed StatePath belongs to this test and is required for Restore.

**Keep this terminal output open. Use its newly printed StatePath when you reach the Restore step.**

### 3. Detect the fault in NetRescue

1. In **Dashboard**, click **Run Check**.
2. Wait for **Possible WAN / ISP Connectivity Issue**. The incident title may be **Internet path failed**.
3. Confirm:

   - **Gateway:** passed.
   - **Internet:** failed.
   - **Name lookup:** **Not tested**, because the external connectivity prerequisite failed.
   - **IPv4 address and gateway:** still present.

### 4. Review the incident without running a repair

1. Open **Incidents**, then the new **Internet path failed** incident.
2. Confirm the detected condition matches the failed Internet test targets.
3. Expect **Manual action required**, with guidance to review the router/WAN connection or firewall policy.
4. Expect **no repair button and no repair popup** for this condition. Do not try a repair from an older incident.
5. Leave the incident open until you restore this simulation. Working websites alone do not prove the configured test targets are unblocked.

### 5. Restore this test

Return to **Administrator PowerShell window 2**. Copy only the full StatePath printed by **this Fault 6 injection**, starting with C:\ProgramData\NetRescueQA and ending with state.json.

Run the block below. When it asks for the StatePath, paste that path **without quotation marks**, then press **Enter**.

```powershell
$fault6StatePath = Read-Host 'Paste the StatePath printed for Fault 6'
& $qaRunner -Mode Restore -StatePath $fault6StatePath
```

Expect **Restored QA changes for Wi-Fi**. If it reports **This QA run was already restored**, continue with the closing check below.

Restore removes this test's external-target firewall rule. It does not change the router or ISP.

### 6. Confirm recovery in the app and check cleanup

In **NetRescue**:

1. Return to **Dashboard** and click **Run Check**.
2. Wait for the new reading. Require **Network Operational**, Gateway and Internet passing, and Name lookup answering. A slow successful lookup is acceptable.
3. Open **Incidents** and the incident created by this test. Confirm it is Closed after the healthy Check, with recovery observed by a check and no repair command recorded for this test.

In **Administrator PowerShell window 2**, run:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both commands should show **no QA entries**. Start Fault 7 only after the closing Check is healthy and these queries are empty.

## Fault 7 - Unreachable DNS servers

**Test:** NetRescue detects a measured DNS failure. Clearing the cache cannot correct the injected manual DNS server addresses, so the app should leave recovery unverified and show manual DNS guidance.

### 1. Check the starting connection

In the same **Administrator PowerShell window 2** prepared above, run:

```powershell
& $qaRunner @qaOptions -Mode Inspect
```

Inspect should display the selected adapter and its current settings without injecting a fault.

In **NetRescue**:

1. Keep MySQL and the administrator application server running.
2. Open **Settings**, confirm **Automatic checks** is off, and save if you changed it.
3. Go to **Dashboard** and click **Run Check**.
4. Wait for the check to finish. Require **Network Operational**, with Gateway and Internet passing and Name lookup answering. A slow successful lookup is acceptable.
5. Keep the Dashboard open before running the fault command.

### 2. Inject the fault

In **Administrator PowerShell window 2**, run:

```powershell
& $qaRunner @qaOptions -Mode Apply -Fault DnsServer
```

Wait for **Rollback armed. StatePath: ...** and **Applied DnsServer to Wi-Fi**. The printed StatePath belongs to this test and is required for Restore.

**Keep this terminal output open. Use its newly printed StatePath when you reach the Restore step.**

### 3. Detect the DNS failure in NetRescue

1. In **Dashboard**, click **Run Check**.
2. Wait for **DNS Resolution Failure**.
3. Confirm:

   - **Gateway:** passed.
   - **Internet:** passed.
   - **Name lookup:** **failed**, not Not tested.
   - **IPv4 address and gateway:** still present.
4. The DNS servers shown in Network details should include the injected addresses **192.0.2.53** and **2001:db8::53**.

### 4. Try the application repair

1. In the **Now** panel, click **Try clearing DNS cache** once.
2. Wait for the cache flush and automatic verification to finish.
3. Expect **Command completed**, but **Condition remains / Recovery not verified** while the injected DNS settings remain.
4. Expect **Manual action required**, with guidance to check the manually configured DNS servers.
5. Expect **no second repair button or popup** for this completed cache-flush result.

### 5. Review the recorded attempt

1. Open **Incidents**, then the new **DNS Resolution Failure** incident.
2. Before Restore, expect it to remain **Open**.
3. Expand **Latest repair details**. Confirm a completed DNS cache-flush command and **Recovery not verified**.
4. Compare **Before: initial check** and **After: final check**. DNS should still fail while Gateway and Internet pass.
5. Expand **Technical details**. The expected completed-command exit code is **0**. Note the command and verification durations.

### 6. Restore this test

Return to **Administrator PowerShell window 2**. Copy only the full StatePath printed by **this Fault 7 injection**, starting with C:\ProgramData\NetRescueQA and ending with state.json.

Run the block below. When it asks for the StatePath, paste that path **without quotation marks**, then press **Enter**.

```powershell
$fault7StatePath = Read-Host 'Paste the StatePath printed for Fault 7'
& $qaRunner -Mode Restore -StatePath $fault7StatePath
```

Expect **Restored QA changes for Wi-Fi**. If it reports **This QA run was already restored**, continue with the closing check below.

Use this saved-state Restore to return the original IPv4/IPv6 DNS configuration. Do not replace it with a generic reset to automatic DNS.

### 7. Confirm recovery in the app and check cleanup

In **NetRescue**:

1. Return to **Dashboard** and click **Run Check**.
2. Wait for the new reading. Require **Network Operational**, Gateway and Internet passing, and Name lookup answering. A slow successful lookup is acceptable.
3. Open **Incidents** and the incident created by this test. Confirm it is Closed after the healthy Check and recovery was observed by that check. The earlier cache-flush attempt must remain unverified.

In **Administrator PowerShell window 2**, run:

```powershell
Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State

Get-NetFirewallRule -Name 'NetRescueQA-*' -ErrorAction SilentlyContinue |
    Select-Object Name, Enabled
```

Both commands should show **no QA entries**. Finish this test only after the closing Check is healthy and these queries are empty.

## Finish the testing session

1. Confirm Fault 7 is restored, the closing **Run Check** is healthy, and both QA cleanup queries are empty.
2. In **Settings**, return **Automatic checks** to its original on/off setting and click **Save settings**.
3. Reconnect any VPN or alternate connection that you intentionally disconnected for testing.
