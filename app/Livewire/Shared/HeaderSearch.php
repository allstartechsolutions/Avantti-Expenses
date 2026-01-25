<?php

namespace App\Livewire\Shared;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HeaderSearch extends Component
{
    public string $search = '';
    public bool $showResults = false;

    public function updatedSearch(): void
    {
        $this->showResults = strlen($this->search) >= 2;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->showResults = false;
    }

    public function getResultsProperty()
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        return Project::where('project_name', 'like', '%' . $this->search . '%')
            ->select('id', 'project_name', 'street', 'city', 'state')
            ->orderBy('project_name')
            ->limit(8)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.shared.header-search');
    }
}
