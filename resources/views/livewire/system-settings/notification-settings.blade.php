<div>
    @php
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
        $field = 'px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    @endphp

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <div class="{{ $card }}">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Task E-mails') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Everything else the system knows stays in the app. A trigger switched off here reaches nobody, whatever people have set on their own profile.') }}
            </p>
        </div>

        <div class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach(\App\Models\NotificationSetting::KEYS as $key)
                @php $setting = $this->settings[$key] ?? null; @endphp
                <div class="flex items-start justify-between gap-6 px-6 py-4" wire:key="setting-{{ $key }}">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900 dark:text-white">{{ \App\Models\NotificationSetting::label($key) }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ \App\Models\NotificationSetting::description($key) }}</p>
                        @if($setting?->updatedBy)
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                {{ __('Last changed by :name on :date', [
                                    'name' => $setting->updatedBy->name,
                                    'date' => $setting->updated_at?->format(config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A'),
                                ]) }}
                            </p>
                        @endif
                    </div>

                    <div class="shrink-0 pt-1">
                        <x-ui.toggle wire:click="toggle('{{ $key }}')" :checked="(bool) ($setting?->is_enabled ?? true)"
                                     :label="($setting?->is_enabled ?? true) ? __('On') : __('Off')" />
                    </div>
                </div>
            @endforeach
        </div>

        <div class="px-6 py-5 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
            <p class="font-medium text-slate-900 dark:text-white">{{ __('When the weekly digest goes out') }}</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Checked every hour; it sends on the day and hour set here. Nobody receives two in one week.') }}
            </p>

            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Day') }}</label>
                    <select wire:model="digestDay" class="{{ $field }}">
                        @foreach($this->days() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Hour') }}</label>
                    <select wire:model="digestHour" class="{{ $field }}">
                        @for($h = 0; $h < 24; $h++)
                            <option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00</option>
                        @endfor
                    </select>
                </div>
                <x-ui.button variant="primary" wire:click="saveDigestSchedule" icon="save">{{ __('Save') }}</x-ui.button>
            </div>
            @error('digestDay') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            @error('digestHour') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror

            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
                {{ __('This needs the scheduler running on the server. Without it, no digest and no overdue mail goes out.') }}
            </p>
        </div>
    </div>
</div>
