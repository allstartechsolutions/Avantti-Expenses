<?php

use App\Models\NotificationSetting;
use Livewire\Volt\Component;

new class extends Component {
    /** @var array<string, bool> */
    public array $preferences = [];

    /**
     * Every trigger this screen offers, task and purchasing alike.
     *
     * Saving rewrites the whole preferences array, so this list has to carry
     * every key: a group left out of it would be silently reset to "send it"
     * the next time somebody saved the page.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_merge(NotificationSetting::KEYS, NotificationSetting::PROCUREMENT_KEYS);
    }

    public function mount(): void
    {
        $stored = auth()->user()->notification_preferences ?? [];

        foreach ($this->keys() as $key) {
            $this->preferences[$key] = (bool) ($stored[$key] ?? true);
        }
    }

    public function save(): void
    {
        $user = auth()->user();

        $user->update([
            'notification_preferences' => collect($this->keys())
                ->mapWithKeys(fn (string $key) => [$key => (bool) ($this->preferences[$key] ?? true)])
                ->all(),
        ]);

        $this->dispatch('profile-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Notifications')" :subheading="__('Choose which e-mails you want to receive')">
        <div class="my-6 w-full space-y-6">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Tasks') }}</h3>

            @foreach(NotificationSetting::KEYS as $key)
                @php $sentByCompany = NotificationSetting::enabled($key); @endphp
                <div class="flex items-start justify-between gap-6" wire:key="pref-{{ $key }}">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900 dark:text-white">{{ NotificationSetting::label($key) }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ NotificationSetting::description($key) }}</p>
                        @unless($sentByCompany)
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                {{ __('Switched off for the whole company, so nobody receives it at the moment.') }}
                            </p>
                        @endunless
                    </div>
                    <div class="shrink-0 pt-1">
                        <x-ui.toggle wire:model.live="preferences.{{ $key }}"
                                     :checked="(bool) ($preferences[$key] ?? true)"
                                     :disabled="! $sentByCompany"
                                     :label="($preferences[$key] ?? true) ? __('Send it') : __('Do not send')" />
                    </div>
                </div>
            @endforeach

            <h3 class="pt-2 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-700">
                <span class="block pt-4">{{ __('Purchasing') }}</span>
            </h3>

            @foreach(NotificationSetting::PROCUREMENT_KEYS as $key)
                @php $sentByCompany = NotificationSetting::enabled($key); @endphp
                <div class="flex items-start justify-between gap-6" wire:key="pref-{{ $key }}">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900 dark:text-white">{{ NotificationSetting::label($key) }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ NotificationSetting::description($key) }}</p>
                        @unless($sentByCompany)
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                {{ __('Switched off for the whole company, so nobody receives it at the moment.') }}
                            </p>
                        @endunless
                    </div>
                    <div class="shrink-0 pt-1">
                        <x-ui.toggle wire:model.live="preferences.{{ $key }}"
                                     :checked="(bool) ($preferences[$key] ?? true)"
                                     :disabled="! $sentByCompany"
                                     :label="($preferences[$key] ?? true) ? __('Send it') : __('Do not send')" />
                    </div>
                </div>
            @endforeach

            <div class="flex items-center gap-4">
                <x-ui.button variant="primary" wire:click="save" icon="save">{{ __('Save') }}</x-ui.button>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </div>
    </x-settings.layout>
</section>
