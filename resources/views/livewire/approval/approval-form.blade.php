@php
    $isBR = config('app.country') === 'BR';
    $input = 'mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300';
    $backUrl = $jobSite ? route('jobsites.approvals', $jobSite) : route('projects.approvals', $project);
@endphp

<div>
    <x-ui.breadcrumb :items="[
        ['label' => __('Projects'), 'url' => route('projects.index')],
        ['label' => $project->project_name, 'url' => route('projects.overview', $project)],
        ['label' => __('Approvals'), 'url' => $backUrl],
        ['label' => $this->isEditing ? $approval->number : __('collaboration.label.new_approval')],
    ]" />

    <form wire:submit="save" class="max-w-5xl space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <h1 class="font-semibold text-slate-900 dark:text-white">
                    {{ $this->isEditing ? __('collaboration.label.edit_approval', ['number' => $approval->number]) : __('collaboration.label.new_approval') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.raised_draft_naming_reviewers_submitting') }}
                </p>
            </div>

            <div class="px-5 py-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                @if(! $jobSite)
                    <div>
                        <label class="{{ $label }}">{{ __('Location') }}</label>
                        <select wire:model="job_site_id" class="{{ $input }}">
                            <option value="">{{ __('Project (General)') }}</option>
                            @foreach($jobSites as $site)
                                <option value="{{ $site->id }}">{{ $site->job_site_name }}</option>
                            @endforeach
                        </select>
                        @error('job_site_id') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="{{ $label }}">{{ __('Type') }}</label>
                    <select wire:model.live="type" class="{{ $input }}">
                        @foreach(\App\Models\Approval::typeOptions() as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $label }}">{{ __('Title') }}</label>
                    <input type="text" wire:model="title" class="{{ $input }}"
                        placeholder="{{ __('collaboration.label.what_being_submitted_line') }}">
                    @error('title') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $label }}">{{ __('Description') }}</label>
                    <textarea wire:model="description" rows="4" class="{{ $input }}"
                        placeholder="{{ __('collaboration.help.specification_finish_make_model_whatever') }}"></textarea>
                    @error('description') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                {{-- Budget line, supplier and catalog item are all sets a
                     working company outgrows: hundreds of lines, thousands of
                     vendors. Each is typed into rather than scrolled through. --}}
                <div>
                    <x-ui.search-select
                        wire:model.live.debounce.300ms="budgetItemSearch"
                        :label="__('collaboration.label.budget_line')"
                        :placeholder="__('Search by code or name...')"
                        :hint="__('Optional — the budget line this is costed against.')"
                        :results="$budgetItemResults"
                        :selectedId="$budget_item_id"
                        :selectedLabel="$budgetItemLabel"
                        select="selectBudgetItem"
                        clear="clearBudgetItem"
                        :search="$budgetItemSearch"
                        :minChars="$minSearch"
                        :total="$budgetLineCount"
                        error="budget_item_id"
                        :empty="__('No budget line of this project matches. Try the code, or part of the name.')"
                        :unavailable="$budgetLineCount === 0 ? __('collaboration.help.project_budget_lines_there_nothing') : null" />
                </div>

                <div>
                    <x-ui.search-select
                        wire:model.live.debounce.300ms="supplierSearch"
                        :label="__('Supplier')"
                        :placeholder="__('Search by name or contact...')"
                        :hint="__('Optional — who is being proposed for this.')"
                        :results="$supplierResults"
                        :selectedId="$supplier_id"
                        :selectedLabel="$supplierLabel"
                        select="selectSupplier"
                        clear="clearSupplier"
                        :search="$supplierSearch"
                        :minChars="$minSearch"
                        :total="$supplierCount"
                        error="supplier_id"
                        :empty="__('No vendor matches. Check the spelling, or register the vendor first.')"
                        :unavailable="$supplierCount === 0 ? __('No vendors are registered yet, so there is none to name here.') : null" />
                </div>

                <div>
                    <x-ui.search-select
                        wire:model.live.debounce.300ms="catalogItemSearch"
                        :label="__('collaboration.label.catalog_item')"
                        :placeholder="__('Search by name or SKU...')"
                        :hint="__('Optional — the catalogued item this covers.')"
                        :results="$catalogItemResults"
                        :selectedId="$catalog_item_id"
                        :selectedLabel="$catalogItemLabel"
                        select="selectCatalogItem"
                        clear="clearCatalogItem"
                        :search="$catalogItemSearch"
                        :minChars="$minSearch"
                        :total="$catalogItemCount"
                        error="catalog_item_id"
                        :empty="__('No active catalog item matches.')"
                        :unavailable="$catalogItemCount === 0 ? __('The catalog is empty, so there is no item to link.') : null" />
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('collaboration.label.due') }}</label>
                    <input type="date" wire:model="due_date" class="{{ $input }}">
                    @error('due_date') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                {{-- US practice cites the specification section; BR practice
                     does not have one. Presentation, never business logic. --}}
                @if(! $isBR)
                    <div>
                        <label class="{{ $label }}">{{ __('collaboration.label.spec_section') }}</label>
                        <input type="text" wire:model="spec_section" class="{{ $input }}" placeholder="{{ __('collaboration.placeholder.e_g_09_30') }}">
                        @error('spec_section') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        </div>

        {{-- The certificate block appears only for the type that has one, and
             disappears again if the type is changed away from it. --}}
        @if($this->isCertificate)
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.certificate') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('collaboration.help.who_issued_how_long_lasts') }}
                    </p>
                </div>

                <div class="px-5 py-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="{{ $label }}">{{ __('collaboration.label.issued') }}</label>
                        <input type="text" wire:model="issuing_body" class="{{ $input }}" placeholder="{{ __('collaboration.placeholder.e_g_inmetro') }}">
                        @error('issuing_body') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('collaboration.label.certificate_number') }}</label>
                        <input type="text" wire:model="certificate_number" class="{{ $input }}">
                        @error('certificate_number') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('collaboration.label.issued_2') }}</label>
                        <input type="date" wire:model="issued_at" class="{{ $input }}">
                        @error('issued_at') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('collaboration.label.valid_until') }}</label>
                        <input type="date" wire:model="valid_until" class="{{ $input }}">
                        @error('valid_until') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        {{-- Distribution --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Distribution') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('collaboration.help.who_gets_copy_separate_who') }}
                    </p>
                </div>
                <x-ui.button variant="secondary" size="sm" type="button" icon="plus" wire:click="addDistributionRow">
                    {{ __('collaboration.label.add_row') }}
                </x-ui.button>
            </div>

            <div class="px-5 py-4 space-y-3">
                @foreach($distributionRows as $index => $row)
                    <div wire:key="dist-{{ $index }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                        <div class="md:col-span-4">
                            <select wire:model="distributionRows.{{ $index }}.user_id" class="{{ $input }} mt-0">
                                <option value="">{{ __('collaboration.label.someone_without_login') }}</option>
                                @foreach($assignableUsers as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- A chosen person is shown, not left as two greyed
                             empty boxes that read as lost data. --}}
                        @php $chosen = $row['user_id'] !== '' ? ($chosenUsers[$row['user_id']] ?? null) : null; @endphp

                        @if($chosen)
                            <div class="md:col-span-6 flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-700/40 text-sm">
                                <span class="text-slate-900 dark:text-white">{{ $chosen['name'] }}</span>
                                <span class="text-slate-500 dark:text-slate-400 truncate">{{ $chosen['email'] }}</span>
                            </div>
                        @else
                            <div class="md:col-span-3">
                                <input type="text" wire:model="distributionRows.{{ $index }}.external_name"
                                    class="{{ $input }} mt-0" placeholder="{{ __('Name') }}">
                                @error('distributionRows.'.$index.'.external_name') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <input type="email" wire:model="distributionRows.{{ $index }}.external_email"
                                    class="{{ $input }} mt-0" placeholder="{{ __('E-mail') }}">
                                @error('distributionRows.'.$index.'.external_email') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="md:col-span-1">
                            <select wire:model="distributionRows.{{ $index }}.role" class="{{ $input }} mt-0">
                                <option value="">—</option>
                                @foreach($roleOptions as $value => $text)
                                    <option value="{{ $value }}">{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-1 flex justify-end items-center md:h-[38px]">
                            <x-ui.icon-button
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                type="button"
                                wire:click="removeDistributionRow({{ $index }})"
                                title="{{ __('Remove this row') }}"
                                aria-label="{{ __('Remove this row') }}"
                                class="hover:text-red-600 dark:hover:text-red-400" />
                        </div>
                    </div>
                @endforeach

                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.rows_naming_nobody_reachable_dropped') }}
                </p>
            </div>
        </div>

        {{-- Attachments, in the same step. --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Attachments') }}</h2>
            </div>

            <div class="px-5 py-5 space-y-3">
                <x-ui.file-drop wire:model="newUploads">
                    {{-- Three keys, three different refusals: `uploads.*` from
                         save(), `newUploads` from this form's own size check,
                         and `newUploads.*` from Livewire's temporary-upload
                         rules, which reject a file before it ever reaches PHP. --}}
                    @error('uploads.*') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    @error('newUploads') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    @error('newUploads.*') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                    @if(count($uploads) > 0)
                        <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                            @foreach($uploads as $index => $upload)
                                <li wire:key="upload-{{ $index }}" class="px-3 py-2 flex items-center justify-between gap-3">
                                    <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                        {{ $upload->getClientOriginalName() }}
                                    </span>
                                    <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                        {{ \App\Services\DocumentSettings::formatBytes($upload->getSize()) }}
                                    </span>
                                    <x-ui.icon-button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        type="button"
                                        wire:click="discardUpload({{ $index }})"
                                        title="{{ __('Remove :file', ['file' => $upload->getClientOriginalName()]) }}"
                                        aria-label="{{ __('Remove :file', ['file' => $upload->getClientOriginalName()]) }}"
                                        class="hover:text-red-600 dark:hover:text-red-400" />
                                </li>
                            @endforeach
                        </ul>

                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ trans_choice(':count file goes up when this is saved.|:count files go up when this is saved.', count($uploads), ['count' => count($uploads)]) }}
                        </p>
                    @endif
                </x-ui.file-drop>

                @if($this->isEditing && $approval->availableFiles()->exists())
                    <div>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.already_attached') }}</p>
                        <ul class="mt-1 text-sm text-slate-600 dark:text-slate-300 list-disc list-inside">
                            @foreach($approval->availableFiles()->get() as $file)
                                <li class="truncate">{{ $file->original_name }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2 pb-8">
            <x-ui.button variant="secondary" :href="$this->isEditing ? route('approvals.show', $approval) : $backUrl">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" icon="save" wire:loading.attr="disabled" wire:target="save,newUploads">
                {{ $this->isEditing ? __('collaboration.label.save_changes') : __('collaboration.label.raise_approval') }}
            </x-ui.button>
        </div>
    </form>
</div>
