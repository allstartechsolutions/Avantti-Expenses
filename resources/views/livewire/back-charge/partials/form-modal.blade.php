{{--
    Create / edit a back charge. Expects ManagesBackChargeForm on the
    component and its backChargeFormOptions() in the view data.
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
    $error = 'mt-1 text-sm text-red-600 dark:text-red-400';
    $editingBc = $editingBackChargeId ? \App\Models\BackCharge::withDeducted()->find($editingBackChargeId) : null;
@endphp

<x-ui.modal name="back-charge-form" maxWidth="full">
    <form wire:submit="saveBackCharge" class="flex min-h-screen flex-col">
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                        {{ $editingBc ? __('Edit :number', ['number' => $editingBc->number]) : __('New Back Charge') }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Money a subcontractor owes, taken out of what they are paid — all at once or in parts.') }}
                    </p>
                </div>
                <button type="button" wire:click="closeBackChargeForm" class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" title="{{ __('Close') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>

        <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6 space-y-6">
            @if($editingBc && $editingBc->getDeductedCents() > 0)
                <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-4 text-sm text-amber-800 dark:text-amber-300">
                    {{ __(':deducted of :amount has already been deducted. The amount cannot go below what was deducted, and who owes it cannot change. To waive the rest, lower the amount to :deducted and say why in the description.', [
                        'deducted' => Number::currency($editingBc->getDeducted(), config('app.currency'), config('app.locale')),
                        'amount' => Number::currency($editingBc->amount, config('app.currency'), config('app.locale')),
                    ]) }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="{{ $card }}">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-4">{{ __('Who owes it') }}</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="bc_subcontractor_id" class="{{ $label }}">{{ __('Subcontractor') }} <span class="text-red-500">*</span></label>
                                <select id="bc_subcontractor_id" wire:model.live="bc_subcontractor_id" class="{{ $field }}">
                                    <option value="">{{ __('Choose a subcontractor') }}</option>
                                    @foreach($formSubcontractors as $sub)
                                        <option value="{{ $sub->id }}">{{ $sub->company_name }}</option>
                                    @endforeach
                                </select>
                                @error('bc_subcontractor_id') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="bc_subcontractor_employee_id" class="{{ $label }}">{{ __('Employee') }} <span class="text-xs font-normal text-slate-400">({{ __('optional') }})</span></label>
                                <select id="bc_subcontractor_employee_id" wire:model="bc_subcontractor_employee_id" class="{{ $field }}" @disabled($bc_subcontractor_id === '')>
                                    <option value="">{{ $bc_subcontractor_id === '' ? __('Choose the subcontractor first') : __('The company itself') }}</option>
                                    @foreach($formEmployees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Against an employee, it is only offered on contracts that name that employee.') }}</p>
                                @error('bc_subcontractor_employee_id') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="{{ $card }}">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-4">{{ __('What happened') }}</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="bc_reason" class="{{ $label }}">{{ __('Reason') }} <span class="text-red-500">*</span></label>
                                <input id="bc_reason" type="text" wire:model="bc_reason" maxlength="255" class="{{ $field }}" placeholder="{{ __('e.g. Damaged drywall in the garage') }}">
                                @error('bc_reason') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="bc_description" class="{{ $label }}">{{ __('Description') }} <span class="text-xs font-normal text-slate-400">({{ __('optional') }})</span></label>
                                <textarea id="bc_description" wire:model="bc_description" rows="5" class="{{ $field }}" placeholder="{{ __('What was wrong, who found it, how the amount was reached…') }}"></textarea>
                                @error('bc_description') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="{{ $card }}">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Where it happened') }} <span class="text-xs font-normal text-slate-400">({{ __('optional') }})</span></h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ __('For reference: a back charge can be deducted from a payment on any site.') }}</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="bc_project_id" class="{{ $label }}">{{ __('Job Site') }}</label>
                                <select id="bc_project_id" wire:model.live="bc_project_id" class="{{ $field }}">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach($formProjects as $project)
                                        <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                                    @endforeach
                                </select>
                                @error('bc_project_id') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="bc_job_site_id" class="{{ $label }}">{{ __('Lot') }}</label>
                                <select id="bc_job_site_id" wire:model="bc_job_site_id" class="{{ $field }}" @disabled($bc_project_id === '')>
                                    <option value="">{{ $bc_project_id === '' ? __('Choose the job site first') : __('None') }}</option>
                                    @foreach($formLots as $lot)
                                        <option value="{{ $lot->id }}">{{ $lot->job_site_name }}</option>
                                    @endforeach
                                </select>
                                @error('bc_job_site_id') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="{{ $card }}">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-4">{{ __('Amount') }}</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="bc_amount" class="{{ $label }}">{{ __('Amount') }} <span class="text-red-500">*</span></label>
                                <input id="bc_amount" type="number" step="0.01" min="{{ $editingBc ? '0' : '0.01' }}" wire:model.live.debounce.400ms="bc_amount" class="{{ $field }} text-right" placeholder="0.00">
                                @error('bc_amount') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="bc_date" class="{{ $label }}">{{ __('Date') }} <span class="text-red-500">*</span></label>
                                <x-ui.date-input id="bc_date" wire:model="bc_date" />
                                @error('bc_date') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        @if($editingBc)
                            @php $newRemaining = max(0, round((is_numeric($bc_amount) ? (float) $bc_amount : 0) - $editingBc->getDeducted(), 2)); @endphp
                            <dl class="mt-5 pt-4 border-t border-slate-200 dark:border-slate-700 space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ __('Deducted so far') }}</dt><dd class="text-slate-900 dark:text-white"><x-ui.money :amount="$editingBc->getDeducted()" /></dd></div>
                                <div class="flex justify-between font-semibold"><dt class="text-slate-700 dark:text-slate-300">{{ __('Remaining after saving') }}</dt><dd class="text-slate-900 dark:text-white"><x-ui.money :amount="$newRemaining" /></dd></div>
                            </dl>
                        @endif
                    </div>
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-5 text-sm text-slate-600 dark:text-slate-300 space-y-2">
                        <p class="font-medium text-slate-900 dark:text-white">{{ __('How it is deducted') }}</p>
                        <p>{{ __('When this subcontractor is paid — in a payment batch or on Contract Payments — its open back charges are listed under the payment. Deduct all of what is left or only part; the rest stays open for the next payment.') }}</p>
                        <p>{{ __('Photos and documents are attached on the back charge\'s page once it is saved.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-end gap-3">
                <x-ui.button type="button" variant="secondary" wire:click="closeBackChargeForm">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="save" wire:loading.attr="disabled" wire:target="saveBackCharge">
                    {{ $editingBc ? __('Save Changes') : __('Create Back Charge') }}
                </x-ui.button>
            </div>
        </div>
    </form>
</x-ui.modal>
