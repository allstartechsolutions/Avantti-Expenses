<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-country="{{ config('app.country') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

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
            <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-sm leading-none">A</span>
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
