@php
    $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
    $canManage = auth()->user()?->can('access.manage');
@endphp

<div>
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
            {{ __('A template is a ready-made set of permissions for one project or one job site. Inviting somebody copies it onto them, and their access is theirs from that moment: editing a template later never changes what an existing member can already do.') }}
        </p>
        @if($canManage)
            <div class="flex items-center gap-2 shrink-0">
                <x-ui.button variant="secondary" size="sm" wire:click="newTemplate('project')" icon="plus">{{ __('New project template') }}</x-ui.button>
                <x-ui.button variant="secondary" size="sm" wire:click="newTemplate('job_site')" icon="plus">{{ __('New job site template') }}</x-ui.button>
            </div>
        @endif
    </div>

    @forelse($this->templates as $level => $templates)
        <div class="{{ $card }} mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    {{ $this->levelName($level) }}
                </h2>
                <span class="text-sm text-slate-500 dark:text-slate-400">
                    {{ trans_choice('{1} :count template|[2,*] :count templates', $templates->count(), ['count' => $templates->count()]) }}
                </span>
            </div>

            @foreach($templates as $template)
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/50 last:border-b-0">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ __($template->name) }}</h3>
                                @if($template->is_system)
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('Built in') }}</span>
                                @endif
                                @if($template->is_guest)
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Guest') }}</span>
                                @endif
                                @unless($template->can_see_money)
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __('No monetary figures') }}</span>
                                @endunless
                            </div>

                            @if($template->description)
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __($template->description) }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                                <span>{{ trans_choice('{0} No abilities|{1} :count ability|[2,*] :count abilities', $template->ability_rows_count, ['count' => $template->ability_rows_count]) }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ trans_choice('{0} Not in use|{1} Used by :count person|[2,*] Used by :count people', $template->memberships_count, ['count' => $template->memberships_count]) }}</span>
                                @if($template->approval_limit)
                                    <span aria-hidden="true">·</span>
                                    <span>{{ __('Approves up to :amount', ['amount' => number_format($template->approval_limit / 100, 2)]) }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <x-ui.button variant="secondary" size="sm" wire:click="edit({{ $template->id }})" icon="edit">
                                {{ $canManage ? __('Edit') : __('View') }}
                            </x-ui.button>
                            @if($canManage)
                                <x-ui.button variant="ghost" size="sm" wire:click="duplicate({{ $template->id }})" icon="plus">{{ __('Duplicate') }}</x-ui.button>
                                @unless($template->is_system)
                                    <x-ui.button
                                        variant="danger"
                                        size="sm"
                                        icon="trash"
                                        wire:click="delete({{ $template->id }})"
                                        wire:confirm="{{ __('Delete this template? People already using it keep the access they have.') }}">
                                        {{ __('Delete') }}
                                    </x-ui.button>
                                @endunless
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="{{ $card }} px-6 py-12 text-center">
            <p class="text-slate-900 dark:text-white font-medium">{{ __('No templates yet.') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Templates are what make inviting somebody one click instead of forty. Run php artisan permissions:sync to restore the built-in ones, or create your own.') }}
            </p>
        </div>
    @endforelse

    @include('livewire.access.partials.template-modal')
</div>
