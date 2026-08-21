<?php

namespace App\Livewire\CostCode;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\CostCodeTemplate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class CostCodeTemplateIndex extends Component
{
    use AuthorizesAbility;

    use WithPagination;

    public $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function deleteTemplate($id)
    {
        $this->authorizeAbility('cost-codes.delete');

        $template = CostCodeTemplate::findOrFail($id);
        $template->delete();
        session()->flash('message', __('Template deleted successfully.'));
    }

    public function duplicateTemplate($id)
    {
        $this->authorizeAbility('cost-codes.create');

        $template = CostCodeTemplate::findOrFail($id);
        $newTemplate = $template->duplicate(Auth::id());
        session()->flash('message', __('Template duplicated successfully as ":name".', ['name' => $newTemplate->name]));
    }

    public function setAsDefault($id)
    {
        $this->authorizeAbility('cost-codes.edit');

        CostCodeTemplate::setDefault($id);
        session()->flash('message', __('Default template updated successfully.'));
    }

    public function render()
    {
        $templates = CostCodeTemplate::query()
            ->with('creator')
            ->withCount('costCodes')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.cost-code.cost-code-template-index', [
            'templates' => $templates,
        ])->layout('components.layouts.app');
    }
}
