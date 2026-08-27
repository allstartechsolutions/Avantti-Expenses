<?php

namespace App\Livewire\Concerns;

use App\Models\Approval;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The approvals list, shared by the project page and the job-site page.
 *
 * Same arrangement as `ManagesRfis`, and for the same reason: the two levels
 * show the same records and differ only in what they are scoped to, so the
 * query lives here once rather than in two components that drift
 * (docs/project-jobsite-parity-rule.md).
 */
trait ManagesApprovals
{
    use AuthorizesAbility;

    public string $approvalSearch = '';

    public string $approvalStatusFilter = 'live';

    public string $approvalTypeFilter = 'all';

    public string $approvalLocationFilter = 'all';

    public string $approvalReviewerFilter = 'all';

    public bool $approvalOverdueOnly = false;

    public bool $approvalCertificateAlertsOnly = false;

    abstract protected function contextProject(): Project;

    abstract protected function contextJobSite(): ?JobSite;

    protected function permissionScope(): Project|JobSite
    {
        return $this->contextJobSite() ?? $this->contextProject();
    }

    public function updatedApprovalSearch(): void
    {
        $this->resetPage();
    }

    public function updatedApprovalStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedApprovalTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedApprovalLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedApprovalReviewerFilter(): void
    {
        $this->resetPage();
    }

    public function updatedApprovalOverdueOnly(): void
    {
        $this->resetPage();
    }

    public function updatedApprovalCertificateAlertsOnly(): void
    {
        $this->resetPage();
    }

    public function clearApprovalFilters(): void
    {
        $this->reset([
            'approvalSearch',
            'approvalStatusFilter',
            'approvalTypeFilter',
            'approvalLocationFilter',
            'approvalReviewerFilter',
            'approvalOverdueOnly',
            'approvalCertificateAlertsOnly',
        ]);

        $this->resetPage();
    }

    public function hasApprovalFilters(): bool
    {
        return $this->approvalSearch !== ''
            || $this->approvalStatusFilter !== 'live'
            || $this->approvalTypeFilter !== 'all'
            || $this->approvalLocationFilter !== 'all'
            || $this->approvalReviewerFilter !== 'all'
            || $this->approvalOverdueOnly
            || $this->approvalCertificateAlertsOnly;
    }

    /**
     * The list, narrowed to what this person may see and then to the filters.
     *
     * `visibleTo()` first and always — and the counters below are built from
     * the same base, because a total over records somebody cannot open is a
     * leak by arithmetic.
     */
    protected function approvalQuery(): Builder
    {
        $query = Approval::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->contextProject()->id);

        if ($jobSite = $this->contextJobSite()) {
            $query->where('job_site_id', $jobSite->id);
        } elseif ($this->approvalLocationFilter === 'project') {
            $query->whereNull('job_site_id');
        } elseif ($this->approvalLocationFilter !== 'all') {
            $query->where('job_site_id', $this->approvalLocationFilter);
        }

        if ($this->approvalSearch !== '') {
            $term = '%'.$this->approvalSearch.'%';

            $query->where(fn (Builder $q) => $q
                ->where('number', 'like', $term)
                ->orWhere('title', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('spec_section', 'like', $term));
        }

        if ($this->approvalStatusFilter === 'live') {
            $query->live();
        } elseif ($this->approvalStatusFilter !== 'all') {
            $query->where('status', $this->approvalStatusFilter);
        }

        if ($this->approvalTypeFilter !== 'all') {
            $query->where('type', $this->approvalTypeFilter);
        }

        if ($this->approvalReviewerFilter === 'mine') {
            $query->awaitingReviewBy(auth()->id());
        } elseif ($this->approvalReviewerFilter !== 'all') {
            $query->awaitingReviewBy((int) $this->approvalReviewerFilter);
        }

        if ($this->approvalOverdueOnly) {
            $query->overdue();
        }

        if ($this->approvalCertificateAlertsOnly) {
            $query->where('type', Approval::TYPE_CERTIFICATE)
                ->whereHas('certificate', fn (Builder $c) => $c
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '<=', now()->addDays(60)));
        }

        return $query;
    }

    /** @return LengthAwarePaginator<Approval> */
    protected function approvals(): LengthAwarePaginator
    {
        return $this->approvalQuery()
            ->with([
                'jobSite:id,job_site_name',
                'ballInCourt:id,name',
                'certificate',
                'currentRevisionRecord.reviewers.user:id,name',
            ])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Approval::DRAFT])
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'approvalPage');
    }

    /** The counters above the list, on the same narrowed base as the list. */
    protected function approvalSummary(): array
    {
        $base = fn () => Approval::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->contextProject()->id)
            ->when($this->contextJobSite(), fn (Builder $q, JobSite $s) => $q->where('job_site_id', $s->id));

        return [
            'live' => $base()->live()->count(),
            'awaiting_me' => $base()->awaitingReviewBy(auth()->id())->count(),
            'overdue' => $base()->overdue()->count(),
            'approved' => $base()->where('status', Approval::APPROVED)->count(),
            'certificates_lapsing' => $base()
                ->where('type', Approval::TYPE_CERTIFICATE)
                ->whereHas('certificate', fn (Builder $c) => $c
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '<=', now()->addDays(60)))
                ->count(),
        ];
    }

    /** The types actually in use here, for the filter. */
    protected function approvalTypeOptions(): array
    {
        $inUse = Approval::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->contextProject()->id)
            ->when($this->contextJobSite(), fn (Builder $q, JobSite $s) => $q->where('job_site_id', $s->id))
            ->distinct()
            ->pluck('type')
            ->all();

        return collect(Approval::typeOptions())
            ->filter(fn (string $label, string $value) => in_array($value, $inUse, true))
            ->all();
    }

    /**
     * The reviewers a list can be filtered by.
     *
     * Whoever actually holds one of the approvals on screen, rather than every
     * user in the company — which on a guest's screen would be a directory.
     */
    protected function approvalReviewerOptions(): array
    {
        return Approval::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->contextProject()->id)
            ->when($this->contextJobSite(), fn (Builder $q, JobSite $s) => $q->where('job_site_id', $s->id))
            ->whereNotNull('ball_in_court_id')
            ->with('ballInCourt:id,name')
            ->get()
            ->pluck('ballInCourt')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
