@php
    $field = 'w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white text-slate-900 placeholder-slate-400';
    $label = 'block text-sm font-medium text-slate-700 mb-1';
@endphp

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 sm:p-8">
        @if($problem)
            {{-- Say which of the four things went wrong, and what to do — a
                 blank "invalid link" leaves somebody stuck with nobody to ask. --}}
            <div class="text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.71-3.03l-6.93-11a2 2 0 00-3.42 0l-6.93 11A2 2 0 005.07 19z"></path>
                    </svg>
                </div>

                <h1 class="mt-4 text-lg font-semibold text-slate-900">
                    @switch($problem)
                        @case('accepted') {{ __('This invitation has already been used.') }} @break
                        @case('expired') {{ __('This invitation has expired.') }} @break
                        @case('revoked') {{ __('This invitation was withdrawn.') }} @break
                        @case('already-a-user') {{ __('You already have an account.') }} @break
                        @default {{ __('This link is not valid.') }}
                    @endswitch
                </h1>

                <p class="mt-2 text-sm text-slate-500">
                    @switch($problem)
                        @case('accepted')
                        @case('already-a-user')
                            {{ __('Sign in with your e-mail address and password. Use "forgotten password" if you need to.') }}
                            @break
                        @case('expired')
                            {{ __('Ask whoever invited you to send it again — it only takes a moment.') }}
                            @break
                        @case('revoked')
                            {{ __('Ask whoever invited you if this was meant to happen.') }}
                            @break
                        @default
                            {{ __('Check that you copied the whole link from the e-mail, or ask for a new one.') }}
                    @endswitch
                </p>

                <div class="mt-6">
                    <a href="{{ route('login') }}" class="inline-block px-4 py-2 rounded-lg bg-[#3F5189] text-white text-sm font-medium">{{ __('Go to sign in') }}</a>
                </div>
            </div>
        @else
            <h1 class="text-xl font-semibold text-slate-900">{{ __('Set up your account') }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Choose a password and you are in. Your e-mail address is :email.', ['email' => $invitation->email]) }}
            </p>

            <form wire:submit="accept" class="mt-6 space-y-4">
                <div>
                    <label class="{{ $label }}">{{ __('Your name') }} <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" class="{{ $field }}" autocomplete="name" autofocus>
                    @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('Password') }} <span class="text-red-500">*</span></label>
                    <input type="password" wire:model="password" class="{{ $field }}" autocomplete="new-password">
                    @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('Confirm password') }} <span class="text-red-500">*</span></label>
                    <input type="password" wire:model="password_confirmation" class="{{ $field }}" autocomplete="new-password">
                </div>

                <button type="submit"
                        class="w-full px-4 py-2.5 rounded-lg bg-[#3F5189] text-white text-sm font-medium hover:bg-[#354673]">
                    <span wire:loading.remove wire:target="accept">{{ __('Set up my account') }}</span>
                    <span wire:loading wire:target="accept">{{ __('Setting up…') }}</span>
                </button>
            </form>
        @endif
    </div>
</div>
