# Purchase Requisitions (Solicitação de Compra) — phase 1 of the quotation chain

**Shipped 2026-08-18.** Phase 1 of `docs/quotation-module-plan.md`. **The rest of the chain
is built too** — see `docs/quotation-module.md` — so an approved requisition here is quoted,
awarded and turned into a purchase order or contract without leaving the app.

The chain it starts:

```
requisição → cotação → mapa comparativo → negociação → adjudicação
   → pedido de compra (material) | contrato + medições (serviço)
```

---

## Decisions this phase locked in

| Question | Decision |
|---|---|
| Who approves a requisition | **Admin or manager** (`User::canReviewRequisitions()`) |
| Who may raise one | Any signed-in user |
| The requester | A system user **and/or** a free-typed name — office staff raise requisitions for people on site who have no login |
| Cost tracking | The **budget item** carries the cost code in this app, so a requisition links to a budget item and nothing else |
| Where it lives | Project level and job-site level, same page both ways (parity rule) |
| Module switch | New `quotations` module in `config/modules.php`, declared **before** `projects` so its route prefixes win the module check |

---

## Data model

### `purchase_requisitions`
`project_id` (required), `job_site_id` (nullable), `requisition_number` (`REQ-0001`,
generated on create), `type` (`material` | `service`), `title`, `justification`,
`needed_by`, `priority` (`low` | `normal` | `urgent`), `status`, `budget_item_id`,
`requested_by` (user, nullable), `requested_by_name` (free text), `reviewed_by`,
`reviewed_at`, `review_notes`, `created_by`, timestamps.

Status: `draft → pending → approved → quoted → fulfilled`, plus `rejected` and `cancelled`.
`quoted` and `fulfilled` are set by later phases, from the quotations that reference the
requisition — nothing writes them yet.

### `purchase_requisition_items`
`purchase_requisition_id`, `catalog_item_id`, `budget_item_id`, `item_name`, `item_type`
(`catalog` | `custom`), `description`, `quantity`, `unit`, `sort_order`. Mirrors
`purchase_order_items` **without the prices** — a requisition states quantities, the
quotation prices them.

### `purchase_requisition_status_histories`
`old_status`, `new_status`, `changed_by`, `reason`, timestamps. Same shape as the PO
history table. The FK and index names are given explicitly (`preq_status_hist_*`) because
the generated names exceed MySQL's 64-character identifier limit for this table name.

### Attachments
The polymorphic `attachments` table, through `<livewire:shared.attachments>`; that
component now accepts `modelType="requisition"` and stores under `storage/app/requisitions`.

---

## Pages

| Route | Component | View |
|---|---|---|
| `projects.requisitions` — `/projects/{project}/requisitions` | `App\Livewire\Project\ProjectRequisitions` | `livewire/project/project-requisitions.blade.php` |
| `jobsites.requisitions` — `/job-sites/{jobSite}/requisitions` | `App\Livewire\JobSite\JobSiteRequisitions` | `livewire/job-site/job-site-requisitions.blade.php` |

Both pages share:

- **`App\Livewire\Concerns\ManagesRequisitions`** — the form, the detail view, the review
  actions and every scoped query. Each page supplies its context through
  `contextProject()` and `contextJobSite()`; the job-site page fixes the location, the
  project page offers the picker. **Every lookup goes through `scopedQuery()`**, so a
  requisition id from another project or another job site 404s rather than loading.
- **`<x-requisition-table>`** — the list, with the location column switched off at job-site
  level.
- **`livewire/requisition/partials/form-modal.blade.php`** and **`view-modal.blade.php`** —
  the two full-page modals.

### The list
Search (number, title, justification, requester name, item name, id), filters for status
(including a single **Open** option covering draft/pending/approved/quoted), type, priority
and — at project level — location. Urgent rows sort first. Four summary cards: total with
the approved count, pending approval, urgent still open, past the needed-by date.

### The form (full-page modal)
Title, type, priority, needed-by, location, justification; requester as a user select **and**
a free-text name; a budget-item picker searching this project's budgets only; a catalog
search that appends priced-later lines carrying the catalog name and purchase unit; a
hand-typed line repeater (item, quantity, unit, specification); attachments.

Two ways out: **Save Draft** keeps it with the raiser, **Submit for Approval** moves it to
`pending`. Editing a line that came from the catalog detaches it from the catalog item,
because it is no longer that item.

### The detail (full-page modal)
Every stored field, the chain-progress strip, the items table, attachments, the full status
history with who and when, and the audit facts (created by/at, reviewed by/at, review notes,
last updated). Actions follow the record's state: submit, edit, approve/reject with notes,
cancel, delete.

---

## Rules enforced server-side

- **Approve and reject** — admin or manager only (`authorizeReview()`), and only from
  `pending`. A rejection **requires** a reason; an approval's notes are optional.
- **Cancel** — the raiser can cancel a draft or a pending one; cancelling an **approved**
  requisition needs a reviewer.
- **Delete** — admin only, and only for `draft`, `rejected` or `cancelled`.
- **Edit** — only while `draft` or `pending`, checked again inside the save transaction.
- **Items** — at least one line with a name and a quantity above zero; blank rows are
  dropped rather than rejected.
- **Tampered input** — job site, budget item and every row index are re-checked against the
  page's own project before use, because Livewire writes public arrays before the hooks run.

---

## How it joins the rest of the chain (built)

- **`quotations.purchase_requisition_id` points back here**, and a requisition may be quoted
  by **more than one round** — the steel to the steel merchants, the concrete to the plants.
  The detail view says which case it is: **Quote it** when no round exists, **Raise Another
  Round** (secondary, with an explanation) once one does, and **Quote it** again if every
  round was cancelled. The list carries a "2 rounds" note on the row.
- **The round copies `purchase_requisition_items` into `quotation_items`**, keeping
  `purchase_requisition_item_id` on each line, so the vendors price exactly the scope the
  site asked for and the trail runs both ways.
- **The status follows the chain automatically** — `PurchaseRequisition::refreshChainStatus()`
  sets `quoted` while a live round points at it, `fulfilled` once a round is converted into
  a purchase order or contract, and back to `approved` if every round is cancelled or
  deleted. Only that derived part of the lifecycle is touched.
- **Vendors do not type their own prices.** The RFQ goes out by e-mail from the system with
  a priceable PDF, and procurement keys in what comes back, attaching the vendor's PDF to
  their proposal row.

## Still open on this page

- A requisition **being quoted cannot be cancelled** (`canBeCancelled()` stops at
  `approved`); the round has to be cancelled first — `docs/review-and-improvements.md` Q22.
- **No duplicate action** yet, which is the honest alternative to bypassing approval — the
  owner asked for it, pending the N1 decision in `docs/permissions-notes.md`.
