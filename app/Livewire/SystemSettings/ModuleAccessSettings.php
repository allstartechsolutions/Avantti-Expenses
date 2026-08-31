<?php

namespace App\Livewire\SystemSettings;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\ModuleAccess;
use App\Models\ModuleAccessHistory;
use Livewire\Component;

class ModuleAccessSettings extends Component
{
    use AuthorizesAbility;

    public function mount(): void
    {
        $this->authorizeAbility('settings.view');
    }

    public bool $showHistoryModal = false;
    public ?int $historyModuleId = null;
    public array $historyEntries = [];

    public function toggle(int $id): void
    {
        $this->authorizeAbility('settings.manage_modules');

        $module = ModuleAccess::findOrFail($id);

        if ($module->is_core) {
            return;
        }

        $oldValue = $module->is_enabled ? 'Enabled' : 'Disabled';
        $module->is_enabled = !$module->is_enabled;
        $module->save();
        $newValue = $module->is_enabled ? 'Enabled' : 'Disabled';

        ModuleAccess::logHistory($module->id, 'updated', 'is_enabled', $oldValue, $newValue);
        ModuleAccess::clearCache($module->module_key);

        session()->flash('message', $module->is_enabled
            ? __(':name module enabled successfully!', ['name' => $module->module_name])
            : __(':name module disabled successfully!', ['name' => $module->module_name]));
    }

    public function viewHistory(int $id): void
    {
        $this->historyModuleId = $id;
        $this->historyEntries = ModuleAccessHistory::where('module_access_id', $id)
            ->with('changedBy')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'action' => $entry->action,
                    'field_changed' => $entry->field_changed,
                    'old_value' => $entry->old_value,
                    'new_value' => $entry->new_value,
                    'changed_by' => $entry->changedBy->name ?? 'Unknown',
                    'created_at' => $entry->created_at->appDateTime(),
                ];
            })
            ->toArray();

        $this->showHistoryModal = true;
        $this->dispatch('open-modal', 'module-access-history-modal');
    }

    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
        $this->historyModuleId = null;
        $this->historyEntries = [];
        $this->dispatch('close-modal', 'module-access-history-modal');
    }

    public function render()
    {
        $modules = ModuleAccess::query()
            ->where('is_core', false)
            ->orderBy('module_name')
            ->get();

        return view('livewire.system-settings.module-access-settings', [
            'modules' => $modules,
        ]);
    }
}
