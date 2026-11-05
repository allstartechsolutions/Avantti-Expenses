<?php

use App\Models\LoginEvent;
use Livewire\Volt\Component;

new class extends Component {
    // Your own sign-ins (docs/login-activity.md). No grant needed: everybody may
    // see where their own account has been used — it is how a stolen password is
    // noticed.

    public function with(): array
    {
        $user = auth()->user();

        return [
            'events' => $user->loginEvents()->limit(30)->get(),
            'failedLastWeek' => $user->loginEvents()->problems()->where('created_at', '>=', now()->subDays(7))->count(),
            'days' => LoginEvent::RETENTION_DAYS,
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Sign-in activity')" :subheading="__('Where and when your account was used. Kept :days days.', ['days' => $days])">
        @if($failedLastWeek > 0)
            <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
                {{ trans_choice(':count failed attempt on your account in the last 7 days.|:count failed attempts on your account in the last 7 days.', $failedLastWeek, ['count' => $failedLastWeek]) }}
                {{ __('If that was not you, change your password and turn on two-factor authentication.') }}
                <a href="{{ route('user-password.edit') }}" wire:navigate class="font-medium underline">{{ __('Change password') }}</a>
            </div>
        @endif

        <div class="-mx-6 sm:mx-0 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            @include('livewire.partials.login-events-list', ['events' => $events])
        </div>

        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Something you do not recognise? Change your password and tell an administrator.') }}</p>
    </x-settings.layout>
</section>
