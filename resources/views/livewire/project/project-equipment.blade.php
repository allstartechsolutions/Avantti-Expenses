<x-project-layout :project="$project" active="equipment" title="{{ __('Equipment') }}">
    @include('livewire.equipment.partials.scoped-list', ['showLocation' => true])
</x-project-layout>
