<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('components.layouts.inc.head')
<body class="bg-slate-50 dark:bg-slate-900 min-h-screen flex flex-col" x-data="{
    sidebarOpen: false,
    sidebarCollapsed: false,
    profileDropdownOpen: false,
    activeSubmenu: @js(
        request()->routeIs('company.*') || request()->routeIs('users.*')
            ? 'company'
            : (request()->routeIs('projects.*') || request()->routeIs('clients.*')
                ? 'projects'
                : null)
    ),
    welcomeSectionVisible: localStorage.getItem('welcomeSectionDismissed') !== 'true',
    toggleSubmenu(menu) {
        this.activeSubmenu = this.activeSubmenu === menu ? null : menu;
    },
    closeWelcomeSection() {
        this.welcomeSectionVisible = false;
        localStorage.setItem('welcomeSectionDismissed', 'true');
    }
}">
<!-- Main Content Wrapper -->
<div class="flex-1 flex flex-col">
    <!-- Mobile Header -->
    <div class="lg:hidden bg-white dark:bg-slate-800 shadow-sm border-b border-slate-200 dark:border-slate-700 fixed w-full top-0 z-50 h-16">
        <div class="flex items-center justify-between h-full px-4">
            <!-- Mobile Menu Toggle -->
            <button @click="sidebarOpen = true" class="p-2 rounded-md text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>

            <!-- Mobile Logo -->
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-sm leading-none">A</span>
                </div>
                <span class="text-xl font-bold text-slate-800 dark:text-white">Avantti</span>
            </div>

            <!-- Mobile Profile -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center space-x-2 p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-700">
                    <div class="w-8 h-8 bg-slate-300 dark:bg-slate-600 rounded-full flex items-center justify-center">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300 leading-none">JD</span>
                    </div>
                </button>

                <!-- Mobile Profile Dropdown -->
                <div x-show="open" @click.away="open = false" x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-md shadow-lg border border-slate-200 dark:border-slate-700 z-50">
                    <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">John Doe</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">john@avantti.com</p>
                    </div>
                    <div class="py-1">
                        <a href="#" class="flex items-center px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Profile
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            Settings
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    @include('components.layouts.inc.sidebar')
    <!-- Main Content -->
    <div class="transition-all duration-300"
         :class="sidebarCollapsed ? 'lg:ml-[70px]' : 'lg:ml-[260px]'">

        <!-- Top Header (Desktop) -->
        @include('components.layouts.inc.header')

        <!-- Main Content Area -->
       @include('components.layouts.inc.content')
    </div>
</div>

<!-- Footer -->
@include('components.layouts.inc.footer')

@stack('scripts')
</body>
</html>
