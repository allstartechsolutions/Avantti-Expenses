@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $field = 'block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm';
    $chip = [
        'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'slate' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
    ];
@endphp
<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Login Activity') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Every sign-in, failed attempt, sign-out and lockout — who, when, from which address and device. Kept :days days.', ['days' => \App\Models\LoginEvent::RETENTION_DAYS]) }}</p>
        </div>
        <x-ui.button variant="secondary" wire:click="exportCsv" icon="arrow-down-tray">{{ __('Export CSV') }}</x-ui.button>
    </div>

    {{-- The period at a glance --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-5 text-white">
            <p class="text-sm font-medium text-white/80">{{ __('Sign-ins') }}</p>
            <p class="text-2xl font-bold mt-1">{{ number_format($summary['logins']) }}</p>
            <p class="mt-1 text-xs text-white/80">{{ trans_choice(':count person|:count people', $summary['people'], ['count' => $summary['people']]) }}</p>
        </div>
        <button type="button" wire:click="showProblems" class="text-left bg-white dark:bg-slate-800 rounded-lg shadow-sm border p-5 {{ $eventFilter === 'problems' ? 'border-red-400 ring-2 ring-red-200 dark:ring-red-900/50' : 'border-slate-200 dark:border-slate-700' }} hover:border-red-300">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Failed attempts') }}</p>
            <p class="text-2xl font-bold mt-1 {{ $summary['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ number_format($summary['failed']) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Wrong password, unknown email or two-factor code') }}</p>
        </button>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Lockouts') }}</p>
            <p class="text-2xl font-bold mt-1 {{ $summary['lockouts'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ number_format($summary['lockouts']) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Too many failed attempts in a minute') }}</p>
        </div>
        <button type="button" wire:click="$toggle('newDevicesOnly')" class="text-left bg-white dark:bg-slate-800 rounded-lg shadow-sm border p-5 {{ $newDevicesOnly ? 'border-amber-400 ring-2 ring-amber-200 dark:ring-amber-900/50' : 'border-slate-200 dark:border-slate-700' }} hover:border-amber-300">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('New devices') }}</p>
            <p class="text-2xl font-bold mt-1 {{ $summary['new_devices'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ number_format($summary['new_devices']) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('A browser or network not seen before for that person') }}</p>
        </button>
        <div class="col-span-2 lg:col-span-1 bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Period') }}</p>
            <p class="text-sm font-semibold mt-1 text-slate-900 dark:text-white">
                {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->appDate() : __('Start') }} – {{ $toDate ? \Carbon\Carbon::parse($toDate)->appDate() : __('Today') }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count event|:count events', $events->total(), ['count' => $events->total()]) }}</p>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">{{ session('message') }}</div>
    @endif

    {{-- Signed in now (stage 4): live sessions, and a way to end one. --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Signed in now') }} <span class="text-sm font-normal text-slate-500 dark:text-slate-400">({{ $sessions->count() }})</span></h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Sessions active in the last :minutes minutes. Signing somebody out also cancels their "remember me" on every device.', ['minutes' => (int) config('session.lifetime')]) }}</p>
        </div>
        @if($sessions->isEmpty())
            <p class="px-6 py-6 text-sm text-slate-500 dark:text-slate-400">{{ __('Nobody is signed in right now.') }}</p>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($sessions->groupBy(fn ($s) => $s->user->id) as $userId => $userSessions)
                    @php $person = $userSessions->first()->user; @endphp
                    <div wire:key="session-user-{{ $userId }}" class="px-6 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $person->name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $person->email }}</p>
                            </div>
                            @can('login-activity.end_sessions')
                                @if($userSessions->count() > 1 || ! $userSessions->first()->is_current)
                                    <x-ui.button variant="outline" size="sm" icon="x"
                                        wire:click="endAllSessions({{ $userId }})"
                                        wire:confirm="{{ __('Sign :name out everywhere? They will have to sign in again on every device.', ['name' => $person->name]) }}">
                                        {{ __('Sign out everywhere') }}
                                    </x-ui.button>
                                @endif
                            @endcan
                        </div>
                        <ul class="mt-2 space-y-1">
                            @foreach($userSessions as $session)
                                <li wire:key="session-{{ $session->handle }}" class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-slate-50 dark:bg-slate-900/40 px-3 py-2 text-sm">
                                    <span class="text-slate-700 dark:text-slate-300">
                                        {{ $session->device }} · <span class="font-mono">{{ $session->ip_address ?? '—' }}</span>
                                        · <span class="text-slate-500 dark:text-slate-400" title="{{ $session->last_activity->appDateTime() }}">{{ __('active :when', ['when' => $session->last_activity->diffForHumans()]) }}</span>
                                        @if($session->is_current)
                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">{{ __('This is you') }}</span>
                                        @endif
                                    </span>
                                    @can('login-activity.end_sessions')
                                        @unless($session->is_current)
                                            <button type="button" wire:click="endSession('{{ $session->handle }}')"
                                                wire:confirm="{{ __('Sign :name out of this session?', ['name' => $person->name]) }}"
                                                class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">{{ __('Sign out') }}</button>
                                        @endunless
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <div class="md:col-span-4">
                <label for="la-search" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Search') }}</label>
                <input id="la-search" type="text" wire:model.live.debounce.300ms="search" class="{{ $field }}" placeholder="{{ __('Name, email or IP address…') }}">
            </div>
            <div class="md:col-span-2">
                <label for="la-user" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Person') }}</label>
                <select id="la-user" wire:model.live="userFilter" class="{{ $field }}">
                    <option value="">{{ __('Everyone') }}</option>
                    @foreach($people as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="la-event" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Event') }}</label>
                <select id="la-event" wire:model.live="eventFilter" class="{{ $field }}">
                    <option value="">{{ __('Every event') }}</option>
                    <option value="problems">{{ __('Problems only') }}</option>
                    @foreach(\App\Models\LoginEvent::EVENTS as $event)
                        <option value="{{ $event }}">{{ \App\Models\LoginEvent::eventLabel($event) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="la-from" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('From') }}</label>
                <x-ui.date-input id="la-from" wire:model.live="fromDate" class="{{ $field }}" />
            </div>
            <div class="md:col-span-2">
                <label for="la-to" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('To') }}</label>
                <x-ui.date-input id="la-to" wire:model.live="toDate" class="{{ $field }}" />
            </div>
        </div>
        @if($this->hasFilters())
            <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('The cards above follow the filters.') }}</p>
                <x-ui.button variant="secondary" size="sm" wire:click="clearFilters" icon="x">{{ __('Clear Filters') }}</x-ui.button>
            </div>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($events->isEmpty())
            <div class="p-12 text-center">
                <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ __('No sign-in activity matches') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $this->hasFilters() ? __('Try a wider date range or clear the filters.') : __('Activity appears here from the first sign-in after this feature was switched on.') }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('When') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Event') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Person') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('IP address') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Device') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($events as $e)
                            <tr wire:key="login-event-{{ $e->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300" title="{{ $e->created_at->appDateTime() }}">
                                    {{ $e->created_at->appDateTime() }}
                                    <div class="text-xs text-slate-400 dark:text-slate-500">{{ $e->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $chip[$e->getEventColor()] }}">{{ $e->getEventLabel() }}</span>
                                    @if($e->via_remember)
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('via "remember me"') }}</div>
                                    @endif
                                    @if($e->actor)
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('by :name', ['name' => $e->actor->name]) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm">
                                    @if($e->user)
                                        @can('users.view')
                                            <a href="{{ route('users.show', $e->user) }}" wire:navigate class="font-medium text-[#3F5189] dark:text-[#8B9BD0] hover:underline">{{ $e->user->name }}</a>
                                        @else
                                            <span class="font-medium text-slate-900 dark:text-white">{{ $e->user->name }}</span>
                                        @endcan
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $e->email ?? $e->user->email }}</div>
                                    @else
                                        <span class="text-slate-700 dark:text-slate-300 break-all">{{ $e->email ?: '—' }}</span>
                                        <div class="text-xs text-red-600 dark:text-red-400">{{ __('No account with this email') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm font-mono text-slate-700 dark:text-slate-300">
                                    <button type="button" wire:click="$set('search', '{{ $e->ip_address }}')" class="hover:underline" title="{{ __('Show everything from this address') }}">{{ $e->ip_address ?? '—' }}</button>
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300" title="{{ $e->user_agent }}">
                                    {{ $e->getDeviceLabel() }}
                                    @if($e->is_new_device)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __('New device') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">{{ $events->links() }}</div>
        @endif
    </div>
</div>
