# Fault 2: normal follow-up allowance and honest retry evidence

> Later correction: see FAULT_2_RECONNECT_WINDOW.md for the current adapter
> command allowance and elapsed reconnect policy. The measurements and outcomes
> described here remain historical evidence.

NR-00029 is a verified repair: command exit code 0, disabled Wi-Fi before,
same Wi-Fi healthy after. Its first automatic reading already timed out at
20.68 seconds. Showing that retained reading later does not change its outcome.

NR-00031 is recovery observed with command completion unconfirmed: the
Enable-NetAdapter process exceeded 35 seconds, the first configuration read
exceeded its five-second allowance, and a later reading measured healthy Wi-Fi.
Those are two distinct timeouts. The stored evidence does not establish why
the Windows command ran longer on that attempt.

The short-first-read change did not alter Enable-NetAdapter or its command
deadline. It ran only after the command result. A 1.28-second read of an
already healthy adapter does not establish the duration of enabling or of
reading an adapter while it reconnects.

This correction removes the special first-reading limit. Every automatic
follow-up uses the normal configuration allowance, 20 seconds by default.
This restores the previous allowance; it does not promise faster repairs or
resolve the separate command timeout. There are still at most three readings,
one-second retry gaps, and one repair command. Automatic verification still
runs while scheduled checks are paused. Local-only scope is preserved.

The command now writes small diagnostic stage markers to its existing output:
powershell_started, command_returned, or command_error, with elapsed time for
the latter two. Enable-NetAdapter and its 35-second deadline are unchanged.
Stage output alone never counts as exit code 0 or verified repair. If the next
request times out, the retained output helps distinguish a command that had
not returned from a process that did not exit after the command returned.

Earlier incomplete readings are labeled as retry warnings when a later
reading finished. Their exact error and duration remain visible. If recovery
was merely observed, the text continues to say the repair is unverified.
Unfinished final verification and command errors retain their error treatment.
No saved checks, recovery actions or incident outcomes are rewritten.

Apply the targeted updater to the existing local-only installation, clear
Laravel caches and restart the elevated server. No database migration/reset is
needed. Existing NR-00029 and NR-00031 should retain their different outcomes.
Windows command timing must be tested on the laptop; Linux tests use mocked
Windows process results.
