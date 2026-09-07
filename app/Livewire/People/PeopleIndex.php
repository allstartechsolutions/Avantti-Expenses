<?php

namespace App\Livewire\People;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Person;
use App\Models\SubcontractorEmployee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everybody known at more than one subcontractor, and the rows that look
 * as if they should be. See docs/people-module.md.
 */
class PeopleIndex extends Component
{
    use AuthorizesAbility;
    use WithPagination;

    public string $activeTab = 'people';

    public string $search = '';

    public function mount(): void
    {
        $this->authorizeAbility('people.view');
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['people', 'suggestions'], true) ? $tab : 'people';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Link every row of one suggested group as the same person. The ids
     * came from the browser: they are re-grouped server-side and only what
     * still matches is linked.
     *
     * @param  array<int, int>  $employeeIds
     */
    public function linkGroup(array $employeeIds, ?string $reason = null): void
    {
        $this->authorizeAbility('people.link');

        $rows = SubcontractorEmployee::with('person')
            ->whereIn('id', array_map('intval', $employeeIds))
            ->get();

        $companies = $rows->pluck('subcontractor_id')->unique();

        if ($rows->count() < 2 || $companies->count() < 2) {
            session()->flash('error', __('Nothing to link — these records were changed in another session. The list has been refreshed.'));

            return;
        }

        $first = $rows->shift();

        foreach ($rows as $row) {
            if ($row->subcontractor_id === $first->subcontractor_id) {
                continue;   // two rows at one company are two people, or one twice — never linked here
            }

            $first->linkWith($row, Auth::user(), $reason);
            $first->refresh();
        }

        session()->flash('message', __('Linked as one person: :name.', ['name' => $first->person?->name ?? $first->name]));
    }

    /**
     * Groups of rows at different companies that share a tax id, a phone or
     * an e-mail and are not already the same person. A shared name alone is
     * not offered here — the vendor page suggests it when the row is typed,
     * where the person adding it can judge; on a list it would be noise.
     *
     * @return Collection<int, array{reason: string, key: string, rows: Collection<int, SubcontractorEmployee>}>
     */
    protected function suggestedGroups(): Collection
    {
        $employees = SubcontractorEmployee::with(['subcontractor', 'person.employees.subcontractor'])
            ->orderBy('name')
            ->get();

        $groups = collect();

        foreach (['tax_id' => fn ($e) => Person::normalizeTaxId($e->tax_id),
            'phone' => fn ($e) => Person::normalizePhone($e->phone),
            'email' => fn ($e) => Person::normalizeEmail($e->email)] as $reason => $key) {
            $employees
                ->groupBy($key)
                ->filter(fn ($rows, $k) => $k !== '' && $rows->pluck('subcontractor_id')->unique()->count() > 1)
                ->each(function ($rows, $k) use ($reason, &$groups) {
                    // Already the same person, every one of them: nothing to suggest.
                    $people = $rows->pluck('person_id')->unique();
                    if ($people->count() === 1 && $people->first() !== null) {
                        return;
                    }

                    $groups->push(['reason' => $reason, 'key' => (string) $k, 'rows' => $rows->values()]);
                });
        }

        // The same rows can match on two keys; keep the strongest one.
        return $groups
            ->unique(fn ($group) => $group['rows']->pluck('id')->sort()->join('-'))
            ->values();
    }

    public function render()
    {
        $term = trim($this->search);

        $people = Person::query()
            ->with(['employees.subcontractor', 'employees.linkedBy'])
            ->withCount(['employees', 'contracts'])
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhereHas('employees', fn ($e) => $e->where('name', 'like', $like)
                            ->orWhere('tax_id', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhereHas('subcontractor', fn ($v) => $v->where('name', 'like', $like)));
                });
            })
            ->orderBy('name')
            ->paginate(25);

        $suggestions = $this->activeTab === 'suggestions' ? $this->suggestedGroups() : collect();

        $counts = [
            'people' => Person::count(),
            'multiTaxId' => Person::with('employees')->get()->filter(fn (Person $p) => $p->distinctTaxIds()->count() > 1)->count(),
            'suggestions' => $this->activeTab === 'suggestions' ? $suggestions->count() : $this->suggestedGroups()->count(),
        ];

        return view('livewire.people.people-index', [
            'people' => $people,
            'suggestions' => $suggestions,
            'counts' => $counts,
        ])->layout('components.layouts.app');
    }
}
