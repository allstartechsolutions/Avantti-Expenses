<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Income;
use App\Models\JobSite;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Income at the job-site level.
 *
 * Two kinds of money land here: income booked directly on this job site,
 * which this page owns, and this job site's share of a project-level income,
 * which the project page owns and this page only shows. The share is read
 * only on purpose — one deposit split across lots has a single place where
 * it is edited.
 */
class JobSiteIncome extends Component
{
    use AuthorizesAbility, WithFileUploads;

    public JobSite $jobSite;

    // Filters
    public $incomeSearch = '';

    // Form modal (create/edit)
    public $editingIncomeId = null;
    public $income_date = '';
    public $income_status = 'received';
    public $income_due_date = '';
    public $income_title = '';
    public $income_description = '';
    public $income_amount = null;
    /**
     * Files chosen for this income, and the box the drop zone writes to.
     *
     * `updatedIncomeNewUploads()` moves them across, so a second drop adds to
     * the queue instead of replacing what was dropped first — Livewire's
     * `uploadMultiple` runs with `append = false`.
     */
    public $income_uploads = [];

    public $income_new_uploads = [];

    // View modal — either this job site's own income, or a read-only look at
    // its share of a project-level income
    public $viewingIncome = null;
    public $viewingShare = false;
    public $viewingShareAmount = null;

    public function mount(JobSite $jobSite): void
    {
        $this->authorizeAbility('income.view', $jobSite);

        $this->jobSite = $jobSite->load('project');
    }

    public function openAddModal(): void
    {
        $this->authorizeAbility('income.create', $this->jobSite);

        $this->resetForm();
        $this->income_status = 'received';
        $this->income_date = now()->format('Y-m-d');
        $this->income_due_date = '';
        $this->dispatch('open-modal', 'income-form-modal');
    }

    public function openEditModal(int $incomeId): void
    {
        if ($this->viewingIncome) {
            $this->closeViewModal();
        }

        // Escape and backdrop clicks close the modal without telling the
        // server, so a previous session can still be holding staged uploads
        // and stale errors. Start clean every time.
        $this->resetForm();

        $income = $this->jobSite->income()->findOrFail($incomeId);

        $this->authorizeAbility('income.edit', $income);

        $this->editingIncomeId = $income->id;
        $this->income_date = $income->income_date->format('Y-m-d');
        $this->income_status = $income->status ?? 'received';
        $this->income_due_date = $income->due_date?->format('Y-m-d') ?? '';
        $this->income_title = $income->title;
        $this->income_description = $income->description ?? '';
        $this->income_amount = $income->amount;

        $this->dispatch('open-modal', 'income-form-modal');
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', 'income-form-modal');
    }

    public function saveIncome(): void
    {
        // This screen writes to its own job site and nowhere else, so there is
        // no destination to check beyond it.
        $this->authorizeAbility(
            $this->editingIncomeId ? 'income.edit' : 'income.create',
            $this->jobSite,
        );

        $validated = $this->validate([
            'income_date' => 'required|date',
            'income_status' => 'required|in:received,expected',
            'income_due_date' => 'nullable|date|required_if:income_status,expected',
            'income_title' => 'required|string|max:255',
            'income_description' => 'nullable|string',
            'income_amount' => 'required|numeric|min:0.01|max:99999999',
            'income_uploads.*' => $this->incomeFileRule(),
        ], [], [
            'income_date' => __('date'),
            'income_status' => __('status'),
            'income_due_date' => __('due date'),
            'income_title' => __('title'),
            'income_description' => __('description'),
            'income_amount' => __('amount'),
            'income_uploads.*' => __('file'),
        ]);

        $data = [
            'project_id' => $this->jobSite->project_id,
            'job_site_id' => $this->jobSite->id,
            'income_date' => $validated['income_date'],
            'status' => $validated['income_status'],
            'due_date' => $validated['income_due_date'] ?: null,
            'title' => $validated['income_title'],
            'description' => $validated['income_description'] ?: null,
            'amount' => $validated['income_amount'],
        ];

        if ($this->editingIncomeId) {
            $income = $this->jobSite->income()->findOrFail($this->editingIncomeId);
            $income->update($data);
            session()->flash('message', __('Income updated successfully.'));
        } else {
            $income = Income::create($data + ['created_by' => auth()->id()]);
            session()->flash('message', __('Income added successfully.'));
        }

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

    public function openViewModal(int $incomeId): void
    {
        $this->authorizeAbility('income.view', $this->jobSite);

        $this->viewingIncome = $this->jobSite->income()
            ->with(['project', 'createdBy', 'attachments'])
            ->findOrFail($incomeId);
        $this->viewingShare = false;
        $this->viewingShareAmount = null;

        $this->dispatch('open-modal', 'income-view-modal');
    }

    /**
     * A project-level income distributed to this job site. Read only: the
     * project page owns that money, so this is a window, not an editor.
     */
    public function openShareModal(int $incomeId): void
    {
        // The record belongs to the project, but what is shown is this job
        // site's share of it, so this job site's grant is the right question.
        $this->authorizeAbility('income.view', $this->jobSite);

        $income = Income::query()
            ->where('project_id', $this->jobSite->project_id)
            ->whereNull('job_site_id')
            ->with(['project', 'createdBy', 'attachments', 'distributions.jobSite'])
            ->findOrFail($incomeId);

        $share = $income->distributions->firstWhere('job_site_id', $this->jobSite->id);

        if (! $share) {
            return;
        }

        $this->viewingIncome = $income;
        $this->viewingShare = true;
        $this->viewingShareAmount = (float) $share->amount;

        $this->dispatch('open-modal', 'income-view-modal');
    }

    public function closeViewModal(): void
    {
        $this->viewingIncome = null;
        $this->viewingShare = false;
        $this->viewingShareAmount = null;
        $this->dispatch('close-modal', 'income-view-modal');
    }

    public function deleteIncome(int $incomeId): void
    {
        $income = $this->jobSite->income()->findOrFail($incomeId);

        $this->authorizeAbility('income.delete', $income);

        $income->delete();

        if ($this->viewingIncome && $this->viewingIncome->id === $incomeId) {
            $this->closeViewModal();
        }

        session()->flash('message', __('Income deleted successfully.'));
    }

    public function markReceived(int $incomeId): void
    {
        $income = $this->jobSite->income()->findOrFail($incomeId);

        // Booking expected money as cash is a correction to the record.
        $this->authorizeAbility('income.edit', $income);

        if ($income->isReceived()) {
            return;
        }

        $income->markReceived();

        if ($this->viewingIncome && $this->viewingIncome->id === $income->id) {
            $this->viewingIncome = $income->fresh()->load(['project', 'createdBy', 'attachments']);
        }

        session()->flash('message', __('Income marked as received.'));
    }

    /**
     * Files dropped or chosen join the queue, and the box is emptied.
     *
     * Emptied **whatever happens**, including for a file the rule refuses. One
     * left behind is invisible — the list on screen is the queue, not this —
     * and would fail every later save on a file with no button to remove it.
     *
     * The refusal is said with `addError()` rather than by throwing, because
     * `validate()` ends with a bare `resetErrorBag()`: a file dropped onto a
     * form already showing "the amount field is required" would silently clear
     * that message while the amount was still empty.
     */
    public function updatedIncomeNewUploads(): void
    {
        $dropped = $this->income_new_uploads;
        $this->income_new_uploads = [];

        $refused = [];

        foreach ($dropped as $file) {
            $validator = Validator::make(
                ['file' => $file],
                ['file' => $this->incomeFileRule()],
                [],
                ['file' => __('file')],
            );

            if ($validator->fails()) {
                $refused[] = $file->getClientOriginalName();

                continue;
            }

            $this->income_uploads[] = $file;
        }

        if ($refused !== []) {
            $this->addError('income_new_uploads', __('Not attached — must be a PDF, JPG or PNG under 10MB: :files', [
                'files' => implode(', ', $refused),
            ]));
        }
    }

    /** Take a file back out of the queue before the income is saved. */
    public function removeIncomeUpload(int $index): void
    {
        // Livewire's own `_removeUpload()` deletes the temporary file; dropping
        // only the array entry would leave it in livewire-tmp until the daily
        // sweep.
        $this->income_uploads[$index]?->delete();

        unset($this->income_uploads[$index]);

        $this->income_uploads = array_values($this->income_uploads);
    }

    /** One place for what an income attachment may be, so the drop zone and the save agree. */
    protected function incomeFileRule(): string
    {
        return 'file|mimes:pdf,jpg,jpeg,png|max:10240';
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingIncomeId',
            'income_date',
            'income_status',
            'income_due_date',
            'income_title',
            'income_description',
            'income_amount',
            'income_uploads',
            'income_new_uploads',
        ]);
        $this->resetErrorBag();
    }

    public function render()
    {
        $incomeQuery = $this->jobSite->income()
            ->with('createdBy')
            ->withCount('attachments');

        if ($this->incomeSearch) {
            $incomeQuery->where(function ($query) {
                $query->where('title', 'like', '%' . $this->incomeSearch . '%')
                    ->orWhere('description', 'like', '%' . $this->incomeSearch . '%');
            });
        }

        $ownIncome = $incomeQuery
            ->orderByRaw('COALESCE(due_date, income_date) DESC')
            ->get();

        // This job site's share of project-level income. Shown, counted, but
        // not editable here.
        //
        // NOTE: `distributions` is loaded filtered to this job site, so the
        // aggregate helpers (distributedTotal/undistributedAmount) must not be
        // called on these instances — they would only see one share. The
        // read-only detail modal reloads the full relation for that reason.
        $shareQuery = Income::query()
            ->where('project_id', $this->jobSite->project_id)
            ->whereNull('job_site_id')
            ->whereHas('distributions', fn ($q) => $q->where('job_site_id', $this->jobSite->id))
            ->with(['distributions' => fn ($q) => $q->where('job_site_id', $this->jobSite->id)]);

        if ($this->incomeSearch) {
            $shareQuery->where(function ($query) {
                $query->where('title', 'like', '%' . $this->incomeSearch . '%')
                    ->orWhere('description', 'like', '%' . $this->incomeSearch . '%');
            });
        }

        $shares = $shareQuery->get();

        // One list, ordered by the date each row actually shows.
        $entries = $ownIncome
            ->map(fn ($income) => [
                'income' => $income,
                'amount' => (float) $income->amount,
                'is_share' => false,
            ])
            ->concat($shares->map(fn ($income) => [
                'income' => $income,
                'amount' => (float) ($income->distributions->first()?->amount ?? 0),
                'is_share' => true,
            ]))
            ->sortByDesc(fn ($entry) => $entry['income']->effectiveDate())
            ->values();

        $received = $entries->filter(fn ($entry) => $entry['income']->isReceived());

        return view('livewire.job-site.job-site-income', [
            'entries' => $entries,
            'totalIncomeAmount' => $received->sum('amount'),
            'thisMonthAmount' => $received
                ->filter(fn ($entry) => $entry['income']->income_date->isSameMonth(now()))
                ->sum('amount'),
            'expectedAmount' => $entries
                ->filter(fn ($entry) => $entry['income']->isExpected())
                ->sum('amount'),
            'overdueAmount' => $entries
                ->filter(fn ($entry) => $entry['income']->isOverdue())
                ->sum('amount'),
        ])->layout('components.layouts.app');
    }
}
