@props(['viewRoute' => null, 'editRoute' => null, 'viewAction' => null, 'editAction' => null])

<div class="flex items-center justify-end space-x-2">
    @if($viewRoute)
        <x-ui.button
            variant="secondary"
            size="sm"
            href="{{ $viewRoute }}"
            icon="eye">
            View
        </x-ui.button>
    @elseif($viewAction)
        <x-ui.button
            variant="secondary"
            size="sm"
            wire:click="{{ $viewAction }}"
            icon="eye">
            View
        </x-ui.button>
    @endif

    @if($editRoute)
        <x-ui.button
            variant="secondary"
            size="sm"
            href="{{ $editRoute }}"
            icon="edit">
            Edit
        </x-ui.button>
    @elseif($editAction)
        <x-ui.button
            variant="secondary"
            size="sm"
            wire:click="{{ $editAction }}"
            icon="edit">
            Edit
        </x-ui.button>
    @endif
</div>
