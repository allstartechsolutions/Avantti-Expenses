<div class="relative" x-data="{ open: @entangle('showResults') }" @click.away="open = false">
    <input type="text"
           wire:model.live.debounce.300ms="search"
           @focus="open = ($wire.search.length >= 2)"
           placeholder="{{ __('Search projects...') }}"
           class="w-64 pl-10 pr-8 py-2 text-sm bg-slate-100 dark:bg-slate-700 border border-transparent rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] dark:text-white dark:placeholder-slate-400">
    <div class="absolute inset-y-0 left-0 flex items-center pl-3">
        <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
    </div>

    @if($search)
        <button type="button"
                wire:click="clearSearch"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    @endif

    <!-- Results Dropdown -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute z-50 mt-1 w-80 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden">

        @if($this->results->count() > 0)
            <ul class="max-h-64 overflow-y-auto">
                @foreach($this->results as $project)
                    <li>
                        <a href="{{ route('projects.overview', $project->id) }}"
                           class="block px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700 border-b border-slate-100 dark:border-slate-700 last:border-b-0">
                            <div class="font-medium text-sm text-slate-800 dark:text-white">
                                {{ $project->project_name }}
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                {{ $project->street }}{{ $project->city ? ', ' . $project->city : '' }}{{ $project->state ? ', ' . $project->state : '' }}
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                {{ __('No projects found') }}
            </div>
        @endif
    </div>
</div>
