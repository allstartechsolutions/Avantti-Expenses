@php
    $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';

    $people = $this->people;
    $currency = config('app.currency');
    $locale = config('app.locale');
@endphp

<div>
    <div class="mb-4">
        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
            {{ __('Who can approve, award, convert or pay — and up to how much. Every line is the answer the application itself gives, not a copy of it, so this page cannot drift from what actually happens.') }}
        </p>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-4">
        <input type="text" wire:model.live.debounce.300ms="search"
               class="{{ $field }} md:max-w-sm" placeholder="{{ __('Search by name or e-mail…') }}">

        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" wire:model.live="onlyAuthorised"
                   class="h-4 w-4 rounded border-slate-300 text-[#3F5189] focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700">
            <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('Only people who can approve something') }}</span>
        </label>
    </div>

    <div class="{{ $card }} overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/40">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Person') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('May approve') }}</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Up to') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Where the limit comes from') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($people as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                            <td class="px-5 py-4 align-top">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $row['user']->name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $row['user']->role?->name ? ucfirst($row['user']->role->name) : __('No role') }}
                                    @if ($row['exceptions'] > 0)
                                        ·
                                        <a href="{{ route('users.access', $row['user']->id) }}" class="text-[#3F5189] dark:text-blue-400 hover:underline">
                                            {{ trans_choice('{1} :count exception|[2,*] :count exceptions', $row['exceptions'], ['count' => $row['exceptions']]) }}
                                        </a>
                                    @endif
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                @if ($row['actions'] === [])
                                    <span class="text-sm text-slate-400 dark:text-slate-500">{{ __('Nothing') }}</span>
                                @else
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($row['actions'] as $action)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                                                  title="{{ $action['ability'] }}">
                                                {{ $action['area'] }} — {{ $action['name'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <td class="px-5 py-4 align-top text-right whitespace-nowrap">
                                @if ($row['limit'] === null)
                                    <span class="text-sm font-semibold {{ $row['actions'] === [] ? 'text-slate-400 dark:text-slate-500' : 'text-amber-700 dark:text-amber-400' }}">
                                        {{ __('No limit') }}
                                    </span>
                                @else
                                    <span class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ Number::currency($row['limit'] / 100, $currency, $locale) }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="text-sm text-slate-600 dark:text-slate-300">{{ $row['limit_source'] }}</div>
                                @foreach ($row['projects'] as $project)
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ __('On :scope: :amount', [
                                            'scope' => $project['label'],
                                            'amount' => Number::currency($project['limit'] / 100, $currency, $locale),
                                        ]) }}
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center">
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    @if ($search !== '')
                                        {{ __('Nobody matches ":term".', ['term' => $search]) }}
                                    @elseif ($onlyAuthorised)
                                        {{ __('Nobody but an administrator can approve anything yet. Approval is granted on a role, on one person, or on a project\'s team.') }}
                                    @else
                                        {{ __('There are no users.') }}
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 max-w-3xl">
        {{ __('A limit set on a project\'s team wins on that project only; everywhere else the person\'s own limit applies, and their role\'s behind it. Administrators are never capped.') }}
    </p>
</div>
