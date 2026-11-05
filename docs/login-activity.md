# Login activity

Who signed in, from where, and who tried and failed — requested 5 Nov 2026, before the
back-charge work. Branch `login-activity`.

## Decisions (5 Nov 2026)

- **Recorded:** successful logins (including "remember me" logins), failed attempts (wrong
  password or unknown email — the email typed is kept), logouts, lockouts (too many failed
  attempts), and the two-factor step (challenged, passed, failed).
- **Shown:** a Login Activity screen (every event, filters, CSV); a Last login column on Users
  and the person's history on their user page; each person's own logins on their profile;
  active sessions with an admin "sign out"; alerts on suspicious activity.
- **Access:** admins only, through a new `login-activity` area. Each person always sees their
  own logins.
- **Retention:** 90 days; older events are pruned nightly.

## Data

`login_events` — one row per event:

| column | |
|---|---|
| `user_id` | nullable: an unknown email has no user; `nullOnDelete` |
| `email` | what was typed (failed / lockout), or the user's email |
| `event` | `login`, `failed`, `logout`, `lockout`, `two_factor_challenged`, `two_factor_passed`, `two_factor_failed` |
| `ip_address`, `user_agent` | from the request |
| `browser`, `platform`, `device` | parsed from the user agent once, at write time |
| `via_remember` | a login through the "remember me" cookie |
| `actor_id` | the administrator who ended a session (`session_ended` events) |
| `is_new_device` | first time this user signed in from this browser + platform + IP block |
| `created_at` | indexed with `user_id`, `event` and `ip_address` |

`users.last_login_at` / `users.last_login_ip` — written on every login so the Users list needs
no subquery.

## Stages

1. **Recording** — migration, model, one listener for every event, user-agent parsing, the
   90-day prune (scheduled). Tests. **Done.**
2. **Login Activity screen** — Company → Login Activity: summary cards (sign-ins, failed,
   lockouts, new devices), filters (person, event or "problems only", dates, search by name /
   email / IP, new devices only), CSV. **Done.**
3. **Users** — Last login column on Users and a Recent sign-ins panel on the user page (both
   for `login-activity.view`); Settings → Sign-in activity for everybody's own. **Done.**
4. **Active sessions** — "Signed in now" on the Login Activity screen, from the `sessions`
   table; `login-activity.end_sessions` ends one session or all of a person's, rotates their
   remember token, and logs a `session_ended` event naming the administrator. A session is
   referred to by a hash of its id; the id never reaches the browser. **Done.**
5. **Alerts** — `SecurityAlerts`: a lockout, one address failing on 5+ accounts in 15 minutes
   (password spraying), a sign-in from a new device. Mailed to holders of `login-activity.view`
   after the response (`defer()->always()`), at most once an hour per account / address;
   switchable in System Settings → Notifications and per person in Settings → Notifications.
   **Done.**
6. **Review and improvements.** ← next

### Found on the way

- **Every settings page was broken** (profile, password, two-factor, notifications, appearance):
  Livewire 4's default page layout `layouts::app` does not exist here. `config/livewire.php`
  now sets `component_layout` to `components.layouts.app`. This also fixed six long-failing
  tests. It has most likely been broken in production since the 3 Sep 2026 composer update.
- **The login throttle never fired `Lockout`**: the named `login` rate limiter answers with a
  429 before Fortify's own lockout step. The limiter's response now fires the event (same 429).
- **System Settings toggles**: the first click on a switch with no saved row did nothing, and
  any key from the browser created a row. Both fixed.

### Codex review, 5 Nov 2026

Five findings, all confirmed and fixed, pinned in `tests/Feature/Auth/LoginActivityReviewTest.php`:

- **P1** the screen re-checks `login-activity.view` on every request (`render()`), not only on
  `mount()` — a page left open after the grant was revoked kept reading the log.
- **P1** CSV cells starting with `= + - @` (or a tab / return) are prefixed with `'`: the email
  and browser string of a failed sign-in are an attacker's to write (formula injection).
- **P2** ending your own other sessions rotates your remember token too.
- **P2** "via remember me" comes from the guard's `viaRemember()`, not from the ticked box.
- **P2** an IPv6 network is the first 48 bits of the binary address, so `2001:db8::1` and
  `2001:db8:0:1::1` no longer look like different networks (false new-device alerts).

### Deploying

Run `php artisan migrate`, and `php artisan event:cache` again if events are cached (the
listener is discovered). The prune is scheduled daily at 03:30.

### Why there is no delete

CLAUDE.md asks every record to ship with a delete. A login event is an audit record, not
something a person creates: deleting one by hand would defeat its purpose. The 90-day prune is
the only way out, and the plan says so on purpose.

## Permissions checklist (docs/permissions-for-new-modules.md)

- [x] Area declared in `config/permissions.php` — `levels`, `money`, actions, `swept => false`
- [x] Menu entry declared in the same file
- [x] `ADMIN_ONLY_ABILITIES` updated, each with a reason
- [x] Added to a system template if it belongs in one (it does not)
- [x] `<area>.delete` — not applicable, see above
- [x] `mount()` guarded on every component
- [x] **Every** action method guarded
- [x] Records fetched by id checked against their own scope
- [x] Routes without a `mount()` carry `ability:` middleware
- [x] CSV export guarded with the screen's grant
- [x] `visibleTo()` — not applicable (company-level, admin-only)
- [x] Views use `@can` for cosmetics only
- [x] `tests/Feature/Permissions/LoginActivityTest.php`: reproduced, revocable, separate
- [x] pt_BR strings added in the same change
- [x] `swept => true`, bookkeeping tests updated, full suite green
