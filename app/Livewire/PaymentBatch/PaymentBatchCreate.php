<?php

namespace App\Livewire\PaymentBatch;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Client;
use App\Models\PaymentBatch;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PaymentBatchCreate extends Component
{
    use AuthorizesAbility;

    public string $name = '';
    public string $payment_date = '';
    public string $notes = '';

    public string $client_id = '';
    public string $project_id = '';
    public string $subcontractor_id = '';
    public string $project_manager_id = '';
    public string $contract_status_filter = '';
    public bool $show_zero_balance = false;

    public function mount(): void
    {
        $this->authorizeAbility('payments.batch');

        $this->payment_date = now()->format('Y-m-d');
    }

    public function updatedClientId(): void
    {
        if ($this->project_id) {
            $project = Project::find($this->project_id);
            if ($project && $this->client_id && $project->client_id != $this->client_id) {
                $this->project_id = '';
            }
        }
    }

    #[Computed]
    public function clients()
    {
        return Client::whereHas('projects.contracts')
            ->orderBy('company_name')
            ->get(['id', 'company_name']);
    }

    #[Computed]
    public function projects()
    {
        return Project::whereHas('contracts')
            ->when($this->client_id, fn ($q) => $q->where('client_id', $this->client_id))
            ->orderBy('project_name')
            ->get(['id', 'project_name', 'client_id']);
    }

    #[Computed]
    public function projectManagers()
    {
        return User::whereHas('managedProjects.contracts')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function subcontractors()
    {
        return Subcontractor::whereHas('contracts')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function save(): void
    {
        $this->authorizeAbility('payments.batch');

        $this->validate([
            'name' => 'required|string|max:255',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $batch = PaymentBatch::create([
            'name' => $this->name,
            'payment_date' => $this->payment_date,
            'notes' => $this->notes ?: null,
            'client_id' => $this->client_id ?: null,
            'project_id' => $this->project_id ?: null,
            'subcontractor_id' => $this->subcontractor_id ?: null,
            'project_manager_id' => $this->project_manager_id ?: null,
            'contract_status_filter' => $this->contract_status_filter ?: null,
            'show_zero_balance' => $this->show_zero_balance,
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        session()->flash('message', __('Payment batch created successfully!'));

        $this->redirect(route('payment-batches.edit', $batch->id), navigate: true);
    }

    public function render()
    {
        return view('livewire.payment-batch.payment-batch-create')
            ->layout('components.layouts.app');
    }
}
