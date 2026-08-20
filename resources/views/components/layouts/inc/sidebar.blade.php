<aside class="app-sidebar fixed top-0 left-0 z-40 h-screen transition-all duration-300 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 shadow-sm"
       :class="[
                   sidebarOpen || !sidebarCollapsed ? 'sidebar-expanded' : 'sidebar-collapsed',
                   sidebarOpen ? 'translate-x-0' : 'lg:translate-x-0 -translate-x-full'
               ]">

    <!-- Sidebar Header -->
    <div class="flex items-center justify-between h-16 px-4 border-b border-slate-200 dark:border-slate-700">
        <div class="flex items-center space-x-3" x-show="!sidebarCollapsed || sidebarOpen" x-cloak>
            <div class="w-8 h-8 bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-sm leading-none">A</span>
            </div>
            <span class="text-xl font-bold text-slate-800 dark:text-white">{{ __('Despesas') }}</span>
        </div>
        <div class="flex items-center space-x-2">
            <!-- Desktop Toggle -->
            <button @click="toggleSidebar()"
                    class="hidden lg:flex p-1.5 rounded-md text-slate-400 dark:text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">
                <!-- Collapsed state - show expand icon (point right) -->
                <svg x-show="sidebarCollapsed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                </svg>
                <!-- Expanded state - show collapse icon (point left) -->
                <svg x-show="!sidebarCollapsed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                </svg>
            </button>
            <!-- Mobile Close -->
            <button @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-md text-slate-400 dark:text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-4 py-4 overflow-y-auto" @scroll="$dispatch('rail-reposition')">
        <!-- MENU Section -->
        <div class="mb-6">
            <div class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3"
                 x-show="!sidebarCollapsed || sidebarOpen" x-cloak>
                {{ __('Menu') }}
            </div>

            {{--
                The whole menu comes from config/permissions.php via
                App\Services\Navigation: an entry is here because the catalogue
                declares it, its module is switched on, and this person holds
                its ability. Empty groups are dropped rather than rendered.
                Nothing about the menu is decided in this file — see
                docs/permissions-module.md.
            --}}
            @foreach(app(\App\Services\Navigation::class)->sidebar(auth()->user()) as $entry)
                @if($entry['type'] === 'group')
                    <x-layouts.inc.nav.group :group="$entry" />
                @else
                    <x-layouts.inc.nav.item :entry="$entry" />
                @endif
            @endforeach

        </div>
    </nav>

    <!-- User Profile Section -->
    <div class="border-t border-slate-200 dark:border-slate-700">
        <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
            <button @click="open = !open"
                    class="flex items-center w-full p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors duration-200">
                <div class="flex items-center space-x-3 flex-1">
                    <div class="w-10 h-10 bg-slate-300 dark:bg-slate-600 rounded-full flex items-center justify-center">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300 leading-none">{{ Auth::user()->initials() }}</span>
                    </div>
                    <div x-show="!sidebarCollapsed || sidebarOpen" x-cloak class="flex-1 text-left">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ Auth::user()->role?->name ? ucfirst(Auth::user()->role->name) : __('User') }}</p>
                    </div>
                </div>
                <svg x-show="(!sidebarCollapsed || sidebarOpen)" x-cloak
                     :class="{ 'rotate-180': open }"
                     class="w-4 h-4 text-slate-400 dark:text-slate-500 transition-transform duration-200"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                </svg>
            </button>

            <!-- Profile Dropdown -->
            <div x-show="open" @click.away="open = false" x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="transform opacity-0 scale-95"
                 x-transition:enter-end="transform opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="transform opacity-100 scale-100"
                 x-transition:leave-end="transform opacity-0 scale-95"
                 :class="rail
                    ? 'fixed left-[70px] bottom-3 w-64 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50'
                    : 'absolute bottom-full left-0 right-0 mb-2 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 z-50'">
                <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ Auth::user()->name }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ Auth::user()->email }}</p>
                </div>
                <div class="py-1">
                    <a href="{{ route('profile') }}" class="flex items-center px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        {{ __('Profile') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center w-full px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            {{ __('Logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile Overlay -->
<div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak
     class="fixed inset-0 z-30 bg-black bg-opacity-50 lg:hidden"></div>
