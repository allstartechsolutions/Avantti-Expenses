<div>
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $company ? __('Company Info') : __('Setup Company') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ $company ? __('Update your company information') : __('Configure your company details') }}
                    </p>
                </div>
                <div>
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('dashboard') }}"
                        icon="arrow-left">
                        {{ __('Back to Dashboard') }}
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

        <!-- Company Form -->
        <form wire:submit="saveCompany" class="space-y-8">
            <!-- Basic Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Basic Information') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Company name and primary details') }}</p>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Company Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Company Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            wire:model.live="name"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('Enter company name') }}"
                        >
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Email and Website -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Email') }}
                            </label>
                            <input
                                type="email"
                                id="email"
                                wire:model.live="email"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="company@example.com"
                            >
                            @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="website" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Website') }}
                            </label>
                            <input
                                type="url"
                                id="website"
                                wire:model.live="website"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="https://example.com"
                            >
                            @error('website') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Address Information') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Company location details') }}</p>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Street Address -->
                    <div>
                        <label for="street" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Street Address') }}
                        </label>
                        <input
                            type="text"
                            id="street"
                            wire:model.live="street"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('123 Business Street') }}"
                        >
                        @error('street') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- City, State, Postal Code -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="city" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('City') }}
                            </label>
                            <input
                                type="text"
                                id="city"
                                wire:model.live="city"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('City') }}"
                            >
                            @error('city') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="state" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('State/Province') }}
                            </label>
                            <input
                                type="text"
                                id="state"
                                wire:model.live="state"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('State') }}"
                            >
                            @error('state') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Postal Code') }}
                            </label>
                            <input
                                type="text"
                                id="postal_code"
                                wire:model.live="postal_code"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="12345"
                            >
                            @error('postal_code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contact Information') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Phone numbers and contact details') }}</p>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Phone Numbers -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Phone') }}
                            </label>
                            <input
                                type="tel"
                                id="phone"
                                wire:model.live="phone" x-data x-phone-mask
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="+1 (555) 123-4567"
                            >
                            @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="mobile" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Mobile') }}
                            </label>
                            <input
                                type="tel"
                                id="mobile"
                                wire:model.live="mobile"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="+1 (555) 987-6543"
                            >
                            @error('mobile') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="fax" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Fax') }}
                            </label>
                            <input
                                type="tel"
                                id="fax"
                                wire:model.live="fax"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="+1 (555) 123-4568"
                            >
                            @error('fax') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logo Upload Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Company Logo') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Printed at the top of estimates, invoices and reports. Use a wide logo here — the square icon on screen is set under Branding below. Max 2MB.') }}</p>
                </div>
                <div class="p-6">
                    <div class="relative">

                        @if($existingLogo && !$logoPreview)
                            <!-- Existing Logo -->
                            <div class="mb-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <img src="{{ asset('storage/' . $existingLogo) }}"
                                             alt="{{ __('Current Logo') }}"
                                             class="w-20 h-20 object-contain bg-slate-50 dark:bg-slate-700 rounded-lg border border-slate-200 dark:border-slate-600 p-2">
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Current Logo') }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Upload a new image to replace') }}</p>
                                    </div>
                                    <div>
                                        <x-ui.button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            wire:click="removeExistingLogo">
                                            {{ __('Remove') }}
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($logoPreview)
                            <!-- New Logo Preview -->
                            <div class="mb-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <img src="{{ $logoPreview }}"
                                             alt="{{ __('Logo Preview') }}"
                                             class="w-20 h-20 object-contain bg-slate-50 dark:bg-slate-700 rounded-lg border border-slate-200 dark:border-slate-600 p-2">
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('New Logo Preview') }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Image ready for upload') }}</p>
                                    </div>
                                    <div>
                                        <x-ui.button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            wire:click="removeLogo">
                                            {{ __('Remove') }}
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Was a hand-rolled drop zone; the shared component now,
                             so the logo behaves like every other upload. --}}
                        <x-ui.file-drop
                            wire:model="logo"
                            :multiple="false"
                            accept="image/*"
                            :label="__('Drop the logo here, or')"
                            :hint="__('PNG, JPG, GIF up to 2MB')">

                            @error('logo') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </x-ui.file-drop>
                    </div>
                </div>
            </div>

            <!-- Branding Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Branding') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Your own name and icons on screen — the sidebar, the header, the sign-in page, the browser tab and outgoing e-mails. Anything you leave empty keeps the default.') }}
                    </p>
                </div>
                <div class="p-6 space-y-8">
                    <!-- Display Name -->
                    <div class="max-w-md">
                        <label for="brand_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Display Name') }}
                        </label>
                        <input
                            type="text"
                            id="brand_name"
                            wire:model.live="brand_name"
                            maxlength="60"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ $name ?: __('Enter company name') }}"
                        >
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ __('A short name for the sidebar and the browser tab. Leave it empty to use the company name.') }}
                        </p>
                        @error('brand_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Live preview: what the sidebar and the browser tab will look like -->
                    @php
                        $previewIcon = $appIconPreview ?: ($existingAppIcon ? asset('storage/'.$existingAppIcon) : config('app.logo_url'));
                        $previewIconDark = $appIconDarkPreview ?: ($existingAppIconDark ? asset('storage/'.$existingAppIconDark) : $previewIcon);
                        $previewFavicon = $faviconPreview ?: ($existingFavicon ? asset('storage/'.$existingFavicon) : config('app.logo_url'));
                        $previewName = $brand_name ?: ($name ?: config('app.name'));
                    @endphp
                    <div>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">{{ __('Preview') }}</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                                <div class="px-3 py-2 bg-slate-50 dark:bg-slate-900/50 text-xs font-medium text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    {{ __('Light theme') }}
                                </div>
                                <div class="flex items-center gap-2 p-4 bg-white">
                                    <img src="{{ $previewIcon }}" alt="" class="h-8 w-8 object-contain shrink-0">
                                    <span class="text-lg font-bold text-slate-800 truncate">{{ $previewName }}</span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                                <div class="px-3 py-2 bg-slate-50 dark:bg-slate-900/50 text-xs font-medium text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    {{ __('Dark theme and sign-in page') }}
                                </div>
                                <div class="flex items-center gap-2 p-4 bg-slate-900">
                                    <img src="{{ $previewIconDark }}" alt="" class="h-8 w-8 object-contain shrink-0">
                                    <span class="text-lg font-bold text-white truncate">{{ $previewName }}</span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                                <div class="px-3 py-2 bg-slate-50 dark:bg-slate-900/50 text-xs font-medium text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    {{ __('Browser tab') }}
                                </div>
                                <div class="p-4 bg-slate-200 dark:bg-slate-700">
                                    <div class="flex items-center gap-2 rounded-t-md bg-white px-3 py-2 max-w-full">
                                        <img src="{{ $previewFavicon }}" alt="" class="h-4 w-4 object-contain shrink-0">
                                        <span class="text-xs text-slate-700 truncate">{{ $previewName }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- The three uploads -->
                    @php
                        $brandUploads = [
                            [
                                'field' => 'app_icon',
                                'pending' => $app_icon,
                                'title' => __('App Icon'),
                                'help' => __('Shown in the sidebar, the header, the sign-in page and at the top of e-mails.'),
                                'hint' => __('Square PNG, JPG or WebP — at least 128×128, up to 1MB'),
                                'accept' => 'image/png,image/jpeg,image/webp',
                                'existing' => $existingAppIcon,
                                'preview' => $appIconPreview,
                                'swatch' => 'bg-white',
                            ],
                            [
                                'field' => 'app_icon_dark',
                                'pending' => $app_icon_dark,
                                'title' => __('Dark Mode Icon'),
                                'help' => __('Optional. Used when the app is in dark mode and on the sign-in page, where a dark logo would otherwise disappear. Falls back to the app icon.'),
                                'hint' => __('Square PNG, JPG or WebP — at least 128×128, up to 1MB'),
                                'accept' => 'image/png,image/jpeg,image/webp',
                                'existing' => $existingAppIconDark,
                                'preview' => $appIconDarkPreview,
                                'swatch' => 'bg-slate-900',
                            ],
                            [
                                'field' => 'favicon',
                                'pending' => $favicon,
                                'title' => __('Favicon'),
                                'help' => __('The small icon on the browser tab and on a bookmark.'),
                                'hint' => __('ICO or PNG — 32×32 or 64×64, up to 512KB'),
                                'accept' => 'image/x-icon,image/vnd.microsoft.icon,.ico,image/png',
                                'existing' => $existingFavicon,
                                'preview' => $faviconPreview,
                                'swatch' => 'bg-white',
                            ],
                        ];
                    @endphp

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        @foreach($brandUploads as $upload)
                            <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4 space-y-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $upload['title'] }}</h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $upload['help'] }}</p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="w-16 h-16 shrink-0 rounded-lg border border-slate-200 dark:border-slate-600 p-2 flex items-center justify-center {{ $upload['swatch'] }}">
                                        <img
                                            src="{{ $upload['preview'] ?: ($upload['existing'] ? asset('storage/'.$upload['existing']) : config('app.logo_url')) }}"
                                            alt="{{ $upload['title'] }}"
                                            class="max-h-full max-w-full object-contain {{ ($upload['preview'] || $upload['existing']) ? '' : 'opacity-40' }}">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        @if($upload['preview'] || $upload['pending'])
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Ready to save') }}</p>
                                            {{-- A .ico has no thumbnail — the browser cannot preview one — so name the file instead. --}}
                                            @if(! $upload['preview'])
                                                <p class="text-xs text-slate-600 dark:text-slate-300 truncate">{{ $upload['pending']->getClientOriginalName() }}</p>
                                            @endif
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Not saved yet — save the form to apply it.') }}</p>
                                        @elseif($upload['existing'])
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('In use') }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Drop a new file to replace it.') }}</p>
                                        @else
                                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Using the default') }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Nothing uploaded — the standard icon is shown.') }}</p>
                                        @endif
                                    </div>
                                </div>

                                @if($upload['preview'] || $upload['pending'] || $upload['existing'])
                                    <div>
                                        @if($upload['preview'] || $upload['pending'])
                                            <x-ui.button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                wire:click="discardUpload('{{ $upload['field'] }}')">
                                                {{ __('Discard') }}
                                            </x-ui.button>
                                        @else
                                            <x-ui.button
                                                type="button"
                                                variant="danger"
                                                size="sm"
                                                wire:click="removeStoredFile('{{ $upload['field'] }}')"
                                                wire:confirm="{{ __('Remove this image and go back to the default?') }}">
                                                {{ __('Remove') }}
                                            </x-ui.button>
                                        @endif
                                    </div>
                                @endif

                                <x-ui.file-drop
                                    wire:model="{{ $upload['field'] }}"
                                    :multiple="false"
                                    :accept="$upload['accept']"
                                    :label="__('Drop the image here, or')"
                                    :hint="$upload['hint']">

                                    @error($upload['field']) <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                </x-ui.file-drop>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex flex-wrap items-center justify-between gap-4 py-6">
                @cannot('company.edit')
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __('You can see the company details but not change them.') }}
                    </p>
                @endcannot

                <div class="flex items-center justify-end space-x-4 ml-auto">
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('dashboard') }}">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    @can('company.edit')
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            icon="save"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50">
                            <span wire:loading.remove>{{ $company ? __('Update Company') : __('Save Company') }}</span>
                            <span wire:loading>{{ $company ? __('Updating...') : __('Saving...') }}</span>
                        </x-ui.button>
                    @endcan
                </div>
            </div>
        </form>
</div>
