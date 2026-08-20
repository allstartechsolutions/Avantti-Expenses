<?php

use App\Models\NotificationSetting;
use Livewire\Volt\Component;

new class extends Component {
    /** @var array<string, bool> */
    public array $preferences = [];

    public function mount(): void
    {
        $stored = auth()->user()->notification_preferences ?? [];

        foreach (NotificationSetting::KEYS as $key) {
            $this->preferences[$key] = (bool) ($stored[$key] ?? true);
        }
    }

    public function save(): void
    {
        $user = auth()->user();

        $user->update([
            'notification_preferences' => collect(NotificationSetting::KEYS)
                ->mapWithKeys(fn (string $key) => [$key => (bool) ($this->preferences[$key] ?? true)])
                ->all(),
        ]);

        $this->dispatch('profile-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Notifications')" :subheading="__('Choose which task e-mails you want to receive')">
        <div class="my-6 w-full space-y-6">
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

            <div class="flex items-center gap-4">
                <x-ui.button variant="primary" wire:click="save" icon="save">{{ __('Save') }}</x-ui.button>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </div>
    </x-settings.layout>
</section>
