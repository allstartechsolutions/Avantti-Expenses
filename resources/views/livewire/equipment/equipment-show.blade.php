@php
    $tab = fn ($key) => $activeTab === $key
        ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]'
        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300';
    $statusChip = [
        'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'in_maintenance' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'retired' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'sold' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ];
    $canEdit = auth()->user()->can('equipment.edit');
    $canDelete = auth()->user()->can('equipment.delete');
@endphp
<div>
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-4 min-w-0">
                @if($equipment->photo_path)
                    <img src="{{ route('files.show', ['path' => $equipment->photo_path]) }}" alt="{{ $equipment->name }}" class="h-16 w-16 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0">
                @endif
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $equipment->name }}</h1>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusChip[$equipment->status] ?? $statusChip['active'] }}">{{ $equipment->getStatusLabel() }}</span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ $equipment->getTypeLabel() }}
                        @if($equipment->make || $equipment->model) &bull; {{ trim($equipment->make.' '.$equipment->model) }}@endif
                        @if($equipment->asset_tag) &bull; {{ __('Tag') }} {{ $equipment->asset_tag }}@endif
                        @if($equipment->plate) &bull; {{ $equipment->plate }}@endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <x-ui.button variant="secondary" href="{{ route('equipment.index') }}" icon="arrow-left">{{ __('Back to Equipment') }}</x-ui.button>
                @if($canEdit)
                    <x-ui.button variant="primary" href="{{ route('equipment.edit', $equipment) }}" icon="edit">{{ __('Edit') }}</x-ui.button>
                @endif
                @if($canDelete)
                    <x-ui.button variant="danger" icon="trash" wire:click="confirmDelete">{{ __('Delete') }}</x-ui.button>
                @endif
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">{{ session('error') }}</div>
    @endif

    <div class="border-b border-slate-200 dark:border-slate-700 mb-6">
        <nav class="-mb-px flex flex-wrap gap-x-8" aria-label="{{ __('Sections') }}">
            <button type="button" wire:click="setActiveTab('overview')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('overview') }}">{{ __('Overview') }}</button>
            @if(in_array('maintenance', $this->tabs()))
                <button type="button" wire:click="setActiveTab('maintenance')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('maintenance') }}">{{ __('Maintenance') }}</button>
            @endif
            @if(in_array('readings', $this->tabs()))
                <button type="button" wire:click="setActiveTab('readings')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('readings') }}">{{ __('Readings') }}</button>
            @endif
            @if(in_array('findings', $this->tabs()))
                <button type="button" wire:click="setActiveTab('findings')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('findings') }}">
                    {{ __('Findings') }}
                    @if($openFindings > 0)<span class="ml-2 py-0.5 px-2 rounded-full text-xs bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ $openFindings }}</span>@endif
                </button>
            @endif
            @if(in_array('assignments', $this->tabs()))
                <button type="button" wire:click="setActiveTab('assignments')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('assignments') }}">{{ __('Assignments') }}</button>
            @endif
            @if(in_array('costs', $this->tabs()))
                <button type="button" wire:click="setActiveTab('costs')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('costs') }}">{{ __('Costs') }}</button>
            @endif
            <button type="button" wire:click="setActiveTab('attachments')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('attachments') }}">{{ __('Attachments') }}</button>
        </nav>
    </div>

    @if($activeTab === 'overview')
        @include('livewire.equipment.partials.overview-tab')
    @elseif($activeTab === 'maintenance' && in_array('maintenance', $this->tabs()))
        @include('livewire.equipment.partials.maintenance-tab')
    @elseif($activeTab === 'readings' && in_array('readings', $this->tabs()))
        @include('livewire.equipment.partials.readings-tab')
    @elseif($activeTab === 'findings' && in_array('findings', $this->tabs()))
        @include('livewire.equipment.partials.findings-tab')
    @elseif($activeTab === 'assignments' && in_array('assignments', $this->tabs()))
        @include('livewire.equipment.partials.assignments-tab')
    @elseif($activeTab === 'costs' && in_array('costs', $this->tabs()))
        @include('livewire.equipment.partials.costs-tab')
    @elseif($activeTab === 'attachments')
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">{{ __('Attachments') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">{{ __('Registration, insurance, manuals, invoices — anything that belongs with this equipment.') }}</p>
            <livewire:shared.attachments model-type="equipment" :model-id="$equipment->id" :key="'equipment-attachments-'.$equipment->id" />
        </div>
    @endif

    @if($showDeleteModal)
        @include('livewire.equipment.partials.delete-modal')
    @endif
</div>
