<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Income;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProjectIncome extends Component
{
    use AuthorizesAdmin, WithFileUploads;

    public Project $project;

    // Filters
    public $incomeSearch = '';
    public $incomeLocationFilter = 'all';

    // Form modal (create/edit)
    public $editingIncomeId = null;
    public $income_date = '';
    public $income_status = 'received';
    public $income_due_date = '';
    public $income_job_site_id = '';
    public $income_title = '';
    public $income_description = '';
    public $income_amount = null;
    public $income_uploads = [];

    // View modal
    public $viewingIncome = null;

    // Where the money goes: one location, or split across several
    public $income_location_mode = 'single';   // single | split
    public $distributionRows = [];
    public $distributionSearch = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function openAddModal(): void
    {
        $this->resetForm();
        $this->income_status = 'received';
        $this->income_date = now()->format('Y-m-d');
        $this->income_due_date = '';
        $this->income_location_mode = 'single';
        $this->loadDistributionRows();
        $this->dispatch('open-modal', 'income-form-modal');
    }

    public function openEditModal(int $incomeId): void
    {
        if ($this->viewingIncome) {
            $this->closeViewModal();
        }

        $income = $this->project->income()->with('distributions')->findOrFail($incomeId);

        $this->editingIncomeId = $income->id;
        $this->income_date = $income->income_date->format('Y-m-d');
        $this->income_status = $income->status ?? 'received';
        $this->income_due_date = $income->due_date?->format('Y-m-d') ?? '';
        $this->income_job_site_id = $income->job_site_id ?? '';
        $this->income_title = $income->title;
        $this->income_description = $income->description ?? '';
        $this->income_amount = $income->amount;
        $this->income_location_mode = $income->isDistributed() ? 'split' : 'single';
        $this->loadDistributionRows($income);

        $this->dispatch('open-modal', 'income-form-modal');
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', 'income-form-modal');
    }

    public function saveIncome(): void
    {
        // The split is checked first: over-allocating is the mistake the user
        // actually made, and saying so beats a per-row error on a derived
        // percent. Only then do the field rules run.
        if ($this->income_location_mode === 'split' && $this->distributionRemainder < 0) {
            $this->addError('distributionRows', __('The distribution cannot exceed the income amount.'));
            return;
        }

        $validated = $this->validate([
            'income_date' => 'required|date',
            'income_status' => 'required|in:received,expected',
            'income_due_date' => 'nullable|date|required_if:income_status,expected',
            'income_title' => 'required|string|max:255',
            'income_description' => 'nullable|string',
            'income_amount' => 'required|numeric|min:0.01|max:99999999',
            'income_job_site_id' => 'nullable|exists:job_sites,id',
            'income_location_mode' => 'required|in:single,split',
            // No max on the percent: it is derived from the amount, so a
            // too-large share reports as over-allocation above instead of a
            // percent-out-of-range error on every row at once.
            'distributionRows.*.amount' => 'nullable|numeric|min:0',
            'distributionRows.*.percent' => 'nullable|numeric|min:0',
            'income_uploads.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [], [
            'income_date' => __('date'),
            'income_status' => __('status'),
            'income_due_date' => __('due date'),
            'income_title' => __('title'),
            'income_description' => __('description'),
            'income_amount' => __('amount'),
            'income_job_site_id' => __('location'),
            'income_uploads.*' => __('file'),
        ]);

        $isSplit = $this->income_location_mode === 'split';

        // A split is project-level money by definition: the shares say where
        // it went, so the record itself belongs to no single location.
        $jobSiteId = $isSplit ? null : ($this->income_job_site_id ?: null);

        if ($jobSiteId && ! $this->project->jobSites()->whereKey($jobSiteId)->exists()) {
            $this->addError('income_job_site_id', __('The selected location is invalid.'));
            return;
        }

        $shares = $isSplit ? $this->collectShares() : [];

        if ($isSplit && $shares === []) {
            $this->addError('distributionRows', __('Assign an amount to at least one location, or choose a single location instead.'));
            return;
        }

        $data = [
            'income_date' => $validated['income_date'],
            'status' => $validated['income_status'],
            // Kept even once received: it records what was expected, and
            // only expected records ever read it back.
            'due_date' => $validated['income_due_date'] ?: null,
            'job_site_id' => $jobSiteId,
            'title' => $validated['income_title'],
            'description' => $validated['income_description'] ?: null,
            'amount' => $validated['income_amount'],
        ];

        $income = DB::transaction(function () use ($data, $shares) {
            if ($this->editingIncomeId) {
                $income = $this->project->income()->findOrFail($this->editingIncomeId);

                // Clear first: the old split is about to be replaced anyway,
                // and an empty one lets the amount move freely in either
                // direction without tripping the model guard.
                $income->syncDistributions([]);
                $income->update($data);
            } else {
                $income = $this->project->income()->create($data + ['created_by' => auth()->id()]);
            }

            if ($shares !== []) {
                $income->syncDistributions($shares);
            }

            return $income;
        });

        session()->flash('message', $this->editingIncomeId
            ? __('Income updated successfully.')
            : __('Income added successfully.'));

        foreach ($this->income_uploads as $upload) {
            $path = $upload->store('income', 'local');

            $income->attachments()->create([
                'file_path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $this->closeFormModal();
    }

    // =========================================================================
    // SPLIT — sharing one income across several of the project's job sites
    //
    // The grid lives inside the income form, so the split is decided in the
    // same breath as the amount instead of being a second trip.
    // =========================================================================

    /** One row per job site, pre-filled from an existing split. */
    protected function loadDistributionRows(?Income $income = null): void
    {
        $existing = $income
            ? $income->distributions->keyBy('job_site_id')
            : collect();

        $this->distributionSearch = '';
        $this->distributionRows = $this->project->jobSites()
            ->orderBy('job_site_name')
            ->get()
            ->map(function ($jobSite) use ($existing) {
                $amount = $existing->has($jobSite->id) ? (float) $existing[$jobSite->id]->amount : null;

                return [
                    'job_site_id' => $jobSite->id,
                    'job_site_name' => $jobSite->job_site_name,
                    'job_site_amount' => (float) $jobSite->job_amount,
                    'selected' => $amount !== null,
                    'amount' => $amount !== null ? $this->money($amount) : '',
                    'percent' => $amount !== null ? $this->percentOf($amount, $this->distributionBase()) : '',
                ];
            })
            ->all();
    }

    /** The number the split is measured against: whatever the form says. */
    protected function distributionBase(): float
    {
        return round((float) ($this->income_amount ?: 0), 2);
    }

    /** Switching to a single location drops the split, and vice versa. */
    public function updatedIncomeLocationMode($value): void
    {
        if ($value === 'split') {
            $this->income_job_site_id = '';
        } else {
            $this->clearAllShares();
        }

        $this->resetErrorBag('distributionRows');
    }

    /** The percents follow the amount the income is worth. */
    public function updatedIncomeAmount(): void
    {
        foreach ($this->distributionRows as $index => $row) {
            $amount = round((float) ($row['amount'] ?: 0), 2);

            $this->distributionRows[$index]['percent'] = $amount > 0
                ? $this->percentOf($amount, $this->distributionBase())
                : '';
        }
    }

    /**
     * Amount and percent are two views of the same number: typing in one
     * rewrites the other, so the grid always shows both and never asks which
     * mode a row is in.
     */
    public function updatedDistributionRows($value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key), 2, null);
        $total = $this->distributionBase();

        if ($field === 'amount') {
            $amount = round((float) ($value ?: 0), 2);
            $this->distributionRows[$index]['percent'] = $amount > 0 ? $this->percentOf($amount, $total) : '';
            $this->distributionRows[$index]['selected'] = $amount > 0;
        }

        if ($field === 'percent') {
            $percent = (float) ($value ?: 0);
            $amount = round($total * $percent / 100, 2);
            $this->distributionRows[$index]['amount'] = $amount > 0 ? $this->money($amount) : '';
            $this->distributionRows[$index]['selected'] = $amount > 0;
        }

        // Unticking a site takes its money back out of the split.
        if ($field === 'selected' && ! $value) {
            $this->distributionRows[$index]['amount'] = '';
            $this->distributionRows[$index]['percent'] = '';
        }
    }

    /** Divide the whole amount across the ticked sites, cents-exact. */
    public function splitEvenly(): void
    {
        $total = $this->distributionBase();
        $selected = collect($this->distributionRows)->filter(fn ($row) => $row['selected'])->keys();

        if ($total <= 0 || $selected->isEmpty()) {
            return;
        }

        $cents = (int) round($total * 100);
        $base = intdiv($cents, $selected->count());
        $leftover = $cents - ($base * $selected->count());

        foreach ($selected as $position => $index) {
            // The odd cents go to the first sites, so the split still adds up.
            $share = ($base + ($position < $leftover ? 1 : 0)) / 100;
            $this->distributionRows[$index]['amount'] = $this->money($share);
            $this->distributionRows[$index]['percent'] = $this->percentOf($share, $total);
        }
    }

    /** Give this site everything not yet assigned to another one. */
    public function assignRemainder(int $index): void
    {
        $others = collect($this->distributionRows)
            ->except($index)
            ->sum(fn ($row) => round((float) ($row['amount'] ?: 0), 2));

        $share = round($this->distributionBase() - $others, 2);

        if ($share <= 0) {
            return;
        }

        $this->distributionRows[$index]['amount'] = $this->money($share);
        $this->distributionRows[$index]['percent'] = $this->percentOf($share, $this->distributionBase());
        $this->distributionRows[$index]['selected'] = true;
    }

    public function clearAllShares(): void
    {
        foreach (array_keys($this->distributionRows) as $index) {
            $this->distributionRows[$index]['selected'] = false;
            $this->distributionRows[$index]['amount'] = '';
            $this->distributionRows[$index]['percent'] = '';
        }
    }

    public function toggleAllSites(): void
    {
        $selectAll = collect($this->distributionRows)->contains(fn ($row) => ! $row['selected']);

        foreach (array_keys($this->distributionRows) as $index) {
            $this->distributionRows[$index]['selected'] = $selectAll;

            if (! $selectAll) {
                $this->distributionRows[$index]['amount'] = '';
                $this->distributionRows[$index]['percent'] = '';
            }
        }
    }

    /** @return array<int, float> job_site_id => amount, blanks dropped */
    protected function collectShares(): array
    {
        $shares = [];

        foreach ($this->distributionRows as $row) {
            $amount = round((float) ($row['amount'] ?: 0), 2);

            if ($amount > 0) {
                $shares[$row['job_site_id']] = $amount;
            }
        }

        return $shares;
    }

    #[Computed]
    public function distributionTotal(): float
    {
        return round(collect($this->distributionRows)
            ->sum(fn ($row) => round((float) ($row['amount'] ?: 0), 2)), 2);
    }

    /** What stays project-level. Negative means the grid is over-allocated. */
    #[Computed]
    public function distributionRemainder(): float
    {
        return round($this->distributionBase() - $this->distributionTotal, 2);
    }

    /** How much of the income the grid has assigned, as a 0-100 bar width. */
    #[Computed]
    public function distributionPercent(): float
    {
        $total = $this->distributionBase();

        return $total > 0 ? min(100, round($this->distributionTotal / $total * 100, 2)) : 0;
    }

    #[Computed]
    public function selectedSiteCount(): int
    {
        return collect($this->distributionRows)->filter(fn ($row) => $row['selected'])->count();
    }

    /** Rows after the site search — the grid never loses the hidden ones. */
    #[Computed]
    public function visibleDistributionRows(): array
    {
        if (! $this->distributionSearch) {
            return $this->distributionRows;
        }

        return collect($this->distributionRows)
            ->filter(fn ($row) => str_contains(
                mb_strtolower($row['job_site_name']),
                mb_strtolower($this->distributionSearch)
            ))
            ->all();
    }

    /** Grid values are plain decimals — the display formatting is the view's job. */
    protected function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    protected function percentOf(float $amount, float $total): string
    {
        return $total > 0 ? number_format($amount / $total * 100, 2, '.', '') : '';
    }

    public function openViewModal(int $incomeId): void
    {
        $this->viewingIncome = $this->project->income()
            ->with(['project', 'jobSite', 'createdBy', 'attachments', 'distributions.jobSite'])
            ->findOrFail($incomeId);

        $this->dispatch('open-modal', 'income-view-modal');
    }

    public function closeViewModal(): void
    {
        $this->viewingIncome = null;
        $this->dispatch('close-modal', 'income-view-modal');
    }

    public function deleteIncome(int $incomeId): void
    {
        $this->authorizeAdmin();

        $income = $this->project->income()->findOrFail($incomeId);
        $income->delete();

        if ($this->viewingIncome && $this->viewingIncome->id === $incomeId) {
            $this->closeViewModal();
        }

        session()->flash('message', __('Income deleted successfully.'));
    }

    /**
     * Book expected money as received today. The report then counts it as
     * cash instead of a receivable.
     */
    public function markReceived(int $incomeId): void
    {
        $income = $this->project->income()->findOrFail($incomeId);

        if ($income->isReceived()) {
            return;
        }

        $income->markReceived();

        if ($this->viewingIncome && $this->viewingIncome->id === $income->id) {
            $this->viewingIncome = $income->fresh()
                ->load(['project', 'jobSite', 'createdBy', 'attachments', 'distributions.jobSite']);
        }

        session()->flash('message', __('Income marked as received.'));
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingIncomeId',
            'income_date',
            'income_status',
            'income_due_date',
            'income_job_site_id',
            'income_title',
            'income_description',
            'income_amount',
            'income_uploads',
            'income_location_mode',
            'distributionRows',
            'distributionSearch',
        ]);
        $this->resetErrorBag();
    }

    public function render()
    {
        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        $incomeQuery = $this->project->income()
            ->with(['jobSite', 'createdBy', 'distributions.jobSite'])
            ->withCount('attachments');

        // Apply location filter
        if ($this->incomeLocationFilter === 'project') {
            $incomeQuery->whereNull('job_site_id');
        } elseif ($this->incomeLocationFilter !== 'all' && is_numeric($this->incomeLocationFilter)) {
            $incomeQuery->where('job_site_id', $this->incomeLocationFilter);
        }

        // Apply search filter
        if ($this->incomeSearch) {
            $incomeQuery->where(function ($query) {
                $query->where('title', 'like', '%' . $this->incomeSearch . '%')
                    ->orWhere('description', 'like', '%' . $this->incomeSearch . '%');
            });
        }

        // Ordered by the date the list actually shows: the receipt date for
        // received money, the due date for money still expected.
        $incomeRecords = $incomeQuery
            ->orderByRaw('COALESCE(due_date, income_date) DESC')
            ->get();

        // Received money only — mixing in what has not arrived would make
        // this page disagree with the company financial report.
        $received = $incomeRecords->filter(fn ($income) => $income->isReceived());

        $totalIncomeAmount = $received->sum('amount');
        $thisMonthAmount = $received
            ->filter(fn ($income) => $income->income_date->isSameMonth(now()))
            ->sum('amount');
        $expectedAmount = $incomeRecords
            ->filter(fn ($income) => $income->isExpected())
            ->sum('amount');
        $overdueAmount = $incomeRecords
            ->filter(fn ($income) => $income->isOverdue())
            ->sum('amount');

        return view('livewire.project.project-income', [
            'incomeRecords' => $incomeRecords,
            'jobSites' => $jobSites,
            'totalIncomeAmount' => $totalIncomeAmount,
            'thisMonthAmount' => $thisMonthAmount,
            'expectedAmount' => $expectedAmount,
            'overdueAmount' => $overdueAmount,
        ])->layout('components.layouts.app');
    }
}
