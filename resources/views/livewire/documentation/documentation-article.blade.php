<div>
    @php
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
        $entry = $this->entry;
        $neighbours = $this->neighbours;
        $stampFormat = config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A';
    @endphp

    <div class="mb-6 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('documentation.index') }}" wire:navigate
               class="inline-flex items-center gap-1 text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                {{ __('Documentation') }}
            </a>

            <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ $entry['title'] }}</h1>

            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                <span class="rounded bg-slate-100 px-1.5 py-0.5 dark:bg-slate-700">{{ $entry['category_label'] }}</span>
                @if($entry['source'] === 'shipped')
                    <span>{{ __('Ships with the product') }}</span>
                @else
                    <span>{{ __('Written by :name', ['name' => $entry['model']->updatedBy?->name ?? $entry['model']->createdBy?->name]) }}</span>
                @endif
                <span>{{ __('updated :date', ['date' => $entry['updated_at']?->format($stampFormat)]) }}</span>
                @unless($entry['is_published'])
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ __('Draft') }}</span>
                @endunless
            </p>
        </div>

        @if($this->canWrite() && $entry['source'] === 'custom')
            <div class="flex items-center gap-2">
                <x-ui.button variant="secondary" size="sm" icon="edit" href="{{ route('documentation.edit', $entry['model']) }}">{{ __('Edit') }}</x-ui.button>
                @if(auth()->user()?->is_admin)
                    <x-ui.button variant="danger" size="sm" icon="trash" wire:click="deleteArticle"
                                 wire:confirm="{{ __('Delete this guide? It cannot be brought back.') }}">
                        {{ __('Delete') }}
                    </x-ui.button>
                @endif
            </div>
        @elseif($this->canWrite())
            <p class="max-w-xs text-xs text-slate-400 dark:text-slate-500">
                {{ __('This guide ships with the product and is kept in step with the code, so it is not edited here.') }}
            </p>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- The guide -->
        <div class="lg:col-span-3 order-2 lg:order-1">
            <article class="{{ $card }} px-6 py-8 sm:px-10">
                <div class="doc-body max-w-none text-slate-700 dark:text-slate-200">
                    {!! $entry['html'] !!}
                </div>
            </article>

            <!-- Reading order -->
            @if($neighbours['previous'] || $neighbours['next'])
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if($neighbours['previous'])
                        <a href="{{ route('documentation.show', $neighbours['previous']['slug']) }}" wire:navigate
                           class="{{ $card }} p-4 transition-colors hover:border-[#3F5189]">
                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('Previous') }}</p>
                            <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $neighbours['previous']['title'] }}</p>
                        </a>
                    @else
                        <div></div>
                    @endif

                    @if($neighbours['next'])
                        <a href="{{ route('documentation.show', $neighbours['next']['slug']) }}" wire:navigate
                           class="{{ $card }} p-4 text-right transition-colors hover:border-[#3F5189]">
                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('Next') }}</p>
                            <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $neighbours['next']['title'] }}</p>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <!-- Contents and the rest of the library -->
        <div class="order-1 lg:order-2 space-y-6">
            @if(count($entry['headings']) > 1)
                <div class="{{ $card }} p-5 lg:sticky lg:top-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('On this page') }}</p>
                    <nav class="mt-3 space-y-1 max-h-[60vh] overflow-y-auto">
                        @foreach($entry['headings'] as $heading)
                            <a href="#{{ $heading['anchor'] }}"
                               class="block truncate text-sm text-slate-600 hover:text-[#3F5189] dark:text-slate-300 dark:hover:text-[#4A5A96] {{ $heading['level'] === 3 ? 'pl-3 text-xs' : '' }}">
                                {{ $heading['text'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            @endif

            <div class="{{ $card }} p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('All Guides') }}</p>
                <div class="mt-3 space-y-4">
                    @foreach($this->siblings as $key => $group)
                        <div wire:key="side-{{ $key }}">
                            <p class="text-xs font-medium text-slate-400 dark:text-slate-500">{{ $group['label'] }}</p>
                            <div class="mt-1 space-y-1">
                                @foreach($group['entries'] as $sibling)
                                    <a href="{{ route('documentation.show', $sibling['slug']) }}" wire:navigate
                                       class="block truncate text-sm {{ $sibling['slug'] === $slug
                                            ? 'font-semibold text-[#3F5189] dark:text-[#4A5A96]'
                                            : 'text-slate-600 hover:text-[#3F5189] dark:text-slate-300' }}">
                                        {{ $sibling['title'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
