{{--
    Every round, newest first. This is the substance of the page: a rejection
    belongs to the submission that was rejected, and the record of it has to
    survive the next attempt.
--}}
@php
@endphp

<div class="space-y-4">
    @foreach($revisions as $revision)
        @php
            $code = $revision->responseCode;
            $tone = match (true) {
                $code === null => 'border-sky-200 dark:border-sky-800',
                $code->opensRevision() => 'border-amber-300 dark:border-amber-700',
                $code->canonical === \App\Models\Collaboration\ResponseCode::REJECTED => 'border-rose-300 dark:border-rose-700',
                default => 'border-emerald-300 dark:border-emerald-700',
            };
        @endphp

        <div class="bg-white dark:bg-slate-800 rounded-lg border-l-4 {{ $tone }} border-y border-r border-slate-200 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">
                        {{ __('collaboration.label.revision', ['revision' => $revision->revision]) }}
                        @if($loop->first && $revision->responded_at === null)
                            <span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">
                                {{ __('collaboration.label.out_review') }}
                            </span>
                        @endif
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('collaboration.label.submitted', [
                            'who' => $revision->submittedBy?->name ?? __('collaboration.label.removed_user'),
                            'when' => $revision->submitted_at?->appDateTime(),
                        ]) }}
                    </p>
                </div>

                @if($code)
                    <span class="inline-flex px-2.5 py-1 rounded-full text-sm font-medium
                        {{ $code->opensRevision()
                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'
                            : ($code->canonical === \App\Models\Collaboration\ResponseCode::REJECTED
                                ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200'
                                : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200') }}">
                        {{ $code->getLabel() }}
                    </span>
                @endif
            </div>

            <div class="px-5 py-4 space-y-4">
                {{-- Who was asked, and where each of them got to. --}}
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('collaboration.label.reviewers') }}</p>

                    @if($revision->reviewers->isEmpty())
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('collaboration.message.nobody_asked') }}</p>
                    @else
                        <ul class="mt-2 space-y-1">
                            @foreach($revision->reviewers as $reviewer)
                                <li class="flex items-center gap-2 text-sm">
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-medium
                                        {{ $reviewer->hasResponded()
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'
                                            : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300' }}">
                                        {{ $reviewer->sequence }}
                                    </span>

                                    <span class="text-slate-900 dark:text-white">{{ $reviewer->user?->name ?? __('collaboration.label.removed_user') }}</span>

                                    @if($reviewer->user?->company_name)
                                        <span class="text-xs text-slate-500 dark:text-slate-400">· {{ $reviewer->user->company_name }}</span>
                                    @endif

                                    @if($reviewer->role)
                                        <span class="text-xs text-slate-500 dark:text-slate-400">· {{ \App\Models\Collaboration\DistributionEntry::roleLabel($reviewer->role) }}</span>
                                    @endif

                                    <span class="ml-auto text-xs {{ $reviewer->hasResponded() ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500' }}">
                                        {{ $reviewer->hasResponded()
                                            ? $reviewer->responded_at->appDateTime()
                                            : __('collaboration.label.waiting') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        {{-- Say which shape this is, rather than leaving the
                             reader to infer it from the numbers. --}}
                        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                            {{ $revision->reviewers->pluck('sequence')->unique()->count() === 1
                                ? __('collaboration.help.reviewed_together_everyone_same_step')
                                : __('collaboration.help.reviewed_turn_each_step_waits') }}
                        </p>
                    @endif
                </div>

                @if($revision->comments)
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Comments') }}</p>
                        <p class="mt-1 text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line">{{ $revision->comments }}</p>
                    </div>
                @endif

                @if($revision->responded_at)
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('collaboration.label.answered', [
                            'who' => $revision->respondedBy?->name ?? __('collaboration.label.removed_user'),
                            'when' => $revision->responded_at->appDateTime(),
                        ]) }}
                        @if($revision->respondedBy?->company_name) · {{ $revision->respondedBy->company_name }} @endif
                    </p>
                @endif
            </div>
        </div>
    @endforeach
</div>
