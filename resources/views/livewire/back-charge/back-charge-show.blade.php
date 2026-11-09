@php
    $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
    $dt = 'text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400';
    $dd = 'mt-1 text-sm text-slate-900 dark:text-white';
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $pct = $bc->amount > 0 ? min(100, round($bc->getDeducted() / $bc->amount * 100)) : 0;
    $taken = $bc->deductions->filter->isTaken();
    $reserved = $bc->deductions->reject->isTaken();
@endphp
<div>
    <div class="mb-6">
        <a href="{{ route('back-charges.index') }}" wire:navigate class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">&larr; {{ __('Back Charges') }}</a>
        <div class="mt-2 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $bc->number }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $bc->isFullyDeducted() ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' }}">{{ $bc->getStatusLabel() }}</span>
                </div>
                <p class="mt-1 text-slate-700 dark:text-slate-300 break-words">{{ $bc->reason }}</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @can('back-charges.edit')
                    <x-ui.button variant="secondary" wire:click="openBackChargeEdit({{ $bc->id }})" icon="edit">{{ __('Edit') }}</x-ui.button>
                @endcan
                @can('back-charges.delete')
                    @if($bc->canBeDeleted())
                        <x-ui.button variant="danger" wire:click="delete" wire:confirm="{{ __('Delete :number? Nothing has been deducted from it.', ['number' => $bc->number]) }}" icon="trash">{{ __('Delete') }}</x-ui.button>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">{{ session('error') }}</div>
    @endif

    {{-- The numbers --}}
    <div class="{{ $card }} p-6 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Amount') }}</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><x-ui.money :amount="$bc->amount" /></p>
            </div>
            <div>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Deducted') }}</p>
                <p class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400"><x-ui.money :amount="$bc->getDeducted()" /></p>
                @if($reserved->isNotEmpty())
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('incl. :amount on batch lines not approved yet', ['amount' => Number::currency($reserved->sum('amount'), config('app.currency'), config('app.locale'))]) }}</p>
                @endif
            </div>
            <div>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Remaining') }}</p>
                <p class="mt-1 text-2xl font-semibold {{ $bc->isFullyDeducted() ? 'text-slate-400 dark:text-slate-500' : 'text-amber-600 dark:text-amber-400' }}"><x-ui.money :amount="$bc->getRemaining()" /></p>
            </div>
        </div>
        <div class="mt-5">
            <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400 mb-1">
                <span>{{ __(':percent% deducted', ['percent' => $pct]) }}</span>
                <span>{{ trans_choice(':count deduction|:count deductions', $bc->deductions->count(), ['count' => $bc->deductions->count()]) }}</span>
            </div>
            <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                <div class="h-full bg-green-500" style="width: {{ $pct }}%"></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Details --}}
        <div class="{{ $card }} p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Details') }}</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <dt class="{{ $dt }}">{{ __('Subcontractor') }}</dt>
                    <dd class="{{ $dd }}">
                        @if($bc->subcontractor)
                            <a href="{{ route('subcontractors.show', $bc->subcontractor_id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $bc->subcontractor->company_name }}</a>
                        @else — @endif
                    </dd>
                </div>
                <div>
                    <dt class="{{ $dt }}">{{ __('Employee') }}</dt>
                    <dd class="{{ $dd }}">{{ $bc->subcontractorEmployee?->name ?? __('The company itself') }}</dd>
                </div>
                <div>
                    <dt class="{{ $dt }}">{{ __('Date') }}</dt>
                    <dd class="{{ $dd }}">{{ $bc->date->appDate() }}</dd>
                </div>
                <div>
                    <dt class="{{ $dt }}">{{ __('Where it happened') }}</dt>
                    <dd class="{{ $dd }}">
                        @if($bc->project)
                            <a href="{{ route('projects.show', $bc->project_id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $bc->project->project_name }}</a>
                            @if($bc->jobSite)
                                <span class="text-slate-400">/</span>
                                <a href="{{ route('jobsites.show', $bc->job_site_id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $bc->jobSite->job_site_name }}</a>
                            @endif
                        @else
                            <span class="text-slate-400 dark:text-slate-500">{{ __('Not recorded') }}</span>
                        @endif
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="{{ $dt }}">{{ __('Reason') }}</dt>
                    <dd class="{{ $dd }} break-words">{{ $bc->reason }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="{{ $dt }}">{{ __('Description') }}</dt>
                    <dd class="{{ $dd }} whitespace-pre-line break-words">{{ $bc->description ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Audit --}}
        <div class="{{ $card }} p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Record') }}</h2>
            <dl class="space-y-4">
                <div>
                    <dt class="{{ $dt }}">{{ __('Created') }}</dt>
                    <dd class="{{ $dd }}">{{ $bc->created_at->appDateTime() }}</dd>
                    <dd class="text-xs text-slate-500 dark:text-slate-400">{{ $bc->createdBy?->name ?? __('Unknown') }}</dd>
                </div>
                <div>
                    <dt class="{{ $dt }}">{{ __('Last updated') }}</dt>
                    <dd class="{{ $dd }}">{{ $bc->updated_at->appDateTime() }}</dd>
                    <dd class="text-xs text-slate-500 dark:text-slate-400">{{ $bc->updatedBy?->name ?? __('Unknown') }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Deductions --}}
    <div class="{{ $card }} overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Deductions') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Each part taken out of a payment, and the contract it lowered.') }}</p>
        </div>
        @if($bc->deductions->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Date') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Contract') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Taken from') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Status') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('By') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($bc->deductions as $d)
                            <tr wire:key="deduction-{{ $d->id }}">
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ ($d->payment?->payment_date ?? $d->batchItem?->batch?->payment_date ?? $d->created_at)->appDate() }}</td>
                                <td class="px-6 py-3 text-sm">
                                    @if($d->contract)
                                        <a href="{{ route('contracts.show', $d->contract_id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $d->contract->contract_number }}</a>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $d->contract->project?->project_name }}@if($d->contract->jobSite) / {{ $d->contract->jobSite->job_site_name }}@endif</div>
                                    @else — @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300">
                                    @if($d->batchItem?->batch)
                                        <a href="{{ route('payment-batches.show', $d->batchItem->payment_batch_id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('Batch: :name', ['name' => $d->batchItem->batch->name]) }}</a>
                                    @elseif($d->payment)
                                        {{ __('Contract payment') }}@if($d->payment->reference_number) · {{ $d->payment->reference_number }}@endif
                                    @else — @endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-xs">
                                    @if($d->isTaken())
                                        <span class="inline-flex px-2 py-0.5 rounded-full font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ __('Deducted') }}</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400" title="{{ __('Set aside on a batch line that is not approved yet. Rejecting the line gives it back.') }}">{{ __('Pending approval') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $d->createdBy?->name ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-medium text-slate-900 dark:text-white"><x-ui.money :amount="$d->amount" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-900/50">
                        <tr class="font-semibold">
                            <td colspan="5" class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total deducted') }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white"><x-ui.money :amount="$bc->getDeducted()" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('Nothing deducted yet. When :company is paid — in a payment batch or on Contract Payments — this back charge is offered under the payment.', ['company' => $bc->subcontractor?->company_name ?? __('the subcontractor')]) }}
            </div>
        @endif
    </div>

    {{-- Files --}}
    <div class="{{ $card }} p-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Photos and documents') }}</h2>
        <livewire:shared.attachments model-type="back-charge" :model-id="$bc->id" :key="'back-charge-attachments-'.$bc->id" />
    </div>

    @include('livewire.back-charge.partials.form-modal')
</div>
