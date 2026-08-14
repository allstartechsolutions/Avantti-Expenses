@props(['viewRoute' => null, 'editRoute' => null, 'viewAction' => null, 'editAction' => null])

<div class="flex items-center justify-end space-x-2">
    @if($viewRoute)
        <x-ui.icon-button
            variant="secondary"
            size="sm"
            href="{{ $viewRoute }}"
            icon="eye"
            title="{{ __('View') }}" />
    @elseif($viewAction)
        <x-ui.icon-button
            variant="secondary"
            size="sm"
            wire:click="{{ $viewAction }}"
            icon="eye"
            title="{{ __('View') }}" />
    @endif

    @if($editRoute)
        <x-ui.icon-button
            variant="secondary"
            size="sm"
            href="{{ $editRoute }}"
            icon="edit"
            title="{{ __('Edit') }}" />
    @elseif($editAction)
        <x-ui.icon-button
            variant="secondary"
            size="sm"
            wire:click="{{ $editAction }}"
            icon="edit"
            title="{{ __('Edit') }}" />
    @endif
</div>
