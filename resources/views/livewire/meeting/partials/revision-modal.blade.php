{{-- Correcting a published minute: the reason is captured before anything may be typed. --}}
<x-ui.modal name="revision-modal" maxWidth="lg" layer="top">
    <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Correct the record') }}</h3>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            {{ __('This minute has been published and people have read it. The correction and its reason are kept and shown on the document, with what it said before.') }}
        </p>

        <textarea wire:model="revisionReason" rows="3"
                  placeholder="{{ __('e.g. Item 3 recorded the wrong date for the concrete pour.') }}"
                  class="mt-4 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400"></textarea>
        @error('revisionReason') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror

        <div class="mt-5 flex justify-end gap-3">
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'revision-modal')">{{ __('Never mind') }}</x-ui.button>
            <x-ui.button variant="warning" wire:click="startRevision">{{ __('Start Correcting') }}</x-ui.button>
        </div>
    </div>
</x-ui.modal>
