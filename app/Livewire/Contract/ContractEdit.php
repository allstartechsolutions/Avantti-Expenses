<?php

namespace App\Livewire\Contract;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesContractAllocations;
use App\Models\Contract;
use App\Models\Subcontractor;
use App\Models\SubcontractorEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContractEdit extends Component
{
    use AuthorizesAbility;

    use ManagesContractAllocations, WithFileUploads;

    public Contract $contract;

    // Form fields
    public $subcontractor_id = null;
    public $subcontractorSearch = '';
    public $subcontractor_employee_id = null;
    public $job_site_id = null;
    public $start_date;
    public $end_date = '';
    public $amount = '';
    public $retention_percent = '';
    public $notes = '';
    public $contract_file = null;
    public $existingFilePath = null;
    public $removeFile = false;

    public function mount(Contract $contract)
    {
        $this->authorizeAbility('contracts.edit', $contract);

        $this->contract = $contract->load(['project', 'jobSite', 'subcontractor']);

        $this->subcontractor_id = $contract->subcontractor_id;
        $this->subcontractorSearch = $contract->subcontractor?->company_name ?? '';
        $this->subcontractor_employee_id = $contract->subcontractor_employee_id;
        $this->job_site_id = $contract->job_site_id;
        $this->start_date = $contract->start_date->format('Y-m-d');
        $this->end_date = $contract->end_date?->format('Y-m-d') ?? '';
        $this->amount = $contract->amount;
        $this->retention_percent = $contract->retention_percent !== null
            ? rtrim(rtrim(number_format((float) $contract->retention_percent, 2, '.', ''), '0'), '.')
            : '';
        $this->notes = $contract->notes ?? '';
        $this->existingFilePath = $contract->contract_file_path;

        $this->allocations = $contract->allocations()
            ->with('budgetItem')
            ->get()
            ->map(fn ($allocation) => [
                'budget_item_id' => $allocation->budget_item_id,
                'code_display' => $allocation->cost_code_display,
                'amount' => $allocation->amount,
            ])
            ->all();
    }

    protected function allocationProjectId(): int
    {
        return $this->contract->project_id;
    }

    public function selectSubcontractor($id)
    {
        $subcontractor = Subcontractor::find($id);
        if ($subcontractor) {
            $this->subcontractor_id = $id;
            $this->subcontractorSearch = $subcontractor->company_name;
            $this->subcontractor_employee_id = null;
        }
    }

    public function clearSubcontractor()
    {
        $this->subcontractor_id = null;
        $this->subcontractorSearch = '';
        $this->subcontractor_employee_id = null;
    }

    public function removeExistingFile()
    {
        $this->removeFile = true;
        $this->existingFilePath = null;
    }

    /** Take the chosen file back off before it is stored. */
    public function clearContractFile()
    {
        // Livewire's own `_removeUpload()` deletes the temporary file; dropping
        // only the reference would leave it in livewire-tmp until the daily
        // sweep.
        $this->contract_file?->delete();

        $this->contract_file = null;
    }

    public function save()
    {
        $this->authorizeAbility('contracts.edit', $this->contract);

        $this->validate([
            'subcontractor_id' => 'nullable|exists:vendors,id,is_subcontractor,1',
            'subcontractor_employee_id' => ['nullable', Rule::exists('subcontractor_employees', 'id')->where('subcontractor_id', $this->subcontractor_id)],
            'job_site_id' => 'nullable|exists:job_sites,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'amount' => 'required|numeric|min:0',
            'retention_percent' => 'nullable|numeric|min:0|max:50',
            'contract_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'retention_percent.max' => __('Retention cannot exceed 50%.'),
        ]);

        if (! $this->allocationsValid()) {
            return;
        }

        // Handle file
        $filePath = $this->contract->contract_file_path;

        if ($this->contract_file) {
            // New file uploaded — delete old if exists
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }
            $filePath = $this->contract_file->store('contracts', 'local');
        } elseif ($this->removeFile) {
            // User removed the existing file
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }
            $filePath = null;
        }

        $before = $this->auditSnapshot();
        $fileChange = match (true) {
            (bool) $this->contract_file => $this->contract->contract_file_path ? 'replaced' : 'added',
            $this->removeFile && $this->contract->contract_file_path !== null => 'removed',
            default => null,
        };

        DB::transaction(function () use ($filePath, $before, $fileChange) {
            $this->contract->update([
                'subcontractor_id' => $this->subcontractor_id ?: null,
                'subcontractor_employee_id' => $this->subcontractor_employee_id ?: null,
                'job_site_id' => $this->job_site_id ?: null,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date ?: null,
                'amount' => $this->amount,
                'retention_percent' => $this->retention_percent === '' || $this->retention_percent === null ? null : $this->retention_percent,
                'notes' => $this->notes ?: null,
                'contract_file_path' => $filePath,
            ]);

            $this->syncAllocations($this->contract);

            $this->contract->refresh();
            $changes = $this->auditDiff($before, $this->auditSnapshot());

            if ($fileChange) {
                $changes['contract_file'] = ['old' => null, 'new' => $fileChange];
            }

            // The price moved, so what was paid may no longer settle it (or
            // may now settle it in full). Only a money change re-derives the
            // status: an edit to the notes must not undo a status somebody
            // set by hand.
            if (array_key_exists('amount', $changes)) {
                $oldStatus = $this->contract->status;
                $newStatus = $this->contract->updateStatusFromPayments('Auto-updated after the contract amount changed');

                if ($newStatus) {
                    $changes['status'] = ['old' => $oldStatus, 'new' => $newStatus];
                }
            }

            $this->contract->recordChange('edited', $changes);
        });

        session()->flash('message', __('Contract updated successfully!'));

        return redirect()->route('contracts.show', $this->contract->id);
    }

    /**
     * The contract's terms as the change history records them: names rather
     * than ids, so an entry still reads correctly after a vendor is renamed
     * or removed.
     */
    private function auditSnapshot(): array
    {
        $contract = $this->contract->fresh(['subcontractor', 'subcontractorEmployee', 'jobSite', 'allocations.budgetItem']);

        return [
            'subcontractor' => $contract->subcontractor?->company_name,
            'subcontractor_employee' => $contract->subcontractorEmployee?->name,
            'job_site' => $contract->jobSite?->job_site_name,
            'start_date' => $contract->start_date?->format('Y-m-d'),
            'end_date' => $contract->end_date?->format('Y-m-d'),
            'amount' => (float) $contract->amount,
            'retention_percent' => $contract->retention_percent !== null ? (float) $contract->retention_percent : null,
            'notes' => $contract->notes,
            'allocations' => $contract->allocations
                ->map(fn ($allocation) => [
                    'code' => $allocation->cost_code_display,
                    'amount' => (float) $allocation->amount,
                ])
                ->sortBy('code')
                ->values()
                ->all(),
        ];
    }

    /** Only the fields that moved, as ['old' => …, 'new' => …]. */
    private function auditDiff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $new) {
            $old = $before[$field] ?? null;

            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /**
     * What saving the amount on screen would do to the contract's money:
     * the figures behind the status, shown beside the field so a price
     * change on a paid contract is never a surprise.
     */
    public function getAmountImpactProperty(): array
    {
        $paid = $this->contract->getAmountPaid();
        $changeOrders = $this->contract->getChangeOrdersTotal();
        $amount = is_numeric($this->amount) ? round((float) $this->amount, 2) : (float) $this->contract->amount;
        $adjusted = round($amount + $changeOrders, 2);

        $status = $this->contract->status;

        if (! in_array($status, Contract::UNCOMMITTED_STATUSES, true) && $paid > 0.009) {
            $status = $paid >= $adjusted - 0.009 ? 'paid' : 'partially_paid';
        }

        return [
            'paid' => $paid,
            'change_orders' => $changeOrders,
            'adjusted' => $adjusted,
            'balance' => round($adjusted - $paid, 2),
            'status' => $status,
            'status_changes' => $status !== $this->contract->status,
            'amount_changed' => abs($amount - (float) $this->contract->amount) >= 0.005,
        ];
    }

    public function render()
    {
        $subcontractors = collect();
        if ($this->subcontractorSearch && strlen($this->subcontractorSearch) >= 2 && !$this->subcontractor_id) {
            $subcontractors = Subcontractor::where('name', 'like', '%' . $this->subcontractorSearch . '%')
                ->take(10)
                ->get();
        }

        $jobSites = $this->contract->project->jobSites()->orderBy('job_site_name')->get();

        $employees = $this->subcontractor_id
            ? SubcontractorEmployee::where('subcontractor_id', $this->subcontractor_id)->orderBy('name')->get()
            : collect();

        $allocationBudget = $this->allocationBudget();

        return view('livewire.contract.contract-edit', [
            'subcontractors' => $subcontractors,
            'jobSites' => $jobSites,
            'employees' => $employees,
            'allocationBudget' => $allocationBudget,
            'allocationItems' => $this->allocationSearchResults(),
            'allocationDefaultItem' => $allocationBudget?->defaultItem(),
        ])->layout('components.layouts.app');
    }
}
