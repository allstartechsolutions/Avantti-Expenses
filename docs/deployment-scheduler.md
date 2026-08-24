# Deployment — the Laravel scheduler on Forge

The application has four recurring jobs. **Forge needs exactly one cron entry for all
of them**, not one per job: Laravel's own scheduler decides which command is due.

## The single entry to add

Forge → the server → **Scheduler** → **New Scheduled Job**:

| Field       | Value                                                |
| ----------- | ---------------------------------------------------- |
| Command     | `php8.4 /home/forge/<site-directory>/artisan schedule:run` |
| User        | `forge`                                              |
| Frequency   | **Every Minute** (`* * * * *`)                       |

Substitute the site's real directory (the folder shown on the site's page in Forge) and
the PHP version the site runs. Check the Scheduler tab first — if a `schedule:run` line
is already there for this site, there is nothing to add and the jobs below are already
running.

Running it every minute is correct and is not wasteful: `schedule:run` exits immediately
on the ~59 minutes an hour when nothing is due.

## Scheduler, not Background Process

Forge offers both. They are not interchangeable:

- **Scheduler** — cron entries. Runs a command; the command exits. `schedule:run` is exactly
  this: it wakes, checks what is due, runs it, exits.
- **Background Process** — a Supervisor-managed daemon that Forge keeps alive and restarts if
  it dies. For processes meant never to exit: `queue:work`, Horizon, Reverb.

`schedule:run` as a Background Process would fail badly — it exits after about a second, so
Supervisor would restart it in a tight loop, thousands of times an hour.

**No Background Process is needed for this application at present.** There is no `ShouldQueue`
anywhere in `app/`, no `app/Jobs` directory, and no `::dispatch()`. Every one of the six
`Mail::to()->send()` call sites is synchronous, `App\Services\TaskNotifier` included, so the
task e-mails go out inline inside the scheduled command. `QUEUE_CONNECTION=database` and the
`jobs` table exist from the Laravel skeleton, but nothing writes to them.

That changes the moment anyone marks a Mailable or a job `ShouldQueue`: mail would then queue
into the `jobs` table with nothing to drain it, and fail silently. At that point add a
Background Process running `php8.4 /home/forge/<site-directory>/artisan queue:work --sleep=3 --tries=3`.

Laravel's `schedule:work` is long-running and would fit a daemon, but it is the local-development
convenience so a developer does not need cron on their machine. Do not use it on Forge.

## What that one entry runs

Registered in `routes/console.php`; `php artisan schedule:list` prints the live list.

| Command                      | When                | Purpose |
| ---------------------------- | ------------------- | ------- |
| `documents:prune-uploads`    | hourly              | Aborts incomplete R2 multipart uploads, which Cloudflare bills until aborted |
| `documents:purge-deleted`    | daily 03:15         | Removes trashed documents past the retention window |
| `tasks:notify-overdue`       | daily 07:00         | E-mails owner and assignees of tasks that just went past due |
| `tasks:send-weekly-digest`   | hourly              | Sends the weekly digest — see below |

The weekly digest is scheduled **hourly on purpose**. The command itself checks the day
and hour configured in System Settings and returns immediately when it is not the moment,
so an admin can move the digest without a deploy. Do not "fix" this by giving it a weekly
cron: that would move the schedule back into code.

All four are `->withoutOverlapping()`, and the task mails are idempotent — the
`task_notifications` log prevents anybody being mailed twice — so a double run is safe.

## Timezone — read before trusting the times above

Laravel evaluates scheduled times in `config('app.schedule_timezone')`, falling back to
`config('app.timezone')`, which this application sets to **`'EST'`** (`config/app.php`).

`EST` is a **fixed UTC−5 zone that does not observe daylight saving.** So `dailyAt('07:00')`
fires at 07:00 EST all year, which is 07:00 local wall-clock in winter but **08:00 local in
summer**. The same applies to the digest's `now()->hour` check, so a digest set to 09:00 in
System Settings goes out at 10:00 local between March and November.

If the intent is "07:00 local, year round", change `config/app.php` to
`'timezone' => 'America/New_York'`. That is a behavioural change to every date in the
application, not just the scheduler, so it belongs in its own change with its own testing —
it is recorded in `docs/review-and-improvements.md` rather than done here.

The server's own clock does not matter: Laravel converts from the app timezone regardless
of what the OS is set to. Forge servers are UTC by default and should be left that way.

## Verifying it works

On the server, from the site directory:

```bash
php artisan schedule:list          # what is registered, and when each is next due
php artisan schedule:run           # run whatever is due right now
php artisan tasks:send-weekly-digest --force   # send the digest immediately, ignoring day/hour
```

`--force` on the digest is the quickest end-to-end proof that mail is configured, since it
does not require waiting for the configured slot. It still respects the notification log,
so nobody already sent to this week gets a second copy.

Where the cron is not running, nothing breaks loudly: no digests go out, no overdue mail is
sent, and R2 slowly accumulates abandoned upload parts. It is silent, so check
`schedule:list` after any server rebuild.
