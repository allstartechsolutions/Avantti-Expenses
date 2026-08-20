<?php

namespace App\Livewire\SystemSettings;

use App\Livewire\Concerns\AuthorizesAbility;
use Livewire\Component;

class SettingsIndex extends Component
{
    use AuthorizesAbility;

    public function mount(): void
    {
        $this->authorizeAbility('settings.view');
    }

    public string $activeTab = 'tax-rates';

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('livewire.system-settings.settings-index')
            ->layout('components.layouts.app');
    }
}
