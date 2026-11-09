<?php

namespace App\Livewire\BackCharge;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesBackChargeForm;
use App\Models\BackCharge;
use App\Models\Subcontractor;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Every back charge, what is left of each, and the form to add one (docs/back-charges.md). */
class BackChargeIndex extends Component
{
    use AuthorizesAbility;
    use ManagesBackChargeForm;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** '' (all) | open | deducted */
    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $subcontractorFilter = '';

    public function mount(): void
    {
        $this->authorizeAbility('back-charges.view');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'subcontractorFilter'], true)) {
            $this->resetPage();
        }
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->statusFilter !== '' || $this->subcontractorFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'subcontractorFilter']);
        $this->resetPage();
    }

    protected function query()
    {
        $deducted = '(select coalesce(sum(d.amount), 0) from back_charge_deductions d where d.back_charge_id = back_charges.id)';

        return BackCharge::query()
            ->when($this->search !== '', fn ($q) => $q->search($this->search))
            ->when($this->statusFilter === 'open', fn ($q) => $q->whereRaw("back_charges.amount > {$deducted}"))
            ->when($this->statusFilter === 'deducted', fn ($q) => $q->whereRaw("back_charges.amount <= {$deducted}"))
            ->when($this->subcontractorFilter !== '', fn ($q) => $q->where('subcontractor_id', (int) $this->subcontractorFilter));
    }

    /** Totals over every back charge, not the page and not the filters. */
    protected function summary(): array
    {
        $all = BackCharge::query()->withDeducted()->get(['id', 'amount']);
        $open = $all->reject(fn (BackCharge $bc) => $bc->isFullyDeducted());

        return [
            'open_count' => $open->count(),
            'open_remaining' => round($open->sum(fn (BackCharge $bc) => $bc->getRemainingCents()) / 100, 2),
            'deducted' => round($all->sum(fn (BackCharge $bc) => $bc->getDeductedCents()) / 100, 2),
            'total' => round($all->sum(fn (BackCharge $bc) => (int) $bc->getRawOriginal('amount')) / 100, 2),
            'count' => $all->count(),
            'done_count' => $all->count() - $open->count(),
        ];
    }

    /** A back charge with nothing deducted goes from the list. */
    public function delete(int $id): void
    {
        $this->authorizeAbility('back-charges.delete');

        $bc = BackCharge::findOrFail($id);

        if (! $bc->canBeDeleted()) {
            session()->flash('error', __(':number has deductions, so it cannot be deleted. Lower its amount to what was deducted instead.', ['number' => $bc->number]));

            return;
        }

        $bc->attachments()->get()->each->delete();
        $bc->delete();

        session()->flash('message', __(':number deleted.', ['number' => $bc->number]));
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorizeAbility('back-charges.view');

        $rows = $this->query()
            ->with(['subcontractor:id,name', 'subcontractorEmployee:id,name', 'project:id,project_name', 'jobSite:id,job_site_name', 'createdBy:id,name'])
            ->withDeducted()
            ->orderByDesc('date')->orderByDesc('id')
            ->get();

        $cell = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
        $money = fn (float $v) => number_format($v, 2, '.', '');

        return response()->streamDownload(function () use ($rows, $cell, $money) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('Number'), __('Date'), __('Subcontractor'), __('Employee'), __('Project'), __('Job Site'), __('Reason'), __('Description'), __('Amount'), __('Deducted'), __('Remaining'), __('Status'), __('Created by')]);

            foreach ($rows as $bc) {
                fputcsv($out, array_map($cell, [
                    $bc->number,
                    $bc->date->format('Y-m-d'),
                    $bc->subcontractor?->company_name ?? '',
                    $bc->subcontractorEmployee?->name ?? '',
                    $bc->project?->project_name ?? '',
                    $bc->jobSite?->job_site_name ?? '',
                    $bc->reason,
                    (string) $bc->description,
                    $money($bc->amount),
                    $money($bc->getDeducted()),
                    $money($bc->getRemaining()),
                    $bc->getStatusLabel(),
                    $bc->createdBy?->name ?? '',
                ]));
            }

            fclose($out);
        }, 'back-charges-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function backChargeSaved(BackCharge $backCharge, bool $created): void
    {
        session()->flash('message', $created
            ? __(':number created. Attach photos or documents here.', ['number' => $backCharge->number])
            : __(':number updated.', ['number' => $backCharge->number]));

        $this->redirectRoute('back-charges.show', $backCharge, navigate: true);
    }

    public function render()
    {
        // Re-checked on every request: a page left open must not keep reading after a revoke.
        $this->authorizeAbility('back-charges.view');

        $backCharges = $this->query()
            ->with(['subcontractor:id,name', 'subcontractorEmployee:id,name', 'project:id,project_name', 'jobSite:id,job_site_name'])
            ->withDeducted()
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(25);

        return view('livewire.back-charge.back-charge-index', [
            'backCharges' => $backCharges,
            'summary' => $this->summary(),
            'subcontractors' => Subcontractor::whereHas('backCharges')->orderBy('name')->get(['id', 'name']),
        ] + $this->backChargeFormOptions())->layout('components.layouts.app');
    }
}
