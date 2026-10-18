# Optional Windows helpers

These scripts set the working directory to the project root and run ordinary
Laravel/Composer commands. They contain no network diagnosis, scoring or repair
decision logic. Those responsibilities belong to `app/Services/Network/`.

| Helper | Purpose |
| --- | --- |
| `START_NETRESCUE.bat` | Start the loopback Laravel web server and scheduler, then open the dashboard. |
| `SETUP_WINDOWS.bat` | Prepare a fresh installation with dependencies, application key, migrations and tests. |

From Administrator PowerShell at the project root:

```powershell
& '.\tools\windows\START_NETRESCUE.bat'
```

Keep MySQL running and avoid starting duplicate web servers or schedulers. The
existing manual startup commands in the root README remain valid.

Use the setup helper only for a fresh installation. An existing installation
already has its `.env`, key and history; reorganizing files does not require
running setup, migrations or seeders. Both helpers were formerly at the project
root. Existing shortcuts must point to their new locations in this directory.
