<?php

namespace App\Livewire\BackCharge;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesBackChargeForm;
use App\Models\BackCharge;
use Livewire\Component;

/** One back charge: everything it knows and every deduction taken from it (docs/back-charges.md). */
class BackChargeShow extends Component
{
    use AuthorizesAbility;
    use ManagesBackChargeForm;

    public BackCharge $backCharge;

    public function mount(BackCharge $backCharge): void
    {
        $this->authorizeAbility('back-charges.view');

        $this->backCharge = $backCharge;
    }

    /** Only while nothing has been deducted; deductions are history. */
    public function delete(): void
    {
        $this->authorizeAbility('back-charges.delete');

        $bc = $this->backCharge->fresh();

        if (! $bc->canBeDeleted()) {
            session()->flash('error', __(':number has deductions, so it cannot be deleted. Lower its amount to what was deducted instead.', ['number' => $bc->number]));

            return;
        }

        $bc->attachments()->get()->each->delete();
        $bc->delete();

        session()->flash('message', __(':number deleted.', ['number' => $bc->number]));
        $this->redirectRoute('back-charges.index', navigate: true);
    }

    protected function backChargeSaved(BackCharge $backCharge, bool $created): void
    {
        $this->backCharge = $backCharge->fresh();
        session()->flash('message', __(':number updated.', ['number' => $backCharge->number]));
    }

    public function render()
    {
        $this->authorizeAbility('back-charges.view');

        $bc = $this->backCharge->fresh([
            'subcontractor:id,name',
            'subcontractorEmployee:id,name',
            'project:id,project_name',
            'jobSite:id,job_site_name,project_id',
            'createdBy:id,name',
            'updatedBy:id,name',
            'deductions' => fn ($q) => $q->latest('id'),
            'deductions.contract:id,contract_number,project_id,job_site_id',
            'deductions.contract.project:id,project_name',
            'deductions.contract.jobSite:id,job_site_name',
            'deductions.batchItem:id,payment_batch_id,status',
            'deductions.batchItem.batch:id,name,payment_date',
            'deductions.payment:id,payment_date,amount,reference_number',
            'deductions.createdBy:id,name',
        ]);

        return view('livewire.back-charge.back-charge-show', [
            'bc' => $bc,
        ] + $this->backChargeFormOptions())->layout('components.layouts.app');
    }
}
