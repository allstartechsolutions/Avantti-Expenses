{{--
    Every reply, newest first, with the one the work is built to marked.

    Replies accumulate rather than overwrite: an SI is answered by whoever can
    answer it, and often that is more than one person. A newer reply does not
    take over on its own — which one counts is somebody's decision, and the
    panel says loudly when the newest is not the chosen one.
--}}
@php
    $newest = $replies->first();
    $newestIsNotValid = $newest && $rfi->valid_reply_id && $newest->id !== $rfi->valid_reply_id;
@endphp

<div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <h2 class="font-semibold text-slate-900 dark:text-white">
            {{ __('collaboration.label.replies') }}
            @if($replies->isNotEmpty())
                <span class="text-slate-400 dark:text-slate-500">({{ $replies->count() }})</span>
            @endif
        </h2>
        @if($rfi->isClosed())
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('collaboration.label.frozen_rfi_closed') }}</span>
        @endif
    </div>

    @if($replies->isEmpty())
        <div class="px-5 py-6">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('collaboration.message.answered') }}</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                @if($rfi->ballInCourt)
                    {{ __('collaboration.message.waiting', ['who' => $rfi->ballInCourt->name]) }}
                @else
                    {{ __('collaboration.help.nobody_been_asked_set_who') }}
                @endif
            </p>
        </div>
    @else
        {{-- The newest reply is not the one that counts: say so, or a reader
             skimming the top of the list takes the wrong answer away. --}}
        @if($newestIsNotValid)
            <div class="px-5 py-3 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800">
                <p class="text-xs font-medium text-amber-900 dark:text-amber-200">
                    {{ __('collaboration.help.newer_reply_is_not_the_valid_one') }}
                </p>
            </div>
        @endif

        <ul class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($replies as $reply)
                @php $isValid = $reply->id === $rfi->valid_reply_id; @endphp

                <li wire:key="reply-{{ $reply->id }}"
                    class="px-5 py-4 {{ $isValid ? 'bg-emerald-50/50 dark:bg-emerald-900/10 border-l-4 border-emerald-400 dark:border-emerald-600' : '' }}">

                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        @if($isValid)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                                {{ __('collaboration.label.valid_answer') }}
                            </span>
                        @endif

                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $reply->getAuthorName() }}</span>

                        @if($reply->repliedBy?->company_name)
                            <span class="text-xs text-slate-500 dark:text-slate-400">· {{ $reply->repliedBy->company_name }}</span>
                        @endif

                        <span class="text-xs text-slate-400 dark:text-slate-500">· {{ $reply->replied_at?->appDateTime() }}</span>

                        @if($reply->wasEdited())
                            <span class="text-xs text-amber-600 dark:text-amber-400">
                                · {{ __('collaboration.label.edited_by_on', [
                                    'who' => $reply->editedBy?->name ?? __('collaboration.label.removed_user'),
                                    'when' => $reply->edited_at?->appDateTime(),
                                ]) }}
                            </span>
                        @endif
                    </div>

                    @if($editingReplyId === $reply->id)
                        <div class="mt-2">
                            <textarea wire:model="editingReplyBody" rows="5"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"></textarea>
                            @error('editingReplyBody') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                            {{-- A closed SI has been sent out and quoted, so a
                                 correction has to be explicable afterwards. --}}
                            @if($rfi->isClosed())
                                <label class="block mt-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('collaboration.label.why_corrected') }}
                                </label>
                                <input type="text" wire:model="editingReplyReason"
                                    class="mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('editingReplyReason') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                            @endif

                            <div class="mt-2 flex gap-2">
                                <x-ui.button variant="primary" size="sm" wire:click="saveReplyEdit">{{ __('Save') }}</x-ui.button>
                                <x-ui.button variant="secondary" size="sm" wire:click="cancelEditingReply">{{ __('Cancel') }}</x-ui.button>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line">{{ $reply->body }}</p>

                        @if($reply->availableFiles->isNotEmpty())
                            <ul class="mt-2 space-y-1">
                                @foreach($reply->availableFiles as $file)
                                    <li>
                                        <button type="button" wire:click="downloadFile({{ $file->id }})"
                                            class="text-left text-xs text-[#3F5189] dark:text-indigo-400 hover:underline truncate">
                                            {{ $file->original_name }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        {{-- Icons rather than words: these two sit against every
                             reply, and the labels repeated down a long list are
                             noise. The name still reaches a screen reader and a
                             hover through `title` and `sr-only`. --}}
                        <div class="mt-2 flex flex-wrap gap-1">
                            @if(! $isValid && $this->canChooseReply)
                                <x-ui.icon-button variant="secondary" size="sm" icon="check"
                                    wire:click="chooseReply({{ $reply->id }})"
                                    title="{{ __('collaboration.label.mark_as_valid') }}">
                                    <span class="sr-only">{{ __('collaboration.label.mark_as_valid') }}</span>
                                </x-ui.icon-button>
                            @endif

                            @if($this->canEditReply($reply))
                                <x-ui.icon-button variant="ghost" size="sm" icon="edit"
                                    wire:click="startEditingReply({{ $reply->id }})"
                                    title="{{ __('Edit') }}">
                                    <span class="sr-only">{{ __('Edit') }}</span>
                                </x-ui.icon-button>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
