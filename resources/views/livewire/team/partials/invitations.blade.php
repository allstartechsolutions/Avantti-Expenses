{{--
    Invitations sent for this project or job site and not yet taken up. Kept
    separate from the team: these people have no account, so they hold nothing.
--}}
@php
    $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
    $scope = $this->isJobSiteLevel() ? $jobSite : $project;
    $canInvite = auth()->user()?->can('team.invite', $scope);
    $canManage = auth()->user()?->can('team.manage', $scope);
@endphp

@if($this->pendingInvitations->count())
    <div class="{{ $card }} overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Invited, not yet accepted') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('No account exists until they use their link, so nobody here can sign in or hold anything yet.') }}
            </p>
        </div>

        @foreach($this->pendingInvitations as $invitation)
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/50 last:border-b-0 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $invitation->name ?: $invitation->email }}</p>
                        @if($invitation->is_guest)
                            <span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Guest') }}</span>
                        @endif
                        <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __('Invited') }}</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        @if($invitation->name){{ $invitation->email }} · @endif
                        {{ __('Sent :when', ['when' => $invitation->last_sent_at?->diffForHumans()]) }}
                        @if($invitation->send_count > 1) · {{ trans_choice('{1} sent once|[2,*] sent :count times', $invitation->send_count, ['count' => $invitation->send_count]) }} @endif
                        · {{ __('Expires :when', ['when' => $invitation->expires_at?->diffForHumans()]) }}
                        @if($invitation->invitedBy) · {{ __('by :who', ['who' => $invitation->invitedBy->name]) }} @endif
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if($canInvite)
                        <x-ui.button variant="ghost" size="sm" wire:click="resendInvitation({{ $invitation->id }})">{{ __('Send again') }}</x-ui.button>
                    @endif
                    @if($canManage)
                        <x-ui.button
                            variant="danger"
                            size="sm"
                            wire:click="withdrawInvitation({{ $invitation->id }})"
                            wire:confirm="{{ __('Withdraw this invitation? Their link stops working immediately.') }}">
                            {{ __('Withdraw') }}
                        </x-ui.button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
