<div>
    @if($showModal)
    <div class="fixed inset-0 z-[60] overflow-y-auto" x-data="{ show: true }" x-show="show">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeModal"></div>

        <!-- Modal Content -->
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-white dark:bg-slate-800 rounded-lg shadow-xl" @click.stop>
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Add New Client</h2>
                        <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Company Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Company Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                wire:model.live="company_name"
                                placeholder="Enter company name"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @error('company_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Contact Name & Title -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Contact Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model.live="contact_name"
                                    placeholder="Enter contact name"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('contact_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Title/Position</label>
                                <input
                                    type="text"
                                    wire:model.live="title"
                                    placeholder="e.g., Project Manager"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Email & Phone -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="email"
                                    wire:model.live="email"
                                    placeholder="company@example.com"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Phone</label>
                                <input
                                    type="tel"
                                    wire:model.live="phone"
                                    placeholder="+1 (555) 123-4567"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Website -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Website</label>
                            <input
                                type="url"
                                wire:model.live="website"
                                placeholder="https://example.com"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @error('website') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Address Section with Google Autocomplete -->
                        <div x-data="clientAddressAutocomplete({ country: '{{ config('app.country') }}' })" x-init="init()">
                            <!-- Street Address with Autocomplete -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Street Address</label>
                                <input
                                    type="text"
                                    x-ref="streetInput"
                                    wire:model.live="street"
                                    placeholder="Start typing an address..."
                                    autocomplete="off"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('street') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <!-- City, State, Postal Code -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">City</label>
                                    <input
                                        type="text"
                                        wire:model.live="city"
                                        placeholder="City"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    @error('city') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">State</label>
                                    <input
                                        type="text"
                                        wire:model.live="state"
                                        placeholder="State"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    @error('state') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        {{ config('app.country') === 'BR' ? 'CEP' : 'Postal Code' }}
                                    </label>
                                    <input
                                        type="text"
                                        wire:model.live="postal_code"
                                        placeholder="{{ config('app.country') === 'BR' ? '00000-000' : '12345' }}"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    @error('postal_code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeModal">
                            Cancel
                        </x-ui.button>
                        <x-ui.button type="button" variant="primary" icon="save" wire:click="saveClient"
                            wire:loading.attr="disabled" wire:loading.class="opacity-50">
                            <span wire:loading.remove wire:target="saveClient">Save Client</span>
                            <span wire:loading wire:target="saveClient">Saving...</span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('clientAddressAutocomplete', (config) => ({
        autocomplete: null,
        country: config.country || 'US',

        async init() {
            if (!window.google || !window.google.maps) {
                return;
            }

            try {
                const { Autocomplete } = await google.maps.importLibrary("places");

                this.autocomplete = new Autocomplete(this.$refs.streetInput, {
                    componentRestrictions: { country: this.country.toLowerCase() },
                    fields: ['address_components', 'formatted_address'],
                    types: ['address']
                });

                this.autocomplete.addListener('place_changed', () => {
                    this.handlePlaceSelect();
                });
            } catch (error) {
                console.error('Error loading Google Places:', error);
            }
        },

        handlePlaceSelect() {
            const place = this.autocomplete.getPlace();

            if (!place.address_components) {
                return;
            }

            let streetNumber = '';
            let route = '';
            let city = '';
            let state = '';
            let postalCode = '';

            for (const component of place.address_components) {
                const type = component.types[0];

                switch (type) {
                    case 'street_number':
                        streetNumber = component.long_name;
                        break;
                    case 'route':
                        route = component.long_name;
                        break;
                    case 'locality':
                        city = component.long_name;
                        break;
                    case 'administrative_area_level_2':
                        if (!city) city = component.long_name;
                        break;
                    case 'administrative_area_level_1':
                        state = component.short_name;
                        break;
                    case 'postal_code':
                        postalCode = component.long_name;
                        break;
                }
            }

            const street = streetNumber ? `${streetNumber} ${route}` : route;

            @this.set('street', street);
            @this.set('city', city);
            @this.set('state', state);
            @this.set('postal_code', postalCode);
        }
    }));
});
</script>
@endpush
