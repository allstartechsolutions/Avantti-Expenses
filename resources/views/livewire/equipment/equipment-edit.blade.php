<div>
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Edit Equipment') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $equipment->name }}@if($equipment->asset_tag) &bull; {{ $equipment->asset_tag }}@endif</p>
            </div>
            <div>
                <x-ui.button variant="secondary" href="{{ route('equipment.show', $equipment) }}" icon="arrow-left">{{ __('Back') }}</x-ui.button>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="space-y-8">
        @include('livewire.equipment.partials.form-body', ['editing' => true])

        <div class="flex items-center justify-end space-x-4">
            <x-ui.button type="button" variant="secondary" href="{{ route('equipment.show', $equipment) }}">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="save" wire:loading.attr="disabled" wire:target="save,photo">{{ __('Save Changes') }}</x-ui.button>
        </div>
    </form>
</div>
