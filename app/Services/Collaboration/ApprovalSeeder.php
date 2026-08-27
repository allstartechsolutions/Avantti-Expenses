<?php

namespace App\Services\Collaboration;

use App\Models\Approval;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a project's orçamento into a list of approvals worth raising.
 *
 * Replaces the US spec-book-driven submittal register, which does not exist in
 * Brazilian practice: there is no specification book to parse, but there is
 * always a budget, and it is already broken down the way the work is.
 *
 * **This proposes; it never publishes.** Every line comes back with a
 * suggestion and a reason, a person ticks what they want, and what they
 * confirm is created as a **draft**. Nothing is submitted to anybody until
 * somebody names its reviewers.
 */
class ApprovalSeeder
{
    /** Beyond this, a budget is not a screen anybody can read. */
    public const MAX_LINES = 500;

    /**
     * Every line of the project's budgets, with whether it is suggested and why.
     *
     * Two signals, deliberately:
     *
     * - the line's value passes the project's threshold, when one is set;
     * - the line is flagged `requires_approval`, copied forward from the cost
     *   code template or set by hand.
     *
     * Value alone is the wrong filter. The biggest lines in a BR orçamento are
     * concreto, aço and alvenaria — commodities with an NBR, approved by
     * certificate if at all. What needs a review cycle is spec-sensitive and
     * often mid-value: porcelanatos, louças e metais, esquadrias, vidros.
     *
     * @return Collection<int, array{item: BudgetItem, suggested: bool, reasons: array<int, string>, type: string, existing: ?Approval}>
     */
    public function candidates(Project $project): Collection
    {
        $budgetIds = Budget::query()
            ->where('project_id', $project->id)
            ->orWhereIn('job_site_id', $project->jobSites()->pluck('id'))
            ->pluck('id');

        if ($budgetIds->isEmpty()) {
            return collect();
        }

        // Lines already covered, so the screen can say so rather than offering
        // to raise a second approval for the same thing.
        $existing = Approval::query()
            ->where('project_id', $project->id)
            ->whereNotNull('budget_item_id')
            ->get()
            ->keyBy('budget_item_id');

        return BudgetItem::query()
            // `budget` is read for every seeded line, inside the transaction
            // that holds the number sequence's row lock. Lazy-loading it there
            // held that lock across hundreds of round trips.
            ->with('budget:id,job_site_id')
            ->whereIn('budget_id', $budgetIds)
            // A parent line is a heading; its children carry the work.
            ->whereNotIn('id', function ($query) use ($budgetIds) {
                $query->select('parent_id')
                    ->from('budget_items')
                    ->whereIn('budget_id', $budgetIds)
                    ->whereNotNull('parent_id');
            })
            ->orderBy('code')
            ->limit(self::MAX_LINES)
            ->get()
            ->map(function (BudgetItem $item) use ($project, $existing) {
                $reasons = [];

                if ($item->requires_approval) {
                    $reasons[] = 'flagged';
                }

                // Both sides in cents. `budgeted_amount` has an accessor that
                // divides by 100, so reading it here would compare a figure in
                // reais against a threshold in centavos and flag almost
                // nothing — the raw column is the one to use.
                if ($project->approval_seed_threshold !== null
                    && (int) $item->getRawOriginal('budgeted_amount') >= (int) $project->approval_seed_threshold) {
                    $reasons[] = 'threshold';
                }

                return [
                    'item' => $item,
                    'suggested' => $reasons !== [],
                    'reasons' => $reasons,
                    'type' => $this->typeFor($item),
                    'existing' => $existing->get($item->id),
                ];
            });
    }

    /**
     * What kind of approval a line probably wants.
     *
     * The flag's own `default_approval_type` wins where it is set — that is a
     * decision somebody has already made. Otherwise the code's wording is a
     * fair guess, and the person seeding can change it: concreto and aço are
     * approved by certificate, esquadrias by shop drawing, finishes by sample.
     *
     * A guess is offered so nobody picks a type forty times; it is not a claim
     * to be right.
     */
    public function typeFor(BudgetItem $item): string
    {
        if ($item->default_approval_type
            && in_array($item->default_approval_type, Approval::TYPES, true)) {
            return $item->default_approval_type;
        }

        $text = mb_strtolower($item->name.' '.$item->code);

        $certificate = ['concreto', 'aço', 'aco', 'impermeabiliz', 'cimento', 'steel', 'concrete', 'waterproof'];
        $shopDrawing = ['esquadria', 'estrutura metálica', 'estrutura metalica', 'pré-moldado', 'pre-moldado',
            'guarda-corpo', 'window', 'door frame', 'structural steel', 'precast'];

        foreach ($certificate as $needle) {
            if (str_contains($text, $needle)) {
                return Approval::TYPE_CERTIFICATE;
            }
        }

        foreach ($shopDrawing as $needle) {
            if (str_contains($text, $needle)) {
                return Approval::TYPE_SHOP_DRAWING;
            }
        }

        return Approval::TYPE_MATERIAL;
    }

    /**
     * Create drafts for the lines somebody confirmed.
     *
     * Ids are re-checked against the project rather than trusted: the list
     * came from a screen, and a budget line from somewhere else must not be
     * costed here. A line that already has an approval is skipped rather than
     * duplicated.
     *
     * @param  array<int, int>  $budgetItemIds
     * @param  array<int, string>  $types  budget item id => approval type
     * @return Collection<int, Approval>
     */
    public function seed(Project $project, array $budgetItemIds, array $types, User $actor): Collection
    {
        $candidates = $this->candidates($project)->keyBy(fn (array $row) => $row['item']->id);

        return DB::transaction(function () use ($candidates, $budgetItemIds, $types, $project, $actor) {
            $created = collect();

            foreach ($budgetItemIds as $id) {
                $row = $candidates->get((int) $id);

                // Not on this project's budget, or already covered.
                if (! $row || $row['existing']) {
                    continue;
                }

                $item = $row['item'];
                $type = $types[$id] ?? $row['type'];

                if (! in_array($type, Approval::TYPES, true)) {
                    $type = $row['type'];
                }

                $approval = Approval::create([
                    'project_id' => $project->id,
                    // The budget line may belong to a job site's own budget;
                    // the approval follows it there.
                    'job_site_id' => $item->budget?->job_site_id,
                    'title' => trim($item->code.' '.$item->name),
                    'type' => $type,
                    'budget_item_id' => $item->id,
                    'status' => Approval::DRAFT,
                    'created_by_id' => $actor->id,
                ]);

                $approval->logActivity(\App\Models\Collaboration\ActivityLogEntry::CREATED, [
                    'seeded_from_budget' => true,
                    'budget_item' => $item->code,
                ]);

                $created->push($approval);
            }

            return $created;
        });
    }
}
