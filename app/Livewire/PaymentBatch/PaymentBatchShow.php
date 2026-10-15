<?php

namespace App\Livewire\PaymentBatch;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\PaymentBatch;
use App\Services\PaymentBatchLayout;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentBatchShow extends Component
{
    use AuthorizesAbility;

    public PaymentBatch $paymentBatch;

    /** Client, project, job site, subcontractor or status; empty lists the lines ungrouped. */
    #[Url(as: 'group', except: '')]
    public string $groupBy = '';

    /** Column the lines are sorted by inside each group; empty keeps the status order. */
    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDirection = 'asc';

    /**
     * Sort by a column; clicking it again flips the direction. Money starts
     * with the largest figure, text with A.
     */
    public function sort(string $field): void
    {
        if (! PaymentBatchLayout::cleanLineSort($field)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'amount' ? 'desc' : 'asc';
        }
    }

    /** Back to the order the batch has always printed in. */
    public function resetLayout(): void
    {
        $this->groupBy = '';
        $this->sortField = '';
        $this->sortDirection = 'asc';
    }

    #[Computed]
    public function summary(): array
    {
        return PaymentBatchLayout::lineTotals($this->paymentBatch->items);
    }

    /**
     * The batch lines in their groups, in the order on screen — the PDF links
     * and the CSV carry the same grouping and sort.
     */
    #[Computed]
    public function groups(): Collection
    {
        return PaymentBatchLayout::lines(
            $this->paymentBatch,
            PaymentBatchLayout::cleanGroup($this->groupBy),
            PaymentBatchLayout::cleanLineSort($this->sortField),
            PaymentBatchLayout::cleanDirection($this->sortDirection),
            documentHealth: true,
        );
    }

    /** The query string the PDF links carry, so paper comes out as the screen shows. */
    public function layoutQuery(): array
    {
        return array_filter([
            'group' => PaymentBatchLayout::cleanGroup($this->groupBy) ?: null,
            'sort' => PaymentBatchLayout::cleanLineSort($this->sortField) ?: null,
            'dir' => $this->sortField && $this->sortDirection === 'desc' ? 'desc' : null,
        ]);
    }

    /**
     * Every line of the batch as CSV, grouped and sorted as on screen, with
     * the status totals underneath. The same grant as the screen: the route
     * has no mount() to carry it.
     */
    public function exportCsv(): StreamedResponse
    {
        $this->authorizeAbility('payments.batch');

        $batch = $this->paymentBatch;
        $groups = $this->groups();
        $summary = $this->summary();
        $groupBy = PaymentBatchLayout::cleanGroup($this->groupBy);
        $money = fn ($value) => number_format((float) $value, 2, '.', '');

        $filename = 'payment-batch-' . $batch->id . '-' . $batch->payment_date->format('Y-m-d') . '.csv';

        return new StreamedResponse(function () use ($batch, $groups, $summary, $groupBy, $money) {
            $out = fopen('php://output', 'w');
            // BOM so Excel reads UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [__('Payment Batch'), $batch->name]);
            fputcsv($out, [__('Status'), __($batch->getStatusLabel())]);
            fputcsv($out, [__('Payment Date'), $batch->payment_date->appDate()]);
            fputcsv($out, [__('Created By'), $batch->createdBy?->name ?? '']);
            if ($batch->approved_at) {
                fputcsv($out, [__('Approved By'), $batch->approvedBy?->name ?? '']);
                fputcsv($out, [__('Approved At'), $batch->approved_at->appDateTime()]);
            }
            if ($batch->notes) {
                fputcsv($out, [__('Notes'), $batch->notes]);
            }
            if ($groupBy) {
                fputcsv($out, [__('Grouped by'), PaymentBatchLayout::groupOptions()[$groupBy]]);
            }
            fputcsv($out, []);

            fputcsv($out, [
                __('Subcontractor'), __('Contact (Employee)'), __('Client'), __('Project'), __('Job Site'), __('Contract #'),
                __('Pays'), __('Method'), __('Phase'), __('Notes'), __('Status'), __('Amount'),
            ]);

            foreach ($groups as $group) {
                if ($group['label'] !== null) {
                    fputcsv($out, [
                        $group['sub'] ? $group['label'] . ' — ' . $group['sub'] : $group['label'],
                        trans_choice(':count item|:count items', $group['totals']['count'], ['count' => $group['totals']['count']]),
                    ]);
                }

                foreach ($group['rows'] as $item) {
                    fputcsv($out, [
                        $item->contract->subcontractor?->company_name ?? '',
                        $item->contract->subcontractorEmployee?->name ?? '',
                        $item->contract->project->client?->company_name ?? '',
                        $item->contract->project->project_name,
                        $item->contract->jobSite?->job_site_name ?? __('Project General'),
                        $item->contract->contract_number,
                        $item->getPaysLabel(),
                        __($item->getPaymentMethodLabel()),
                        $item->phase ?? '',
                        $item->notes ?? '',
                        __($item->getStatusLabel()),
                        $money($item->amount),
                    ]);
                }

                if ($group['label'] !== null) {
                    fputcsv($out, array_merge(
                        array_fill(0, 10, ''),
                        [__('Subtotal'), $money($group['totals']['total_amount'])],
                    ));
                    fputcsv($out, []);
                }
            }

            fputcsv($out, []);
            fputcsv($out, [__('Approved'), $money($summary['approved_amount'])]);
            fputcsv($out, [__('Pending'), $money($summary['pending_amount'])]);
            fputcsv($out, [__('Rejected'), $money($summary['rejected_amount'])]);
            fputcsv($out, [__('Total'), $money($summary['total_amount'])]);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function render()
    {
        $this->paymentBatch->load(['client', 'project', 'subcontractor', 'projectManager', 'supervisor', 'createdBy', 'approvedBy']);

        return view('livewire.payment-batch.payment-batch-show', [
            'groups' => $this->groups(),
            'items' => $this->groups()->flatMap(fn ($group) => $group['rows']),
        ])->layout('components.layouts.app');
    }
}
