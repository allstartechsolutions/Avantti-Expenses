# Client change requests — November 2026

Source: `Alteracoes Expenses Avantti November.26.docx` (27 requests, one screenshot each).
Worked one item at a time. Status: **todo**, **done**, **needs decision**.

## Payment Batches

| # | Request | Status |
|---|---|---|
| 1 | Batch list: totals by payment date and by PM (e.g. PM Alexandre $210,362.48 = Oct 8 $100,012.50 + Oct 9 $110,349.98) | **done** (5 Nov 2026) |
| 2 | Batch screen: when filtered by subcontractor, show the selection total and an "approve this selection" button | **done** (5 Nov 2026) |
| 3 | "Reject all pending" button | **done** (5 Nov 2026) |
| 4 | Back charges: approve / decline in the batch | **done** (9 Nov 2026) — `docs/back-charges.md` |
| 5 | BCs already deducted: hide from the batch, keep visible on the lot with the deduction date | **done** (9 Nov 2026) |
| 6 | Filter by employee | **done** (5 Nov 2026) |
| 7 | Sort: pending first, rejected last | **done** (5 Nov 2026) |
| 8 | Group rows by employee (same employee consecutively) | **done** (5 Nov 2026) |
| 9 | Batch list: filter by PM, total summary at the bottom | **done** (5 Nov 2026) |
| 10 | BC must appear in the exported Excel/CSV (checks are printed from it) | **done** (5 Nov / 9 Nov 2026) — change orders, then back charges and check amounts, on batch view, PDF and CSV |
| 11 | After approval, show which BC was deducted and what for (e.g. CTR-1204) | **done** (5 Nov / 9 Nov 2026) — change orders, then back charges and check amounts, on batch view, PDF and CSV |

## Other modules

| # | Request | Status |
|---|---|---|
| 12 | Payment: deleting a payment asks for a justification note | **done** (5 Nov 2026) |
| 13 | Company Expenses list: Notes column | **done** (5 Nov 2026) |
| 14 | Expense files: paperclip with count on every expense list (company, project, job site, equipment costs) opens the expense at its files | **done** (5 Nov 2026) |
| 15 | Company Expense details: show the equipment / machine | **done** (5 Nov 2026) |
| 16 | Dashboard Receivables: lots with an open contract (client's answer) | **done** (5 Nov 2026) |
| 17 | Dashboard Cash to Pay: split by PM plus grand total | **done** (5 Nov 2026) |
| 18 | Job Sites list: total costs (contracts + expenses) and balance columns | **done** (5 Nov 2026) |
| 19 | Job Site overview: total amount and overall profit/loss (sum of lots) | **done** (5 Nov 2026) |
| 20 | Payment Details report: filter by employee | **done** (5 Nov 2026) |
| 21 | Lot details: "View Contracts" button like "View Expenses" | **done** (5 Nov 2026) |
| 22 | Lot / job site: "Estimated cost" field, shown as a card on the overview (information only) | **done** (5 Nov 2026) |
| 23 | Create Supplier: address autocomplete, as on Clients | **done** (5 Nov 2026) |
| 24 | Expense check number when the method is Check (company, job site and lot) | **done** (5 Nov 2026) |
| 25 | Company Expenses: report by payment method (check / card / autopay) over a date range | **done** (5 Nov 2026) |
| 26 | Equipment overview: show the costs | **done** (5 Nov 2026) |
| 27 | Suppliers: documents (W-9 / COI) | **done** (5 Nov 2026) |

## Decisions

- **#24 check number** — one number per expense, installments included (option A). First built
  for company expenses only; on 6 Nov 2026 the client asked for it on job site and lot expenses
  too, so every expense form (including the project page's modal) has it, and the project and
  lot expense lists show it and search it. The reports that list expenses one by one show it too
  — Payment Details, Expense Report (detail) and Accounts Payable, on screen, in the PDF and as a
  CSV column. Payment Schedule only shows totals, so it has nowhere to put one. Numbers already typed into Notes are not migrated. The field
  appears only for method *Check*; another method clears it. Stays editable after payment.
- **#22 estimated cost** — one value per lot (`job_sites.estimated_cost`, plain currency,
  nullable), entered on both job-site forms. Information only, as decided on 5 Nov: it never
  enters the profit, so nothing needs zeroing when a contract is issued (the client had
  suggested counting it until then). Once contracts exist the card compares them with the
  estimate instead.
- **#14 expense files** — read as "show which expenses carry files and open them": a paperclip
  with the file count on every expense list (company, project, job site, equipment costs)
  opens the expense scrolled to its files. The equipment Costs tab can also open each expense.
- **#21 view contracts** — a Contracts card in the lot overview sidebar (count, total, paid
  bar, button), shown only to who holds `contracts.view` on that lot.

- **#1 / #9 batch list** — filters for project manager (incl. "no project manager") and payment
  date range; a Totals panel under the list for every batch the filters keep (not just the page):
  by PM split by payment date, by payment date, grand total. Same sum as the Total Amount column
  (every line in the batch, whatever its status).
- **#2 / #3 selection and reject** — on the batch screen, when the filters select some but not
  all pending lines, a bar shows their count and total with "Approve this selection" and "Reject
  this selection". "Reject All Pending" sits beside "Approve All Pending". The selection is the
  pending lines whose contract matches the filters (the screen itself always lists every pending
  line). Approving checks every line first and pays none if one cannot be paid, as Approve All does.
- **#6 / #7 / #8 employee and order** — Employee filter on the batch screen (narrowed by the
  subcontractor; screen-only, not saved on the batch). Rows: pending, approved, not in the batch,
  rejected last; inside that subcontractor → employee → project → lot. A column sort applies inside
  each band. "Employee" is also a grouping everywhere batches and /contract-payments group.
  Pending-first also applies to the batch view, PDF and CSV (they were approved-first).

- **#12 payment delete reason** — the trash icon on a contract payment opens a dialog that
  requires a reason (3–500 characters); it is stored with the "Payment Deleted" entry in the
  contract's change history.
- **#17 cash to pay by PM** — the dashboard card lists each project manager's share, then
  "No project manager" and "Company (general)", and the grand total. Same three sources as the
  total (installments and one-time expenses due this month, open contract balances).
- **#18 / #19 job site money** — `App\Services\ProjectFinancials`: contract value − expenses −
  committed contracts (adjusted), the same sum each lot's overview makes. The list gains Total
  Costs and Balance; the overview gains the four cards and a "profit or loss by lot" table whose
  rows add up to the cards (a "Job Site (General)" row carries what is filed on no lot, and any
  difference between a manual job site amount and its lots).
- **#23 supplier address** — Google Places on supplier create and edit through a shared
  `<x-address-autocomplete-script />` (`placesAddress`). The client and project forms keep their
  own copies for now — consolidating them is a review-phase item.
- **#25 payment method report** — on Company Expenses: a Payment method filter (Autopay is its
  own bucket, whatever the method), a "By payment method" table over the filters (click a row to
  filter), and Export CSV (needs `reports.export`) with the summary underneath. The From/To date
  filters were widened at the same time (they were cutting the date off).
- **#27 supplier documents** — the subcontractor documents module moved into
  `ManagesVendorDocuments` + `livewire/vendor/partials/documents-tab|document-modals`, and the
  supplier page uses it. Documents belong to the vendor row: a vendor that is both shows one set.
  Same grants (`vendors.renew_documents`, `vendors.archive_documents`, `vendors.delete`).
  Expiry reminders and document health used to watch subcontractors only; since the Codex review
  (5 Nov) they watch any vendor still classified as a subcontractor or a supplier, the reminder
  mail links to the supplier page for a supplier-only vendor, and downloads bind the plain vendor.
  Changed on purpose: a vendor that turns from subcontractor into supplier stays a compliance
  concern (two older tests were updated to a vendor that is neither).

## Codex review, 5 Nov 2026

Five findings, all confirmed and fixed, each pinned in `tests/Feature/CodexReviewRegressionTest.php`:
P1 job site totals/columns shown without `expenses.view` / `contracts.view` (now gated; profit
and balance need both); supplier document downloads 404'd; supplier documents not watched for
expiry; equipment "Not paid yet" counted whole installment expenses; batch-list totals bypassed
the money switch (now `<x-ui.money rollup>`).

- **#16 receivables** — decided 5 Nov: client payments are not tracked in the system, so the
  card is lots with an open contract (committed, not paid in full): their value less their
  contracts (`ProjectFinancials::openLots()`), with the lots' value, contracts, already paid and
  still to pay underneath, and the old figure kept as "Invoiced, due this month". Needs
  `projects.view` + `contracts.view` (it discloses both). For somebody with that and no
  `invoices.view`, the card now takes the slot the Over Budget count used to; the over-budget
  list further down is unchanged. Back charges deducted lower the contracts' cost and the cash
  paid in `ProjectFinancials::figures()` (9 Nov 2026).

## Back charge notes (items 4, 5, 10, 11)

Today a BC is entered as a negative change order on a contract. In practice it is a
deduction from a **subcontractor's payment**, and often has nothing to do with the
contract it is taken from. The client's answers:

- Declined in a batch → carried to the next payment.
- Declined permanently → never deducted, but recorded as "BC not deducted".
- Already deducted → leaves the batch, stays visible (with the date) on the lot.
- The BC must be on the batch screen and in the export; today change orders show on
  neither the view nor the export.

This points to a BC as its own record against the subcontractor, deducted through a
batch, rather than a change order. **Built 9 Nov 2026** — see `docs/back-charges.md`:
its own record (company or employee, optional origin lot), deducted in full or in part from
batch lines and direct contract payments, the rest carried automatically; "declined for good"
is lowering the amount to what was deducted, with the reason in the description.

## Client how-to document

Every finished item gets a "✔ DONE — how to use it" block (steps, notes, screenshots) under its
request in `Alteracoes Expenses Avantti November.26.docx`, written in English. The kit is in
`instructions/`:

- `source-original.docx` — the client's file exactly as received; the build always starts from it.
- `items.py` — one entry per finished item (steps, notes, screenshots).
- `screenshots/` — the images; `capture/` — the headless-Chrome scripts that take them
  (`capture/seed_demo.php` adds the demo records they show, all marked "Demo";
  login `demo@avantti.test` / `DemoPass!2026`, local only).
- `build_doc.py` — rebuilds the Word file: `python3 docs/changes_requested/instructions/build_doc.py`.
