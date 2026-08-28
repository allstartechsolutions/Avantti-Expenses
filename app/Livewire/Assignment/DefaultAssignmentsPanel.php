<?php

namespace App\Livewire\Assignment;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\DefaultAssignment;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\User;
use App\Services\BuyerDirectory;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Who work falls to here when nobody says otherwise.
 *
 * One component for all three tiers — the install (System Settings), a
 * project and a job site — because the only thing that differs between them
 * is which context row is written and which people are eligible. Three
 * near-identical screens is how they drift apart.
 *
 * The panel never *does* the assigning; it names the fallback the approver
 * sees pre-filled. Nothing here grants anybody anything: being named the
 * default buyer is a work list, not a permission, and the picker only offers
 * people who already hold `quotations.create` here precisely so that the
 * fallback cannot point at somebody who would hit a 403.
 */
class DefaultAssignmentsPanel extends Component
{
    use AuthorizesAbility;

    /** 'global' | 'project' | 'job_site' */
    public string $contextType = DefaultAssignment::CONTEXT_GLOBAL;

    public ?int $contextId = null;

    /** role_key => selected user id (empty string = nobody set at this level). */
    public array $choices = [];

    public function mount(string $contextType = DefaultAssignment::CONTEXT_GLOBAL, ?int $contextId = null): void
    {
        abort_unless(in_array($contextType, [
            DefaultAssignment::CONTEXT_GLOBAL,
            DefaultAssignment::CONTEXT_PROJECT,
            DefaultAssignment::CONTEXT_JOB_SITE,
        ], true), 404);

        $this->contextType = $contextType;
        $this->contextId = $contextId;

        $this->authorizeAbility('assignment-defaults.view', $this->scope());

        foreach (DefaultAssignment::ROLE_KEYS as $roleKey) {
            $this->choices[$roleKey] = (string) (DefaultAssignment::userIdAt(
                $roleKey,
                $this->contextType,
                (int) $this->contextId,
            ) ?? '');
        }
    }

    /**
     * The project or job site this panel is about — null at the install tier.
     *
     * Loaded fresh rather than held as a public property: a Livewire property
     * round-trips through the browser, and the scope an ability is checked
     * against must never be something the browser can rewrite.
     */
    protected function scope(): Project|JobSite|null
    {
        return match ($this->contextType) {
            DefaultAssignment::CONTEXT_PROJECT => Project::findOrFail($this->contextId),
            DefaultAssignment::CONTEXT_JOB_SITE => JobSite::findOrFail($this->contextId),
            default => null,
        };
    }

    public function canEdit(): bool
    {
        return $this->allowsAbility('assignment-defaults.edit', $this->scope());
    }

    /**
     * The people one default may name: whoever holds the ability that role
     * needs — `quotations.create` to be handed a round, `requisitions.approve`
     * to be asked for a decision.
     *
     * The list comes from the shared BuyerDirectory, which is also what the
     * requisition form, the approve dialog and the reassign control offer, so
     * a picker and the endpoint behind it can never disagree.
     *
     * @return Collection<int, User>
     */
    public function candidatesFor(string $roleKey): Collection
    {
        return $this->candidates[$roleKey] ??= app(BuyerDirectory::class)
            ->holdersOf(DefaultAssignment::abilityFor($roleKey), $this->scope());
    }

    /** Memo per render: the view asks twice for each role. */
    protected array $candidates = [];

    /**
     * What this level would fall back to if it named nobody.
     *
     * Shown beside an empty select so a project that inherits says so, rather
     * than looking unset. The install tier inherits from nothing.
     */
    #[Computed]
    public function inherited(): array
    {
        if ($this->contextType === DefaultAssignment::CONTEXT_GLOBAL) {
            return [];
        }

        $scope = $this->scope();
        $jobSite = $scope instanceof JobSite ? $scope : null;
        $project = $scope instanceof Project ? $scope : $jobSite?->project;

        $out = [];

        foreach (DefaultAssignment::ROLE_KEYS as $roleKey) {
            // Ask the tier above this one: a job site inherits from its
            // project, a project from the install.
            $out[$roleKey] = $jobSite
                ? DefaultAssignment::resolve($roleKey, null, $project)
                : DefaultAssignment::resolve($roleKey, null, null);
        }

        return $out;
    }

    /** The rows as stored at this exact level, for the audit line. */
    #[Computed]
    public function rows(): array
    {
        $out = [];

        foreach (DefaultAssignment::ROLE_KEYS as $roleKey) {
            $out[$roleKey] = DefaultAssignment::at($roleKey, $this->contextType, (int) $this->contextId);
        }

        return $out;
    }

    public function save(string $roleKey): void
    {
        $scope = $this->scope();

        $this->authorizeAbility('assignment-defaults.edit', $scope);

        abort_unless(in_array($roleKey, DefaultAssignment::ROLE_KEYS, true), 404);

        $chosen = $this->choices[$roleKey] ?? '';
        $userId = $chosen === '' ? null : (int) $chosen;

        // Never trust a user id from the browser: an id that is not on the
        // list this panel offered is refused, not saved. Otherwise the picker
        // is a suggestion and the endpoint is the real interface.
        if ($userId !== null && ! $this->candidatesFor($roleKey)->contains('id', $userId)) {
            $this->addError('choices.'.$roleKey, __('That person cannot raise a quotation round here.'));

            return;
        }

        DefaultAssignment::set($roleKey, $this->contextType, (int) $this->contextId, $userId, auth()->id());

        unset($this->rows, $this->inherited);
        $this->candidates = [];

        $this->resetErrorBag('choices.'.$roleKey);

        session()->flash('assignment-defaults-message', $userId === null
            ? __(':role now follows the level above.', ['role' => DefaultAssignment::roleLabel($roleKey)])
            : __(':role was saved.', ['role' => DefaultAssignment::roleLabel($roleKey)]));
    }

    public function render()
    {
        return view('livewire.assignment.default-assignments-panel');
    }
}
