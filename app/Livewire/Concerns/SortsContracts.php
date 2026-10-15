<?php

namespace App\Livewire\Concerns;

use App\Services\PaymentBatchLayout;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Column sorting, grouping and the totals rows for the contract payment
 * tables (/contract-payments, its PDF and the payment batch screen). Paid
 * and balance are derived per row and lots sort in natural order, so all of
 * it works on a loaded collection rather than in SQL.
 */
trait SortsContracts
{
    /** Column the table is sorted by; empty keeps the project / job site grouping. */
    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDirection = 'asc';

    public const SORTABLE = ['subcontractor', 'project', 'job_site', 'contract', 'amount', 'paid', 'balance'];

    /**
     * Sort by a column; clicking it again flips the direction. Money columns
     * start with the largest figure, text columns with A. Returns false for a
     * column that is not sortable.
     */
    protected function applySort(string $field): bool
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return false;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($field, ['amount', 'paid', 'balance'], true) ? 'desc' : 'asc';
        }

        return true;
    }

    protected function isSorted(): bool
    {
        return in_array($this->sortField, self::SORTABLE, true);
    }

    /**
     * The totals row: what the listed contracts add up to, column by column.
     * Expects total_paid_cents / change_orders_total_cents summed.
     */
    protected function contractTotals(Collection $contracts): array
    {
        $amount = round($contracts->sum(fn ($c) => (float) $c->amount), 2);
        $changeOrders = round($contracts->sum(fn ($c) => ($c->change_orders_total_cents ?? 0) / 100), 2);
        $paid = round($contracts->sum(fn ($c) => ($c->total_paid_cents ?? 0) / 100), 2);

        return [
            'count' => $contracts->count(),
            'amount' => $amount,
            'change_orders' => $changeOrders,
            'adjusted' => round($amount + $changeOrders, 2),
            'paid' => $paid,
            'balance' => round($amount + $changeOrders - $paid, 2),
        ];
    }

    /**
     * Expects project, jobSite and subcontractor loaded and total_paid_cents /
     * change_orders_total_cents summed. Project-level contracts (no job site)
     * always come after the lots, and contracts with no subcontractor after
     * the rest, whichever the direction.
     */
    protected function sortContracts(Collection $contracts): Collection
    {
        if (! $this->isSorted()) {
            return $contracts;
        }

        $descending = $this->sortDirection === 'desc';
        $paid = fn ($c) => ($c->total_paid_cents ?? 0) / 100;
        $balance = fn ($c) => round($c->amount + ($c->change_orders_total_cents ?? 0) / 100 - $paid($c), 2);

        return $contracts->sort(function ($a, $b) use ($descending, $paid, $balance) {
            if ($this->sortField === 'job_site' && ($a->jobSite === null) !== ($b->jobSite === null)) {
                return $a->jobSite === null ? 1 : -1;
            }

            if ($this->sortField === 'subcontractor' && ($a->subcontractor === null) !== ($b->subcontractor === null)) {
                return $a->subcontractor === null ? 1 : -1;
            }

            $result = match ($this->sortField) {
                'subcontractor' => strnatcasecmp($a->subcontractor?->company_name ?? '', $b->subcontractor?->company_name ?? ''),
                'project' => strnatcasecmp($a->project?->project_name ?? '', $b->project?->project_name ?? ''),
                'job_site' => strnatcasecmp($a->jobSite?->job_site_name ?? '', $b->jobSite?->job_site_name ?? ''),
                'contract' => strnatcasecmp($a->contract_number, $b->contract_number),
                'amount' => $a->amount <=> $b->amount,
                'paid' => $paid($a) <=> $paid($b),
                'balance' => $balance($a) <=> $balance($b),
            };

            return $descending ? -$result : $result;
        })->values();
    }

    /**
     * Sorted contracts in their groups (client, project, job site,
     * subcontractor), the groups in reading order. Expects what
     * sortContracts() expects, plus project.client.
     */
    protected function groupContracts(Collection $contracts, string $groupBy): Collection
    {
        return PaymentBatchLayout::arrange($this->sortContracts($contracts), $groupBy, fn ($contract) => $contract);
    }

    /**
     * A heading for each group with a row among $shownIds, keyed by its first
     * row there. The figures are the whole group's, and a group that began
     * on an earlier page says so.
     */
    protected function groupHeaders(Collection $groups, Collection $shownIds): array
    {
        $headers = [];

        foreach ($groups as $group) {
            $ids = $group['rows']->pluck('id');
            $first = $ids->first(fn ($id) => $shownIds->contains($id));

            if ($first !== null && $group['label'] !== null) {
                $headers[$first] = [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'sub' => $group['sub'],
                    'continued' => $ids->first() !== $first,
                    'totals' => $this->contractTotals($group['rows']),
                ];
            }
        }

        return $headers;
    }
}
