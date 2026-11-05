<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * A project's money in one place, worked out the way each lot's overview
 * works it out: contract value − expenses − contracts = profit or loss
 * (client requests 18 and 19, Nov 2026).
 *
 * - Contract value: the project's adjusted contract value (approved change
 *   orders included), per lot the lot amount plus its approved change orders.
 * - Expenses: every expense filed on the project or one of its lots — the
 *   same set the lot overview totals.
 * - Contracts: committed contracts at their adjusted amount (the contract's
 *   own change orders included), with what has been paid against them.
 *
 * Everything is summed in grouped queries, so a page of projects costs a
 * handful of queries rather than a handful per row.
 */
class ProjectFinancials
{
    /**
     * Totals for several projects at once, keyed by project id.
     *
     * @return array<int, array{contract_value: float, expenses: float, contracts: float, paid: float, unpaid: float, costs: float, balance: float}>
     */
    public static function forProjects(Collection $projects): array
    {
        if ($projects->isEmpty()) {
            return [];
        }

        $projects = new EloquentCollection($projects->all());
        $ids = $projects->pluck('id');
        $projects->loadMissing(['jobSites:id,project_id,job_amount', 'changeOrders:id,project_id,job_site_id,amount,status']);

        $expenses = Expense::whereIn('project_id', $ids)
            ->selectRaw('project_id, SUM(total_amount) as cents')
            ->groupBy('project_id')
            ->pluck('cents', 'project_id');

        $contracts = self::contracts($ids)->groupBy('project_id');

        $totals = [];

        foreach ($projects as $project) {
            $rows = $contracts->get($project->id, collect());
            $totals[$project->id] = self::figures(
                $project->getAdjustedContractValue(),
                ((int) ($expenses[$project->id] ?? 0)) / 100,
                $rows,
            );
        }

        return $totals;
    }

    /**
     * One project's totals and the same figures for each of its lots, plus
     * a "Job Site (general)" row for what is filed on no lot. The rows add
     * up to the totals: the general row's contract value is whatever of the
     * project's value the lots do not account for.
     */
    public static function forProject(Project $project): array
    {
        // Its own copy, so the caller's model is not handed partial relations.
        $totals = self::forProjects(Project::whereKey($project->id)->get())[$project->id];

        $lots = JobSite::where('project_id', $project->id)
            ->with('changeOrders:id,job_site_id,amount,status')
            ->get(['id', 'project_id', 'job_site_name', 'job_amount']);

        $expenses = Expense::where('project_id', $project->id)
            ->selectRaw('job_site_id, SUM(total_amount) as cents')
            ->groupBy('job_site_id')
            ->pluck('cents', 'job_site_id');

        $contracts = self::contracts(collect([$project->id]))->groupBy(fn ($c) => $c->job_site_id ?? 0);

        $rows = $lots
            ->sortBy(fn (JobSite $lot) => $lot->job_site_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (JobSite $lot) => ['id' => $lot->id, 'name' => $lot->job_site_name] + self::figures(
                $lot->getAdjustedContractValue(),
                ((int) ($expenses[$lot->id] ?? 0)) / 100,
                $contracts->get($lot->id, collect()),
            ))
            ->values();

        $general = self::figures(
            round($totals['contract_value'] - $rows->sum('contract_value'), 2),
            ((int) ($expenses[''] ?? $expenses[0] ?? 0)) / 100,
            $contracts->get(0, collect()),
        );

        if (abs($general['contract_value']) >= 0.005 || $general['expenses'] > 0 || $general['contracts'] > 0) {
            $rows->push(['id' => null, 'name' => null] + $general);
        }

        return ['totals' => $totals, 'lots' => $rows, 'lot_count' => $lots->count()];
    }

    /**
     * Receivables as the client reads them (request 16, Nov 2026): every lot
     * with an open subcontractor contract (committed, not yet paid in full),
     * its value less what its contracts cost, and on those contracts what has
     * been paid and what is still to pay. They do not record client payments
     * in the system, so nothing is taken off for money received.
     *
     * Back charges reach this through the contracts' adjusted amounts today;
     * when they become their own deductions they are subtracted here, once.
     *
     * @param  array<int>|null  $projectIds  null for every project
     */
    public static function openLots(?array $projectIds = null): array
    {
        $contracts = Contract::committed()
            ->whereNotNull('job_site_id')
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->withSum('changeOrders as change_orders_cents', 'amount')
            ->withSum('payments as paid_cents', 'amount')
            ->get(['id', 'project_id', 'job_site_id', 'amount', 'status']);

        $openLotIds = $contracts->whereNotIn('status', ['paid', 'cancelled'])->pluck('job_site_id')->unique()->values();

        $lots = JobSite::whereIn('id', $openLotIds)
            ->with('changeOrders:id,job_site_id,amount,status')
            ->get(['id', 'project_id', 'job_amount']);

        $value = round($lots->sum(fn (JobSite $lot) => $lot->getAdjustedContractValue()), 2);
        $figures = self::figures($value, 0, $contracts->whereIn('job_site_id', $openLotIds));

        return [
            'lots' => $lots->count(),
            'job_sites' => $lots->pluck('project_id')->unique()->count(),
            'value' => $value,
            'contracts' => $figures['contracts'],
            'paid' => $figures['paid'],
            'to_pay' => $figures['unpaid'],
            'receivable' => round($value - $figures['contracts'], 2),
        ];
    }

    /** Committed contracts of these projects, with their change orders and payments summed. */
    protected static function contracts(Collection $projectIds): Collection
    {
        return Contract::committed()
            ->whereIn('project_id', $projectIds)
            ->withSum('changeOrders as change_orders_cents', 'amount')
            ->withSum('payments as paid_cents', 'amount')
            ->get(['id', 'project_id', 'job_site_id', 'amount', 'status']);
    }

    protected static function figures(float $contractValue, float $expenses, Collection $contracts): array
    {
        $adjusted = round($contracts->sum(fn ($c) => (float) $c->amount + ((int) $c->change_orders_cents) / 100), 2);
        $paid = round($contracts->sum(fn ($c) => ((int) $c->paid_cents) / 100), 2);
        $expenses = round($expenses, 2);
        $costs = round($expenses + $adjusted, 2);

        return [
            'contract_value' => round($contractValue, 2),
            'expenses' => $expenses,
            'contracts' => $adjusted,
            'paid' => $paid,
            'unpaid' => round($adjusted - $paid, 2),
            'costs' => $costs,
            'balance' => round($contractValue - $costs, 2),
        ];
    }
}
