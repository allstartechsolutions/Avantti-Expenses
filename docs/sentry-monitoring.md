# Error monitoring — Sentry

The application reports its own crashes to [Sentry](https://sentry.io) so a failure on a
customer's screen reaches you without the customer having to describe it. It reports PHP
exceptions, failures inside Livewire components, failures inside the scheduled commands,
and — when tracing is on — how long each request took and which queries it ran.

**It is optional.** With `SENTRY_LARAVEL_DSN` empty the SDK is a no-op: no data is
collected, nothing is sent, and every screen behaves exactly as it did before the package
was added. An install that has not been given a DSN is not degraded in any way.

---

## 1. Turning it on

### 1.1 Create the project in Sentry

Sentry → **Projects** → **Create Project** → platform **Laravel**. Name it after the
install. Sentry shows the DSN immediately; it is also at
**Settings → Projects → [project] → Client Keys (DSN)**.

The DSN is not a secret in the way an API key is — it only allows *writing* events — but
it belongs in `.env`, not in the repository.

### 1.2 Set it in `.env`

```env
SENTRY_LARAVEL_DSN=https://<key>@o<org>.ingest.sentry.io/<project>
SENTRY_TRACES_SAMPLE_RATE=1.0
```

Then `php artisan config:clear` (and `config:cache` on the server if the site caches its
config).

### 1.3 Prove it works

```bash
php artisan sentry:test
```

It sends a deliberate test exception and prints whether Sentry accepted it. The event
appears in the project's Issues list within a few seconds. Delete it afterwards — it is a
real issue as far as Sentry is concerned.

---

## 2. What is sent, and what is deliberately not

This application handles money, client records, vendor details and payment data. The
default Sentry configuration is more generous with request data than is appropriate here,
so several options in `config/sentry.php` are set against their defaults on purpose.

| Data | Sent? | Why |
|---|---|---|
| Exception class, message, stack trace | **Yes** | The point of the exercise. |
| File and line, with surrounding source | **Yes** | Frames inside `vendor/` are marked "not in app". |
| Which URL, which route, which Livewire component | **Yes** | A trace reads `BudgetCreate::save`, not `POST /livewire/update`. |
| User id and name | **Yes** | Enough for support to ring the right person. |
| User role, access scope, guest flag | **Yes** | Usually the first question asked of a permissions bug. |
| Project / job site id and name | **Yes** | Which record the person was looking at. |
| SQL query shapes | **Yes** | `select * from expenses where project_id = ?`. |
| **SQL query bindings** | **No** | The bindings *are* the amounts, names and document numbers. |
| **Request body (POST payload)** | **No** | Sentry's default is to attach it. For this application that is the expense form, the payment, the client record. |
| **User e-mail address** | **No** | The name identifies the person without putting an address in a third-party system. |
| **IP address, cookies, auth headers** | **No** | `send_default_pii` is off. |

The two settings that matter most are these, both in `config/sentry.php`:

```php
'send_default_pii'      => env('SENTRY_SEND_DEFAULT_PII', false),
'max_request_body_size' => env('SENTRY_MAX_REQUEST_BODY_SIZE', 'never'),
```

**They are independent.** Sentry captures the request body according to
`max_request_body_size` (whose own default is `medium`) *regardless* of
`send_default_pii`. Turning PII off is not sufficient to keep payloads on the server;
both are needed. Do not raise either without deciding, deliberately, that customer
financial data may leave the server.

The context that *is* useful is attached by hand instead, in
`app/Http/Middleware/AttachSentryContext.php` — last in the `web` group, so routing and
authentication have both finished by the time it runs.

### What never reaches Sentry at all

Laravel's own "do not report" list is applied before Sentry ever sees an exception, so
these are already excluded and need no configuration:

- 404s (`NotFoundHttpException`, `ModelNotFoundException`)
- Validation failures (`ValidationException`)
- "Please log in" (`AuthenticationException`)
- Permission denials — every `abort(403)` from the ability middleware and the resolver
  (`AuthorizationException`)
- Expired CSRF tokens (`TokenMismatchException`)

If something genuinely noisy does get through, add its class to `ignore_exceptions` in
`config/sentry.php` rather than silencing it in application code.

---

## 3. Performance tracing

`SENTRY_TRACES_SAMPLE_RATE=1.0` traces **every** request: the total time, each SQL query
as a span, each view render, each Livewire component update, each outbound HTTP call
(Google, Visual Crossing, R2). It is the fastest way to find out which report screen is
slow on real customer data and which page is running an N+1.

It is also the setting most likely to exhaust a Sentry quota, because it is one event per
request rather than one per crash. If the quota starts running low, lower it — the value
is a probability, so `0.2` traces one request in five:

```env
SENTRY_TRACES_SAMPLE_RATE=0.2
```

**Errors are unaffected by this number.** Sampling applies to traces only; every
exception is still reported in full at `1.0`.

The `/up` health check is excluded from tracing already (`ignore_transactions`).

---

## 4. The scheduled commands

The four recurring jobs in `routes/console.php` each carry `->sentryMonitor()`, which
sends a check-in before and after every run:

| Command | Schedule |
|---|---|
| `documents:prune-uploads` | hourly |
| `documents:purge-deleted` | daily 03:15 |
| `tasks:notify-overdue` | daily 07:00 |
| `tasks:send-weekly-digest` | hourly |

This catches the failure that is otherwise completely invisible: **the cron entry that
stopped running.** A crashed command already reports itself as an exception; a
`schedule:run` line that was never added to Forge, or that quietly stopped, produces no
exception at all — just work silently not happening. Sentry raises a "missed check-in"
alert instead.

The monitors create themselves in Sentry on the first run (**Crons** in the sidebar), each
slugged after its command. Like everything else here, the check-ins are skipped entirely
when there is no DSN — the scheduler on an unconfigured install runs exactly as before.

See `docs/deployment-scheduler.md` for the single Forge cron entry that drives all four.

---

## 5. Releases — tying an error to a deploy

Set `SENTRY_RELEASE` at deploy time and Sentry can say *which deploy* introduced an
error, and mark it resolved when a later deploy stops it happening.

Add this to the Forge deploy script, before `php artisan config:cache`:

```bash
# Tag this deploy in Sentry with the commit it came from
sed -i "/^SENTRY_RELEASE=/d" .env
echo "SENTRY_RELEASE=$(git rev-parse --short HEAD)" >> .env
```

Without it, everything simply appears under no release — Sentry works, it just cannot
group by deploy.

---

## 6. Where each piece lives

| File | Purpose |
|------|---------|
| `config/sentry.php` | All options, with the privacy decisions commented in place |
| `bootstrap/app.php` | `Integration::handles($exceptions)` — hands reported exceptions to Sentry |
| `app/Http/Middleware/AttachSentryContext.php` | User, role, access scope, project/job site, locale |
| `routes/console.php` | `->sentryMonitor()` on the four scheduled commands |
| `.env.example` | The documented, empty-by-default block |
| `phpunit.xml` | Empty DSN pinned, so the test suite never reports |

---

## 7. Notes

- **The test suite never reports.** `phpunit.xml` pins `SENTRY_LARAVEL_DSN` to empty and
  the trace rate to `0`, whatever sits in `.env`.
- **Local development.** Leave the DSN empty locally; a stack trace in the terminal is
  faster than one in a browser tab. Set it only if you are chasing something that happens
  in `local` and nowhere else.
- **`APP_ENV` becomes the Sentry environment** automatically, so `local`, `staging` and
  `production` are separable in the Issues filter without extra configuration.
- **Front-end errors are not covered.** This is the PHP SDK only. Alpine.js and browser
  JavaScript errors do not reach Sentry; adding `@sentry/browser` through Vite is a
  separate change, worth making only if browser-side bugs start being the ones that hurt.
- **Sentry is not a log.** It reports what crashed, not what happened. `LOG_CHANNEL` and
  `storage/logs` are unchanged and still the place to look for ordinary application logs.
