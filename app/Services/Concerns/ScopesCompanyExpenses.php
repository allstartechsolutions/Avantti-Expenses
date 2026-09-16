<?php

namespace App\Services\Concerns;

use App\Models\Expense;

/**
 * How a report treats company (general) expenses — the rows in `expenses`
 * with no project (docs/company-expenses.md).
 *
 * The rule is the same on every report:
 *
 *   - A project, job-site or client filter excludes them, by construction:
 *     `where('project_id', X)` and `whereHas('project')` never match NULL.
 *   - Unfiltered, they are included only for a reader who holds
 *     `company-expenses.view` — the screen and the PDF controller say so
 *     through `includeCompany()`. The default is *not* to include them, so
 *     a caller that forgets can only under-report, never leak.
 *   - "Company (general)" on the Project dropdown narrows the report to
 *     them alone (`companyScopeFrom()` / `onlyCompany()`).
 *
 * A company row prints *Company (general)* where a project name goes and
 * its category where a job site goes: the category is what the company
 * side is broken down by, exactly as a project is broken down by site.
 */
trait ScopesCompanyExpenses
{
    protected bool $includeCompany = false;

    protected bool $companyOnly = false;

    /** Whether unfiltered results carry company rows — the reader's grant, decided by the caller. */
    public function includeCompany(bool $include = true): static
    {
        $this->includeCompany = $include;
        $this->forgetCompanyScopeCache();

        return $this;
    }

    /** Narrow the report to company rows alone. Still needs `includeCompany()` to show anything. */
    public function onlyCompany(bool $only = true): static
    {
        $this->companyOnly = $only;
        $this->forgetCompanyScopeCache();

        return $this;
    }

    /** The Project dropdown's "Company (general)" entry arrives as the literal `company`. */
    public function companyScopeFrom(mixed $projectFilter): static
    {
        return $projectFilter === 'company' ? $this->onlyCompany() : $this;
    }

    public function isCompanyOnly(): bool
    {
        return $this->companyOnly;
    }

    /** Apply the rule to a query over `expenses` (or one with `project_id` from that table). */
    protected function applyCompanyScope($query): void
    {
        if ($this->companyOnly) {
            $query->whereNull('project_id');
        }

        if (! $this->includeCompany) {
            $query->whereNotNull('project_id');
        }
    }

    public static function companyLabel(): string
    {
        return __('Company (general)');
    }

    /**
     * The two location cells a report prints for an expense.
     *
     * @return array{project: ?string, job_site: ?string}
     */
    protected function expenseLocation(?Expense $expense): array
    {
        if ($expense === null) {
            return ['project' => null, 'job_site' => null];
        }

        if ($expense->project_id === null) {
            return [
                'project' => static::companyLabel(),
                'job_site' => $expense->category?->getDisplayLabel(),
            ];
        }

        return [
            'project' => $expense->project?->project_name,
            'job_site' => $expense->jobSite?->job_site_name,
        ];
    }

    /** The scope is set before the first query, but a cached result must not survive a change. */
    protected function forgetCompanyScopeCache(): void
    {
        foreach (['cache', 'contractCache', 'expenseScheduleCache', 'contractScheduleCache', 'outstandingCache', 'openContractCache'] as $property) {
            if (property_exists($this, $property)) {
                $this->{$property} = null;
            }
        }
    }
}
