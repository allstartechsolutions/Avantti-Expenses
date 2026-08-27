<?php

namespace App\Livewire\Approval;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Approval;
use App\Models\Project;
use App\Services\Collaboration\ApprovalSeeder;
use Livewire\Component;

/**
 * "Gerar aprovações do orçamento" — turn budget lines into draft approvals.
 *
 * The screen proposes and the person decides. Lines arrive pre-ticked where a
 * signal suggests them, every tick can be changed, and what is confirmed
 * becomes a **draft** — nothing is submitted to anybody until its reviewers
 * are named.
 */
class ApprovalSeedFromBudget extends Component
{
    use AuthorizesAbility;

    public Project $project;

    /** budget item id => ticked. */
    public array $selected = [];

    /** budget item id => approval type. */
    public array $types = [];

    /** The threshold, in the currency's major unit, as typed. */
    public ?string $threshold = null;

    public function mount(Project $project): void
    {
        $this->authorizeAbility('approvals.seed', $project);

        $this->project = $project;

        $this->threshold = $project->approval_seed_threshold !== null
            ? (string) ($project->approval_seed_threshold / 100)
            : null;

        $this->preselect();
    }

    /** Tick what the signals suggest, and offer each line a type. */
    protected function preselect(): void
    {
        foreach ($this->candidates() as $row) {
            $id = $row['item']->id;

            $this->selected[$id] = $row['suggested'] && ! $row['existing'];
            $this->types[$id] = $row['type'];
        }
    }

    /**
     * The budget scan, computed once per request.
     *
     * `mount()` and `render()` both want it, and every tick of Select all or
     * Back to suggested wanted it again — so one page load scanned up to 500
     * budget lines, with their existing-approval lookup, several times over.
     */
    protected ?\Illuminate\Support\Collection $candidateCache = null;

    protected function candidates(): \Illuminate\Support\Collection
    {
        return $this->candidateCache ??= app(ApprovalSeeder::class)->candidates($this->project);
    }

    /** After anything that changes what the scan would return. */
    protected function forgetCandidates(): void
    {
        $this->candidateCache = null;
    }

    /*
    |---------------------------------------------------------------------------
    | Actions
    |---------------------------------------------------------------------------
    */

    /**
     * Change the threshold and re-tick.
     *
     * Saved on the project, because it is a setting rather than a search: the
     * next person to seed this project starts from the same place.
     */
    public function applyThreshold(): void
    {
        $this->authorizeAbility('approvals.seed', $this->project);

        $this->validate([
            'threshold' => 'nullable|numeric|min:0',
        ]);

        $this->project->update([
            'approval_seed_threshold' => $this->threshold === null || $this->threshold === ''
                ? null
                : (int) round((float) $this->threshold * 100),
        ]);

        $this->project->refresh();
        $this->forgetCandidates();
        $this->preselect();
    }

    public function selectAll(): void
    {
        foreach ($this->candidates() as $row) {
            if (! $row['existing']) {
                $this->selected[$row['item']->id] = true;
            }
        }
    }

    public function selectNone(): void
    {
        foreach (array_keys($this->selected) as $id) {
            $this->selected[$id] = false;
        }
    }

    /** Back to what the signals suggest, after any amount of ticking. */
    public function resetToSuggested(): void
    {
        $this->preselect();
    }

    public function generate(): void
    {
        $this->authorizeAbility('approvals.seed', $this->project);

        $ids = collect($this->selected)
            ->filter()
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            $this->addError('selected', __('collaboration.help.nothing_ticked_there_nothing_create'));

            return;
        }

        $created = app(ApprovalSeeder::class)->seed($this->project, $ids, $this->types, auth()->user());

        session()->flash('message', trans_choice('collaboration.count.draft_approval_created_draft',
            $created->count(),
            ['count' => $created->count()],
        ));

        $this->redirectRoute('projects.approvals', $this->project, navigate: true);
    }

    public function render()
    {
        $candidates = $this->candidates();

        return view('livewire.approval.approval-seed', [
            'candidates' => $candidates,
            'suggestedCount' => $candidates->where('suggested', true)->count(),
            'coveredCount' => $candidates->filter(fn (array $r) => $r['existing'] !== null)->count(),
            'selectedCount' => collect($this->selected)->filter()->count(),
            'typeOptions' => Approval::typeOptions(),
        ])->layout('components.layouts.app');
    }
}
