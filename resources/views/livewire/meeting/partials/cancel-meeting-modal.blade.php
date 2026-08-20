<x-ui.modal name="cancel-meeting-modal" maxWidth="lg" layer="top">
    <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Cancel this meeting') }}</h3>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            {{ __('The meeting keeps its number and stays in the list. Nothing on its agenda is closed — those tasks stay open and will be proposed at the next meeting.') }}
        </p>

        <textarea wire:model="cancelReason" rows="3"
                  placeholder="{{ __('e.g. Postponed — half the site was rained off.') }}"
                  class="mt-4 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400"></textarea>

        <div class="mt-5 flex justify-end gap-3">
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'cancel-meeting-modal')">{{ __('Never mind') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="cancelMeeting">{{ __('Cancel Meeting') }}</x-ui.button>
        </div>
    </div>
</x-ui.modal>
