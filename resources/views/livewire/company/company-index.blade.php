@section('content')
    <div class="max-w-7xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Companies</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage your company database</p>
                </div>
                <div>
                    <x-ui.button 
                        variant="primary" 
                        href="{{ route('companies.create') }}"
                        icon="plus">
                        Add Company
                    </x-ui.button>
                </div>
            </div>
        </div>

        <!-- Companies List -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Company List</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Coming soon - full company listing and management</p>
            </div>
            <div class="p-6">
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <p class="mt-4 text-slate-500 dark:text-slate-400">Company list will be available after the create functionality is tested</p>
                    <div class="mt-4">
                        <x-ui.button 
                            variant="primary" 
                            href="{{ route('companies.create') }}"
                            icon="plus">
                            Create Your First Company
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
