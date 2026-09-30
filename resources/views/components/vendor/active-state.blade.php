@props(['vendor', 'canEdit' => false])

{{--
    The vendor's on/off control: the switch for somebody who may edit
    vendors, the plain chip for everybody else. The switch calls
    `toggleActive()` on the enclosing Livewire component (TogglesVendorActive).
    The wire:key carries the state so a flip rebuilds the checkbox instead of
    relying on the morph to change `checked` in place.
--}}
@if($canEdit)
    <div wire:key="vendor-active-{{ $vendor->id }}-{{ $vendor->is_active ? 'on' : 'off' }}">
        <x-ui.toggle
            wire:click="toggleActive({{ $vendor->id }})"
            :checked="$vendor->is_active"
            :onLabel="__('Active')"
            :offLabel="__('Inactive')" />
    </div>
@else
    <x-vendor.active-badge :active="$vendor->is_active" />
@endif
