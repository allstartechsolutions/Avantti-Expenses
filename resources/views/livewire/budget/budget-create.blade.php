<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Create Budget') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Create a new budget for') }}
                    @if($jobSite)
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $jobSite->job_site_name }}</span>
                    @else
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $project->project_name }}</span>
                        <span class="text-slate-400">{{ __('(Project Level)') }}</span>
                    @endif
                </p>
            </div>
            <x-ui.button
                variant="secondary"
                href="{{ $jobSite ? route('jobsites.show', $jobSite->id) : route('projects.budget', $project->id) }}"
                icon="arrow-left">
                {{ __('Back') }}
            </x-ui.button>
        </div>
    </div>

    <!-- Location Info Card -->
    <div class="mb-6 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-200 dark:border-slate-700 p-4">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-[#3F5189]/10 dark:bg-[#3F5189]/20 rounded-lg">
                <x-ui.icon name="map-pin" class="w-5 h-5 text-[#3F5189]" />
            </div>
            <div>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Location') }}</p>
                <p class="font-medium text-slate-900 dark:text-white">{{ $this->locationName }}</p>
            </div>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <form wire:submit="save" class="p-6 space-y-6">
            <div class="grid grid-cols-1 gap-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ __('Budget Name') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        wire:model="name"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="{{ __('e.g., Main Budget, Phase 1 Budget') }}">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ __('Notes') }}
                    </label>
                    <textarea
                        id="notes"
                        wire:model="notes"
                        rows="3"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="{{ __('Optional notes about this budget') }}"></textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Template Selection -->
                <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-4">
                        {{ __('Cost Code Template') }}
                    </label>

                    <div class="space-y-4">
                        <!-- Option: Blank -->
                        <label class="flex items-start gap-3 p-4 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors {{ !$use_template ? 'ring-2 ring-[#3F5189] bg-[#3F5189]/5' : '' }}">
                            <input
                                type="radio"
                                wire:model.live="use_template"
                                value=""
                                class="mt-1 text-[#3F5189] focus:ring-[#3F5189]">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ __('Start with blank budget') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Add cost codes manually after creation') }}</p>
                            </div>
                        </label>

                        <!-- Option: Use Template -->
                        <label class="flex items-start gap-3 p-4 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors {{ $use_template ? 'ring-2 ring-[#3F5189] bg-[#3F5189]/5' : '' }}">
                            <input
                                type="radio"
                                wire:model.live="use_template"
                                value="1"
                                class="mt-1 text-[#3F5189] focus:ring-[#3F5189]">
                            <div class="flex-1">
                                <p class="font-medium text-slate-900 dark:text-white">{{ __('Use cost code template') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">{{ __('Cost codes will be copied with $0.00 amounts') }}</p>

                                @if($use_template)
                                    <select
                                        wire:model="source_template_id"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        <option value="">{{ __('Select a template...') }}</option>
                                        @foreach($templates as $template)
                                            <option value="{{ $template->id }}">
                                                {{ $template->name }}
                                                ({{ $template->costCodes->count() }} cost codes)
                                                {{ $template->is_default ? '★ Default' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('source_template_id')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                @endif
                            </div>
                        </label>
                    </div>

                    @if($templates->isEmpty())
                        <p class="mt-4 text-sm text-amber-600 dark:text-amber-400">
                            <x-ui.icon name="alert-triangle" class="w-4 h-4 inline mr-1" />
                            {{ __('No cost code templates found.') }}
                            <a href="{{ route('cost-codes.templates.create') }}" class="underline hover:no-underline">{{ __('Create one') }}</a> to use as a starting point.
                        </p>
                    @endif
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200 dark:border-slate-700">
                <x-ui.button
                    type="button"
                    variant="secondary"
                    href="{{ $jobSite ? route('jobsites.show', $jobSite->id) : route('projects.budget', $project->id) }}">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button
                    type="submit"
                    variant="primary"
                    icon="check">
                    {{ __('Create Budget') }}
                </x-ui.button>
            </div>
        </form>
    </div>
</div>
