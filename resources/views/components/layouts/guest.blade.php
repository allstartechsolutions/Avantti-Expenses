<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-country="{{ config('app.country') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ config('app.logo_url') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ config('app.logo_url') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-[#3F5189] text-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-4 flex items-center space-x-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/95 p-1">
                <x-app-logo-icon class="h-full w-full" />
            </div>
            <span class="text-lg font-semibold">
                {{ \App\Models\Company::first()?->name ?? config('app.name') }}
            </span>
        </div>
    </header>

    <!-- Content -->
    <main class="flex-1 py-8 px-4 sm:px-6">
        <div class="max-w-4xl mx-auto">
            {{ $slot }}
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 text-center text-sm text-slate-400">
        &copy; {{ date('Y') }} {{ \App\Models\Company::first()?->name ?? config('app.name') }}
    </footer>

    @stack('scripts')
</body>
</html>
