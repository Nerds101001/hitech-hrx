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

## The rest of the automation (same folder, same rule: edit in `C:\HRX-Automation`, copy back here)

| File | What it does |
| --- | --- |
| `start-server.ps1` | Makes sure MySQL, the local web server (port 8001), TallyPrime and the Tally watcher are running (and, on this PC, the dev tunnel). Safe to re-run; never starts a duplicate. `-NoTally` is used by the boot task (nobody is signed in, so a desktop app cannot be shown). Started at sign-in by the Startup-folder shortcut "HRX Start", and by every sync run. |
| `register-boot-task.cmd` | One-time, **run as administrator**: starts the services at Windows boot, before anyone signs in (`start-server.ps1 -NoTally`, as SYSTEM). |
| `register-logon-task.cmd` | Older alternative: a sign-in task instead of the Startup-folder shortcut. Not needed now. |
| `sync-tally.ps1` / `sync-tally-parties.ps1` | The Task Scheduler jobs: sales sync every 2 hours in work hours, ledger/party sync once a day. |
