{{--
    Google Places address autocomplete for any Livewire form (client request 23, Nov 2026).

    Put <x-address-autocomplete-script /> once on the page, then on the wrapper:
        x-data="placesAddress({ country: '{{ config('app.country') }}',
                                fields: { street: 'street', city: 'city', state: 'state',
                                          postal_code: 'postal_code', neighborhood: 'neighborhood' } })"
    and x-ref="streetInput" on the street input. `fields` maps each part of the
    address to the Livewire property it fills; a part left out is not set, so a
    form without (say) coordinates is never sent a property it does not have.
    Without a Maps API key the inputs simply stay plain text fields.
--}}
@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('placesAddress', (config) => ({
                    autocomplete: null,

                    async init() {
                        if (!window.google || !window.google.maps) {
                            return;
                        }

                        try {
                            const { Autocomplete } = await google.maps.importLibrary('places');

                            this.autocomplete = new Autocomplete(this.$refs.streetInput, {
                                componentRestrictions: { country: (config.country || 'US').toLowerCase() },
                                fields: ['address_components', 'geometry'],
                                types: ['address'],
                            });

                            this.autocomplete.addListener('place_changed', () => this.fill());
                        } catch (error) {
                            console.error('Error loading Google Places:', error);
                        }
                    },

                    fill() {
                        const place = this.autocomplete.getPlace();

                        if (!place || !place.address_components) {
                            return;
                        }

                        const parts = { street_number: '', route: '', city: '', state: '', postal_code: '', neighborhood: '' };

                        for (const component of place.address_components) {
                            switch (component.types[0]) {
                                case 'street_number': parts.street_number = component.long_name; break;
                                case 'route': parts.route = component.long_name; break;
                                case 'locality': parts.city = component.long_name; break;
                                case 'administrative_area_level_2': if (!parts.city) parts.city = component.long_name; break;
                                case 'administrative_area_level_1': parts.state = component.short_name; break;
                                case 'postal_code': parts.postal_code = component.long_name; break;
                                case 'sublocality_level_1':
                                case 'sublocality': parts.neighborhood = component.long_name; break;
                            }
                        }

                        const values = {
                            street: parts.street_number ? `${parts.street_number} ${parts.route}` : parts.route,
                            city: parts.city,
                            state: parts.state,
                            postal_code: parts.postal_code,
                            neighborhood: parts.neighborhood,
                            latitude: place.geometry ? place.geometry.location.lat() : null,
                            longitude: place.geometry ? place.geometry.location.lng() : null,
                        };

                        for (const [part, property] of Object.entries(config.fields || {})) {
                            if (property && part in values) {
                                this.$wire.set(property, values[part]);
                            }
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
