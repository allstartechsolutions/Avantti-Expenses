{{-- Rows: [['employee' => SubcontractorEmployee, 'reasons' => [...]], …]. Picks into link_target_id. --}}
@php $th = 'px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap'; @endphp
<div class="overflow-x-auto -mx-5">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
        <thead>
            <tr>
                <th class="{{ $th }}"></th>
                <th class="{{ $th }}">{{ __('Name') }}</th>
                <th class="{{ $th }}">{{ __('Company') }}</th>
                <th class="{{ $th }}">{{ __('Title') }}</th>
                <th class="{{ $th }}">{{ __('Phone') }}</th>
                <th class="{{ $th }}">{{ __('Email') }}</th>
                <th class="{{ $th }}">{{ __('Tax ID') }}</th>
                <th class="{{ $th }}">{{ __('Also at') }}</th>
                @if($withReasons)<th class="{{ $th }}">{{ __('Matched on') }}</th>@endif
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($rows as $row)
                @php $candidate = $row['employee']; $chosen = $link_target_id === $candidate->id; @endphp
                <tr wire:click="chooseLinkTarget({{ $candidate->id }})" class="cursor-pointer {{ $chosen ? 'bg-[#3F5189]/10 dark:bg-[#4A5A96]/20' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50' }}">
                    <td class="px-4 py-3">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border {{ $chosen ? 'border-[#3F5189] bg-[#3F5189] text-white' : 'border-slate-300 dark:border-slate-600' }}">
                            @if($chosen)<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>@endif
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white whitespace-nowrap">{{ $candidate->name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-900 dark:text-white">{{ $candidate->subcontractor?->company_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $candidate->title ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $candidate->formatted_phone ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $candidate->email ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm font-mono text-slate-900 dark:text-white whitespace-nowrap">{{ $candidate->tax_id ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                        {{ $candidate->siblings()->map(fn ($s) => $s->subcontractor?->company_name)->filter()->join(', ') ?: '—' }}
                    </td>
                    @if($withReasons)
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($row['reasons'] as $reason)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $reason === 'tax_id' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">
                                        {{ \App\Models\SubcontractorEmployee::matchReasonLabel($reason) }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
