<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Add Expense') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $project->project_name }}
                    @if($jobSite)
                        / {{ $jobSite->job_site_name }}
                    @endif
                </p>
            </div>
            <div>
                <x-ui.button
                    variant="secondary"
                    href="{{ $jobSite ? route('jobsites.show', ['jobSite' => $jobSite->id, 'tab' => 'expenses']) : route('projects.expenses', $project->id) }}"
                    icon="arrow-left">
                    {{ __('Back') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
        @include('livewire.expense.partials.form-body', ['amountsLocked' => false])

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4">
            <x-ui.button
                type="button"
                variant="secondary"
                href="{{ $jobSite ? route('jobsites.show', ['jobSite' => $jobSite->id, 'tab' => 'expenses']) : route('projects.expenses', $project->id) }}">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="save">
                {{ __('Save Expense') }}
            </x-ui.button>
        </div>
    </form>

    @include('livewire.expense.partials.item-modal', ['amountsLocked' => false])
</div>
