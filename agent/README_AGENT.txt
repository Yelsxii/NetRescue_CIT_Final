NETRESCUE AGENT 2.2
===================

PURPOSE
-------
This lightweight Windows endpoint agent lets a PC report its own real network
measurements to the Laravel-based NetRescue Central dashboard.

THE ENDPOINT DOES NOT NEED:
- XAMPP
- MySQL
- Composer
- Laravel
- Node.js

NORMAL INSTALLATION — ZERO CONFIGURATION
----------------------------------------
1. On NetRescue Central, open Devices.
2. Click Download One-Click Installer.
3. Copy NetRescueAgentSetup.bat to one or more authorized Windows 10/11 PCs.
4. Double-click it and approve the Windows Administrator prompt.
5. Done. No server URL or enrollment code is typed on the endpoint.

The downloaded installer already contains the Central address and a temporary
deployment authorization. It downloads the current agent scripts directly from
the Laravel server, registers the endpoint, performs the first real network
check, and creates the automatic Windows Scheduled Task.

The same downloaded installer can be reused across multiple authorized PCs
until its deployment authorization expires or reaches its usage limit.
Downloading a new installer after rotating deployment authorization invalidates
older copies.

MANUAL FALLBACK
---------------
The agent folder still contains INSTALL_AGENT.bat / Install-Agent.ps1 as a
troubleshooting fallback. When used directly, those files can ask for the
Central URL and enrollment code. Normal deployment should use the generated
one-click installer from Laravel.

WHAT THE AGENT REPORTS
----------------------
- Computer name / Windows version
- Active adapter
- Local IPv4
- Default gateway
- DNS servers
- Gateway reachability + latency
- Internet TCP connectivity + connect time
- DNS resolution + lookup time

REMOTE RECOVERY
---------------
Only these approved actions can be dispatched by NetRescue Central:
- flush_dns
- renew_dhcp

There is NO arbitrary PowerShell/CMD execution endpoint.

RUNNING MODEL
-------------
The installer creates a Windows Scheduled Task called "NetRescue Agent".
That task starts one background process at boot, as SYSTEM, and starts it
again after the PC wakes if Windows ended the process. The process stays
running and checks about once a minute: heartbeat, a real health result, and
any approved recovery command. If the saved Central address does not answer,
the agent looks for Central on the current Wi-Fi and continues when both
computers are on the same router.

A sleeping PC cannot measure its network, because Windows suspends the
network adapter. Central shows that computer as offline until it wakes.

PAIRING
-------
Central shows a key for the router it is using. Another PC on that same
Wi-Fi opens Central and enters the key. The key changes when Central joins
a different router. A PC on a different router cannot use the key.

LOG
---
C:\ProgramData\NetRescueAgent\agent.log
