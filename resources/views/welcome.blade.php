<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@include('components.layouts.inc.head')
<body class="bg-slate-50 dark:bg-slate-900 min-h-screen flex flex-col" x-data="{
    sidebarOpen: false,
    sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
    profileDropdownOpen: false,
    activeSubmenu: null,
    welcomeSectionVisible: localStorage.getItem('welcomeSectionDismissed') !== 'true',
    // Desktop rail: collapsed, and not the mobile drawer.
    get rail() { return this.sidebarCollapsed && ! this.sidebarOpen },
    toggleSidebar() {
    this.sidebarCollapsed = !this.sidebarCollapsed;
    localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed ? 'true' : 'false');
    },
    toggleSubmenu(menu) {
    this.activeSubmenu = this.activeSubmenu === menu ? null : menu;
    },
    closeWelcomeSection() {
    this.welcomeSectionVisible = false;
    localStorage.setItem('welcomeSectionDismissed', 'true');
    }
    }" x-init="document.documentElement.classList.remove('sidebar-collapsed-init')">
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
                <x-app-logo-icon class="h-8 w-8 shrink-0" />
                <span class="text-xl font-bold text-slate-800 dark:text-white">{{ config('app.name') }}</span>
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
    <div class="app-content transition-all duration-300"
         :class="sidebarCollapsed ? 'lg:ml-[70px]' : 'lg:ml-[260px]'">

        <!-- Top Header (Desktop) -->
        @include('components.layouts.inc.header')

        <!-- Main Content Area -->
        <main class="p-6 pt-20 lg:pt-6">
            <!-- Welcome Section -->
            @include('components.layouts.inc.welcome_banner')

            <!-- Stats Cards -->
            @include('components.layouts.inc.stats_cards')

            <!-- Content Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Recent Activity -->
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Recent Activity</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <!-- Activity Item -->
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 bg-[#3F5189]/10 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#3F5189]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">New user registered</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Sarah Johnson joined the platform</p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">2 minutes ago</p>
                                </div>
                            </div>
                            <!-- Activity Item -->
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">Order completed</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Order #1234 has been delivered</p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">5 minutes ago</p>
                                </div>
                            </div>
                            <!-- Activity Item -->
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">Payment received</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">$2,500 payment from client</p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">1 hour ago</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Quick Actions</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Quick Action -->
                            <button class="flex flex-col items-center p-4 bg-slate-50 dark:bg-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors duration-200">
                                <svg class="w-8 h-8 text-[#3F5189] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">Add User</span>
                            </button>
                            <!-- Quick Action -->
                            <button class="flex flex-col items-center p-4 bg-slate-50 dark:bg-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors duration-200">
                                <svg class="w-8 h-8 text-green-600 dark:text-green-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">View Reports</span>
                            </button>
                            <!-- Quick Action -->
                            <button class="flex flex-col items-center p-4 bg-slate-50 dark:bg-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors duration-200">
                                <svg class="w-8 h-8 text-orange-600 dark:text-orange-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">Settings</span>
                            </button>
                            <!-- Quick Action -->
                            <button class="flex flex-col items-center p-4 bg-slate-50 dark:bg-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors duration-200">
                                <svg class="w-8 h-8 text-purple-600 dark:text-purple-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">Support</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Button Components Showcase -->
        <section class="p-6">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Button Components</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Reusable button components with different variants and sizes</p>
                </div>

                <div class="p-6 space-y-8">
                    <!-- Normal Buttons -->
                    <div>
                        <h4 class="text-md font-medium text-slate-800 dark:text-white mb-4">Normal Buttons</h4>

                        <!-- Button Variants -->
                        <div class="space-y-4">
                            <div>
                                <h5 class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-3">Variants</h5>
                                <div class="flex flex-wrap gap-3">
                                    <x-ui.button variant="primary">Primary Button</x-ui.button>
                                    <x-ui.button variant="secondary">Secondary Button</x-ui.button>
                                    <x-ui.button variant="success">Success Button</x-ui.button>
                                    <x-ui.button variant="warning">Warning Button</x-ui.button>
                                    <x-ui.button variant="danger">Danger Button</x-ui.button>
                                    <x-ui.button variant="ghost">Ghost Button</x-ui.button>
                                    <x-ui.button variant="outline">Outline Button</x-ui.button>
                                </div>
                            </div>

                            <div>
                                <h5 class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-3">Sizes</h5>
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-ui.button variant="primary" size="sm">Small</x-ui.button>
                                    <x-ui.button variant="primary" size="md">Medium</x-ui.button>
                                    <x-ui.button variant="primary" size="lg">Large</x-ui.button>
                                    <x-ui.button variant="primary" size="xl">Extra Large</x-ui.button>
                                </div>
                            </div>

                            <div>
                                <h5 class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-3">With Icons</h5>
                                <div class="flex flex-wrap gap-3">
                                    <x-ui.button variant="primary" icon="plus">Add User</x-ui.button>
                                    <x-ui.button variant="secondary" icon="edit">Edit</x-ui.button>
                                    <x-ui.button variant="success" icon="save">Save</x-ui.button>
                                    <x-ui.button variant="danger" icon="trash">Delete</x-ui.button>
                                    <x-ui.button variant="outline" icon="download" :icon-position="'right'">Download</x-ui.button>
                                </div>
                            </div>

                            <div>
                                <h5 class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-3">States</h5>
                                <div class="flex flex-wrap gap-3">
                                    <x-ui.button variant="primary">Normal</x-ui.button>
                                    <x-ui.button variant="primary" disabled>Disabled</x-ui.button>
                                    <x-ui.button variant="secondary" href="#">As Link</x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Icon Buttons -->
                    <div>
                        <h4 class="text-md font-medium text-slate-800 dark:text-white mb-4">Icon-Only Buttons</h4>

                        <div class="space-y-4">
                            <div>
                                <h5 class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-3">Variants</h5>
                                <div class="flex flex-wrap gap-3">
                                    <x-ui.icon-button variant="primary" icon="plus" />
                                    <x-ui.icon-button variant="secondary" icon="edit" />
                                    <x-ui.icon-button variant="success" icon="save" />
                                    <x-ui.icon-button variant="warning" icon="star" />
                                    <x-ui.icon-button variant="danger" icon="trash" />
                                    <x-ui.icon-button variant="ghost" icon="heart" />
                                    <x-ui.icon-button variant="outline" icon="settings" />
                                </div>
                            </div>

                            <div>
                                <h5 class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-3">Sizes</h5>
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-ui.icon-button variant="primary" icon="search" size="sm" />
                                    <x-ui.icon-button variant="primary" icon="search" size="md" />
                                    <x-ui.icon-button variant="primary" icon="search" size="lg" />
                                    <x-ui.icon-button variant="primary" icon="search" size="xl" />
                                </div>
                            </div>

                            <div>
                                <h5 class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-3">Common Actions</h5>
                                <div class="flex flex-wrap gap-3">
                                    <x-ui.icon-button variant="primary" icon="plus" title="Add" />
                                    <x-ui.icon-button variant="secondary" icon="edit" title="Edit" />
                                    <x-ui.icon-button variant="ghost" icon="eye" title="View" />
                                    <x-ui.icon-button variant="warning" icon="download" title="Download" />
                                    <x-ui.icon-button variant="danger" icon="trash" title="Delete" />
                                    <x-ui.icon-button variant="outline" icon="settings" title="Settings" />
                                </div>
                            </div>

                            <div>
                                <h5 class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-3">States</h5>
                                <div class="flex flex-wrap gap-3">
                                    <x-ui.icon-button variant="primary" icon="check" title="Normal" />
                                    <x-ui.icon-button variant="primary" icon="x" disabled title="Disabled" />
                                    <x-ui.icon-button variant="secondary" icon="arrow-right" href="#" title="As Link" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Usage Examples -->
                    <div>
                        <h4 class="text-md font-medium text-slate-800 dark:text-white mb-4">Usage Examples</h4>
                        <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600 dark:text-slate-400">Table Actions:</span>
                                    <div class="flex gap-2">
                                        <x-ui.icon-button variant="ghost" icon="eye" size="sm" title="View" />
                                        <x-ui.icon-button variant="ghost" icon="edit" size="sm" title="Edit" />
                                        <x-ui.icon-button variant="ghost" icon="trash" size="sm" title="Delete" />
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600 dark:text-slate-400">Form Actions:</span>
                                    <div class="flex gap-3">
                                        <x-ui.button variant="secondary">Cancel</x-ui.button>
                                        <x-ui.button variant="primary" icon="save">Save Changes</x-ui.button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600 dark:text-slate-400">Toolbar:</span>
                                    <div class="flex gap-2">
                                        <x-ui.button variant="outline" icon="plus" size="sm">New</x-ui.button>
                                        <x-ui.icon-button variant="secondary" icon="download" size="sm" title="Export" />
                                        <x-ui.icon-button variant="secondary" icon="settings" size="sm" title="Settings" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Footer -->
@include('components.layouts.inc.footer')
</body>
</html>
