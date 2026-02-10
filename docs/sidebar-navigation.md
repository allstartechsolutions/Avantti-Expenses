# Sidebar Navigation

This document explains how the sidebar navigation works and how to add new menu items.

---

## File Locations

| File | Purpose |
|------|---------|
| `resources/views/components/layouts/app.blade.php` | Main layout with Alpine.js state for submenus |
| `resources/views/components/layouts/inc/sidebar.blade.php` | Sidebar component with menu items |

---

## How Submenus Work

The sidebar uses Alpine.js to manage submenu state. The `activeSubmenu` variable controls which submenu is currently open.

### State Initialization (app.blade.php)

The `activeSubmenu` is initialized based on the current route:

```blade
<body x-data="{
    sidebarOpen: false,
    sidebarCollapsed: false,
    activeSubmenu: @js(
        request()->routeIs('company.*') || request()->routeIs('users.*')
            ? 'company'
            : (request()->routeIs('projects.*') || request()->routeIs('clients.*') || request()->routeIs('cost-codes.*')
                ? 'projects'
                : (request()->routeIs('catalog.*')
                    ? 'catalog'
                    : null))
    ),
    toggleSubmenu(menu) {
        this.activeSubmenu = this.activeSubmenu === menu ? null : menu;
    }
}">
```

### Key Points

1. **Submenu stays open** when navigating to any page within that submenu's routes
2. **Route matching** uses Laravel's `request()->routeIs()` with wildcard patterns
3. **Toggle function** allows clicking the menu to expand/collapse

---

## Adding a New Menu Item

### Option 1: Simple Link (No Submenu)

Add directly to sidebar.blade.php:

```blade
<!-- My Feature -->
<a href="{{ route('my-feature.index') }}"
   class="flex items-center px-2.5 py-2.5 mb-1 text-sm font-medium {{ request()->routeIs('my-feature.*') ? 'text-white bg-gradient-to-r from-[#3F5189] to-[#4A5A96]' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }} rounded-lg group">
    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <!-- SVG path here -->
    </svg>
    <span x-show="!sidebarCollapsed || sidebarOpen" x-cloak>My Feature</span>
</a>
```

### Option 2: Add to Existing Submenu

1. **Update the submenu button** in sidebar.blade.php to include new routes in the active check:

```blade
<button @click="toggleSubmenu('projects')"
        class="... {{ request()->routeIs('projects.*') || request()->routeIs('clients.*') || request()->routeIs('my-new-feature.*') ? 'text-[#3F5189]...' : '...' }} ...">
```

2. **Add the submenu item** inside the submenu div:

```blade
<a href="{{ route('my-new-feature.index') }}"
   class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('my-new-feature.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <!-- SVG path here -->
    </svg>
    My New Feature
</a>
```

3. **Update activeSubmenu initialization** in app.blade.php to include the new route:

```blade
activeSubmenu: @js(
    request()->routeIs('projects.*') || request()->routeIs('clients.*') || request()->routeIs('my-new-feature.*')
        ? 'projects'
        : ...
),
```

### Option 3: Create New Submenu

1. **Add the submenu button** in sidebar.blade.php:

```blade
<!-- My Section -->
<div class="mb-1">
    <button @click="toggleSubmenu('mysection')"
            class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium {{ request()->routeIs('feature-a.*') || request()->routeIs('feature-b.*') ? 'text-[#3F5189] dark:text-[#4A5A96] bg-slate-100 dark:bg-slate-700' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 group">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <!-- SVG path here -->
            </svg>
            <span x-show="!sidebarCollapsed || sidebarOpen" x-cloak>My Section</span>
        </div>
        <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu !== 'mysection'" x-cloak
             class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
        <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu === 'mysection'" x-cloak
             class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <!-- My Section Submenu -->
    <div x-show="activeSubmenu === 'mysection' && (!sidebarCollapsed || sidebarOpen)" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         class="ml-8 mt-2 space-y-1">

        <a href="{{ route('feature-a.index') }}"
           class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('feature-a.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <!-- SVG path -->
            </svg>
            Feature A
        </a>

        <a href="{{ route('feature-b.index') }}"
           class="flex items-center px-3 py-2 text-sm {{ request()->routeIs('feature-b.*') ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <!-- SVG path -->
            </svg>
            Feature B
        </a>
    </div>
</div>
```

2. **Update activeSubmenu initialization** in app.blade.php:

```blade
activeSubmenu: @js(
    request()->routeIs('company.*') || request()->routeIs('users.*')
        ? 'company'
        : (request()->routeIs('projects.*') || request()->routeIs('clients.*') || request()->routeIs('cost-codes.*')
            ? 'projects'
            : (request()->routeIs('catalog.*')
                ? 'catalog'
                : (request()->routeIs('feature-a.*') || request()->routeIs('feature-b.*')
                    ? 'mysection'
                    : null)))
),
```

---

## Current Menu Structure

```
MENU
├── Dashboard (simple link)
├── Company (submenu)
│   ├── Settings
│   └── Users
├── Projects (submenu)
│   ├── All Projects
│   ├── Subcontractors
│   ├── Clients
│   └── Cost Codes
├── Catalog (submenu)
│   ├── All Items
│   ├── Categories
│   └── Suppliers
├── Payments (simple link)
├── Estimates (simple link)
└── Settings (submenu)
    ├── Tax Rates
    └── Messages
```

---

## Styling Reference

### Active States

| Element | Active Class |
|---------|--------------|
| Simple link (active) | `text-white bg-gradient-to-r from-[#3F5189] to-[#4A5A96]` |
| Simple link (inactive) | `text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700` |
| Submenu button (active) | `text-[#3F5189] dark:text-[#4A5A96] bg-slate-100 dark:bg-slate-700` |
| Submenu button (inactive) | `text-slate-600 dark:text-slate-300` |
| Submenu item (active) | `text-[#3F5189] dark:text-[#4A5A96] font-medium` |
| Submenu item (inactive) | `text-slate-600 dark:text-slate-300` |

### Icons

- Main menu icons: `w-5 h-5 mr-3`
- Submenu item icons: `w-4 h-4 mr-2`
- Chevron icons: `w-4 h-4`

---

## Checklist for Adding New Menu Items

1. [ ] Add route(s) to `routes/web.php`
2. [ ] Add menu item to `sidebar.blade.php`
3. [ ] If submenu: Update `activeSubmenu` in `app.blade.php` to include new routes
4. [ ] If submenu: Update parent button's active class condition
5. [ ] Test navigation and active state highlighting
