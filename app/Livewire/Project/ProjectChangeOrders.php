<?php

namespace App\Livewire\Project;

use App\Models\ChangeOrder;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProjectChangeOrders extends Component
{
    use WithFileUploads;

    public Project $project;

    // Filters
    public $changeOrderSearch = '';
    public $changeOrderLocationFilter = 'all';

    // Modal state
    public $showChangeOrderModal = false;
    public $changeOrderModalMode = 'create'; // create, edit, view
    public $editingChangeOrder = null;

    // Form fields
    public $co_job_site_id = null;
    public $co_title = '';
    public $co_requested_date = '';
    public $co_description = '';
    public $co_amount = '';
    public $co_file = null;
    public $existingFilePath = null;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function openChangeOrderCreateModal(): void
    {
        $this->reset(['co_job_site_id', 'co_title', 'co_requested_date', 'co_description', 'co_amount', 'co_file', 'existingFilePath', 'editingChangeOrder']);
        $this->co_requested_date = now()->format('Y-m-d');
        $this->changeOrderModalMode = 'create';
        $this->showChangeOrderModal = true;
        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function openChangeOrderEditModal(int $changeOrderId): void
    {
        $changeOrder = ChangeOrder::findOrFail($changeOrderId);

        $this->editingChangeOrder = $changeOrder->id;
        $this->co_job_site_id = $changeOrder->job_site_id;
        $this->co_title = $changeOrder->title;
        $this->co_requested_date = $changeOrder->requested_date->format('Y-m-d');
        $this->co_description = $changeOrder->description;
        $this->co_amount = $changeOrder->amount;
        $this->existingFilePath = $changeOrder->file_path;
        $this->co_file = null;

        $this->changeOrderModalMode = 'edit';
        $this->showChangeOrderModal = true;
        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function openChangeOrderViewModal(int $changeOrderId): void
    {
        $changeOrder = ChangeOrder::findOrFail($changeOrderId);

        $this->editingChangeOrder = $changeOrder->id;
        $this->co_job_site_id = $changeOrder->job_site_id;
        $this->co_title = $changeOrder->title;
        $this->co_requested_date = $changeOrder->requested_date->format('Y-m-d');
        $this->co_description = $changeOrder->description;
        $this->co_amount = $changeOrder->amount;
        $this->existingFilePath = $changeOrder->file_path;

        $this->changeOrderModalMode = 'view';
        $this->showChangeOrderModal = true;
        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function saveChangeOrder(): void
    {
        $this->validate([
            'co_title' => 'required|string|max:255',
            'co_requested_date' => 'required|date',
            'co_description' => 'nullable|string',
            'co_amount' => 'required|numeric|min:0',
            'co_file' => 'nullable|file|max:10240',
            'co_job_site_id' => 'nullable|exists:job_sites,id',
        ]);

        $filePath = $this->existingFilePath;

        if ($this->co_file) {
            if ($this->existingFilePath) {
                Storage::delete($this->existingFilePath);
            }
            $filePath = $this->co_file->store('change_orders', 'local');
        }

        $data = [
            'project_id' => $this->project->id,
            'job_site_id' => $this->co_job_site_id ?: null,
            'title' => $this->co_title,
            'requested_date' => $this->co_requested_date,
            'description' => $this->co_description,
            'amount' => $this->co_amount,
            'file_path' => $filePath,
        ];

        if ($this->changeOrderModalMode === 'edit' && $this->editingChangeOrder) {
            $changeOrder = ChangeOrder::findOrFail($this->editingChangeOrder);
            $changeOrder->update($data);
            session()->flash('message', __('Change order updated successfully!'));
        } else {
            $data['created_by'] = Auth::id();
            ChangeOrder::create($data);
            session()->flash('message', __('Change order created successfully!'));
        }

        $this->closeChangeOrderModal();
        $this->project->refresh();
    }

    public function deleteChangeOrder(int $changeOrderId): void
    {
        $changeOrder = ChangeOrder::findOrFail($changeOrderId);

        if ($changeOrder->file_path) {
            Storage::delete($changeOrder->file_path);
        }

        $changeOrder->delete();

        session()->flash('message', __('Change order deleted successfully!'));
        $this->project->refresh();
    }

    public function closeChangeOrderModal(): void
    {
        $this->showChangeOrderModal = false;
        $this->reset(['co_job_site_id', 'co_title', 'co_requested_date', 'co_description', 'co_amount', 'co_file', 'existingFilePath', 'editingChangeOrder']);
        $this->dispatch('close-modal', 'change-order-modal');
    }

    public function render()
    {
        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        // Change Orders query with filters
        $changeOrdersQuery = $this->project->changeOrders()->with(['jobSite', 'createdBy']);

        // Apply location filter
        if ($this->changeOrderLocationFilter === 'project') {
            $changeOrdersQuery->whereNull('job_site_id');
        } elseif ($this->changeOrderLocationFilter !== 'all' && is_numeric($this->changeOrderLocationFilter)) {
            $changeOrdersQuery->where('job_site_id', $this->changeOrderLocationFilter);
        }

        // Apply search filter
        if ($this->changeOrderSearch) {
            $changeOrdersQuery->where(function ($query) {
                $query->where('title', 'like', '%' . $this->changeOrderSearch . '%')
                    ->orWhere('description', 'like', '%' . $this->changeOrderSearch . '%');
            });
        }

        $changeOrders = $changeOrdersQuery->orderBy('requested_date', 'desc')->get();
        $totalChangeOrdersAmount = $changeOrders->sum('amount');

        return view('livewire.project.project-change-orders', [
            'changeOrders' => $changeOrders,
            'jobSites' => $jobSites,
            'totalChangeOrdersAmount' => $totalChangeOrdersAmount,
        ])->layout('components.layouts.app');
    }
}
