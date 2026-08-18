npm # Project & Job Site Parity Rule

## Core Rule

**Every module that exists at the Project level MUST also exist at the Job Site level, and vice versa.** Any change, improvement, or new feature applied to one level MUST be reflected on the other.

This is a mandatory guideline for all current and future development.

---

## What This Means

### 1. New Modules
When creating a new module (e.g., Invoices, Timesheets, Documents):
- It must work at **both** the Project level and the Job Site level
- Use the dual foreign key pattern: `project_id` (required) + `job_site_id` (nullable)
- When `job_site_id` is null, the record belongs to the project directly
- When `job_site_id` is set, the record belongs to that specific job site

### 2. UI Changes
When modifying the UI of any module (tables, forms, modals, filters):
- The same change must be applied to **both** the Project page version and the Job Site page version
- Column layouts, filters, search, and actions should be consistent between both levels
- The Job Site version may show less data (scoped to that site), but the functionality should match

### 3. Bug Fixes
When fixing a bug in a module at one level:
- Check if the same bug exists at the other level
- Fix it in both places

### 4. Feature Enhancements
When enhancing a feature (e.g., adding a new filter, a new column, export functionality):
- Apply the enhancement to both levels

---

## Current Modules That Follow This Rule

| Module | Project Level | Job Site Level |
|--------|--------------|----------------|
| Expenses | `ProjectExpenses` | `JobSiteShow` (expenses tab) |
| Change Orders | `ProjectChangeOrders` | `JobSiteShow` (change orders tab) |
| Daily Reports | `ProjectDailyReports` | `JobSiteShow` (daily reports tab) |
| Purchase Orders | `ProjectPurchaseOrders` | `JobSiteShow` (purchase orders tab) |
| Contracts | `ProjectContracts` | `JobSiteContracts` |
| Income | `ProjectIncome` | `JobSiteIncome` |
| Budget | `ProjectBudget` | `JobSiteShow` (budget tab) |
| Delete | `ProjectIndex`, `ProjectOverview` | `ProjectJobSites`, `JobSiteOverview` |

> **Note:** Job Site pages are being migrated from tabs to standalone page components (see `docs/jobsite-tabs-to-pages.md`). Update this table as migration progresses.

---

## Database Pattern

```
// Migration pattern for dual-level support
Schema::table('your_table', function (Blueprint $table) {
    $table->foreignId('project_id')->constrained()->onDelete('cascade');
    $table->foreignId('job_site_id')->nullable()->constrained()->onDelete('cascade');
});
```

```
// Model pattern
class YourModel extends Model
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }
}
```

---

## Checklist for Any Module Change

Before considering a task complete, verify:

- [ ] Does this change exist at the Project level? If yes, apply it to Job Site level too.
- [ ] Does this change exist at the Job Site level? If yes, apply it to Project level too.
- [ ] Are the UI layouts consistent between both levels?
- [ ] Are the same filters/search available at both levels?
- [ ] Do forms include the location selector when at the Project level?
- [ ] Are route references updated for both levels?
