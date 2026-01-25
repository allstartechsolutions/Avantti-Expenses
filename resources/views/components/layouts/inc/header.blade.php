
<header class="hidden lg:block bg-white dark:bg-slate-800 shadow-sm border-b border-slate-200 dark:border-slate-700 h-16 px-6">
    <div class="flex items-center justify-between h-full">
        <div class="flex items-center space-x-4">
         <!--  left side menu or title  <h1 class="text-xl font-semibold text-slate-800 dark:text-white">Dashboard</h1>
            <nav class="flex space-x-1">
                <span class="text-sm text-slate-500 dark:text-slate-400">Pages</span>
                <span class="text-sm text-slate-400 dark:text-slate-500">/</span>
                <span class="text-sm text-slate-600 dark:text-slate-300">Starter</span>
            </nav> -->
        </div>

        <div class="flex items-center space-x-4">
            <!-- Search Projects -->
            <livewire:shared.header-search />

            <!-- Messages -->
            <button class="relative p-2 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <span class="absolute top-1 right-1 w-2 h-2 bg-green-500 rounded-full"></span>
            </button>

            <!-- Fullscreen -->
            <button
                x-data="{ isFullscreen: false }"
                x-init="document.addEventListener('fullscreenchange', () => isFullscreen = !!document.fullscreenElement)"
                @click="
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen();
                    } else {
                        document.exitFullscreen();
                    }
                "
                class="p-2 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">
                <!-- Expand icon -->
                <svg x-show="!isFullscreen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                </svg>
                <!-- Collapse icon -->
                <svg x-show="isFullscreen" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v4m0-4h4m7 5l5-5m0 0v4m0-4h-4M9 15l-5 5m0 0v-4m0 4h4m7-5l5 5m0 0v-4m0 4h-4"></path>
                </svg>
            </button>
        </div>
    </div>
</header>
