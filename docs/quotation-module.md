# Quotation rounds (Cotação) — phase 2 of the quotation chain

**Shipped 2026-08-18, phases 3–8 added 2026-08-19.** Phases 2–8 of
`docs/quotation-module-plan.md`, on top of the requisition
(`docs/requisition-module.md`). The round can be raised, scoped, have its vendors invited,
be **e-mailed to the vendors from the app** with the scope as a PDF, have **each vendor's
proposal keyed in** with equalized totals, be **compared on the map**, and have every
**round of negotiation recorded**. **The award is phase 6.**

```
requisição ──► COTAÇÃO ──► PROPOSTAS ──► MAPA ──► NEGOCIAÇÃO ──► ADJUDICAÇÃO ──► PEDIDO / CONTRATO
                (here)      (here)      (here)      (here)          (here)             (here)
```

---

## Data model

### `quotations` — the round
`project_id`, `job_site_id` (nullable), `purchase_requisition_id` (nullable — a round can be
standalone), `quotation_number` (`COT-0001`), `type` (`material` | `service`), `title`,
`description`, `needed_by`, `responses_due_at`, `status`, `budget_item_id`,
`awarded_vendor_id`, `awarded_at`, `awarded_by`, `award_reason`, `is_split_award`,
`converted_type` + `converted_id`, `created_by`, timestamps.

Status: `draft → sent → comparing → negotiating → awarded → converted`, plus `cancelled`.
Phase 2 drives `draft → sent` and `cancelled`; the award columns are written by phase 6 and
the conversion columns by phase 7.

### `quotation_items` — the shared scope
`quotation_id`, `purchase_requisition_item_id` (nullable — where the line came from),
`catalog_item_id`, `budget_item_id`, `item_name`, `item_type`, `description`, `quantity`,
`unit`, `sort_order`. One list, priced by every vendor: that is what makes the comparison
honest.

### `quotation_vendors` — one invited vendor = one proposal
`quotation_id`, `vendor_id`, `status` (`invited` | `responded` | `declined` | `awarded` |
`rejected`), and — because **the vendors answer by e-mail** — how they were asked and how
the answer arrived:

| Column | Meaning |
|---|---|
| `invited_at`, `invite_method`, `invited_email` | when the RFQ went out, by which channel, to which address |
| `source`, `received_at`, `responded_at` | how the proposal came back, when it arrived, when they answered |

Plus the terms phase 3 fills in: `proposal_valid_until`, `lead_time_days`, `payment_terms`,
`freight_type` (`cif`/`fob`), `freight_amount`, `discount_amount`, `tax_amount`, `notes`.
Money in cents. Unique on (`quotation_id`, `vendor_id`).

### `quotation_vendor_items` — the prices
`quotation_vendor_id`, `quotation_item_id`, `unit_price`, `total_amount` (both in cents),
`is_unavailable`, `offered_brand`, `offered_spec`, `notes`. Unique on
(`quotation_vendor_id`, `quotation_item_id`), so re-keying a proposal updates in place
rather than doubling it.

A line is answered in one of three ways and never left ambiguous: **priced**, **cannot
supply** (`is_unavailable`, total forced to zero), or **substituted** (`offered_brand` /
`offered_spec` — priced, but not what was asked for).

**A line left blank has no row at all.** Blank means "not quoted", never "quoted at zero" —
storing a blank as R$ 0.00 would make a vendor who skipped half the scope look like the
cheapest offer. A price the buyer explicitly types as `0` *is* kept, because "included, no
charge" is a real answer; blanking it again removes the row. `answeredCount()`,
`unquotedCount()` and `coversScope()` read from that, and the detail view shows
**":count not quoted"** in red on any partial proposal.

The vendor's PDF proposal attaches to **this row**, not to the round.

### `quotation_rfq_emails` — what was actually sent
`quotation_id`, `quotation_vendor_id`, `sent_to`, `cc`, `subject`, `body`, `status`
(`sent` | `failed`), `error`, `sent_by`, `sent_at`. One row per vendor per send, **failures
included** — a bad address or a dead SMTP server shows on the round instead of disappearing.

### The award, on `quotations` and `quotation_items`
`awarded_vendor_id`, `awarded_at`, `awarded_by`, `award_reason`, `is_split_award` on the
round; **`quotation_items.awarded_quotation_vendor_id`** for a split, where each line names
the proposal that won it (null = the line was not awarded, which is a real outcome when
nobody priced it).

A whole-round award sets `awarded_vendor_id` and clears every line winner; a split does the
opposite — `awarded_vendor_id` stays null because there is no single winner. The two can
never disagree.

### The link to what gets paid
`purchase_orders.quotation_id` and `contracts.quotation_id` are the reliable direction,
because a split award produces one record per winning vendor and the round cannot point at
a single one. `quotations.converted_type` + `converted_id` stay as the shortcut for the
ordinary one-winner case and are **null on a split**.

### `quotation_negotiations` — the rounds, kept
`quotation_vendor_id`, `round` (1, 2, 3…), `previous_total`, `new_total` (equalized totals
in cents), `note`, `negotiated_by`, `negotiated_at`. Unique on (`quotation_vendor_id`,
`round`).

Negotiating **rewrites the prices**, so without this the fact that a vendor came down from
48,300 to 44,000 over two rounds would vanish the moment the new numbers were keyed in.

### `quotation_status_histories`
`old_status`, `new_status`, `changed_by`, `reason` — same shape as the PO and requisition
history tables.

---

## Pages

| Route | Component |
|---|---|
| `projects.quotations` — `/projects/{project}/quotations` | `App\Livewire\Project\ProjectQuotations` |
| `jobsites.quotations` — `/job-sites/{jobSite}/quotations` | `App\Livewire\JobSite\JobSiteQuotations` |

Both share `App\Livewire\Concerns\ManagesQuotations`, `<x-quotation-table>` and the two
full-page modals in `resources/views/livewire/quotation/partials/`. Context comes from
`contextProject()` / `contextJobSite()`, and **every lookup goes through `scopedQuery()`**,
so an id from another project or job site 404s.

### The list
Search (number, title, description, item name, vendor name, id), filters for status
(including a single **Open**), type and — at project level — location. Each row shows
**responded / invited** with a plain warning when the round is below the 3-proposal norm or
below the 2-proposal floor. Four cards: total with awarded, awaiting proposals, past the
deadline, awarded.

Above the list, **approved requisitions waiting to be quoted** are listed with a **Quote it**
button, so nothing approved sits forgotten.

### The form (full-page modal)
Title, requisition link, type, location, needed-on-site, responses-due, scope notes, budget
item, attachments; the scope repeater (catalog search + hand-typed lines, same as the
requisition); and the **invited vendors** block — vendor search with the per-round e-mail
address, a live count coloured against the 3/2 rule, and a checkbox to search every vendor
rather than only suppliers (material) or subcontractors (service), because a vendor is often
both and may simply not be flagged yet.

### The detail (full-page modal)
Progress strip, a proposals panel stating in words whether the round meets the norm, all
stored fields, the requisition backlink, the scope table, the invited vendors table (when
and how each was asked, their status, mark-declined / re-invite), attachments, full status
history, and the **Send the Round** panel.

---

## Rules enforced server-side

- **Sending** requires at least one item **and** one vendor; it stamps `invited_at`,
  `invite_method` and the e-mail on every vendor still waiting, then moves the round to
  `sent`. Vendors already stamped keep their original invitation.
- **Editing** is allowed while `draft` or `sent` — once comparing starts, changing the scope
  would invalidate the proposals already received.
- **A vendor who has answered cannot be dropped** from the round, in the form or by a
  tampered index; their proposal is part of the record.
- **Cancelling** is admin-or-manager (same rule as approving a requisition). **Deleting** is
  admin only, and only for `draft` or `cancelled`.
- **Duplicate invitations** collapse to one row (unique index plus a form-level guard).
- The **requisition follows the chain**: `PurchaseRequisition::refreshChainStatus()` sets it
  to `quoted` while a live round points at it, `fulfilled` once a round is converted, and
  back to `approved` if every round is cancelled or deleted. Only the derived part of the
  lifecycle is touched.
- Job site, requisition and budget item are re-checked against the page's own project before
  they are stored.

## Quote it — from the requisition to the round

The requisition detail gained a quoting button (shown while the requisition is `approved`
or `quoted`) and a **Quotation Rounds** panel listing the rounds already raised from it.

**A requisition stays quotable once quoted, on purpose** — one requisition is often split
across rounds, the rebar to the steel merchants and the concrete to the plants — but the
screen now says which case it is instead of offering the same button either way:

| State | What the detail shows |
|---|---|
| No round yet | **Quote it**, primary |
| One or more live rounds | **Raise Another Round**, secondary, above a line explaining that a round already covers this and another is only for splitting the scope |
| Every round cancelled | **Quote it** again, above a line saying the earlier rounds were cancelled |
| Fulfilled | no quoting action at all |

The list carries the same fact as a **"2 rounds"** note on the row, so it is visible
without opening anything. The button links to the quotations page with `?requisition={id}`; the page opens
the form pre-filled with the requisition's title, type, location, needed-by, budget item and
**every item**, each line keeping `purchase_requisition_item_id` so the scope traces back to
what the site asked for. A bogus or foreign id is ignored — the page still loads.

---

## The RFQ e-mail (pedido de cotação)

The vendors are asked by e-mail and answer by e-mail, so the round leaves the system from
here — the composer is opened with **Compose the E-mail** on a draft round, or **E-mail the
Request** once it is out.

- **One message per vendor.** Each gets their own e-mail and their own PDF; they never see
  each other. CC is for the buyer's own side.
- **Prefilled and editable** — subject `Quotation request COT-0001 — <title>`, a body that
  states the response deadline and asks for freight, taxes, lead time, payment terms and
  validity, signed with the company name.
- **The attached PDF** (`resources/views/pdf/quotation-rfq.blade.php`, also served by
  `QuotationRfqPdfController` at `quotations.rfq.pdf.download` / `.view`) carries the company
  header, who it is addressed to, the round's dates and delivery location, then the scope
  table **with empty Unit Price and Total columns** for the vendor to fill in, and a block
  listing what the proposal must state so the offers can be equalized later.
- **Sending stamps the record**: `invited_at` (only the first time — a re-send does not
  rewrite when the vendor was first asked), `invite_method = email`, `invited_email`, one
  `quotation_rfq_emails` row per attempt, and the round moves `draft → sent` with a history
  entry naming the count.
- **One failure does not cost the round.** Each vendor is sent, logged and reported on its
  own; failures are written to the log with the stack, shown on the round with the error
  message, and the successful ones still go.
- **Re-send** per vendor from the invited-vendors table (Send request / Send again), which
  opens the composer targeting just that vendor.
- **No mail server?** The composer says so plainly and offers the PDF to download or preview
  so procurement can send it from their own e-mail and then record the round as sent. The
  send button stays, honestly labelled **Send anyway (log only)**, so the flow is testable
  on an install without SMTP. `rfqMailIsDeliverable()` treats the `log` and `array` mailers,
  a missing from-address, and an SMTP mailer with no host as "cannot deliver".

Recording a round sent **outside** the system is still there: pick the channel (e-mail,
WhatsApp, phone, in person) and **Mark as Sent**, which stamps the same fields.

---

## Proposal entry (lançamento de propostas)

**Enter proposal** / **Edit proposal** on any invited or responded vendor in the round's
detail view opens a full-page screen holding one row per scope line.

- **Per line:** unit price with the line total recomputed as you type, a **Cannot supply**
  toggle that greys the price out and excludes the line from the total, **brand offered**
  and **spec offered** for a substitute, and a per-line note.
- **Terms:** freight CIF/FOB and its amount, taxes, discount, lead time in days, payment
  terms, and how long the proposal stands.
- **How it arrived:** channel (e-mail, WhatsApp, phone, in person) and the date received —
  because the answer comes back outside the system, the record says where it came from.
- **The vendor's PDF** attaches to *their* row, so every keyed-in price has the original
  behind it.
- **The equalized total is on screen the whole time**: lines → freight → taxes → discount →
  total, with a plain note when lines are excluded because the vendor cannot supply them.
  Nobody needs a calculator to check the screen.

Saving marks the vendor **responded**, stamps `received_at` / `responded_at`, and moves the
round `sent → comparing` on the first proposal. The invited-vendors table then shows each
proposal's equalized total, line subtotal, not-supplied and substituted counts, lead time,
freight, payment terms and validity — with **expired proposals flagged in red** rather than
silently compared.

### Rules

- **Line totals are computed server-side** from the scope quantity — the browser never
  decides what a line costs.
- **A line from another round cannot be priced here**: every submitted row is matched
  against this round's own scope and anything else is dropped.
- **An empty proposal is refused** — at least one line must be priced or marked as one the
  vendor cannot supply.
- **Awarded and cancelled rounds are frozen**, as are declined vendors: no entry, no edit.
  Changing the numbers after an award would rewrite the reason the award was justified.
- **Removing a proposal** (back to `invited`, prices and terms cleared) is a reviewer's
  call — admin or manager. If it was the last one, the round drops back from `comparing` to
  `sent`, because there is nothing left to compare.
- Scope lines deleted after a proposal arrived take their prices with them.
- **A scope quantity that changes re-totals the prices already keyed in** (unit price × the
  new quantity), so the comparison never uses the total of a quantity nobody quoted. Only
  reachable while the round is still `draft` or `sent` — the first proposal moves it to
  `comparing`, which is not editable.
- **A cancelled or converted round takes no more traffic**: no sending, no marking sent, no
  declining or re-inviting vendors.

---

## The comparison map (mapa comparativo)

**Comparison Map** on the round's detail view, or **Map** straight from the list once any
proposal is in. One shape is built by `App\Services\QuotationComparisonService` and used by
both the screen and the PDF, so the two can never disagree.

**The grid** — scope lines as rows, proposals as columns, the vendor's name frozen in the
header and the item column frozen on the left as it scrolls sideways. Each cell shows the
line total and the unit price, with the **best unit price on the row highlighted**; a cell
is one of three things and never ambiguous: **priced**, **Cannot supply**, or **Not
quoted**. A substitute is called out under the price with the brand offered. Rows carry the
**spread** between the dearest and cheapest offer for that line.

**The footers equalize in the open** — lines → freight (with CIF/FOB) → taxes → discount →
**equalized total**, then a terms row with lead time, payment terms, validity, and the
not-quoted / not-supplied / substituted counts. Each column shows how far above the
benchmark it sits.

**The benchmark is the cheapest proposal that covers the whole scope and has not expired.**
An expired proposal, or one that leaves lines unquoted, is shown but cannot win the
highlight — it is not cheaper, it is incomplete, and crowning it would advertise a price
nobody can buy at. If nothing is comparable, every column is ranked so the screen still
says something.

**Four figures across the top:**

| Card | What it says |
|---|---|
| Lowest Equalized Offer | the benchmark total, and whose it is |
| Saving vs the Highest | measured **inside the comparable set** — with only one comparable offer it says so instead of inventing a saving |
| If Split Line by Line | what the round would cost taking each line's best price, and how much below the single winner that is (phase 6 decides whether to split) |
| Against the Budget | the benchmark against the linked budget item, red when over — a warning, never a block |

**Warnings that must not be buried** sit above the grid: fewer than two proposals (an award
will be blocked), only two (the BR norm is three), expired proposals, and proposals that do
not cover the scope. Vendors still silent or who declined are listed under **Not on the
Map**, so the round never looks more complete than it is.

**Empty state is designed too** — a round whose vendors have not answered says so and says
what to do, rather than showing an empty grid.

**The PDF** (`quotations.map.pdf.download` / `.view`, landscape) is the same map with the
same numbers, for filing with the award.

Opening the map moves a round from `sent` to `comparing` when proposals exist — looking at
the offers is what comparing means. A round with nothing keyed in is left alone.

---

## Negotiation rounds (rodadas de negociação)

**Negotiate** next to any vendor who has answered opens the **same price screen** in
negotiation mode — prices change either way, and a round of haggling is that change plus
the reason for it, so there is no second place where prices are edited.

- The screen states the offer on the table now and keeps it as the **before** figure.
- **A note is required.** A price change nobody can explain later is not a negotiation, and
  the save is refused without one.
- The totals panel adds **Against the standing offer**, moving live as the prices change —
  green when the vendor comes down, red if the round went the wrong way.
- Saving writes the new prices *and* a `quotation_negotiations` row (round number, before,
  after, note, who, when), and moves the quotation to **negotiating**.
- Earlier rounds are listed in the form, on the round's detail view (every round with its
  note, its movement, and the running total won), and on the map and its PDF as
  **"was 48,300"** under the current total.
- Removing a proposal removes its rounds with it — there is no history of an offer that is
  no longer on the table.
- Anyone doing procurement can record a round; it is not restricted to reviewers, because
  haggling is the buyer's daily work.

---

## Modals stack

Every full-page screen in this chain is an `x-ui.modal`, and several of them are opened
**from inside** another one — proposal entry, negotiation, the RFQ composer, the map and
the edit form all open from the round's detail view. The component gained a `layer` prop
for that:

- `layer="top"` paints at z-index 60, the default `base` at 50, so the child lands above
  its parent instead of behind it. The z-index is **inline**, not a Tailwind class, so the
  fix works without rebuilding the CSS bundle.
- **Escape closes only the topmost modal.** Which one that is comes from the DOM — the open
  modal with the highest z-index — not from a counter.
- **The page scroll lock is released by the last modal to close**, so a child closing does
  not unlock the page under its parent. It is re-evaluated from the DOM on every open,
  close and Alpine `destroy()`, because Livewire can remove an open modal from the page
  outright and a counter would then never come back down.

The parent stays open underneath on purpose: closing the proposal screen puts the buyer
back on the round they were reading, not on the list.

## Next: phase 6 — the award

Pick a vendor, write the reason, confirm: status → `awarded`, losing proposals marked
`rejected`, everything frozen. **Fewer than two responded proposals blocks the award;
fewer than three warns.** Whole-quote award is the default, with a split-across-vendors
toggle revealing a per-item winner picker.
