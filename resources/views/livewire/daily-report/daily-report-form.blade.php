<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-2 text-sm text-slate-500 dark:text-slate-400 mb-2">
                    <a href="{{ route('projects.index') }}" class="hover:text-[#3F5189] dark:hover:text-[#4A5A96]">{{ __('Projects') }}</a>
                    <span>/</span>
                    <a href="{{ route('projects.overview', $project->id) }}" class="hover:text-[#3F5189] dark:hover:text-[#4A5A96]">{{ $project->project_name }}</a>
                    @if($jobSite)
                        <span>/</span>
                        <a href="{{ route('jobsites.overview', $jobSite->id) }}" class="hover:text-[#3F5189] dark:hover:text-[#4A5A96]">{{ $jobSite->job_site_name }}</a>
                    @endif
                    <span>/</span>
                    <span class="text-slate-900 dark:text-white">{{ $mode === 'edit' ? 'Edit' : 'Create' }} Daily Report</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $mode === 'edit' ? 'Edit' : 'Create' }} Daily Report</h1>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    wire:click="cancel"
                    icon="arrow-left">
                    {{ __('Cancel') }}
                </x-ui.button>
                @if($mode === 'edit' && $dailyReport)
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('dailyreports.pdf.download', $dailyReport) }}"
                        title="{{ __('Download PDF') }}">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        PDF
                    </x-ui.button>
                @endif
                <x-ui.button
                    variant="primary"
                    wire:click="save"
                    icon="save">
                    {{ __('Save Report') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    @if (session()->has('task_message'))
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300">
            {{ session('task_message') }}
        </div>
    @endif

    @if (session()->has('weather_message'))
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300">
            {{ session('weather_message') }}
        </div>
    @endif

    @if (session()->has('observation_message'))
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300">
            {{ session('observation_message') }}
        </div>
    @endif

    @if (session()->has('manpower_message'))
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300">
            {{ session('manpower_message') }}
        </div>
    @endif

    <!-- Report Information Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Report Information') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Project') }}</label>
                <p class="text-slate-900 dark:text-white">{{ $project->project_name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Location') }}</label>
                @if($jobSite)
                    <p class="text-slate-900 dark:text-white">{{ $jobSite->job_site_name }}</p>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                        {{ __('Project (General)') }}
                    </span>
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Address') }}</label>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ $jobSite?->full_address ?? $project->full_address }}</p>
            </div>
            <div>
                <label for="report_date" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Report Date *') }}</label>
                <input
                    type="date"
                    id="report_date"
                    wire:model="report_date"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                    required>
                @error('report_date') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Prepared By') }}</label>
                <p class="text-slate-900 dark:text-white">{{ Auth::user()->name }}</p>
            </div>
        </div>
    </div>

    <!-- Weather Report Section -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Weather Report') }}</h2>
            <div class="flex items-center space-x-2">
                @if($weather)
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        wire:click="clearWeather">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
                <x-ui.button
                    variant="primary"
                    size="sm"
                    wire:click="fetchWeather"
                    wire:loading.attr="disabled"
                    wire:target="fetchWeather">
                    <span wire:loading.remove wire:target="fetchWeather">
                        {{ $weather ? 'Refresh Weather' : 'Fetch Weather' }}
                    </span>
                    <span wire:loading wire:target="fetchWeather" class="inline-flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Fetching...') }}
                    </span>
                </x-ui.button>
            </div>
        </div>

        @if($weatherError)
            <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300 mb-4">
                {{ $weatherError }}
            </div>
        @endif

        @if($weather)
            @php
                $tempUnit = $this->getTemperatureUnit();
            @endphp

            <!-- Weather Metrics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Temperature Card -->
                <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Temperature') }}</h3>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Low') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatTemperature($weather['temp_low']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('High') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatTemperature($weather['temp_high']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Avg') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatTemperature($weather['temp_avg']) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Precipitation Card -->
                <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Precipitation Since') }}</h3>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Midnight') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatPrecipitation($weather['precip_midnight']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">2 Days</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatPrecipitation($weather['precip_2_days']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">3 Days</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatPrecipitation($weather['precip_3_days']) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Humidity Card -->
                <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Humidity') }}</h3>
                    </div>
                    <div class="grid grid-cols-4 gap-1 text-center">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Low') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $weather['humidity_low'] ?? '-' }}%</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Avg') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $weather['humidity_avg'] ?? '-' }}%</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('High') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $weather['humidity_high'] ?? '-' }}%</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Dew') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatTemperature($weather['dew_point']) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Wind Speed Card -->
                <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Wind Speed') }}</h3>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Avg') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatWindSpeed($weather['wind_avg']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Max') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatWindSpeed($weather['wind_max']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Gust') }}</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $this->formatWindSpeed($weather['wind_gust']) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Snapshot -->
            @if(!empty($weather['snapshots']))
                <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                    <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">{{ __('Daily Snapshot') }}</h3>
                    <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                        @foreach($weather['snapshots'] as $snapshot)
                            <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 text-center">
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ $snapshot['time'] }}</p>
                                <div class="mb-2">
                                    @php
                                        $icon = $snapshot['icon'] ?? 'cloudy';
                                    @endphp
                                    @if(str_contains($icon, 'clear'))
                                        <svg class="w-8 h-8 mx-auto text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z"/>
                                        </svg>
                                    @elseif(str_contains($icon, 'partly'))
                                        <svg class="w-8 h-8 mx-auto text-slate-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M4.5 9.75a6 6 0 0111.573-2.226 3.75 3.75 0 014.133 4.303A4.5 4.5 0 0118 20.25H6.75a5.25 5.25 0 01-2.23-10.004 6.072 6.072 0 01-.02-.496z"/>
                                        </svg>
                                    @elseif(str_contains($icon, 'rain'))
                                        <svg class="w-8 h-8 mx-auto text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M4.5 9.75a6 6 0 0111.573-2.226 3.75 3.75 0 014.133 4.303A4.5 4.5 0 0118 20.25H6.75a5.25 5.25 0 01-2.23-10.004 6.072 6.072 0 01-.02-.496zM9 17.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V18a.75.75 0 01.75-.75zm6 0a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V18a.75.75 0 01.75-.75z" clip-rule="evenodd"/>
                                        </svg>
                                    @elseif(str_contains($icon, 'snow'))
                                        <svg class="w-8 h-8 mx-auto text-blue-300" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2.25a.75.75 0 01.75.75v3.75h3.75a.75.75 0 010 1.5h-3.75v3.75h3.75a.75.75 0 010 1.5h-3.75v3.75h3.75a.75.75 0 010 1.5h-3.75V21a.75.75 0 01-1.5 0v-2.25H7.5a.75.75 0 010-1.5h3.75v-3.75H7.5a.75.75 0 010-1.5h3.75V8.25H7.5a.75.75 0 010-1.5h3.75V3a.75.75 0 01.75-.75z"/>
                                        </svg>
                                    @else
                                        <svg class="w-8 h-8 mx-auto text-slate-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M4.5 9.75a6 6 0 0111.573-2.226 3.75 3.75 0 014.133 4.303A4.5 4.5 0 0118 20.25H6.75a5.25 5.25 0 01-2.23-10.004 6.072 6.072 0 01-.02-.496z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mb-1">{{ $snapshot['condition'] }}</p>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $this->formatTemperature($snapshot['temp']) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <!-- Empty state -->
            <div class="text-center py-8 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-lg">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No weather data') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @php
                        $hasCoordinates = ($jobSite?->latitude && $jobSite?->longitude) || ($project?->latitude && $project?->longitude);
                    @endphp
                    @if($hasCoordinates)
                        Click "Fetch Weather" to get weather data for this date.
                    @else
                        Add an address with geocoding to the {{ $jobSite ? 'job site' : 'project' }} first.
                    @endif
                </p>
            </div>
        @endif
    </div>

    <!-- Observed Weather Conditions Section -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Observed Weather Conditions') }}</h2>
            <x-ui.button
                variant="primary"
                size="sm"
                icon="plus"
                wire:click="openAddObservationModal">
                {{ __('Add Observation') }}
            </x-ui.button>
        </div>

        @if(count($weatherObservations) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Time') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Delay') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Sky') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Temp') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Wind') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Precip') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Notes') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($weatherObservations as $index => $obs)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-3 py-2 text-slate-900 dark:text-white">
                                    {{ \Carbon\Carbon::createFromFormat('H:i', $obs['observed_at'])->format('g:i A') }}
                                </td>
                                <td class="px-3 py-2">
                                    @if($obs['weather_delay'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">{{ __('Yes') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">{{ __('No') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-400">
                                    {{ $this->getSkyConditions()[$obs['sky_condition']] ?? $obs['sky_condition'] }}
                                </td>
                                <td class="px-3 py-2 text-slate-900 dark:text-white font-medium">
                                    {{ $obs['temperature'] ? $this->formatTemperature($obs['temperature']) : '-' }}
                                </td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-400">
                                    {{ $this->getWindConditions()[$obs['wind_condition']] ?? $obs['wind_condition'] }}
                                </td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-400">
                                    {{ $this->getPrecipitationTypes()[$obs['precipitation']] ?? $obs['precipitation'] }}
                                </td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-400 max-w-xs truncate">
                                    {{ $obs['notes'] ?? '-' }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex items-center justify-end space-x-1">
                                        <button
                                            type="button"
                                            wire:click="openEditObservationModal({{ $index }})"
                                            class="p-1 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="removeObservation({{ $index }})"
                                            onclick="return confirm('Are you sure you want to remove this observation?')"
                                            class="p-1 text-slate-400 hover:text-red-600 dark:hover:text-red-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-lg">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No observations recorded') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Add manual weather observations throughout the day.') }}</p>
            </div>
        @endif
    </div>

    <!-- Manpower Log Section -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Manpower Log') }}</h2>
            <x-ui.button
                variant="primary"
                size="sm"
                icon="plus"
                wire:click="openAddManpowerModal">
                {{ __('Add Entry') }}
            </x-ui.button>
        </div>

        @if(count($manpowerLogs) > 0)
            <div class="space-y-4">
                @foreach($manpowerLogs as $index => $log)
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 hover:border-slate-300 dark:hover:border-slate-600 transition-colors">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center space-x-3">
                                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-[#3F5189] text-white text-sm font-medium">
                                    {{ $index + 1 }}
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $log['contact_company'] }}</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $log['workers'] ?? 1 }} {{ Str::plural('worker', $log['workers'] ?? 1) }} &bull; {{ $log['hours'] }} hours</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-ui.button
                                    variant="secondary"
                                    size="sm"
                                    icon="edit"
                                    wire:click="openEditManpowerModal({{ $index }})">
                                    {{ __('Edit') }}
                                </x-ui.button>
                                <x-ui.button
                                    variant="danger"
                                    size="sm"
                                    icon="trash"
                                    wire:click="removeManpower({{ $index }})"
                                    onclick="return confirm('Are you sure you want to remove this entry?')">
                                    {{ __('Remove') }}
                                </x-ui.button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Works') }}</p>
                                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $log['works'] }}</p>
                            </div>
                            @if(!empty($log['comments']))
                                <div>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Comments') }}</p>
                                    <p class="text-sm text-slate-700 dark:text-slate-300">{{ $log['comments'] }}</p>
                                </div>
                            @endif
                        </div>

                        @if(!empty($log['images']))
                            <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                                <div class="flex items-center text-sm text-slate-500 dark:text-slate-400 mb-2">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ count($log['images']) }} {{ Str::plural('image', count($log['images'])) }}
                                </div>
                                <div class="grid grid-cols-4 md:grid-cols-6 gap-2">
                                    @foreach($log['images'] as $imageIndex => $image)
                                        <div class="relative group">
                                            <div class="aspect-square rounded overflow-hidden bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600">
                                                @if(isset($image['temp_path']))
                                                    <img src="{{ route('files.show', ['path' => $image['temp_path']]) }}" alt="{{ $image['file_name'] }}" class="w-full h-full object-cover">
                                                @elseif(isset($image['file_path']))
                                                    <img src="{{ route('files.show', ['path' => $image['file_path']]) }}" alt="{{ $image['file_name'] }}" class="w-full h-full object-cover">
                                                @endif
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="removeManpowerImage({{ $index }}, {{ $imageIndex }})"
                                                @click.stop
                                                class="absolute top-1 right-1 p-0.5 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-lg">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No manpower entries yet') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Track workers and contractors on this job site.') }}</p>
                <div class="mt-6">
                    <x-ui.button
                        variant="primary"
                        icon="plus"
                        wire:click="openAddManpowerModal">
                        {{ __('Add Entry') }}
                    </x-ui.button>
                </div>
            </div>
        @endif
    </div>

    <!-- Tasks Section -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Tasks') }}</h2>
            <x-ui.button
                variant="primary"
                size="sm"
                icon="plus"
                wire:click="openAddTaskModal">
                {{ __('Add Task') }}
            </x-ui.button>
        </div>

        @if(count($tasks) > 0)
            <div class="space-y-4">
                @foreach($tasks as $index => $task)
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 hover:border-slate-300 dark:hover:border-slate-600 transition-colors">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex items-center space-x-2">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#3F5189] text-white text-sm font-medium">
                                    {{ $index + 1 }}
                                </span>
                                <h3 class="text-sm font-medium text-slate-900 dark:text-white">Task {{ $index + 1 }}</h3>
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-ui.button
                                    variant="secondary"
                                    size="sm"
                                    icon="edit"
                                    wire:click="openEditTaskModal({{ $index }})">
                                    {{ __('Edit') }}
                                </x-ui.button>
                                <x-ui.button
                                    variant="danger"
                                    size="sm"
                                    icon="trash"
                                    wire:click="removeTask({{ $index }})"
                                    onclick="return confirm('Are you sure you want to remove this task?')">
                                    {{ __('Remove') }}
                                </x-ui.button>
                            </div>
                        </div>
                        <div class="prose prose-sm dark:prose-invert max-w-none text-slate-600 dark:text-slate-400">
                            {!! App\Support\RichText::sanitize($task['description']) !!}
                        </div>
                        @if(!empty($task['images']))
                            <div class="mt-3">
                                <div class="flex items-center text-sm text-slate-500 dark:text-slate-400 mb-2">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ count($task['images']) }} {{ Str::plural('image', count($task['images'])) }}
                                </div>
                                <div class="grid grid-cols-4 md:grid-cols-6 gap-2">
                                    @foreach($task['images'] as $imageIndex => $image)
                                        <div class="relative group">
                                            <div class="aspect-square rounded overflow-hidden bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600">
                                                @if(isset($image['temp_path']))
                                                    <img src="{{ route('files.show', ['path' => $image['temp_path']]) }}" alt="{{ $image['file_name'] }}" class="w-full h-full object-cover">
                                                @elseif(isset($image['file_path']))
                                                    <img src="{{ route('files.show', ['path' => $image['file_path']]) }}" alt="{{ $image['file_name'] }}" class="w-full h-full object-cover">
                                                @endif
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="removeTaskImage({{ $index }}, {{ $imageIndex }})"
                                                @click.stop
                                                class="absolute top-1 right-1 p-0.5 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-lg">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No tasks added yet') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Get started by adding your first task to this daily report.') }}</p>
                <div class="mt-6">
                    <x-ui.button
                        variant="primary"
                        icon="plus"
                        wire:click="openAddTaskModal">
                        {{ __('Add Task') }}
                    </x-ui.button>
                </div>
            </div>
        @endif
    </div>

    <!-- Task Modal -->
    <x-ui.modal name="task-modal" maxWidth="3xl">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                {{ $taskModalMode === 'edit' ? 'Edit' : 'Add' }} Task
            </h3>
        </div>

        <div class="p-6">
            <form wire:submit="saveTask">
                <!-- Task Description -->
                <div class="mb-4">
                    <label for="taskDescription" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Task Description *') }}</label>
                    <x-ui.tinymce-editor
                        wireModel="taskDescription"
                        id="taskDescription"
                        :height="300">
                    </x-ui.tinymce-editor>
                    @error('taskDescription') <span class="text-sm text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Image Upload -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Images') }}</label>

                    <!-- Existing Images -->
                    @if(count($existingTaskImages) > 0)
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                            @foreach($existingTaskImages as $index => $image)
                                <div class="relative group">
                                    <div class="aspect-square rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600">
                                        @if(isset($image['temp_path']))
                                            <img src="{{ route('files.show', ['path' => $image['temp_path']]) }}" alt="{{ $image['file_name'] }}" class="w-full h-full object-cover">
                                        @elseif(isset($image['file_path']))
                                            <img src="{{ route('files.show', ['path' => $image['file_path']]) }}" alt="{{ $image['file_name'] }}" class="w-full h-full object-cover">
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="removeModalImage({{ $index }})"
                                        @click.stop
                                        class="absolute top-2 right-2 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 truncate">{{ $image['file_name'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- New Image Previews -->
                    @if($taskImages && count($taskImages) > 0)
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                            @foreach($taskImages as $index => $image)
                                <div class="relative group" wire:key="new-image-{{ $index }}">
                                    <div class="aspect-square rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-700 border-2 border-green-500">
                                        @if($image)
                                            <img src="{{ $image->temporaryUrl() }}" alt="{{ __('Preview') }}" class="w-full h-full object-cover">
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="removeNewImage({{ $index }})"
                                        @click.stop
                                        class="absolute top-2 right-2 p-1 bg-red-600 text-white rounded-full hover:bg-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                    <p class="text-xs text-green-600 dark:text-green-400 mt-1 font-medium">{{ __('New Upload') }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Upload New Images -->
                    <div x-data="{ dragOver: false }">
                        <div @dragover.prevent="dragOver = true"
                             @dragleave.prevent="dragOver = false"
                             @drop.prevent="dragOver = false; $refs.taskImageInput.files = $event.dataTransfer.files; $refs.taskImageInput.dispatchEvent(new Event('change', { bubbles: true }));"
                             :class="dragOver ? 'border-[#3F5189] bg-blue-50 dark:bg-blue-900/20' : 'border-slate-300 dark:border-slate-600'"
                             class="border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer"
                             @click="$refs.taskImageInput.click()">

                            <svg class="mx-auto h-12 w-12 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <div class="mt-4">
                                <label for="taskImages" class="cursor-pointer">
                                    <span class="mt-2 block text-sm font-medium text-slate-900 dark:text-white">
                                        {{ __('Click to upload or drag and drop') }}
                                    </span>
                                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('PNG, JPG, GIF up to 10MB each') }}
                                    </span>
                                </label>
                                <input
                                    type="file"
                                    id="taskImages"
                                    x-ref="taskImageInput"
                                    wire:model="taskImages"
                                    multiple
                                    accept="image/*"
                                    class="hidden">
                            </div>

                            <!-- Show uploading progress -->
                            <div wire:loading wire:target="taskImages" class="mt-4">
                                <div class="inline-flex items-center px-4 py-2 text-sm text-slate-600 dark:text-slate-400">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-[#3F5189]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('Uploading...') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @error('taskImages.*') <span class="text-sm text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end space-x-4 pt-4 mt-4 border-t border-slate-200 dark:border-slate-700">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        wire:click="closeTaskModal">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary">
                        {{ $taskModalMode === 'edit' ? 'Update Task' : 'Add Task' }}
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    <!-- Weather Observation Modal -->
    <x-ui.modal name="observation-modal" maxWidth="lg">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                {{ $observationModalMode === 'edit' ? 'Edit' : 'Add' }} Weather Observation
            </h3>
        </div>

        <div class="p-6">
            <form wire:submit="saveObservation">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Time -->
                    <div>
                        <label for="observation_time" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Time *') }}</label>
                        <input
                            type="time"
                            id="observation_time"
                            wire:model="observation_time"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                            required>
                        @error('observation_time') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Weather Delay -->
                    <div class="flex items-center pt-6">
                        <label class="flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="observation_weather_delay"
                                class="w-4 h-4 text-[#3F5189] border-slate-300 rounded focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700">
                            <span class="ml-2 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Weather Delay?') }}</span>
                        </label>
                    </div>

                    <!-- Sky Condition -->
                    <div>
                        <label for="observation_sky" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Sky Condition *') }}</label>
                        <select
                            id="observation_sky"
                            wire:model="observation_sky"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                            required>
                            <option value="">{{ __('Select...') }}</option>
                            @foreach($this->getSkyConditions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('observation_sky') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Temperature -->
                    <div>
                        <label for="observation_temp" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Temperature ({{ $this->getTemperatureUnit() }})
                        </label>
                        <input
                            type="number"
                            step="0.1"
                            id="observation_temp"
                            wire:model="observation_temp"
                            placeholder="{{ config('app.country') === 'BR' ? 'e.g., 25' : 'e.g., 72' }}"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white">
                        @error('observation_temp') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Wind Condition -->
                    <div>
                        <label for="observation_wind" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Wind Condition *') }}</label>
                        <select
                            id="observation_wind"
                            wire:model="observation_wind"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                            required>
                            <option value="">{{ __('Select...') }}</option>
                            @foreach($this->getWindConditions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('observation_wind') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Precipitation -->
                    <div>
                        <label for="observation_precip" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Precipitation *') }}</label>
                        <select
                            id="observation_precip"
                            wire:model="observation_precip"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                            required>
                            <option value="">{{ __('Select...') }}</option>
                            @foreach($this->getPrecipitationTypes() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('observation_precip') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-4">
                    <label for="observation_notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes') }}</label>
                    <textarea
                        id="observation_notes"
                        wire:model="observation_notes"
                        rows="2"
                        placeholder="{{ __('Any additional notes about the weather conditions...') }}"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"></textarea>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end space-x-4 pt-4 mt-4 border-t border-slate-200 dark:border-slate-700">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        wire:click="closeObservationModal">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary">
                        {{ $observationModalMode === 'edit' ? 'Update' : 'Add' }} Observation
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    <!-- Manpower Log Modal -->
    <x-ui.modal name="manpower-modal" maxWidth="3xl">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                {{ $manpowerModalMode === 'edit' ? 'Edit' : 'Add' }} Manpower Entry
            </h3>
        </div>

        <div class="p-6">
            <form wire:submit="saveManpower">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <!-- Contact/Company -->
                    <div class="md:col-span-1">
                        <label for="manpower_contact_company" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Contact / Company *') }}</label>
                        <input
                            type="text"
                            id="manpower_contact_company"
                            wire:model="manpower_contact_company"
                            placeholder="{{ __('e.g., ABC Electrical, John Smith') }}"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                            required>
                        @error('manpower_contact_company') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Workers -->
                    <div>
                        <label for="manpower_workers" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"># of Workers *</label>
                        <input
                            type="number"
                            min="1"
                            id="manpower_workers"
                            wire:model="manpower_workers"
                            placeholder="e.g., 3"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                            required>
                        @error('manpower_workers') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Hours -->
                    <div>
                        <label for="manpower_hours" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Hours *') }}</label>
                        <input
                            type="number"
                            step="0.5"
                            min="0"
                            max="24"
                            id="manpower_hours"
                            wire:model="manpower_hours"
                            placeholder="e.g., 8"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                            required>
                        @error('manpower_hours') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Works -->
                <div class="mb-4">
                    <label for="manpower_works" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Works Performed *') }}</label>
                    <textarea
                        id="manpower_works"
                        wire:model="manpower_works"
                        rows="3"
                        placeholder="{{ __('Describe the work performed...') }}"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                        required></textarea>
                    @error('manpower_works') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Comments -->
                <div class="mb-4">
                    <label for="manpower_comments" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Comments') }}</label>
                    <textarea
                        id="manpower_comments"
                        wire:model="manpower_comments"
                        rows="2"
                        placeholder="{{ __('Any additional comments...') }}"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"></textarea>
                    @error('manpower_comments') <span class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Image Upload -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Images') }}</label>

                    <!-- Existing Images -->
                    @if(count($existingManpowerImages) > 0)
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                            @foreach($existingManpowerImages as $index => $image)
                                <div class="relative group">
                                    <div class="aspect-square rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600">
                                        @if(isset($image['temp_path']))
                                            <img src="{{ route('files.show', ['path' => $image['temp_path']]) }}" alt="{{ $image['file_name'] }}" class="w-full h-full object-cover">
                                        @elseif(isset($image['file_path']))
                                            <img src="{{ route('files.show', ['path' => $image['file_path']]) }}" alt="{{ $image['file_name'] }}" class="w-full h-full object-cover">
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="removeManpowerModalImage({{ $index }})"
                                        @click.stop
                                        class="absolute top-2 right-2 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 truncate">{{ $image['file_name'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- New Image Previews -->
                    @if($manpowerImages && count($manpowerImages) > 0)
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                            @foreach($manpowerImages as $index => $image)
                                <div class="relative group" wire:key="new-manpower-image-{{ $index }}">
                                    <div class="aspect-square rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-700 border-2 border-green-500">
                                        @if($image)
                                            <img src="{{ $image->temporaryUrl() }}" alt="{{ __('Preview') }}" class="w-full h-full object-cover">
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="removeNewManpowerImage({{ $index }})"
                                        @click.stop
                                        class="absolute top-2 right-2 p-1 bg-red-600 text-white rounded-full hover:bg-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                    <p class="text-xs text-green-600 dark:text-green-400 mt-1 font-medium">{{ __('New Upload') }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Upload New Images -->
                    <div x-data="{ dragOver: false }">
                        <div @dragover.prevent="dragOver = true"
                             @dragleave.prevent="dragOver = false"
                             @drop.prevent="dragOver = false; $refs.manpowerImageInput.files = $event.dataTransfer.files; $refs.manpowerImageInput.dispatchEvent(new Event('change', { bubbles: true }));"
                             :class="dragOver ? 'border-[#3F5189] bg-blue-50 dark:bg-blue-900/20' : 'border-slate-300 dark:border-slate-600'"
                             class="border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer"
                             @click="$refs.manpowerImageInput.click()">

                            <svg class="mx-auto h-12 w-12 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <div class="mt-4">
                                <label for="manpowerImages" class="cursor-pointer">
                                    <span class="mt-2 block text-sm font-medium text-slate-900 dark:text-white">
                                        {{ __('Click to upload or drag and drop') }}
                                    </span>
                                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('PNG, JPG, GIF up to 10MB each') }}
                                    </span>
                                </label>
                                <input
                                    type="file"
                                    id="manpowerImages"
                                    x-ref="manpowerImageInput"
                                    wire:model="manpowerImages"
                                    multiple
                                    accept="image/*"
                                    class="hidden">
                            </div>

                            <!-- Show uploading progress -->
                            <div wire:loading wire:target="manpowerImages" class="mt-4">
                                <div class="inline-flex items-center px-4 py-2 text-sm text-slate-600 dark:text-slate-400">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-[#3F5189]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('Uploading...') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @error('manpowerImages.*') <span class="text-sm text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end space-x-4 pt-4 mt-4 border-t border-slate-200 dark:border-slate-700">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        wire:click="closeManpowerModal">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary">
                        {{ $manpowerModalMode === 'edit' ? 'Update' : 'Add' }} Entry
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
</div>
