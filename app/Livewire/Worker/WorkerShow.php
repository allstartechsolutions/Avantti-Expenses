<?php

namespace App\Livewire\Worker;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Contract;
use App\Models\SubcontractorEmployee;
use App\Models\Worker;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * One worker: every company they have been at, the name and tax id they
 * used there, and every contract any of those rows was the contact on.
 * This is the "every contract this worker was involved in" report.
 */
class WorkerShow extends Component
{
    use AuthorizesAbility;

    public Worker $worker;

    // Edit dialog: the worker's own name and notes
    public string $worker_name = '';

    public string $worker_notes = '';

    public function validationAttributes(): array
    {
        return [
            'worker_name' => __('name'),
            'worker_notes' => __('notes'),
        ];
    }

    public function mount(Worker $worker): void
    {
        $this->authorizeAbility('workers.view');

        $this->worker = $worker;
    }

    public function startEdit(): void
    {
        $this->authorizeAbility('workers.link');

        $this->worker_name = $this->worker->name;
        $this->worker_notes = $this->worker->notes ?? '';
        $this->resetValidation();
        $this->dispatch('open-modal', 'edit-worker-modal');
    }

    public function cancelEdit(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', 'edit-worker-modal');
    }

    public function saveWorker(): void
    {
        $this->authorizeAbility('workers.link');

        $this->validate([
            'worker_name' => 'required|string|max:255',
            'worker_notes' => 'nullable|string|max:2000',
        ]);

        $this->worker->update([
            'name' => trim($this->worker_name),
            'notes' => trim($this->worker_notes) ?: null,
        ]);

        $this->cancelEdit();

        session()->flash('message', __('Worker updated.'));
    }

    /** Take one company's row off this worker; it becomes a worker of its own. */
    public function unlinkEmployee(int $employeeId): void
    {
        $this->authorizeAbility('workers.link');

        $employee = SubcontractorEmployee::where('id', $employeeId)
            ->where('worker_id', $this->worker->id)
            ->firstOrFail();

        if (! $employee->unlink()) {
            session()->flash('error', __('This is the only record on this worker — there is nothing to unlink.'));

            return;
        }

        session()->flash('message', __(':name at :company is now a worker of their own.', [
            'name' => $employee->name,
            'company' => $employee->subcontractor?->company_name,
        ]));
    }

    public function render()
    {
        $this->worker->load(['createdBy', 'employees.subcontractor', 'employees.linkedBy']);

        $employees = $this->worker->employees
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
            'open' => $contracts->whereIn('status', ['active', 'partially_paid'])->count(),
        ];

        return view('livewire.worker.worker-show', [
            'employees' => $employees,
            'contracts' => $contracts,
            'contractTotals' => $contractTotals,
            'taxIds' => $this->worker->distinctTaxIds(),
            'confined' => $user->isConfined(),
            'canSeeMoney' => $this->allowsMoney(),
        ])->layout('components.layouts.app');
    }
}
