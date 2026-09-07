<?php

namespace App\Livewire\People;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Contract;
use App\Models\Person;
use App\Models\SubcontractorEmployee;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * One person: every company they have been at, the name and tax id they
 * used there, and every contract any of those rows was the contact on.
 * This is the "every contract this person was involved in" report.
 */
class PersonShow extends Component
{
    use AuthorizesAbility;

    public Person $person;

    // Edit dialog: the person's own name and notes
    public string $person_name = '';

    public string $person_notes = '';

    public function validationAttributes(): array
    {
        return [
            'person_name' => __('name'),
            'person_notes' => __('notes'),
        ];
    }

    public function mount(Person $person): void
    {
        $this->authorizeAbility('people.view');

        $this->person = $person;
    }

    public function startEdit(): void
    {
        $this->authorizeAbility('people.link');

        $this->person_name = $this->person->name;
        $this->person_notes = $this->person->notes ?? '';
        $this->resetValidation();
        $this->dispatch('open-modal', 'edit-person-modal');
    }

    public function cancelEdit(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', 'edit-person-modal');
    }

    public function savePerson(): void
    {
        $this->authorizeAbility('people.link');

        $this->validate([
            'person_name' => 'required|string|max:255',
            'person_notes' => 'nullable|string|max:2000',
        ]);

        $this->person->update([
            'name' => trim($this->person_name),
            'notes' => trim($this->person_notes) ?: null,
        ]);

        $this->cancelEdit();

        session()->flash('message', __('Person updated.'));
    }

    /** Take one company's row off this person. The page may cease to exist as a result. */
    public function unlinkEmployee(int $employeeId)
    {
        $this->authorizeAbility('people.link');

        $employee = SubcontractorEmployee::where('id', $employeeId)
            ->where('person_id', $this->person->id)
            ->firstOrFail();

        $employee->unlink();

        if (! Person::whereKey($this->person->id)->exists()) {
            session()->flash('message', __(':name is no longer linked to any other record, so the person page is gone.', ['name' => $employee->name]));

            return redirect()->route('people.index');
        }

        session()->flash('message', __(':name is no longer linked to this person.', ['name' => $employee->name]));
    }

    public function render()
    {
        $this->person->load(['createdBy', 'employees.subcontractor', 'employees.linkedBy']);

        $employees = $this->person->employees
            ->sortBy([
                fn ($a, $b) => ($b->ended_at === null) <=> ($a->ended_at === null),
                fn ($a, $b) => ($b->started_at?->getTimestamp() ?? 0) <=> ($a->started_at?->getTimestamp() ?? 0),
            ])
            ->values();

        $user = Auth::user();

        $contracts = Contract::query()
            ->visibleTo($user)
            ->whereIn('subcontractor_employee_id', $employees->pluck('id'))
            ->with(['project', 'jobSite', 'subcontractor', 'subcontractorEmployee'])
            ->orderByDesc('start_date')
            ->get();

        $contractTotals = [
            'count' => $contracts->count(),
            'amount' => $contracts->sum(fn (Contract $c) => $c->amount),
            'adjusted' => $contracts->sum(fn (Contract $c) => $c->getAdjustedAmount()),
            'paid' => $contracts->sum(fn (Contract $c) => $c->getAmountPaid()),
            'open' => $contracts->where('status', 'active')->count() + $contracts->where('status', 'partially_paid')->count(),
        ];

        $taxIds = $this->person->distinctTaxIds();

        return view('livewire.people.person-show', [
            'employees' => $employees,
            'contracts' => $contracts,
            'contractTotals' => $contractTotals,
            'taxIds' => $taxIds,
            'confined' => $user->isConfined(),
            'canSeeMoney' => $this->allowsMoney(),
        ])->layout('components.layouts.app');
    }
}
