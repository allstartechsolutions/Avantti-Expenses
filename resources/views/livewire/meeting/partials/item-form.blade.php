{{-- Raising a new agenda line, inline where it will sit. --}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
@endphp

<div class="border-y border-[#3F5189]/30 bg-[#3F5189]/5 dark:bg-[#4A5A96]/10 px-6 py-4">
    @php $editingLine = $this->editingItem; @endphp

    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
        @if($editingLine)
            {{ __('Editing item :number', ['number' => $editingLine->number()]) }}
        @else
            {{ $item_parent_id ? __('New sub-item') : __('New agenda item') }}
        @endif
    </h3>

    @if($editingLine?->task)
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ __('This line carries task :code. The title, owner, date and location are the task\'s, so changing them here changes it everywhere and is recorded on its history.', ['code' => $editingLine->task->code()]) }}
        </p>
    @endif

    @if($errors->any())
        <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 dark:border-red-800 dark:bg-red-900/20">
            <ul class="space-y-0.5 text-sm text-red-700 dark:text-red-400">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-1">
            <label class="{{ $label }}">{{ __('Type') }}</label>
            <select wire:model.live="item_type" class="{{ $field }}" @disabled((bool) $editingLine?->task)>
                <option value="information">{{ __('Information') }}</option>
                <option value="decision">{{ __('Decision') }}</option>
                <option value="action">{{ __('Action Item') }}</option>
            </select>
            @if($editingLine?->task)
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('It has a task, so it stays an action item.') }}</p>
            @endif
        </div>

        <div class="md:col-span-3">
            <label class="{{ $label }}">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" wire:model="item_title" class="{{ $field }}"
                   placeholder="{{ __('What is being raised') }}">
            @error('item_title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="{{ $label }}">{{ __('Project') }}</label>
            <select wire:model.live="item_project_id" class="{{ $field }}" @disabled((bool) $item_parent_id)>
                <option value="">{{ __('General — no project') }}</option>
                @foreach($this->projects as $project)
                    <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="{{ $label }}">{{ __('Job Site') }}</label>
            <select wire:model="item_job_site_id" class="{{ $field }}" @disabled(! $item_project_id || $item_parent_id)>
                <option value="">{{ __('The project as a whole') }}</option>
                @foreach($this->itemJobSites as $site)
                    <option value="{{ $site->id }}">{{ $site->job_site_name }}</option>
                @endforeach
            </select>
        </div>

        @if($item_type === 'action' || $editingLine?->task)
            <div class="md:col-span-2">
                <label class="{{ $label }}">{{ __('Owner') }} <span class="text-red-500">*</span></label>
                <select wire:model="item_task_owner_id" class="{{ $field }}">
                    <option value="">{{ __('Who will do it') }}</option>
                    @foreach($this->assignableUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                @error('item_task_owner_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="{{ $label }}">{{ __('Due Date') }} <span class="text-red-500">*</span></label>
                <x-ui.date-input wire:model="item_task_due_date"
                       class="{{ $field }} {{ $errors->has('item_task_due_date') ? 'border-red-400 dark:border-red-500' : '' }}" />
                @if($editingLine?->task && ! $editingLine->task->due_date)
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        {{ __('This one has never had a date. Give it one — the minute cannot be published without it.') }}
                    </p>
                @endif
                @error('item_task_due_date') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-4">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if($editingLine?->task)
                        {{ __('Moving the owner or the date here is how a meeting reassigns work — the change is logged on the task.') }}
                    @else
                        {{ __('An action item becomes a task: from now on it carries forward on its own until its owner marks it ready and the chair confirms it.') }}
                    @endif
                </p>
            </div>
        @endif
    </div>

    <div class="mt-4 flex items-center justify-end gap-3">
        <x-ui.button variant="secondary" size="sm" wire:click="cancelItemForm">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button variant="primary" size="sm" wire:click="saveItem">
            {{ $editingLine ? __('Save Changes') : __('Add to the Agenda') }}
        </x-ui.button>
    </div>
</div>
