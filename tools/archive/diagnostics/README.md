# Archived troubleshooting scripts

These three temporary PHP files were used to investigate Windows/PHP process
execution during development. Their contents are preserved unchanged outside
the public web directory. They are not Laravel routes, application entry
points, or replacements for NetRescue's Check and repair workflow.

The scripts assume their original location in `public/`. They must not be run
from this archive. Some contain an isolated adapter-enable test. No script was
executed when organizing this directory.

Normal requests enter `public/index.php`, follow `routes/web.php`, and use the
controllers and services in `app/`. Current guarded fault-demonstration
procedures are documented in `docs/FAULT_SIMULATION_TESTING_GUIDE.md`.
