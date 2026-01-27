# Phase 5: Navigation Refactor - Tabs to Sidebar Menu with Sub-Routes

**Date:** 2026-01-26
**Status:** Planned

---

## Overview

Refactor ProjectShow and JobSiteShow from a tab-based interface to a sidebar menu with separate routes for each section. This improves performance, navigation persistence, and scalability.

### Current Problems

1. **Performance**: Tab-based components load data for all tabs on initial render, even if user only views one section. With thousands of expenses, daily reports, etc., this becomes slow.

2. **Navigation Loss**: Tab state is managed via Livewire properties which reset on page load. When redirecting after creating an expense, the tab context is lost and user lands on Overview instead of Expenses.

3. **No Deep Linking**: Cannot bookmark or share links to specific sections (e.g., project expenses).

4. **Large Components**: ProjectShow.php and JobSiteShow.php are very large files handling all functionality.

### Target State

- Each section becomes its own route and component
- Sidebar menu for navigation between sections
- Only load data for the current section
- URLs reflect current section (bookmarkable, shareable)
- Smaller, focused components

---

## Proposed Route Structure

### Project Routes
```
/projects/{project}                    → ProjectOverview (dashboard/summary)
/projects/{project}/expenses           → ProjectExpenses (list + view modal)
/projects/{project}/daily-reports      → ProjectDailyReports
/projects/{project}/change-orders      → ProjectChangeOrders
/projects/{project}/budget             → ProjectBudget
/projects/{project}/files              → ProjectFiles
/projects/{project}/job-sites          → ProjectJobSites (list of job sites)
```

### Job Site Routes
```
/job-sites/{jobSite}                   → JobSiteOverview (dashboard/summary)
/job-sites/{jobSite}/expenses          → JobSiteExpenses
/job-sites/{jobSite}/daily-reports     → JobSiteDailyReports
/job-sites/{jobSite}/change-orders     → JobSiteChangeOrders
/job-sites/{jobSite}/budget            → JobSiteBudget
/job-sites/{jobSite}/files             → JobSiteFiles
```

---

## UI Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Header: Project Name / Job Site Name                           [Edit]   │
├──────────────────┬──────────────────────────────────────────────────────┤
│                  │                                                      │
│  Sidebar Menu    │  Content Area                                        │
│                  │                                                      │
│  ○ Overview      │  (Loaded based on current route)                     │
│  ○ Expenses      │                                                      │
│  ○ Daily Reports │                                                      │
│  ○ Change Orders │                                                      │
│  ○ Budget        │                                                      │
│  ○ Files         │                                                      │
│  ○ Job Sites     │  (Project only)                                      │
│                  │                                                      │
│  ─────────────── │                                                      │
│  Project Info    │                                                      │
│  Client: ABC     │                                                      │
│  Status: Active  │                                                      │
│                  │                                                      │
└──────────────────┴──────────────────────────────────────────────────────┘
```

---

## Implementation Plan

### Step 5.1: Create Shared Layout Component
- [ ] Create `resources/views/components/layouts/project-layout.blade.php`
- [ ] Create `resources/views/components/layouts/jobsite-layout.blade.php`
- [ ] Include sidebar menu with navigation links
- [ ] Highlight active menu item based on current route

### Step 5.2: Create Project Section Components
- [ ] `App\Livewire\Project\ProjectOverview` - Dashboard summary
- [ ] `App\Livewire\Project\ProjectExpenses` - Expenses list
- [ ] `App\Livewire\Project\ProjectDailyReports` - Daily reports list
- [ ] `App\Livewire\Project\ProjectChangeOrders` - Change orders list
- [ ] `App\Livewire\Project\ProjectBudget` - Budget view
- [ ] `App\Livewire\Project\ProjectFiles` - Files list
- [ ] `App\Livewire\Project\ProjectJobSites` - Job sites list

### Step 5.3: Create Job Site Section Components
- [ ] `App\Livewire\JobSite\JobSiteOverview` - Dashboard summary
- [ ] `App\Livewire\JobSite\JobSiteExpenses` - Expenses list
- [ ] `App\Livewire\JobSite\JobSiteDailyReports` - Daily reports list
- [ ] `App\Livewire\JobSite\JobSiteChangeOrders` - Change orders list
- [ ] `App\Livewire\JobSite\JobSiteBudget` - Budget view
- [ ] `App\Livewire\JobSite\JobSiteFiles` - Files list

### Step 5.4: Update Routes
- [ ] Add new routes in `routes/web.php`
- [ ] Keep old routes temporarily for backward compatibility
- [ ] Update all redirect URLs throughout the app

### Step 5.5: Update Navigation Links
- [ ] Update ExpenseCreate redirect to use new routes
- [ ] Update DailyReportForm redirect
- [ ] Update all other create/edit components
- [ ] Update sidebar links in index pages

### Step 5.6: Migrate Data Loading Logic
- [ ] Move expense-related logic to ProjectExpenses/JobSiteExpenses
- [ ] Move daily report logic to respective components
- [ ] Move change order logic to respective components
- [ ] Each component only loads its own data

### Step 5.7: Deprecate Old Components
- [ ] Mark ProjectShow.php as deprecated
- [ ] Mark JobSiteShow.php as deprecated
- [ ] Remove after testing period

---

## Benefits

| Aspect | Before (Tabs) | After (Routes) |
|--------|---------------|----------------|
| Initial Load | Loads all data | Loads only current section |
| URL | `/projects/1` | `/projects/1/expenses` |
| Bookmarkable | No | Yes |
| Browser Back | Doesn't work | Works naturally |
| After Create | Loses tab context | Stays on section |
| Component Size | ~1500 lines | ~200-400 lines each |
| Code Reuse | Duplicated | Shared layout |

---

## Shared Layout Structure

### Sidebar Menu Component
```blade
<!-- resources/views/components/project-sidebar.blade.php -->
@props(['project', 'activeSection' => 'overview'])

<nav class="w-64 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700">
    <!-- Project Header -->
    <div class="p-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="font-semibold text-slate-900 dark:text-white">{{ $project->project_name }}</h2>
        <p class="text-sm text-slate-500">{{ $project->client->company_name }}</p>
    </div>

    <!-- Navigation Links -->
    <ul class="p-2 space-y-1">
        <li>
            <a href="{{ route('projects.overview', $project) }}"
               class="{{ $activeSection === 'overview' ? 'bg-slate-100' : '' }}">
                Overview
            </a>
        </li>
        <li>
            <a href="{{ route('projects.expenses', $project) }}"
               class="{{ $activeSection === 'expenses' ? 'bg-slate-100' : '' }}">
                Expenses
            </a>
        </li>
        <!-- ... other links -->
    </ul>
</nav>
```

### Section Component Example
```php
// App\Livewire\Project\ProjectExpenses.php
class ProjectExpenses extends Component
{
    public Project $project;
    public $expenses;
    public $search = '';
    public $statusFilter = '';

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function render()
    {
        $this->expenses = $this->project->expenses()
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(20);

        return view('livewire.project.project-expenses')
            ->layout('components.layouts.project-layout', [
                'project' => $this->project,
                'activeSection' => 'expenses'
            ]);
    }
}
```

---

## Migration Strategy

1. **Create new components alongside old ones** - Don't break existing functionality
2. **Test each section independently** - Ensure feature parity
3. **Update redirects one by one** - Point create/edit forms to new routes
4. **Monitor for issues** - Keep old routes as fallback
5. **Remove old code** - After full validation

---

## Files to Create

```
app/Livewire/Project/
├── ProjectOverview.php
├── ProjectExpenses.php
├── ProjectDailyReports.php
├── ProjectChangeOrders.php
├── ProjectBudget.php
├── ProjectFiles.php
└── ProjectJobSites.php

app/Livewire/JobSite/
├── JobSiteOverview.php
├── JobSiteExpenses.php
├── JobSiteDailyReports.php
├── JobSiteChangeOrders.php
├── JobSiteBudget.php
└── JobSiteFiles.php

resources/views/livewire/project/
├── project-overview.blade.php
├── project-expenses.blade.php
├── project-daily-reports.blade.php
├── project-change-orders.blade.php
├── project-budget.blade.php
├── project-files.blade.php
└── project-job-sites.blade.php

resources/views/livewire/job-site/
├── job-site-overview.blade.php
├── job-site-expenses.blade.php
├── job-site-daily-reports.blade.php
├── job-site-change-orders.blade.php
├── job-site-budget.blade.php
└── job-site-files.blade.php

resources/views/components/layouts/
├── project-layout.blade.php
└── jobsite-layout.blade.php

resources/views/components/
├── project-sidebar.blade.php
└── jobsite-sidebar.blade.php
```

---

## Notes

1. **Start with ProjectShow** - It's the more complex one, establish patterns there first
2. **Reuse view/edit modals** - Can keep existing modals, just move them to section components
3. **Consider lazy loading** - For heavy sections like Files, consider lazy loading
4. **Mobile responsive** - Sidebar should collapse to hamburger menu on mobile
5. **Preserve query params** - Search filters should persist in URL where applicable

---

## Questions to Resolve

- Should the sidebar be collapsible?
- Should we show counts on menu items (e.g., "Expenses (23)")?
- Do we need breadcrumbs?
- How to handle the project settings/edit - separate page or modal?
