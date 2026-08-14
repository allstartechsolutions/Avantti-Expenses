<?php

namespace App\Livewire\CostCode;

use App\Models\CostCodeTemplate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CostCodeTemplateCreate extends Component
{
    public $name = '';
    public $description = '';
    public $is_default = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'is_default' => 'boolean',
    ];

    public function save()
    {
        $this->validate();

        // If setting as default, clear other defaults first
        if ($this->is_default) {
            CostCodeTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = CostCodeTemplate::create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'is_default' => $this->is_default,
            'created_by' => Auth::id(),
        ]);

        session()->flash('message', __('Template created successfully!'));
        return redirect()->route('cost-codes.templates.show', $template->id);
    }

    public function render()
    {
        return view('livewire.cost-code.cost-code-template-create')
            ->layout('components.layouts.app');
    }
}
