<x-ui.modal name="delete-equipment-modal" :show="true" maxWidth="lg">
    <div class="p-6">
        <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/20">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white text-center mb-2">{{ __('Delete Equipment') }}</h3>
        @if($equipment->deleteBlockers() !== [])
            <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                {{ __('This equipment has expenses tagged to it and cannot be deleted. Retire it instead.') }}
            </p>
            <div class="flex justify-end">
                <x-ui.button variant="secondary" wire:click="cancelDelete" icon="x">{{ __('Close') }}</x-ui.button>
            </div>
        @else
            <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                {{ __('Delete :name? This cannot be undone. If it simply left the fleet, retire or mark it sold instead — that keeps its history.', ['name' => $equipment->name]) }}
            </p>
            @if(array_sum($deleteCounts) > 0)
                <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">{{ __('The following data will be permanently deleted:') }}</p>
                    <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                        @if($deleteCounts['readings'] > 0)<li>{{ trans_choice(':count meter reading|:count meter readings', $deleteCounts['readings'], ['count' => $deleteCounts['readings']]) }}</li>@endif
                        @if($deleteCounts['assignments'] > 0)<li>{{ trans_choice(':count assignment|:count assignments', $deleteCounts['assignments'], ['count' => $deleteCounts['assignments']]) }}</li>@endif
                        @if($deleteCounts['plans'] > 0)<li>{{ trans_choice(':count maintenance plan|:count maintenance plans', $deleteCounts['plans'], ['count' => $deleteCounts['plans']]) }}</li>@endif
                        @if($deleteCounts['maintenances'] > 0)<li>{{ trans_choice(':count maintenance|:count maintenances', $deleteCounts['maintenances'], ['count' => $deleteCounts['maintenances']]) }}</li>@endif
                        @if($deleteCounts['findings'] > 0)<li>{{ trans_choice(':count finding|:count findings', $deleteCounts['findings'], ['count' => $deleteCounts['findings']]) }}</li>@endif
                        @if($deleteCounts['attachments'] > 0)<li>{{ trans_choice(':count attachment|:count attachments', $deleteCounts['attachments'], ['count' => $deleteCounts['attachments']]) }}</li>@endif
                    </ul>
                </div>
            @endif
            <div class="flex justify-end space-x-3">
                <x-ui.button variant="secondary" wire:click="cancelDelete" icon="x">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger" wire:click="delete" icon="trash">{{ __('Delete Equipment') }}</x-ui.button>
            </div>
        @endif
    </div>
</x-ui.modal>
