<aside class="fixed top-0 left-0 z-40 h-screen transition-all duration-300 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 shadow-sm"
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
            <span class="text-xl font-bold text-slate-800 dark:text-white">Despesas</span>
        </div>
        <div class="flex items-center space-x-2">
            <!-- Desktop Toggle -->
            <button @click="sidebarCollapsed = !sidebarCollapsed"
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
    <nav class="flex-1 px-4 py-4 overflow-y-auto">
        <!-- MENU Section -->
        <div class="mb-6">
            <div class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3"
                 x-show="!sidebarCollapsed || sidebarOpen" x-cloak>
                MENU
            </div>

            <!-- Dashboard -->
            <a href="{{ route('dashboard') }}" class="flex items-center px-2.5 py-2.5 mb-1 text-sm font-medium {{ request()->routeIs('dashboard') ? 'text-white bg-gradient-to-r from-[#3F5189] to-[#4A5A96]' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }} rounded-lg group">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span x-show="!sidebarCollapsed || sidebarOpen" x-cloak>Dashboard</span>
            </a>

            <!-- Company -->
            <div class="mb-1">
                <button @click="toggleSubmenu('company')"
                        class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium {{ request()->routeIs('company.*') || request()->routeIs('users.*') ? 'text-[#3F5189] dark:text-[#4A5A96] bg-slate-100 dark:bg-slate-700' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 group">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <span x-show="!sidebarCollapsed || sidebarOpen" x-cloak>Company</span>
                    </div>
                    <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu !== 'company'" x-cloak
                         class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu === 'company'" x-cloak
                         class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <!-- Company Submenu -->
                <div x-show="activeSubmenu === 'company' && (!sidebarCollapsed || sidebarOpen)" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     class="ml-8 mt-2 space-y-1">
                    <a href="{{ route('company.settings') }}"
                       class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('company.settings') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Settings
                    </a>
                    <a href="{{ route('users.index') }}"
                       class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('users.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        Users
                    </a>
                </div>
            </div>

            <!-- Projects -->
            <div class="mb-1">
                <button @click="toggleSubmenu('projects')"
                        class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium {{ request()->routeIs('projects.*') || request()->routeIs('clients.*') || request()->routeIs('subcontractors.*') || request()->routeIs('cost-codes.*') ? 'text-[#3F5189] dark:text-[#4A5A96] bg-slate-100 dark:bg-slate-700' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 group">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span x-show="!sidebarCollapsed || sidebarOpen" x-cloak>Projects</span>
                    </div>
                    <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu !== 'projects'" x-cloak
                         class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu === 'projects'" x-cloak
                         class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <!-- Projects Submenu -->
                <div x-show="activeSubmenu === 'projects' && (!sidebarCollapsed || sidebarOpen)" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     class="ml-8 mt-2 space-y-1">
                    <a href="{{ route('projects.index') }}"
                       class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('projects.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        All Projects
                    </a>
                    <a href="{{ route('subcontractors.index') }}"
                       class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('subcontractors.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Subcontractors
                    </a>
                    <a href="{{ route('clients.index') }}"
                       class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('clients.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Clients
                    </a>
                    <a href="{{ route('cost-codes.templates.index') }}"
                       class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('cost-codes.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        Cost Codes
                    </a>
                </div>
            </div>

            <!-- Catalog -->
            <div class="mb-1">
                <button @click="toggleSubmenu('catalog')"
                        class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium {{ request()->routeIs('catalog.*') || request()->routeIs('suppliers.*') ? 'text-[#3F5189] dark:text-[#4A5A96] bg-slate-100 dark:bg-slate-700' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 group">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <span x-show="!sidebarCollapsed || sidebarOpen" x-cloak>Catalog</span>
                    </div>
                    <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu !== 'catalog'" x-cloak
                         class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu === 'catalog'" x-cloak
                         class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <!-- Catalog Submenu -->
                <div x-show="activeSubmenu === 'catalog' && (!sidebarCollapsed || sidebarOpen)" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     class="ml-8 mt-2 space-y-1">
                    <a href="{{ route('catalog.index') }}"
                       class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('catalog.index') || request()->routeIs('catalog.create') || request()->routeIs('catalog.edit') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        All Items
                    </a>
                    <a href="{{ route('catalog.categories.index') }}"
                       class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('catalog.categories.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        Categories
                    </a>
                    <a href="{{ route('suppliers.index') }}"
                       class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('suppliers.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path>
                        </svg>
                        Suppliers
                    </a>
                </div>
            </div>

            <!-- Payments -->
            <a href="{{ route('payments.index') }}" class="flex items-center px-2.5 py-2.5 mb-1 text-sm font-medium {{ request()->routeIs('payments.*') ? 'text-white bg-gradient-to-r from-[#3F5189] to-[#4A5A96]' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }} rounded-lg group">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span x-show="!sidebarCollapsed || sidebarOpen" x-cloak>Payments</span>
            </a>
        </div>
    </nav>

    <!-- User Profile Section -->
    <div class="border-t border-slate-200 dark:border-slate-700">
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open"
                    class="flex items-center w-full p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors duration-200">
                <div class="flex items-center space-x-3 flex-1">
                    <div class="w-10 h-10 bg-slate-300 dark:bg-slate-600 rounded-full flex items-center justify-center">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300 leading-none">{{ Auth::user()->initials() }}</span>
                    </div>
                    <div x-show="!sidebarCollapsed || sidebarOpen" x-cloak class="flex-1 text-left">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ Auth::user()->role?->name ? ucfirst(Auth::user()->role->name) : 'User' }}</p>
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
            <div x-show="open && (!sidebarCollapsed || sidebarOpen)" @click.away="open = false" x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="transform opacity-0 scale-95"
                 x-transition:enter-end="transform opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="transform opacity-100 scale-100"
                 x-transition:leave-end="transform opacity-0 scale-95"
                 class="absolute bottom-full left-0 right-0 mb-2 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 z-50">
                <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ Auth::user()->name }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ Auth::user()->email }}</p>
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
</aside>

<!-- Mobile Overlay -->
<div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak
     class="fixed inset-0 z-30 bg-black bg-opacity-50 lg:hidden"></div>
<?php
