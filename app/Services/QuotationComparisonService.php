<?php

namespace App\Services;

use App\Models\Quotation;
use Illuminate\Support\Collection;

/**
 * The comparison map (mapa comparativo).
 *
 * Brazilian practice is explicit that raw prices must not be compared as they
 * arrive: the offers are equalized first — freight, taxes and discounts folded
 * in, lines nobody quoted marked as such rather than counted as zero — and the
 * choice must then be justifiable, which is why the map also carries lead
 * time, payment terms and proposal validity.
 *
 * One shape is built here and used by both the screen and the PDF, so the two
 * can never disagree.
 */
class QuotationComparisonService
{
    public function build(Quotation $quotation): array
    {
        $quotation->loadMissing([
            'items',
            'quotationVendors.vendor',
            'quotationVendors.items',
            'quotationVendors.negotiations',
            'budgetItem',
        ]);

        // Only vendors who actually answered belong on the map; the ones still
        // waiting or who declined are reported beside it, not compared.
        $proposals = $quotation->quotationVendors
            ->filter(fn ($row) => $row->hasResponded())
            ->values();

        $columns = $this->columns($quotation, $proposals);
        $rows = $this->rows($quotation, $proposals);

        return [
            'quotation' => $quotation,
            'columns' => $columns,
            'rows' => $rows,
            'summary' => $this->summary($quotation, $columns, $rows),
            'awaiting' => $quotation->quotationVendors->where('status', 'invited')->values(),
            'declined' => $quotation->quotationVendors->where('status', 'declined')->values(),
        ];
    }

    /** One column per proposal, with its equalized total and its terms. */
    protected function columns(Quotation $quotation, Collection $proposals): Collection
    {
        $scopeLines = $quotation->items->count();

        $columns = $proposals->map(function ($row) use ($scopeLines) {
            return [
                'row' => $row,
                'vendor_name' => $row->vendor?->name ?? __('Unknown'),
                'subtotal' => $row->itemsSubtotal(),
                'freight' => (float) $row->freight_amount,
                'freight_type' => $row->freight_type,
                'tax' => (float) $row->tax_amount,
                'discount' => (float) $row->discount_amount,
                'total' => $row->equalizedTotal(),
                'lead_time_days' => $row->lead_time_days,
                'payment_terms' => $row->payment_terms,
                'valid_until' => $row->proposal_valid_until,
                'expired' => $row->proposalExpired(),
                'unavailable' => $row->unavailableCount(),
                'substitutes' => $row->substituteCount(),
                'unquoted' => $row->unquotedCount($scopeLines),
                'covers_scope' => $row->coversScope($scopeLines),
                'negotiated_rounds' => $row->negotiationRounds(),
                'opening_total' => $row->openingTotal(),
                'negotiated_saving' => $row->negotiatedSaving(),
                'is_awarded' => $row->status === 'awarded',
                'is_benchmark' => false,
                'is_lowest' => false,
                'delta_to_lowest' => 0.0,
            ];
        });

        // The cheapest complete offer is the benchmark. A proposal that leaves
        // lines unquoted is not cheaper — it is incomplete, and saying so beats
        // crowning it on a total that covers less work.
        $comparable = $columns->filter(fn ($column) => $column['covers_scope'] && ! $column['expired']);
        $benchmark = $comparable->isNotEmpty() ? $comparable : $columns;
        $lowest = $benchmark->min('total');

        $benchmarkIds = $benchmark->pluck('row.id')->all();

        return $columns->map(function ($column) use ($lowest, $benchmarkIds) {
            $column['is_benchmark'] = in_array($column['row']->id, $benchmarkIds, true);
            $column['is_lowest'] = $lowest !== null
                && $column['is_benchmark']
                && $this->sameMoney($column['total'], $lowest);
            $column['delta_to_lowest'] = $lowest === null ? 0.0 : round($column['total'] - $lowest, 2);

            return $column;
        });
    }

    /** One row per scope line, with a cell per proposal. */
    protected function rows(Quotation $quotation, Collection $proposals): Collection
    {
        return $quotation->items->map(function ($item) use ($proposals) {
            $cells = $proposals->map(function ($row) use ($item) {
                $priced = $row->items->firstWhere('quotation_item_id', $item->id);

                if (! $priced) {
                    return [
                        'vendor_row_id' => $row->id,
                        'state' => 'unquoted',
                        'unit_price' => null,
                        'total' => null,
                        'is_best' => false,
                        'brand' => null,
                        'spec' => null,
                        'notes' => null,
                    ];
                }

                return [
                    'vendor_row_id' => $row->id,
                    'state' => $priced->is_unavailable ? 'unavailable' : 'priced',
                    'unit_price' => $priced->is_unavailable ? null : (float) $priced->unit_price,
                    'total' => $priced->is_unavailable ? null : (float) $priced->total_amount,
                    'is_best' => false,
                    'brand' => $priced->offered_brand,
                    'spec' => $priced->offered_spec,
                    'notes' => $priced->notes,
                ];
            });

            // Best price on the line, ignoring the cells that are not offers.
            $prices = $cells->where('state', 'priced')->pluck('unit_price')->filter(fn ($p) => $p !== null);
            $best = $prices->isNotEmpty() ? $prices->min() : null;

            $cells = $cells->map(function ($cell) use ($best) {
                $cell['is_best'] = $best !== null
                    && $cell['state'] === 'priced'
                    && $this->sameMoney($cell['unit_price'], $best);

                return $cell;
            });

            $lineTotals = $cells->where('state', 'priced')->pluck('total')->filter(fn ($t) => $t !== null);

            return [
                'item' => $item,
                'cells' => $cells,
                'best_unit_price' => $best,
                'spread' => $lineTotals->count() > 1
                    ? round($lineTotals->max() - $lineTotals->min(), 2)
                    : 0.0,
                'quoted_by' => $cells->where('state', 'priced')->count(),
            ];
        });
    }

    /** The figures the buyer is judged on: the saving, and the budget. */
    protected function summary(Quotation $quotation, Collection $columns, Collection $rows): array
    {
        // The saving has to be measured inside the set the winner was chosen
        // from. Comparing the winner against an expired or half-quoted offer
        // would advertise a saving nobody can actually take.
        $comparable = $columns->where('is_benchmark', true);
        $totals = ($comparable->isNotEmpty() ? $comparable : $columns)->pluck('total');
        $lowestColumn = $columns->firstWhere('is_lowest', true);

        // Cherry-picking every line's best price: what the round could cost if
        // it were split across vendors. Phase 6 decides whether it is.
        $splitTotal = $rows->sum(function ($row) {
            $best = $row['cells']->where('state', 'priced')->pluck('total')->filter(fn ($t) => $t !== null);

            return $best->isNotEmpty() ? $best->min() : 0;
        });

        $budgetAmount = $quotation->budgetItem ? (float) $quotation->budgetItem->budgeted_amount : null;
        $lowestTotal = $lowestColumn['total'] ?? ($totals->isNotEmpty() ? $totals->min() : null);

        return [
            'proposals' => $columns->count(),
            'lowest' => $lowestTotal,
            'highest' => $totals->isNotEmpty() ? $totals->max() : null,
            'saving_vs_highest' => $totals->count() > 1 ? round($totals->max() - $totals->min(), 2) : 0.0,
            'comparable' => $comparable->count(),
            'lowest_vendor' => $lowestColumn['vendor_name'] ?? null,
            'split_total' => round((float) $splitTotal, 2),
            'split_saving' => $lowestTotal !== null ? round($lowestTotal - (float) $splitTotal, 2) : 0.0,
            'budget_amount' => $budgetAmount,
            'budget_delta' => $budgetAmount !== null && $lowestTotal !== null
                ? round($lowestTotal - $budgetAmount, 2)
                : null,
            'negotiated_saving' => round((float) $columns->sum('negotiated_saving'), 2),
            'meets_minimum' => $quotation->meetsProposalMinimum(),
            'meets_norm' => $quotation->meetsProposalNorm(),
            'expired' => $columns->where('expired', true)->count(),
            'incomplete' => $columns->where('covers_scope', false)->count(),
        ];
    }

    /** Money compared in cents: 0.1 + 0.2 is not 0.3 in floating point. */
    protected function sameMoney(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        return (int) round($a * 100) === (int) round($b * 100);
    }
}
