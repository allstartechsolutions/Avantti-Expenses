<?php

namespace App\Livewire\Concerns;

use App\Models\BackCharge;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\SubcontractorEmployee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

/**
 * The create / edit form of a back charge (docs/back-charges.md), shared by
 * the list and the back charge's own page. Expects AuthorizesAbility.
 */
trait ManagesBackChargeForm
{
    public ?int $editingBackChargeId = null;

    public string $bc_subcontractor_id = '';

    public string $bc_subcontractor_employee_id = '';

    public string $bc_project_id = '';

    public string $bc_job_site_id = '';

    public string $bc_amount = '';

    public string $bc_date = '';

    public string $bc_reason = '';

    public string $bc_description = '';

    public function openBackChargeCreate(?int $subcontractorId = null): void
    {
        $this->authorizeAbility('back-charges.create');

        $this->resetBackChargeForm();
        $this->bc_date = now()->toDateString();
        $this->bc_subcontractor_id = $subcontractorId ? (string) $subcontractorId : '';

        $this->dispatch('open-modal', 'back-charge-form');
    }

    public function openBackChargeEdit(int $id): void
    {
        $this->authorizeAbility('back-charges.edit');

        $bc = BackCharge::findOrFail($id);

        $this->resetBackChargeForm();
        $this->editingBackChargeId = $bc->id;
        $this->bc_subcontractor_id = (string) $bc->subcontractor_id;
        $this->bc_subcontractor_employee_id = (string) ($bc->subcontractor_employee_id ?? '');
        $this->bc_project_id = (string) ($bc->project_id ?? '');
        $this->bc_job_site_id = (string) ($bc->job_site_id ?? '');
        $this->bc_amount = number_format($bc->amount, 2, '.', '');
        $this->bc_date = $bc->date->toDateString();
        $this->bc_reason = $bc->reason;
        $this->bc_description = (string) $bc->description;

        $this->dispatch('open-modal', 'back-charge-form');
    }

    public function closeBackChargeForm(): void
    {
        $this->resetBackChargeForm();
        $this->dispatch('close-modal', 'back-charge-form');
    }

    protected function resetBackChargeForm(): void
    {
        $this->reset([
            'editingBackChargeId', 'bc_subcontractor_id', 'bc_subcontractor_employee_id', 'bc_project_id',
            'bc_job_site_id', 'bc_amount', 'bc_date', 'bc_reason', 'bc_description',
        ]);
        $this->resetValidation();
    }

    /** Another company's employee is never left selected. */
    public function updatedBcSubcontractorId(): void
    {
        $this->bc_subcontractor_employee_id = '';
    }

    public function updatedBcProjectId(): void
    {
        $this->bc_job_site_id = '';
    }

    protected function backChargeRules(): array
    {
        return [
            'bc_subcontractor_id' => ['required', Rule::exists('vendors', 'id')->where('is_subcontractor', true)],
            // Only an employee of the chosen company.
            'bc_subcontractor_employee_id' => ['nullable', Rule::exists('subcontractor_employees', 'id')->where('subcontractor_id', (int) $this->bc_subcontractor_id)],
            'bc_project_id' => ['nullable', Rule::exists('projects', 'id')],
            // Only a lot of the chosen job site.
            'bc_job_site_id' => ['nullable', Rule::exists('job_sites', 'id')->where('project_id', (int) $this->bc_project_id)],
            // Zero is allowed on an edit: waiving a back charge nothing was
            // deducted from is lowering it to what was deducted — nothing.
            'bc_amount' => ['required', 'numeric', $this->editingBackChargeId ? 'min:0' : 'min:0.01', 'max:99999999'],
            'bc_date' => ['required', 'date'],
            'bc_reason' => ['required', 'string', 'max:255'],
            'bc_description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function backChargeValidationAttributes(): array
    {
        return [
            'bc_subcontractor_id' => __('subcontractor'),
            'bc_subcontractor_employee_id' => __('employee'),
            'bc_project_id' => __('job site'),
            'bc_job_site_id' => __('lot'),
            'bc_amount' => __('amount'),
            'bc_date' => __('date'),
            'bc_reason' => __('reason'),
            'bc_description' => __('description'),
        ];
    }

    public function saveBackCharge(): void
    {
        $editing = $this->editingBackChargeId !== null;
        $this->authorizeAbility($editing ? 'back-charges.edit' : 'back-charges.create');

        $this->validate($this->backChargeRules(), [], $this->backChargeValidationAttributes());

        $fields = [
            'subcontractor_id' => (int) $this->bc_subcontractor_id,
            'subcontractor_employee_id' => $this->bc_subcontractor_employee_id !== '' ? (int) $this->bc_subcontractor_employee_id : null,
            'project_id' => $this->bc_project_id !== '' ? (int) $this->bc_project_id : null,
            'job_site_id' => $this->bc_job_site_id !== '' ? (int) $this->bc_job_site_id : null,
            'amount' => round((float) $this->bc_amount, 2),
            'date' => $this->bc_date,
            'reason' => trim($this->bc_reason),
            'description' => trim($this->bc_description) ?: null,
        ];

        $saved = DB::transaction(function () use ($editing, $fields) {
            if (! $editing) {
                return BackCharge::create($fields + [
                    'number' => BackCharge::generateNumber(),
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }

            $bc = BackCharge::lockForUpdate()->findOrFail($this->editingBackChargeId);
            $deductedCents = $bc->getDeductedCents();

            // What has been deducted is history: the amount cannot go below it,
            // and the company it was deducted from cannot change.
            if ((int) round($fields['amount'] * 100) < $deductedCents) {
                $this->addError('bc_amount', __('Already deducted: :amount. The amount cannot be lower.', [
                    'amount' => Number::currency($deductedCents / 100, config('app.currency'), config('app.locale')),
                ]));

                return null;
            }

            if ($deductedCents > 0 && ($fields['subcontractor_id'] !== $bc->subcontractor_id || $fields['subcontractor_employee_id'] !== $bc->subcontractor_employee_id)) {
                $this->addError('bc_subcontractor_id', __('Part of this back charge has been deducted, so who owes it cannot change.'));

                return null;
            }

            $bc->update($fields + ['updated_by' => Auth::id()]);

            return $bc;
        });

        if (! $saved) {
            return;
        }

        $this->closeBackChargeForm();
        $this->backChargeSaved($saved, ! $editing);
    }

    /** What the screen does once saved: the list opens the new record, the page refreshes. */
    abstract protected function backChargeSaved(BackCharge $backCharge, bool $created): void;

    /** The dropdowns of the form. */
    protected function backChargeFormOptions(): array
    {
        $subcontractorId = $this->bc_subcontractor_id !== '' ? (int) $this->bc_subcontractor_id : null;

        return [
            'formSubcontractors' => Subcontractor::activeOrCurrent($subcontractorId)->orderBy('name')->get(['id', 'name']),
            'formEmployees' => $subcontractorId
                ? SubcontractorEmployee::where('subcontractor_id', $subcontractorId)
                    ->activeOrCurrent($this->bc_subcontractor_employee_id ?: null)
                    ->orderBy('name')->get(['id', 'name'])
                : new Collection,
            'formProjects' => Project::visibleTo(Auth::user())->orderBy('project_name')->get(['id', 'project_name']),
            'formLots' => $this->bc_project_id !== ''
                ? JobSite::where('project_id', (int) $this->bc_project_id)->orderBy('job_site_name')->get(['id', 'job_site_name'])
                : new Collection,
        ];
    }
}
