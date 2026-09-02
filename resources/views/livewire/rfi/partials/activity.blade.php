{{--
    The document's history, including the times somebody only opened it. That
    is the point of the log: "sent to the projetista on the 4th, opened on the
    5th" is a sentence this module has to be able to say.
--}}
<div x-data="{ open: false }"
    class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
    {{-- Closed by default: the history is a reference, reached for when
         somebody asks "when was this sent?". Left open it pushes the replies
         off the screen on every visit. --}}
    <button type="button" x-on:click="open = ! open"
        class="w-full px-5 py-4 flex items-center justify-between text-left"
        :aria-expanded="open ? 'true' : 'false'">
        <h2 class="font-semibold text-slate-900 dark:text-white">
            {{ __('History') }}
            <span class="text-slate-400 dark:text-slate-500">({{ $activity->count() }})</span>
        </h2>

        <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="open && 'rotate-180'"
            fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="open" x-cloak class="border-t border-slate-200 dark:border-slate-700">

    @if($activity->isEmpty())
        <p class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">
            {{ __('collaboration.message.nothing_happened_rfi') }}
        </p>
    @else
        <ol class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($activity as $entry)
                <li class="px-5 py-3 flex items-start gap-3">
                    <span class="mt-1.5 h-2 w-2 rounded-full shrink-0 {{ $entry->action === \App\Models\Collaboration\ActivityLogEntry::VIEWED ? 'bg-slate-300 dark:bg-slate-600' : 'bg-[#3F5189]' }}"></span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-900 dark:text-white">
                            <span class="font-medium">{{ $entry->getActionLabel() }}</span>
                            <span class="text-slate-500 dark:text-slate-400">— {{ $entry->getActorName() }}</span>
                        </p>

                        @if($entry->action === \App\Models\Collaboration\ActivityLogEntry::REVISED && ($entry->context['reason'] ?? null))
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                {{ __('collaboration.label.reason') }} {{ $entry->context['reason'] }}
                            </p>
                            @if($entry->context['previous_answer'] ?? null)
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 whitespace-pre-line border-l-2 border-slate-200 dark:border-slate-600 pl-3">
                                    {{ __('collaboration.label.replaced') }} {{ $entry->context['previous_answer'] }}
                                </p>
                            @endif
                        @endif

                        <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                            {{ $entry->created_at?->appDateTime() }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
</div>
