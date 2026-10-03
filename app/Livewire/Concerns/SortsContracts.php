<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Column sorting for the contract payment tables (/contract-payments and the
 * payment batch screen). Paid and balance are derived per row and lots sort
 * in natural order, so the sort runs on a loaded collection rather than in SQL.
 */
trait SortsContracts
{
    /** Column the table is sorted by; empty keeps the project / job site grouping. */
    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDirection = 'asc';

    public const SORTABLE = ['job_site', 'contract', 'amount', 'paid', 'balance'];

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
     * Expects jobSite loaded and total_paid_cents / change_orders_total_cents
     * summed. Project-level contracts (no job site) always come after the
     * lots, whichever the direction.
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

            $result = match ($this->sortField) {
                'job_site' => strnatcasecmp($a->jobSite?->job_site_name ?? '', $b->jobSite?->job_site_name ?? ''),
                'contract' => strnatcasecmp($a->contract_number, $b->contract_number),
                'amount' => $a->amount <=> $b->amount,
                'paid' => $paid($a) <=> $paid($b),
                'balance' => $balance($a) <=> $balance($b),
            };

            return $descending ? -$result : $result;
        })->values();
    }
}
