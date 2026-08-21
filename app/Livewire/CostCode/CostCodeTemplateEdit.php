<?php

namespace App\Livewire\CostCode;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\CostCodeTemplate;
use Livewire\Component;

class CostCodeTemplateEdit extends Component
{
    use AuthorizesAbility;

    public CostCodeTemplate $template;

    public $name = '';
    public $description = '';
    public $is_default = false;

    public function mount(CostCodeTemplate $template)
    {
        $this->authorizeAbility('cost-codes.edit');

        $this->template = $template;
        $this->name = $template->name;
        $this->description = $template->description ?? '';
        $this->is_default = $template->is_default;
    }

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
        ];
    }

    public function save()
    {
        $this->authorizeAbility('cost-codes.edit');

        $this->validate();

        // If setting as default, clear other defaults first
        if ($this->is_default && !$this->template->is_default) {
            CostCodeTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $this->template->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'is_default' => $this->is_default,
        ]);

        session()->flash('message', __('Template updated successfully!'));
        return redirect()->route('cost-codes.templates.show', $this->template->id);
    }

    public function render()
    {
        return view('livewire.cost-code.cost-code-template-edit')
            ->layout('components.layouts.app');
    }
}
