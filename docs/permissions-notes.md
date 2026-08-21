# Permissions — running notations

**Status: N1, N2, N3, N7 and §4b are settled (M7, M8, M10 and M12, 2026-08-21); N5 is half
closed — the document repository in M12, the PDF controllers still open; N8 needed nothing, the
check was already in the right place; N4 and N9 are closed by the passes as they land.** This is the place to write down permission
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
*Opened 2026-08-19 (owner). Status: **fully settled — M7 and M8, 2026-08-21**.*

**What was done (M7).** Options 2 and 4 of the four below, plus a piece of option 3:

- **Submitted is locked.** `canBeEdited()` is `draft` only. Changing a submitted requisition
  means pressing **Return to Draft** first — `requisitions.edit`, and either yours or a
  reviewer's — which costs it its place in the queue and writes `pending → draft` into its
  history. The lock is about the record, so an administrator is refused too.
- **Duplicate**, from any status including approved and rejected, into a fresh draft owned by
  whoever pressed it. Its own grant, `requisitions.duplicate`.
- **Submitting is its own grant** (`requisitions.submit`), so raising an ask and putting it in
  the queue are separable. Cancelling, which had no guard at all, now needs
  `requisitions.edit` — and `requisitions.approve` when the requisition is already approved.

**Closed in M8 (owner's choice: a grant, defined in the roles and templates matrix).** Raising a
round *from an approved requisition* needs `quotations.create`; raising one *from nothing* needs
`quotations.create_standalone` on top. Checked in three places — the button is not rendered,
`openAddModal()` refuses, and `saveQuotation()` refuses again for any new round with no
requisition behind it, so driving the form directly does not get past it.

This **tightens** what the application did: an employee can raise a standalone round today and
cannot after M8. A manager keeps it, because a manager can approve the requisition they would
otherwise have needed — nothing is being walked around — and it is one tick for anybody else.

---

**The original notation, for the record:**

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
*Opened 2026-08-19 (from the audit). Status: **settled in M7, 2026-08-21**.*

**Blocked**, and lifted by a grant rather than by an exception in the code.

Approving is refused when the approver either keyed the requisition in (`created_by`) or is
named as the person it is for (`requested_by`) — approving your own ask is the same act under
either heading. Rejecting your own is not blocked; it is not the problem self-approval is.

The "block above a value" option was not available: a requisition carries no money at all (its
items have a quantity and never a price), which is also why `requisitions.approve` is not
`limited`. Value thresholds start at M8.

`requisitions.approve_own` lifts the block. It is held back from both seeded roles and from
every permission template, so a company small enough that the raiser and the reviewer are the
same person ticks one box — and the fact that they did is on the record rather than being a
quiet special case. The detail view says *"You raised this requisition, so somebody else has to
approve it"* instead of dropping the button silently.

**Known limit:** an administrator holds every ability by definition, `approve_own` included, so
administrators can still approve their own. Making the block admin-proof would need a rule
outside the ability system, which is the thing this module exists to remove. Recorded as a
consequence rather than an oversight.

---

**The original notation, for the record:**

A manager can raise a requisition and approve it themselves; nothing checks that the
reviewer is not the requester. Standard BR practice for larger purchases is that the two are
different people, often with a value threshold below which it does not matter.

**Options:** block self-approval outright; block it above a value; allow it but record it
plainly on the detail view and in the history ("approved by the person who raised it").

### N3 — Award and conversion authority
*Opened 2026-08-19. Status: **settled in M8, 2026-08-21**.*

All three questions answered:

- **Should awarding above a value need somebody specific?** Yes — `quotations.award` obeys
  `approval_limit`, checked against what the proposed winner would commit. M8 is the first pass
  to use the ceiling at all.
- **Should converting to a contract be tighter than converting to a PO?** Yes — they are now
  two grants. `quotations.convert` commits one purchase order; `quotations.convert_contract`
  commits a schedule of future payments. Both obey the ceiling.
- **Should the person who keyed in the proposals be allowed to award them?** No, by default.
  `quotation_vendors.priced_by` records who typed the prices (`created_by` records who invited
  the vendor, a different act), the block applies to the **winning** rows only, and
  `quotations.award_own` lifts it — held back from both seeded roles and every template, the
  same shape as `requisitions.approve_own`.

---

**The original notation, for the record:**

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
*Opened 2026-08-19 (from the audit). Status: **the repository half is closed (M12, 2026-08-21);
the PDF controllers are still open**.*

**Closed in M12, for the document repository.** `Document::isVisibleTo()` returned true for every
non-internal document to anybody — including a signed-out visitor — so the download and preview
routes handed any project's files to any signed-in person who guessed an id. It now asks
`documents.view` on the document's own project, plus `documents.see_internal` for the internal
ones, and `scopeVisibleTo()` narrows lists the same way.

**Still open:** the PDF controllers named below. `/quotations/{id}/rfq/pdf`, the report PDFs and
the estimate and invoice PDFs are behind `auth` only. Each module's own pass should guard its
PDFs the way M12 guarded the repository — M8 did not, and that is a gap to sweep in F3 if no
later pass picks it up.

---

**The original notation, for the record:**

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
*Opened 2026-08-19 (document repository, phase 7). Status: **settled in M12, 2026-08-21**.*

**The owner's answer: leave it with admin and manager, but make it a grant.** `documents.share`
is seeded exactly as the old check behaved, and is now revocable per role, per template, per
project and per person. The folder-vs-document split offered below was not taken — a folder link
and a document link are the same grant.

---

**The original notation, for the record:**

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

## 4a. Decisions taken — 2026-08-20 (owner)

Recorded when the permission module was designed. The design lives in
**`docs/permissions-module-plan.md`**; this section is only the record of what was decided.

| Question | Decision |
|---|---|
| 6. Per-project confinement (N4) | **Yes, as a per-user switch.** Each user is *Company-wide* or *Assigned only*. Existing users all migrate as Company-wide, so nothing changes on deploy; people are confined one at a time. |
| 1, 3, 4. Approval gaps (N1, N2) | **Settled inside the permission module, not deferred.** Self-approval blocked; a submitted requisition locked to its raiser (an edit sends it back to draft and needs re-approval); a quotation round requires an approved requisition; **duplicate requisition** added so a lesser user has an honest way round. |
| 2. Duplicate a requisition (N1) | **Yes**, any status, into a new draft owned by whoever duplicated it. |
| 5. Award authority and thresholds (N3) | Abilities `quotations.award` / `quotations.convert`, plus an **optional `approval_limit`** per membership — nullable, so the feature is invisible until a customer wants it. |
| 6b. Granularity (N6) | **Action matrix**: per area, View / Create / Edit / Approve / Delete plus a few area-specific actions. Roles and templates become presets over the same matrix. |
| 7. Document access (N5, N8) | **Tightened app-wide** in the enforcement sweep: every PDF and file controller authorizes against the record's scope, and a presigned R2 URL is only minted after the check. |
| 8. Share links (N7) | Becomes the `documents.share` **ability**, granted by template rather than by role name — so each install decides whether managers hold it, and folder links can be held tighter than single-document ones. |
| — Global search (N9) | Scoped in the same phase as confinement. |
| — External guests | **Allowed.** A client, engineer or vendor can hold a login confined to one project or job site, with no sidebar, no index of all projects and no global search. |
| — Change-order approval (§4b) | Same answers as the requisition: its own ability, self-approval blocked, un-approving narrower than approving, and deleting an approved one behind a separate ability. |

Still open, and not blocking: guest notifications, whether approval limits also belong on a
role, what happens to a removed member's drafts and tasks, and whether any action needs a
genuine two-person rule.

## 4b. Change orders (added 2026-08-19, phase 2)
*Status: **settled in M10, 2026-08-21**.*

All four questions answered, each following the pattern the earlier passes set:

1. **Who may approve** — `change-orders.approve`, seeded to manager and administrator, and it
   obeys `approval_limit`. A **tightening**: an employee can approve one today and cannot after
   M10. The ceiling is measured against the **cost** side, by magnitude, so a deductive change
   order is not waved through just because its sign is negative.
2. **Self-approval** — blocked, lifted by `change-orders.approve_own`. The same answer as N2
   (requisitions, M7) and N3 (quotation awards, M8), which is what this notation suggested.
   Turning down your own *pending* change order is not blocked.
3. **Un-approving** — narrower than approving, in its own grant. A *pending* change order's
   lines are not in the budget, so turning it down needs only `approve`; an *approved* one's are,
   so rejecting or returning it to pending needs `change-orders.unapprove`. Somebody who may
   approve any amount still cannot undo one.
4. **Deleting an approved change order** — refused outright, for everybody including
   administrators. It would take the cost lines out of every budget they revised with no record
   that the revision happened. Un-approve it first, which is visible and needs `unapprove`.

`approve_own` and `unapprove` are held back from both seeded roles and every permission template.

---

**The original notation, for the record:**

Approving a change order is what moves the cost budget, and today **anyone who can reach the
change orders screen can approve, reject or return one to pending**. No admin guard, no
separation between the person who raises it and the person who approves it.

Open questions for the owner:

1. **Who may approve a change order** — everyone, managers, admins, or a value threshold?
2. **Self-approval** — may the person who raised it approve it? (Same question as N3 for
   requisitions; the answer should probably be the same for both.)
3. **Un-approving** — Return to Pending and Reject pull money back out of a live budget. Should
   those be narrower than approving in the first place?
4. **Deleting an approved change order** — currently allowed, and it silently removes its cost
   lines from every budget. Block it, or require admin?

## 5. Related

- `docs/quotation-module-plan.md` — the chain and its phases.
- `docs/requisition-module.md`, `docs/quotation-module.md` — what each phase enforces today.
- `docs/module-access.md` — the install-level module switch, which is not user permissions.
- `docs/file-repository-plan.md` — the document repository, its roles and its share links.
