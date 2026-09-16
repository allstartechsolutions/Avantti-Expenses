<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Add Company Expense') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Company (general)') }} &bull; {{ __('Belongs to no project; filed under a category with its account code.') }}
                </p>
            </div>
            <div>
                <x-ui.button variant="secondary" href="{{ route('company-expenses.index') }}" icon="arrow-left">
                    {{ __('Back') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    @if($categories->isEmpty())
        <div class="mb-6 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <p class="text-sm text-amber-900 dark:text-amber-200">
                {{ __('There is no active expense category to file this under. An administrator can add one on the Settings screen, under Expense Categories.') }}
            </p>
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
        @include('livewire.expense.partials.form-body', ['amountsLocked' => false])

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4">
            <x-ui.button type="button" variant="secondary" href="{{ route('company-expenses.index') }}">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="save">
                {{ __('Save Expense') }}
            </x-ui.button>
        </div>
    </form>

    @include('livewire.expense.partials.item-modal', ['amountsLocked' => false])
</div>
