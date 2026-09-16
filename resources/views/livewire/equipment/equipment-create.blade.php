<div>
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Add Equipment') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('A vehicle, a machine or a tool the company owns, leases or rents.') }}</p>
            </div>
            <div>
                <x-ui.button variant="secondary" href="{{ route('equipment.index') }}" icon="arrow-left">{{ __('Back to Equipment') }}</x-ui.button>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="space-y-8">
        @include('livewire.equipment.partials.form-body', ['editing' => false])

        <div class="flex items-center justify-end space-x-4">
            <x-ui.button type="button" variant="secondary" href="{{ route('equipment.index') }}">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="save" wire:loading.attr="disabled" wire:target="save,photo">{{ __('Save Equipment') }}</x-ui.button>
        </div>
    </form>
</div>
