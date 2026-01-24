<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Project</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Update project information</p>
            </div>
            <div>
                <x-ui.button
                    variant="secondary"
                    href="{{ route('projects.index') }}"
                    icon="arrow-left">
                    Back to Projects
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Error Message -->
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- Edit Form -->
    <form wire:submit="updateProject" class="space-y-8">
        <!-- Client Selection Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Client Selection</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Select the client for this project</p>
            </div>
            <div class="p-6">
                <!-- Client Search Dropdown -->
                <div class="relative">
                    <label for="clientSearch" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Search Client <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="clientSearch"
                        wire:model.live.debounce.300ms="clientSearch"
                        wire:focus="$set('showClientDropdown', true)"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Type to search for a client..."
                        autocomplete="off"
                    >
                    @error('client_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                    <!-- Dropdown Results -->
                    @if($showClientDropdown && count($clients) > 0)
                        <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-md shadow-lg max-h-60 overflow-auto">
                            @foreach($clients as $client)
                                <button
                                    type="button"
                                    wire:click="selectClient({{ $client->id }})"
                                    class="w-full text-left px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-600 border-b border-slate-100 dark:border-slate-600 last:border-b-0">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-full flex items-center justify-center">
                                            <span class="text-sm font-medium text-white">{{ $client->initials }}</span>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $client->company_name }}</p>
                                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $client->contact_name }} - {{ $client->email }}</p>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @elseif($showClientDropdown && !empty($clientSearch) && count($clients) === 0)
                        <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-md shadow-lg p-4">
                            <p class="text-sm text-slate-500 dark:text-slate-400">No clients found. <a href="{{ route('clients.create') }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">Create a new client</a></p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Project Information Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Project Information</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Basic project details</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Project Name -->
                <div>
                    <label for="project_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Project Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="project_name"
                        wire:model.live="project_name"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Enter project name"
                    >
                    @error('project_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- Initial Amount and Status -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="initial_amount" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Initial Amount <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-slate-500 dark:text-slate-400">$</span>
                            <input
                                type="number"
                                step="0.01"
                                id="initial_amount"
                                wire:model.live="initial_amount"
                                class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="0.00"
                            >
                        </div>
                        @error('initial_amount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Project Status <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="status"
                            wire:model.live="status"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @foreach($statuses as $statusOption)
                                <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                        @error('status') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Description
                    </label>
                    <textarea
                        id="description"
                        wire:model.live="description"
                        rows="4"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Enter project description..."></textarea>
                    @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <!-- Contact Information Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Contact Information</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Primary contact for this project (editable)</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Contact Person and Phone -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Contact Person <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="contact_person"
                            wire:model.live="contact_person"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="Enter contact person name"
                        >
                        @error('contact_person') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Phone Number
                        </label>
                        <input
                            type="tel"
                            id="phone"
                            wire:model.live="phone"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="+1 (555) 123-4567"
                        >
                        @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        wire:model.live="email"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="contact@example.com"
                    >
                    @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <!-- Address Information Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700"
            x-data="addressAutocomplete({
                country: '{{ config('app.country') }}',
                streetInputId: 'project-edit-street'
            })"
            x-init="init()">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Project Address</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Start typing to search for an address or enter manually</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Street Address with Autocomplete -->
                <div>
                    <label for="project-edit-street" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Street Address
                    </label>
                    <input
                        type="text"
                        id="project-edit-street"
                        x-ref="streetInput"
                        wire:model.live="street"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Start typing an address..."
                        autocomplete="off"
                    >
                    @error('street') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- Address Line 2 -->
                <div>
                    <label for="address_2" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Address Line 2
                    </label>
                    <input
                        type="text"
                        id="address_2"
                        wire:model.live="address_2"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Suite, Apt, Unit, etc."
                    >
                    @error('address_2') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                @if(config('app.country') === 'BR')
                <!-- Neighborhood (Brazil only) -->
                <div>
                    <label for="neighborhood" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Neighborhood (Bairro)
                    </label>
                    <input
                        type="text"
                        id="neighborhood"
                        wire:model.live="neighborhood"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Bairro"
                    >
                    @error('neighborhood') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                @endif

                <!-- City, State, Postal Code -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="city" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            City
                        </label>
                        <input
                            type="text"
                            id="city"
                            wire:model.live="city"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="City"
                        >
                        @error('city') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="state" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            State
                        </label>
                        <input
                            type="text"
                            id="state"
                            wire:model.live="state"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="State"
                        >
                        @error('state') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ config('app.country') === 'BR' ? 'CEP' : 'Postal Code' }}
                        </label>
                        <input
                            type="text"
                            id="postal_code"
                            wire:model.live="postal_code"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ config('app.country') === 'BR' ? '00000-000' : '12345' }}"
                        >
                        @error('postal_code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Hidden lat/long fields -->
                <input type="hidden" wire:model="latitude">
                <input type="hidden" wire:model="longitude">
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4 py-6">
            <x-ui.button
                variant="secondary"
                href="{{ route('projects.index') }}">
                Cancel
            </x-ui.button>
            <x-ui.button
                type="submit"
                variant="primary"
                icon="save"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50">
                <span wire:loading.remove>Update Project</span>
                <span wire:loading>Updating...</span>
            </x-ui.button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('addressAutocomplete', (config) => ({
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
                    fields: ['address_components', 'geometry', 'formatted_address'],
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

            if (!place.geometry) {
                return;
            }

            let streetNumber = '';
            let route = '';
            let city = '';
            let state = '';
            let postalCode = '';
            let neighborhood = '';

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
                    case 'sublocality_level_1':
                    case 'sublocality':
                        neighborhood = component.long_name;
                        break;
                }
            }

            const street = streetNumber ? `${streetNumber} ${route}` : route;

            @this.set('street', street);
            @this.set('city', city);
            @this.set('state', state);
            @this.set('postal_code', postalCode);
            @this.set('neighborhood', neighborhood);
            @this.set('latitude', place.geometry.location.lat());
            @this.set('longitude', place.geometry.location.lng());
        }
    }));
});
</script>
@endpush
