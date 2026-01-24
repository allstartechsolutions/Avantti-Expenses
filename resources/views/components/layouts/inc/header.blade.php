
<header class="hidden lg:block bg-white dark:bg-slate-800 shadow-sm border-b border-slate-200 dark:border-slate-700 h-16 px-6">
    <div class="flex items-center justify-between h-full">
        <div class="flex items-center space-x-4">
            <h1 class="text-xl font-semibold text-slate-800 dark:text-white">Dashboard</h1>
            <nav class="flex space-x-1">
                <span class="text-sm text-slate-500 dark:text-slate-400">Pages</span>
                <span class="text-sm text-slate-400 dark:text-slate-500">/</span>
                <span class="text-sm text-slate-600 dark:text-slate-300">Starter</span>
            </nav>
        </div>

        <div class="flex items-center space-x-4">
            <!-- Search -->
            <div class="relative">
                <input type="text"
                       placeholder="Search..."
                       class="w-64 pl-10 pr-4 py-2 text-sm bg-slate-100 dark:bg-slate-700 border border-transparent rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] dark:text-white dark:placeholder-slate-400">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            <!-- Notifications -->
            <button class="relative p-2 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5-5-5 5h5v0a6 6 0 01-12 0V9a6 6 0 0112 0v8.5z"></path>
                </svg>
                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
            </button>

            <!-- Messages -->
            <button class="relative p-2 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <span class="absolute top-1 right-1 w-2 h-2 bg-green-500 rounded-full"></span>
            </button>

            <!-- Apps Grid -->
            <button class="p-2 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                </svg>
            </button>

            <!-- Fullscreen -->
            <button class="p-2 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                </svg>
            </button>
        </div>
    </div>
</header>
