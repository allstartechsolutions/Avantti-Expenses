# Permissions — running notations

**Status: notes only. Nothing here is built.** This is the place to write down permission
problems as they are noticed, so the eventual permission work is designed once, from a full
list, instead of being patched one screen at a time.

Started 2026-08-19, from the observation that a requisition must be approved before it can
be quoted — and that the rule only holds if a lesser user cannot go around it.

**How to use this file:** add a numbered notation with the date, what was observed, why it
matters, and the options. Leave it `open` until a decision is taken; then record the
decision and, when built, link the doc that describes it.

---

## 1. What exists today

**Three roles**, seeded in `RoleSeeder`: `admin`, `manager`, `employee`. One role per user
(`users.role_id`), no per-project assignment beyond `projects.project_manager_id`, which is
used for reporting, not for access.

**Two helpers on `User`:**

| Helper | Means |
|---|---|
| `$user->is_admin` | role is `admin` |
| `$user->is_manager` | role is `manager` |
| `$user->canReviewRequisitions()` | admin **or** manager |

**Two guard patterns, both server-side:**

- `AuthorizesAdmin::authorizeAdmin()` — used by 15 Livewire components, mostly for deletes.
- `authorizeReview()` in the requisition and quotation traits — admin or manager.

**Module access** (`config/modules.php` + `module_access` table) switches whole modules off
per install. That is an install-level switch, **not** a per-user permission.

**What does not exist:**

- No `app/Policies`, no gates, no permission table — authorization is inline in components.
- **No per-project or per-job-site scoping.** Any signed-in user can reach any project's
  data. The chain's own screens scope by project only to stop cross-project id tampering,
  not to restrict people.
- No concept of **owning** a record. Anyone can act on anyone's draft.
- PDF controllers (estimates, invoices, reports, and the two new quotation ones) are behind
  `auth` only: any signed-in user can fetch any document by id. Consistent across the app,
  and consistently a gap.

---

## 2. The buy-side chain as it stands

| Action | Who can do it today |
|---|---|
| Raise a requisition | any signed-in user |
| Submit a requisition for approval | any signed-in user — **including on someone else's draft** |
| **Approve / reject a requisition** | **admin or manager** |
| Cancel a requisition (draft or pending) | any signed-in user |
| Cancel an **approved** requisition | admin or manager |
| Delete a requisition | admin |
| Raise a quotation round, invite vendors, send the RFQ | any signed-in user |
| Key in a proposal, record a negotiation round | any signed-in user |
| Cancel a round | admin or manager |
| Delete a round, remove a proposal | admin (delete) / admin or manager (remove) |
| **Award a round** (built 2026-08-19) | **admin or manager**, no value threshold — the assumption below was taken as the default |
| **Revoke an award** | admin or manager |
| **Convert to a PO or contract** (built 2026-08-19) | admin or manager |

The intent behind the split: **approving is a control, buying is daily work.** Procurement
should not need an admin to key in a price or haggle; they should need one to say a purchase
is justified.

---

## 3. Notations

### N1 — Approval must not be bypassable, and there needs to be a way around it that is honest
*Opened 2026-08-19 (owner). Status: open.*

**Observed.** The approval gate is only meaningful if a lesser user cannot reach the same
outcome another way. Today an employee cannot approve, but the surrounding rules are loose
enough to matter:

- An employee can **cancel** someone else's pending requisition, and can **submit** someone
  else's draft.
- An employee can edit a requisition while it is `pending` — i.e. change what is being asked
  for after it was submitted, without a new approval.
- Nothing stops an employee **raising a quotation round standalone**, with no requisition at
  all. The requisition step is enforced as a *habit*, not as a rule: `quotations.purchase_
  requisition_id` is nullable by design (a round can legitimately be standalone), so the
  approval gate can simply be walked around by starting at the round.

**Why it matters.** The whole reason the requisition exists in the chain is that somebody
with authority says "yes, buy this" before vendors are approached. If the round can start
without one, the control is decorative.

**Owner's direction.** A lesser employee should be able to **duplicate a requisition** — so
they can raise a near-identical ask without touching an approved one, and without needing to
bypass approval.

**Options to weigh:**

1. **Require a requisition on a round** — per install, or per requisition type, or only for
   users below a role. Keeps standalone rounds for admins/managers.
2. **Restrict editing after submission**: a pending requisition becomes read-only to its
   raiser; changes send it back to draft and require re-approval.
3. **Ownership rules**: you may submit, edit and cancel **your own** draft; someone else's
   needs a reviewer.
4. **Duplicate action** on a requisition (any status, including approved and rejected) that
   copies title, type, location, budget item and items into a new **draft** owned by the
   person duplicating. This is the piece the owner asked for.

### N2 — Self-approval
*Opened 2026-08-19 (from the audit). Status: open.*

A manager can raise a requisition and approve it themselves; nothing checks that the
reviewer is not the requester. Standard BR practice for larger purchases is that the two are
different people, often with a value threshold below which it does not matter.

**Options:** block self-approval outright; block it above a value; allow it but record it
plainly on the detail view and in the history ("approved by the person who raised it").

### N3 — Award and conversion authority
*Opened 2026-08-19. Status: **built on the assumption**, still open for a decision.*

The award and the conversion shipped as **admin or manager, no value thresholds**, which was
the stated assumption. Both are guarded server-side and neither action is offered to an
employee on any screen. Worth revisiting now that money is genuinely committed by them:

- Should awarding above a value need an admin specifically?
- Should converting to a contract (which creates a payment schedule) be tighter than
  converting to a PO (which creates an expense on approval)?
- Should the person who keyed in the proposals be allowed to award them?

Changing any of these is now a change to working code rather than a design decision, so it
belongs in the module's review phase (`docs/review-and-improvements.md`).

### N4 — No per-project scoping
*Opened 2026-08-19 (from the audit). Status: open.*

Every signed-in user can open every project. For a company where a site supervisor should
only see their own site, there is no way to express that today. `projects.project_manager_id`
exists but is only used for reporting.

**Question for the owner:** do any of the installs need people confined to their own
projects or job sites? If yes, that is a much larger change than role tweaks, and it should
be decided before more screens are built on the assumption that everyone sees everything.

### N5 — Documents are reachable by id
*Opened 2026-08-19 (from the audit). Status: open.*

`/quotations/{id}/rfq/pdf`, `/quotations/{id}/map/pdf` and every existing report/estimate/
invoice PDF are behind `auth` only. Any signed-in user can fetch any of them by guessing an
id. Consistent with the rest of the app, which is why it was not "fixed" in the quotation
work alone — it needs one decision applied everywhere.

### N6 — Roles are a single flat field
*Opened 2026-08-19 (from the audit). Status: open.*

One role per user, three roles, checked by name in code. There is no way to say "this person
may approve requisitions but not delete anything", or "this person is procurement". As the
chain grows (award, conversion, payments) the number of distinct capabilities will outgrow
three names.

**Options:** keep roles but attach a capability list to each; add a fourth role
(`procurement`); or move to per-capability permissions with roles as presets.

### N7 — A share link is unauthenticated access, granted by a manager
*Opened 2026-08-19 (document repository, phase 7). Status: open.*

**Observed.** The file repository lets an admin **or manager** create a public link that hands
a document — or a whole folder, including anything filed into it later — to someone with no
login at all. The link is revocable, can carry a password and an expiry, and every access is
recorded against it, so it is auditable. But it is still the one place in the application
where a non-admin grants access to an outsider, and the folder form of it is open-ended by
nature.

Internal-only documents are excluded from folder links, and sharing one directly warns the
user, so the obvious foot-gun is covered. What is not decided is **who should hold the
authority**.

**Options:** leave it with admin and manager; restrict creation to admins; or allow managers
to create links only for a single document, keeping folder links to admins.

### N8 — Download links are bearer access for their lifetime
*Opened 2026-08-19 (document repository). Status: recorded, not a defect.*

**Observed.** Files are served by redirecting to a presigned Cloudflare R2 URL, so the bytes
never pass through PHP. The signature in that URL *is* the credential: for as long as it is
valid, anyone holding it can fetch that one file, logged in or not. Permission is checked
before the link is issued and each download is recorded, but a copied URL is access.

The window is `DOCUMENTS_PRESIGN_TTL`, set to **60 seconds** by the owner on 2026-08-19. This
is inherent to serving files directly from object storage; the alternative is streaming every
byte through the application, which forfeits the reason for using R2 at all.

**Related:** N5 — the PDF controllers are auth-only across the app. Whatever is decided there
should take the same view of the repository's links.

### N9 — The header search is not scoped
*Opened 2026-08-19. Status: open.*

The global search in the top header (`app/Livewire/Shared/HeaderSearch.php`) queries every
project and job site in the install, with no filter beyond the search term. It is a direct
consequence of N4 rather than a separate problem: while everyone can open every project,
searching everything is consistent. The moment per-project confinement lands, this component
has to be scoped in the same pass, otherwise it becomes the easiest way to enumerate records
a user is not meant to see — names, clients and addresses are all shown in the dropdown.

**Question for the owner:** none of its own. It rides on the answer to N4.

---

## 4. Decisions needed from the owner

1. **Can a quotation round start without an approved requisition?** Always, never, or only
   for admins/managers?
2. **Duplicate a requisition** — available to everyone, on any status? (N1, the owner has
   already said yes in principle.)
3. **Self-approval** — blocked, allowed, or allowed-but-flagged?
4. **Own vs anyone's records** — should submitting, editing and cancelling be restricted to
   the person who raised it, plus reviewers?
5. **Award authority** and any value thresholds (N3).
6. **Per-project confinement** — needed by any install? (N4)
7. **Document access** — tighten PDFs app-wide? (N5)
8. **Share links** — may managers create them, or admins only? Folder links too? (N7)

---

## 5. Related

- `docs/quotation-module-plan.md` — the chain and its phases.
- `docs/requisition-module.md`, `docs/quotation-module.md` — what each phase enforces today.
- `docs/module-access.md` — the install-level module switch, which is not user permissions.
- `docs/file-repository-plan.md` — the document repository, its roles and its share links.
