# Local monitoring acceptance checks

| Check | Expected result |
| --- | --- |
| Open dashboard without readings | Waiting state; no fabricated measurements |
| Run Check | One fresh local reading; current facts and diagnosis update |
| Open Checks | This computer's saved readings only |
| Open/filter Incidents | This computer's records only; exact repair evidence retained |
| Retain old remote rows | No effect on local totals, bars, history or status polling |
| Open a retained remote incident ID | 404; no viewing, repair or retest |
| Request old Devices/agent URLs | 404; no registration, measurements or command queue writes |
| Run scheduled monitor | Local read only; no neighbor scan or heartbeat/offline work |
| Pause scheduled checks, then repair | Repair still performs automatic verification |
| Configuration read fails | Reading marked unavailable; not fabricated as an adapter failure |
| Repair command times out | Completion unconfirmed; later recovery observation is separate |
| Disabled-adapter repair | Same target, command exit 0 and measured recovery needed for verified repair |
| Open old NR-00029 | Retains its original outcome and intermediate timeout evidence |
| Small screen and light/dark mode | Navigation, cards, check table and evidence remain usable |

Use php artisan test for regression checks. The Windows process boundary is
mocked in the suite. Do not treat those tests as new Windows command/timing
results. No injected network fault is needed to review the local-only UI.
