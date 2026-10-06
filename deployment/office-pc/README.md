# Scripts that run on the office PC (not on the web server)

These are copies of files that live in `C:\HRX-Automation` on the PC where TallyPrime runs. Windows Task Scheduler
and the Startup folder run them from there; the copies here are for history and review. Edit the live ones in
`C:\HRX-Automation`, then copy the change back here.

| File | What it does |
| --- | --- |
| `watch-tally.ps1` | Started at sign-in by `start-server.ps1`. After a restart TallyPrime waits for a person to load the company; this watches for that, emails ai@rustx.com a reminder (3 min, 45 min, 2 h) while it is still waiting, and runs **one** catch-up sync (last 3 days) the moment the company is loaded. Once per Windows boot, never two copies, gives up after 4 h. It never logs in to Tally and never handles a password. |
| `notify-tally.php` | Sends that email through the app's own mail config (`MAIL_GMAIL_*` in `.env`). Refuses to claim success on the `log` driver. No credential is ever put in a mail. |

Test switches on `watch-tally.ps1`: `-PollSeconds`, `-FirstReminderSeconds`, `-MaxMinutes`, `-StatePath` (throwaway state
file), `-Company` (a made-up name exercises the "not loaded" path) and `-NoMail` (log instead of sending).
