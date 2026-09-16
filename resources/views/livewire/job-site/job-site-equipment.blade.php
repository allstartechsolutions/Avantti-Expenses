<x-jobsite-layout :jobSite="$jobSite" active="equipment" title="{{ __('Equipment') }}">
    @include('livewire.equipment.partials.scoped-list', ['showLocation' => false])
</x-jobsite-layout>
