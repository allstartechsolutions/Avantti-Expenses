# Payment batches and Contract Payments: grouping, column sort, the employee on paper (15 Oct 2026)

Answers the client's items 3 and 6 (*"seleção por subcontractor e jobsite"*) for the batch
screens, which `changelog-2026-10-13-payments-and-employee-status.md` left on hold: they meant
**grouping**, not another filter.

## What changed

**Batch detail (`/payment-batches/{id}`)**
- **Group by** Client, Project, Job Site, Subcontractor or Status. Each group opens with a heading
  row: its name (a lot carries its project beside it, since lot names repeat between projects),
  the line count, the subtotal, and the approved / pending / rejected split.
- **Sortable columns**: Subcontractor, Project, Job Site, Contract #, Amount, Method, Status. The
  sort applies inside each group. *Reset order* goes back to the default.
- The contract's **employee** under the subcontractor (as on the edit screen), a **Pays** column
  (it was already on the PDF and CSV), and a **Total** footer row.
- Grouping and sort live in the URL (`?group=&sort=&dir=`), so a reload or a shared link keeps them.

**PDF and CSV follow the screen.** *View PDF* / *Download PDF* carry the current grouping and sort
in their links; *Export CSV* reads them from the screen. Both print the group headings and
subtotals, and *Grouped by* in the header. The **employee** prints under the subcontractor on the
PDF (with their title) and as a *Contact (Employee)* column in the CSV.

**Batch edit (`/payment-batches/{id}/edit`)**
- **Group by** Client, Project, Job Site or Subcontractor (status is a line's, not a contract's).
  The heading row shows the **whole group's** Amount / Change Orders / Paid / Balance, not just the
  rows on this page; a group that started on the previous page says *continued from the previous page*.
- **Subcontractor** and **Project** are now sortable, beside the existing columns.

**Contract Payments (`/contract-payments`)** — the same table, so the same treatment:
- **Group by** Client, Project, Job Site or Subcontractor, with the shared heading row (whole-group
  Amount / Change Orders / Paid / Balance). Kept in the URL (`?group=`).
- **Subcontractor** and **Project** sortable.
- **PDF** (Summary and Detailed) carries the grouping, prints a heading row per group with
  Amount / CO / Adjusted / Paid / Balance, and *Grouped by* among the filters. The **employee** prints
  under the subcontractor.
- **CSV** now follows the screen: grouping **and sort** (before, it ignored the sort and always came
  out by project / job site). A `Group` row (Type column) opens each group with its totals.

  **Moved, for anyone with a spreadsheet built on it:** a *Contact (Employee)* column is inserted
  after *Subcontractor*, so every later column shifts one to the right; *Status* is now the
  translated label (*Partially Paid* / *Parcialmente Pago*) rather than the raw value
  (*Partially paid*); and the file starts with a UTF-8 BOM so Excel reads accents correctly, as the
  batch CSV already did.

**Fixed along the way:** the Contract Payments PDF named its sort column with a `match` that did
not know `subcontractor` / `project`, so a link sorted by either would have thrown instead of
printing. Both are named now, and a `?sort[]=` array no longer reaches the trait.

## Order rules
- Blanks go last in either direction: *Project General* after the lots, *No subcontractor* /
  *No client* after the named ones. Lots sort naturally (Lot 2 before Lot 10).
- With no column chosen, lines keep the order they always had (approved, pending, rejected), then
  project, lot, contract number — the default is unchanged except that ties are now deterministic.

## Files
| File | Change |
|---|---|
| `app/Services/PaymentBatchLayout.php` | **New.** Grouping, line sort, group totals; the one answer for screen, PDF and CSV. Unknown `group`/`sort`/`dir` values fall back to the default. |
| `app/Livewire/PaymentBatch/PaymentBatchShow.php` + view | Group / sort / reset, employee, Pays, footer total, CSV in screen order. |
| `app/Http/Controllers/PaymentBatchPdfController.php`, `resources/views/pdf/payment-batch.blade.php` | Reads `?group=&sort=&dir=`; group headings; employee. |
| `app/Livewire/PaymentBatch/PaymentBatchEdit.php` + view | Group by, page-aware group headings with whole-group totals. |
| `app/Livewire/Concerns/SortsContracts.php` | `subcontractor` and `project` sortable; `groupContracts()` and `groupHeaders()` shared by the batch edit screen, Contract Payments and its PDF. |
| `app/Livewire/Contract/ContractPayments.php` + view | Group by, sortable Subcontractor / Project, heading rows, CSV in screen order with the employee. |
| `app/Http/Controllers/ContractPaymentsPdfController.php`, `resources/views/pdf/contract-payments.blade.php` | `?group=`, heading rows, employee, sort-label fix. |
| `resources/views/livewire/contract/partials/group-header.blade.php` | **New.** The heading row both screens include. |
| `app/Models/PaymentBatchItem.php` | `static statusLabel()` beside `getStatusLabel()`. |
| `lang/en.json`, `lang/pt_BR.json` | 9 strings. |
| `tests/Feature/Contract/PaymentBatchGroupingTest.php` | **New.** 11 tests. |

No migration, no new ability: everything sits behind the existing `payments.batch` /
`payments.view` grants.
