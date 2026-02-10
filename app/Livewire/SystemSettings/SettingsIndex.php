<?php

namespace App\Livewire\SystemSettings;

use Livewire\Component;

class SettingsIndex extends Component
{
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
