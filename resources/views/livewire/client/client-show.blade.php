<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Client Details</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">View client information</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('clients.index') }}"
                    icon="arrow-left">
                    Back to Clients
                </x-ui.button>
                <x-ui.button
                    variant="primary"
                    href="{{ route('clients.edit', $client->id) }}"
                    icon="edit">
                    Edit Client
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Company Profile Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Company Information</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-6 mb-6">
                        <div class="flex-shrink-0">
                            <div class="h-20 w-20 rounded-full bg-gradient-to-r from-[#3F5189] to-[#4A5A96] flex items-center justify-center text-white text-2xl font-medium">
                                {{ $client->initials }}
                            </div>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $client->company_name }}</h2>
                            <p class="text-slate-500 dark:text-slate-400">{{ $client->email }}</p>
                            @if($client->website)
                                <a href="{{ $client->website }}" target="_blank" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    {{ $client->website }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Company Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Company Name
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->company_name }}</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Email Address
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->email }}</p>
                        </div>

                        <!-- Website -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Website
                            </label>
                            @if($client->website)
                                <a href="{{ $client->website }}" target="_blank" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    {{ $client->website }}
                                </a>
                            @else
                                <p class="text-slate-900 dark:text-white">Not provided</p>
                            @endif
                        </div>

                        <!-- Created At -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Added On
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->created_at->format('F d, Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Person Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Contact Person</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Contact Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Full Name
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->contact_name }}</p>
                        </div>

                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Title/Position
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->title ?? 'Not provided' }}</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Phone Number
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->phone ?? 'Not provided' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Address Information</h3>
                </div>
                <div class="p-6">
                    @if($client->full_address)
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Full Address
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->full_address }}</p>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Street -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Street Address
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->street ?? 'Not provided' }}</p>
                        </div>

                        <!-- City -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                City
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->city ?? 'Not provided' }}</p>
                        </div>

                        <!-- State -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                State
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->state ?? 'Not provided' }}</p>
                        </div>

                        <!-- Postal Code -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Postal Code
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->postal_code ?? 'Not provided' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Quick Actions</h3>
                </div>
                <div class="p-6 space-y-3">
                    <x-ui.button
                        variant="secondary"
                        class="w-full justify-center"
                        href="{{ route('clients.edit', $client->id) }}"
                        icon="edit">
                        Edit Client
                    </x-ui.button>

                    @if($client->email)
                        <a href="mailto:{{ $client->email }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Send Email
                        </a>
                    @endif

                    @if($client->phone)
                        <a href="tel:{{ $client->phone }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            Call Client
                        </a>
                    @endif
                </div>
            </div>

            <!-- Client Stats -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Client Information</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Client ID</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $client->id }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Added</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $client->created_at->diffForHumans() }}</span>
                    </div>
                    @if($client->created_at != $client->updated_at)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Last Updated</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $client->updated_at->diffForHumans() }}</span>
                        </div>
                    @endif
                    @if($client->createdBy)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Added By</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $client->createdBy->name }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
