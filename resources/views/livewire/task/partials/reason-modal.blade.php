{{--
    Reopening, blocking and cancelling all have to say why, so they share one
    prompt. A record of a task that came back with no reason is worth nothing.
--}}
@php
    $copy = [
        'reopen' => [
            'title' => __('Reopen this task'),
            'help' => __('It goes back to In Progress and the completion is cleared. Say what was not finished.'),
            'placeholder' => __('e.g. The water is pooling again after the first rain.'),
            'button' => __('Reopen'),
            'variant' => 'warning',
        ],
        'block' => [
            'title' => __('Block this task'),
            'help' => __('Say what it is waiting on. It stays open and still counts as overdue if it passes its date.'),
            'placeholder' => __('e.g. Waiting on the engineer to approve the detail.'),
            'button' => __('Block'),
            'variant' => 'warning',
        ],
        'cancel' => [
            'title' => __('Cancel this task'),
            'help' => __('It stops counting as open work. The task and everything on it are kept.'),
            'placeholder' => __('e.g. The client dropped this from the scope.'),
            'button' => __('Cancel Task'),
            'variant' => 'danger',
        ],
    ][$reasonAction] ?? null;
@endphp

<x-ui.modal name="task-reason-modal" maxWidth="lg" layer="top">
    @if($copy)
        <form wire:submit="submitReason" class="p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $copy['title'] }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $copy['help'] }}</p>

            <textarea
                wire:model="reasonText"
                rows="4"
                placeholder="{{ $copy['placeholder'] }}"
                class="mt-4 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500"></textarea>
            @error('reasonText') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror

            <div class="mt-5 flex justify-end gap-3">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'task-reason-modal')">
                    {{ __('Never mind') }}
                </x-ui.button>
                <x-ui.button type="submit" :variant="$copy['variant']">{{ $copy['button'] }}</x-ui.button>
            </div>
        </form>
    @endif
</x-ui.modal>
