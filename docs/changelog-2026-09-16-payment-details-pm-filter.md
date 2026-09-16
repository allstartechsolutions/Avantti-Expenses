# Payment Details report: Project Manager filter (16 Sep 2026)

The Payment Details report (`/reports/payment-details`) gained a **Project Manager**
filter, placed between Client and Project. Picking a manager restricts the report to
every project whose `project_manager_id` is that user, and every job site under those
projects, across expense installments, one-time expenses, contract payments and open
contract balances. It stacks with the other filters exactly as Client does.

## Behaviour

- The dropdown lists users who manage at least one project (`User::whereHas('managedProjects')`).
- With a manager selected, the **Project** and **Job Site** dropdowns narrow to that
  manager's projects and their sites. A selection outside that set is cleared when the
  manager changes; a selection still inside it is kept.
- **Company (general)** disappears from the Project dropdown while a manager is selected,
  and company expenses drop out of the rows: they belong to no project, so no manager.
- The filter lives in the query string (`projectManagerFilter`), so bookmarks, the CSV
  export and both PDF routes honour it. The PDF header prints `Project Manager: <name>`
  beside the other active filters.

## Where it lives

| File | Change |
|---|---|
| `app/Services/PaymentDetailReportService.php` | New last constructor argument; `whereHas('project', …)` on the expense and contract scopes. |
| `app/Livewire/Report/PaymentDetailReport.php` | Property, query-string entry, `projectManagers` list, narrowed `projects` / `jobSites`, `updatedProjectManagerFilter()`. |
| `app/Http/Controllers/PaymentDetailReportPdfController.php` | Reads the parameter, passes it to the service, resolves the user for the header. |
| `resources/views/livewire/report/payment-detail-report.blade.php` | The dropdown; Company entry hidden while a manager is selected. |
| `resources/views/pdf/payment-detail-report.blade.php` | Header line. |
| `tests/Feature/Report/PaymentDetailReportProjectManagerFilterTest.php` | Service filter, stacking with job site, dropdown narrowing, PDF data. |

No migration, no new ability: the report keeps `reports.payment_details`, the export keeps
`reports.export`. The pattern is the one Contract Payments already used for its manager filter.
