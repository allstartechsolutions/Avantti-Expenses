<div>
    @php
        $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
        $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
    @endphp

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                {{ $article ? __('Edit Guide') : __('Write a Guide') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Your own procedures, alongside the guides that ship with the product.') }}
            </p>
        </div>
        <x-ui.button variant="secondary" icon="arrow-left" href="{{ route('documentation.index') }}">{{ __('Back') }}</x-ui.button>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="{{ $card }} p-6 space-y-4">
                <div>
                    <label class="{{ $label }}">{{ __('Title') }} <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="title" class="{{ $field }}"
                           placeholder="{{ __('e.g. How we close out a job site') }}">
                    @error('title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('Summary') }}</label>
                    <input type="text" wire:model="summary" class="{{ $field }}"
                           placeholder="{{ __('One line, shown on the card in the library.') }}">
                    @error('summary') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="{{ $card }} p-6">
                <label class="{{ $label }}">{{ __('The Guide') }} <span class="text-red-500">*</span></label>
                <div wire:ignore>
                    <x-ui.tinymce-editor wireModel="body" id="doc-body-{{ $article?->id ?? 'new' }}" :height="520"
                                         :uploads="true"
                                         :uploadUrl="route('documentation.images.store')"
                                         :uploadContext="$article?->id" />
                </div>
                @error('body') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Images added here are uploaded to the same cloud storage as the rest of the system, not embedded in the page.') }}
                </p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="{{ $card }} p-6 space-y-4">
                <div>
                    <label class="{{ $label }}">{{ __('Section') }} <span class="text-red-500">*</span></label>
                    <select wire:model="category" class="{{ $field }}">
                        @foreach($this->categories() as $key => $categoryLabel)
                            <option value="{{ $key }}">{{ $categoryLabel }}</option>
                        @endforeach
                    </select>
                    @error('category') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('Order in its section') }}</label>
                    <input type="number" wire:model="position" min="0" max="9999" class="{{ $field }}">
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Lower numbers come first.') }}</p>
                </div>

                <div>
                    <x-ui.toggle wire:model="is_published" :checked="$is_published" label="{{ __('Published') }}" />
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ $is_published
                            ? __('Everyone signed in can read it.')
                            : __('Only admins and managers can see it while it is a draft.') }}
                    </p>
                </div>
            </div>

            <div class="{{ $card }} p-6 space-y-3">
                <x-ui.button type="submit" variant="primary" icon="save" class="w-full justify-center">
                    {{ $article ? __('Save Changes') : __('Create Guide') }}
                </x-ui.button>
                <x-ui.button type="button" variant="secondary" href="{{ route('documentation.index') }}" class="w-full justify-center">
                    {{ __('Cancel') }}
                </x-ui.button>
            </div>
        </div>
    </form>
</div>
