<?php

/**
 * The ability catalogue.
 *
 * Every permission in the system is named `area.action` — `expenses.create`,
 * `requisitions.approve` — and every one of them is declared here. The database
 * stores only *grants* (who holds what), never the list itself, so adding an
 * ability is a deploy and not a data migration.
 *
 * Read this file with docs/permissions-module-plan.md open; the two are meant to
 * agree.
 *
 * Each area declares:
 *
 *   name     Human label, translated at render time.
 *   module   Key in config/modules.php. If the customer has that module switched
 *            off, nothing in this area is reachable regardless of permissions.
 *   levels   Where the ability can be granted: 'global' (a company-wide screen,
 *            granted by role), 'project' and/or 'job_site' (granted by a
 *            membership on that record).
 *   money    True when the area puts monetary figures on screen. Areas flagged
 *            here obey `can_see_money` / `finance.view_amounts` masking.
 *   nav      How the area appears in the menu. `group` is the sidebar group
 *            ('company', 'projects', 'catalog', 'meetings', 'reports') or null
 *            for a top-level entry; `route` is where the entry points; `order`
 *            sorts within the group; `tab` is the project / job-site nav key.
 *            Omit `nav` entirely for an area that has no menu entry of its own.
 *   swept    THE LEGACY BRIDGE. False means this area has not yet had its
 *            permission pass (docs/permissions-module-plan.md §9.1): the
 *            resolver falls back to today's role checks for company-wide users
 *            and denies assigned users and guests outright. Each module pass
 *            flips exactly one of these to true. When they are all true the
 *            bridge is deleted.
 *   actions  The actions the area supports. A bare string is a plain action
 *            using the label from `actions` below; an array can override the
 *            label or flag the action `sensitive` (rendered with a warning in
 *            the permission matrix, never granted by a template by default).
 */

return [

    /*
    |---------------------------------------------------------------------------
    | Action labels
    |---------------------------------------------------------------------------
    | The shared vocabulary. An area may add its own action names, but these
    | five carry the same meaning everywhere and are the columns of the matrix.
    */

    'actions' => [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'approve' => 'Approve',
        'delete' => 'Delete',
    ],

    /*
    |---------------------------------------------------------------------------
    | Sidebar groups
    |---------------------------------------------------------------------------
    | The collapsible groups of the left menu. Groups and top-level menu items
    | share one ordering space, so `order` here and `order` in `menu` below are
    | compared against each other. A group with no visible children is not
    | rendered at all.
    */

    'groups' => [
        'company' => [
            'name' => 'Company',
            'order' => 20,
            'active' => ['company.*', 'users.*', 'access.*'],
            'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        ],
        'projects' => [
            'name' => 'Projects',
            'order' => 30,
            'active' => ['projects.*', 'clients.*', 'subcontractors.*', 'cost-codes.*', 'payments.*', 'contract-payments.*', 'payment-batches.*'],
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        'catalog' => [
            'name' => 'Catalog',
            'order' => 40,
            'active' => ['catalog.*', 'suppliers.*'],
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        ],
        'meetings' => [
            'name' => 'Meetings',
            'order' => 70,
            'active' => ['meetings.*', 'meeting-series.*', 'tasks.*'],
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        ],
        'reports' => [
            'name' => 'Reports',
            'order' => 80,
            'active' => ['reports.*'],
            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | The left menu
    |---------------------------------------------------------------------------
    | The sidebar, written out once. `App\Services\Navigation` renders exactly
    | this, dropping any entry whose module is switched off or whose `ability`
    | the person does not hold, and dropping a group that ends up with no
    | children. There is no menu markup anywhere else, which is what stops an
    | entry and its route ever disagreeing again.
    |
    | One area can own several entries (Catalog owns All Items and Categories),
    | and an entry can name any ability of its area — Meeting Series is a
    | meetings entry gated on `meetings.manage_series`.
    |
    | `header` puts the entry in the top bar instead of the sidebar.
    */

    'menu' => [
        [
            'key' => 'dashboard',
            'name' => 'Dashboard',
            'group' => null,
            'order' => 10,
            'route' => 'dashboard',
            'ability' => 'dashboard.view',
            'active' => ['dashboard'],
            'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        ],

        [
            'key' => 'company-info',
            'name' => 'Company Info',
            'group' => 'company',
            'order' => 10,
            'route' => 'company.info',
            'ability' => 'company.view',
            'active' => ['company.info'],
            'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        ],
        [
            'key' => 'users',
            'name' => 'Users',
            'group' => 'company',
            'order' => 20,
            'route' => 'users.index',
            'ability' => 'users.view',
            'active' => ['users.*'],
            'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        ],

        [
            'key' => 'access',
            'name' => 'Roles & Access',
            'group' => 'company',
            'order' => 30,
            'route' => 'access.index',
            'ability' => 'access.view',
            'active' => ['access.*'],
            'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
        ],

        [
            'key' => 'all-projects',
            'name' => 'All Projects',
            'group' => 'projects',
            'order' => 10,
            'route' => 'projects.index',
            'ability' => 'projects.view',
            'active' => ['projects.*'],
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'key' => 'subcontractors',
            'name' => 'Subcontractors',
            'group' => 'projects',
            'order' => 20,
            'route' => 'subcontractors.index',
            'ability' => 'vendors.view',
            'active' => ['subcontractors.*'],
            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        ],
        [
            'key' => 'clients',
            'name' => 'Clients',
            'group' => 'projects',
            'order' => 30,
            'route' => 'clients.index',
            'ability' => 'clients.view',
            'active' => ['clients.*'],
            'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        ],
        [
            'key' => 'cost-codes',
            'name' => 'Cost Codes',
            'group' => 'projects',
            'order' => 40,
            'route' => 'cost-codes.templates.index',
            'ability' => 'cost-codes.view',
            'active' => ['cost-codes.*'],
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        ],
        [
            'key' => 'payments',
            'name' => 'Payments',
            'group' => 'projects',
            'order' => 50,
            'route' => 'payments.index',
            'ability' => 'payments.view',
            'active' => ['payments.*'],
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'key' => 'contract-payments',
            'name' => 'Contract Payments',
            'group' => 'projects',
            'order' => 60,
            'route' => 'contract-payments.index',
            'ability' => 'payments.view',
            'active' => ['contract-payments.*'],
            'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        ],
        [
            'key' => 'payment-batches',
            'name' => 'Payment Batches',
            'group' => 'projects',
            'order' => 70,
            'route' => 'payment-batches.index',
            'ability' => 'payments.batch',
            'active' => ['payment-batches.*'],
            'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        ],

        [
            'key' => 'catalog-items',
            'name' => 'All Items',
            'group' => 'catalog',
            'order' => 10,
            'route' => 'catalog.index',
            'ability' => 'catalog.view',
            'active' => ['catalog.index', 'catalog.create', 'catalog.edit'],
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        ],
        [
            'key' => 'catalog-categories',
            'name' => 'Categories',
            'group' => 'catalog',
            'order' => 20,
            'route' => 'catalog.categories.index',
            'ability' => 'catalog.view',
            'active' => ['catalog.categories.*'],
            'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        ],
        [
            'key' => 'suppliers',
            'name' => 'Suppliers',
            'group' => 'catalog',
            'order' => 30,
            'route' => 'suppliers.index',
            'ability' => 'vendors.view',
            'active' => ['suppliers.*'],
            'icon' => 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
        ],

        [
            'key' => 'estimates',
            'name' => 'Estimates',
            'group' => null,
            'order' => 50,
            'route' => 'estimates.index',
            'ability' => 'estimates.view',
            'active' => ['estimates.*'],
            'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        ],
        [
            'key' => 'invoices',
            'name' => 'Invoices',
            'group' => null,
            'order' => 60,
            'route' => 'invoices.index',
            'ability' => 'invoices.view',
            'active' => ['invoices.*'],
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],

        [
            'key' => 'minutes',
            'name' => 'Minutes',
            'group' => 'meetings',
            'order' => 10,
            'route' => 'meetings.index',
            'ability' => 'meetings.view',
            'active' => ['meetings.*'],
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        ],
        // The buying queue: what one person has been asked to price. It has no
        // project of its own, so its list filters rather than being guarded —
        // see MyQuotations and PurchaseRequisition::visibleTo().
        [
            'key' => 'my-quotations',
            'name' => 'My Quotations',
            'group' => 'projects',
            'order' => 45,
            'route' => 'quotations.mine',
            'ability' => 'quotations.view',
            'active' => ['quotations.mine'],
            'badge' => [\App\Livewire\Quotation\MyQuotations::class, 'navBadge'],
            'icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
        ],
        [
            'key' => 'my-tasks',
            'name' => 'My Tasks',
            'group' => 'meetings',
            'order' => 20,
            'route' => 'tasks.mine',
            'ability' => 'tasks.view',
            'active' => ['tasks.*'],
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        ],
        [
            'key' => 'meeting-series',
            'name' => 'Meeting Series',
            'group' => 'meetings',
            'order' => 30,
            'route' => 'meeting-series.index',
            'ability' => 'meetings.manage_series',
            'active' => ['meeting-series.*'],
            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        ],

        [
            'key' => 'report-sales-tax',
            'name' => 'Sales Tax Report',
            'group' => 'reports',
            'order' => 10,
            'route' => 'reports.sales-tax',
            'ability' => 'reports.sales_tax',
            'active' => ['reports.sales-tax'],
            'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        ],
        [
            'key' => 'report-accounts-payable',
            'name' => 'Accounts Payable',
            'group' => 'reports',
            'order' => 20,
            'route' => 'reports.accounts-payable',
            'ability' => 'reports.accounts_payable',
            'active' => ['reports.accounts-payable'],
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'key' => 'report-company-financials',
            'name' => 'Company Financials',
            'group' => 'reports',
            'order' => 30,
            'route' => 'reports.company-financials',
            'ability' => 'reports.company_financials',
            'active' => ['reports.company-financials'],
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'key' => 'report-expenses',
            'name' => 'Expense Report',
            'group' => 'reports',
            'order' => 40,
            'route' => 'reports.expenses',
            'ability' => 'reports.expenses',
            'active' => ['reports.expenses'],
            'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'key' => 'report-payment-schedule',
            'name' => 'Payment Schedule',
            'group' => 'reports',
            'order' => 50,
            'route' => 'reports.payment-schedule',
            'ability' => 'reports.payment_schedule',
            'active' => ['reports.payment-schedule'],
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        ],
        [
            'key' => 'report-payment-details',
            'name' => 'Payment Details',
            'group' => 'reports',
            'order' => 60,
            'route' => 'reports.payment-details',
            'ability' => 'reports.payment_details',
            'active' => ['reports.payment-details'],
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        ],

        [
            'key' => 'documentation',
            'name' => 'Documentation',
            'group' => null,
            'order' => 90,
            'route' => 'documentation.index',
            'ability' => 'documentation.view',
            'active' => ['documentation.*'],
            'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
        ],

        // The gear in the top bar. It is rendered for everybody today and
        // 403s for anybody who is not an administrator; from here it is only
        // rendered for the people who can actually open it.
        [
            'key' => 'settings',
            'name' => 'Settings',
            'group' => null,
            'order' => 900,
            'header' => true,
            'route' => 'system-settings.index',
            'ability' => 'settings.view',
            'active' => ['system-settings.*'],
            'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        ],
    ],


    /*
    |---------------------------------------------------------------------------
    | The groups of the project and job-site tab bar
    |---------------------------------------------------------------------------
    | Seventeen tabs in one scrolling row was a row nobody could read, so the
    | bar is grouped: three tabs stay flat (Overview, Job Sites, Team) and the
    | rest live in these four dropdowns. Groups and flat tabs share one ordering
    | space, exactly like the sidebar — `order` here is compared against
    | `project_order` / `job_site_order` in `tabs` below.
    |
    | A group whose tabs this person cannot see is not rendered at all, and a
    | group left with a single visible tab is flattened back into the bar rather
    | than shown as a dropdown that opens onto one line.
    |
    | The labels are read from lang/en/navigation.php and its pt_BR twin —
    | `name` here is the fallback and the English wording, never a translation.
    */

    'tab_groups' => [
        'financial' => [
            'name' => 'Financial',
            'order' => 30,
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'procurement' => [
            'name' => 'Procurement',
            'order' => 40,
            'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        ],
        'collaboration' => [
            'name' => 'Collaboration',
            'order' => 50,
            'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        ],
        'field' => [
            'name' => 'Field',
            'order' => 60,
            'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        ],
    ],


    /*
    |---------------------------------------------------------------------------
    | The project and job-site tabs
    |---------------------------------------------------------------------------
    | The two nav bars, written out once. `App\Services\Navigation` renders
    | them, dropping any tab whose module is switched off or whose ability the
    | person does not hold on that project or job site, and placing what is left
    | into the groups declared above.
    |
    | `job_site_route` is null for a tab that only exists at project level. The
    | two orders were kept separate while the bars were flat and disagreed about
    | where change orders belonged; grouping settled that, so they now match —
    | the pair of keys stays because a level may need to differ again.
    */

    'tabs' => [
        [
            'key' => 'overview',
            'name' => 'Overview',
            'ability' => 'project.view',
            'group' => null,
            'project_route' => 'projects.overview',
            'project_order' => 10,
            'job_site_route' => 'jobsites.overview',
            'job_site_order' => 10,
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'key' => 'jobsites',
            'name' => 'Job Sites',
            'ability' => 'project.view',
            'group' => null,
            'project_route' => 'projects.jobsites',
            'project_order' => 20,
            'job_site_route' => null,
            'job_site_order' => null,
            'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
        ],
        [
            'key' => 'budget',
            'name' => 'Budget',
            'ability' => 'budget.view',
            'group' => 'financial',
            'project_route' => 'projects.budget',
            'project_order' => 31,
            'job_site_route' => 'jobsites.budget',
            'job_site_order' => 31,
            'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        ],
        [
            'key' => 'expenses',
            'name' => 'Expenses',
            'ability' => 'expenses.view',
            'group' => 'financial',
            'project_route' => 'projects.expenses',
            'project_order' => 32,
            'job_site_route' => 'jobsites.expenses',
            'job_site_order' => 32,
            'icon' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
        ],
        [
            'key' => 'income',
            'name' => 'Income',
            'ability' => 'income.view',
            'group' => 'financial',
            'project_route' => 'projects.income',
            'project_order' => 33,
            'job_site_route' => 'jobsites.income',
            'job_site_order' => 33,
            'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        ],
        [
            'key' => 'report',
            'name' => 'Report',
            'ability' => 'project-report.view',
            'group' => 'financial',
            'project_route' => 'projects.report',
            'project_order' => 34,
            'job_site_route' => 'jobsites.report',
            'job_site_order' => 34,
            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        ],
        [
            'key' => 'requisitions',
            'name' => 'Requisitions',
            'ability' => 'requisitions.view',
            'group' => 'procurement',
            'project_route' => 'projects.requisitions',
            'project_order' => 41,
            'job_site_route' => 'jobsites.requisitions',
            'job_site_order' => 41,
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        ],
        [
            'key' => 'quotations',
            'name' => 'Quotations',
            'ability' => 'quotations.view',
            'group' => 'procurement',
            'project_route' => 'projects.quotations',
            'project_order' => 42,
            'job_site_route' => 'jobsites.quotations',
            'job_site_order' => 42,
            'icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
        ],
        [
            'key' => 'purchase-orders',
            'name' => 'Purchase Orders',
            'ability' => 'purchase-orders.view',
            'group' => 'procurement',
            'project_route' => 'projects.purchase-orders',
            'project_order' => 43,
            'job_site_route' => 'jobsites.purchase-orders',
            'job_site_order' => 43,
            'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        ],
        [
            'key' => 'contracts',
            'name' => 'Contracts',
            'ability' => 'contracts.view',
            'group' => 'procurement',
            'project_route' => 'projects.contracts',
            'project_order' => 44,
            'job_site_route' => 'jobsites.contracts',
            'job_site_order' => 44,
            'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        ],
        [
            'key' => 'change-orders',
            'name' => 'Change Orders',
            'ability' => 'change-orders.view',
            'group' => 'procurement',
            'project_route' => 'projects.change-orders',
            'project_order' => 45,
            'job_site_route' => 'jobsites.change-orders',
            'job_site_order' => 45,
            'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        ],
        [
            'key' => 'documents',
            'name' => 'Documents',
            'ability' => 'documents.view',
            'group' => 'collaboration',
            'project_route' => 'projects.documents',
            'project_order' => 51,
            'job_site_route' => 'jobsites.documents',
            'job_site_order' => 51,
            'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        ],
        [
            'key' => 'rfis',
            'name' => 'RFIs',
            'ability' => 'rfis.view',
            'group' => 'collaboration',
            'project_route' => 'projects.rfis',
            'project_order' => 52,
            'job_site_route' => 'jobsites.rfis',
            'job_site_order' => 52,
            'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'key' => 'approvals',
            'name' => 'Approvals',
            'ability' => 'approvals.view',
            'group' => 'collaboration',
            'project_route' => 'projects.approvals',
            'project_order' => 53,
            'job_site_route' => 'jobsites.approvals',
            'job_site_order' => 53,
            'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        ],
        [
            'key' => 'daily-reports',
            'name' => 'Daily Reports',
            'ability' => 'daily-reports.view',
            'group' => 'field',
            'project_route' => 'projects.daily-reports',
            'project_order' => 61,
            'job_site_route' => 'jobsites.daily-reports',
            'job_site_order' => 61,
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        ],
        [
            'key' => 'tasks',
            'name' => 'Tasks',
            'ability' => 'tasks.view',
            'group' => 'field',
            'project_route' => 'projects.tasks',
            'project_order' => 62,
            'job_site_route' => 'jobsites.tasks',
            'job_site_order' => 62,
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        ],
        [
            'key' => 'team',
            'name' => 'Team',
            'ability' => 'team.view',
            'group' => null,
            'project_route' => 'projects.team',
            'project_order' => 70,
            'job_site_route' => 'jobsites.team',
            'job_site_order' => 70,
            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        ],
    ],
    /*
    |---------------------------------------------------------------------------
    | Areas
    |---------------------------------------------------------------------------
    */

    'areas' => [

        /*
        | -- Company-wide areas: the left menu and everything behind it --------
        */

        // The dashboard is the only screen in the application that is entirely
        // a roll-up: every card and every panel on it summarises another
        // module. So its permissions come in two parts. `view` opens the page
        // — everybody has it, because it is where a login lands — and
        // `overview` is what turns the page into the company overview.
        // Everything ON the overview is then gated by the ability of the
        // module it summarises, so the overview shows a person their own
        // slice and never more (see docs/permissions-module.md, M18).
        'dashboard' => [
            'name' => 'Dashboard',
            'module' => 'dashboard',
            'levels' => ['global'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view' => ['name' => 'Open the dashboard'],
                'overview' => [
                    'name' => 'See the company overview',
                    'sensitive' => true,
                ],
            ],
        ],

        'company' => [
            'name' => 'Company Info',
            'module' => 'company',
            'levels' => ['global'],
            'swept' => true,
            'actions' => ['view', 'edit'],
        ],

        'users' => [
            'name' => 'Users',
            'module' => 'company',
            'levels' => ['global'],
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit',
                'suspend' => ['name' => 'Suspend or reactivate', 'sensitive' => true],
            ],
        ],

        // The permission module managing itself. Whoever holds `access.manage`
        // can hand out every other ability, so it is the most sensitive grant
        // in the system and is never part of a template.
        'access' => [
            'name' => 'Roles & Access',
            'module' => 'company',
            'levels' => ['global'],
            'swept' => true,
            'actions' => [
                'view' => ['name' => 'See who has access'],
                'manage' => ['name' => 'Grant and revoke access', 'sensitive' => true],
            ],
        ],

        'settings' => [
            'name' => 'System Settings',
            'module' => 'company',
            'levels' => ['global'],
            // Reached from the gear in the header rather than the sidebar.
            'swept' => true,
            'actions' => [
                'view', 'edit',
                'manage_modules' => ['name' => 'Switch modules on and off', 'sensitive' => true],
            ],
        ],

        'projects' => [
            'name' => 'Projects',
            'module' => 'projects',
            'levels' => ['global'],
            'swept' => true,
            'actions' => [
                'view' => ['name' => 'See the project list'],
                'create', 'edit',
                'archive' => ['name' => 'Archive or close'],
                'delete' => ['sensitive' => true],
            ],
        ],

        'clients' => [
            'name' => 'Clients',
            'module' => 'projects',
            'levels' => ['global'],
            'swept' => true,
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        // Suppliers and subcontractors are one table since the vendor
        // unification; they stay one area with one set of abilities.
        'vendors' => [
            'name' => 'Vendors',
            'module' => 'projects',
            'levels' => ['global'],
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'merge' => ['name' => 'Merge duplicates', 'sensitive' => true],
            ],
        ],

        'cost-codes' => [
            'name' => 'Cost Codes',
            'module' => 'projects',
            'levels' => ['global'],
            'swept' => true,
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        // The three company-wide money screens: the payments dashboard, the
        // contract payments list and the payment batches. All three had no
        // guard at all beyond being signed in — noted in E1, deliberately left
        // for this pass rather than patched ahead of the engine.
        //
        // `pay` is NOT `limited`, and that is a limitation rather than a
        // decision: `approval_limit` lives on a membership or a template, and
        // these screens belong to no project, so there is nothing to read a
        // ceiling from. See P13 in docs/review-and-improvements.md.
        'payments' => [
            'name' => 'Payments',
            'module' => 'projects',
            'levels' => ['global'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view',
                // `limited` since F1: this releases money, the same act as
                // contracts.pay, and until then it was the one way round the
                // approval ceiling (P19).
                'pay' => ['name' => 'Record a payment', 'limited' => true],
                'batch' => ['name' => 'Build payment batches'],
                'refund' => ['name' => 'Refund a payment', 'sensitive' => true],
            ],
        ],

        'catalog' => [
            'name' => 'Catalog',
            'module' => 'catalog',
            'levels' => ['global'],
            'money' => true,
            'swept' => true,
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        'estimates' => [
            'name' => 'Estimates',
            'module' => 'estimates',
            'levels' => ['global'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'send' => ['name' => 'Send to the client'],
            ],
        ],

        'invoices' => [
            'name' => 'Invoices',
            'module' => 'invoices',
            'levels' => ['global'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'send' => ['name' => 'Send to the client'],
                'record_payment' => ['name' => 'Record a payment'],
            ],
        ],

        'reports' => [
            'name' => 'Reports',
            'module' => 'reports',
            'levels' => ['global'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view' => ['name' => 'Open reports'],
                'export' => ['name' => 'Export and print'],
                'sales_tax' => ['name' => 'Sales Tax'],
                'accounts_payable' => ['name' => 'Accounts Payable'],
                'company_financials' => ['name' => 'Company Financials', 'sensitive' => true],
                'expenses' => ['name' => 'Expense Report'],
                'payment_schedule' => ['name' => 'Payment Schedule'],
                'payment_details' => ['name' => 'Payment Details'],
            ],
        ],

        // The in-app guide library. Reading it is open to everybody signed in
        // by design — it is the manual — but `view` is still a real toggle,
        // because an install that writes its own procedures into it may not
        // want an outside guest reading them. Writing has always been
        // manager-or-above and deleting administrator-only; both are grants
        // now (F2, the last area off the bridge).
        'documentation' => [
            'name' => 'Documentation',
            'module' => 'documentation',
            'levels' => ['global'],
            'swept' => true,
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        /*
        | -- Project and job-site areas: the tabs of the two nav bars ----------
        |
        | Every one of these can be granted on a project (cascading to its job
        | sites) or on a single job site (overriding the project for that site).
        | `global` on the same area means the company-wide list of it, where one
        | exists.
        */

        'project' => [
            'name' => 'Project Overview',
            'module' => 'projects',
            'levels' => ['project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'edit',
                'archive' => ['name' => 'Archive or close'],
            ],
        ],

        'team' => [
            'name' => 'Team',
            'module' => 'projects',
            'levels' => ['project', 'job_site'],
            'swept' => true,
            'actions' => [
                'view' => ['name' => 'See who is on this project'],
                'invite' => ['name' => 'Invite people'],
                'manage' => ['name' => 'Change what people can do', 'sensitive' => true],
            ],
        ],

        'expenses' => [
            'name' => 'Expenses',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'pay' => ['name' => 'Mark as paid'],
                'edit_paid' => ['name' => 'Edit a paid expense', 'sensitive' => true],
            ],
        ],

        'income' => [
            'name' => 'Income',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'distribute' => ['name' => 'Distribute across job sites'],
            ],
        ],

        // A requisition asks for *things*, not a sum: its items carry a quantity
        // and a unit and never a price, because pricing is what the quotation
        // round is for. So no figure on these screens is monetary, and
        // `approve` is deliberately NOT `limited` — there is nothing to compare
        // an approval ceiling against. Limits start at M8, where money is.
        'requisitions' => [
            'name' => 'Requisitions',
            'module' => 'quotations',
            'levels' => ['global', 'project', 'job_site'],
            'money' => false,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'submit' => ['name' => 'Submit for approval'],
                'approve' => ['name' => 'Approve or reject'],
                'approve_own' => [
                    'name' => 'Approve their own requisitions',
                    'sensitive' => true,
                ],
                // Handing the requisition to whoever will run the cotação.
                // Held apart from `approve` because the two are not the same
                // act: approving says the company will buy this, assigning
                // says who goes and gets the prices. A procurement lead who
                // may not approve spend still shares the work out.
                'assign' => ['name' => 'Assign who quotes it'],
                'duplicate' => ['name' => 'Duplicate into a new draft'],
            ],
        ],

        // Where money genuinely arrives, and so the first area whose actions
        // obey `approval_limit`. Four of the seven are held apart on purpose:
        //
        //  create_standalone  a round raised with no requisition is how the
        //                     whole approval chain gets walked around (N1)
        //  award_own          whoever typed a vendor's prices picking that
        //                     vendor as the winner (N3, mirrors M7's rule)
        //  convert            committing an award into a purchase order
        //  convert_contract   committing it into a *schedule of payments*,
        //                     which is a bigger act than one expense
        'quotations' => [
            'name' => 'Quotations',
            'module' => 'quotations',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'create_standalone' => [
                    'name' => 'Raise a round with no requisition',
                    'sensitive' => true,
                ],
                'award' => ['name' => 'Award a round', 'limited' => true],
                'award_own' => [
                    'name' => 'Award proposals they keyed in',
                    'sensitive' => true,
                ],
                'convert' => ['name' => 'Convert to a purchase order', 'limited' => true],
                'convert_contract' => [
                    'name' => 'Convert to a contract',
                    'limited' => true,
                    'sensitive' => true,
                ],
                // Who owns the round and who else is on it. Held apart from
                // `edit` for the same reason `requisitions.assign` is held
                // apart from `approve`: deciding who does the work is not the
                // same act as doing it. Note that being put on a round grants
                // nothing — a collaborator without `edit` sees it and cannot
                // price it, which is correct.
                'assign' => ['name' => 'Assign who works the round'],
            ],
        ],

        // Who work falls to here when nobody says otherwise — the buyer who
        // runs a cotação today, RFI ball-in-court and approval reviewers next.
        // Its own area rather than an action on `team`, because naming the
        // person every approved requisition is handed to is a scheduling
        // decision, not the same act as granting somebody access. The panel
        // sits on the Team page; the install-wide tier is a System Settings tab.
        'assignment-defaults' => [
            'name' => 'Assignment defaults',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'money' => false,
            'swept' => false,
            'actions' => [
                'view' => ['name' => 'See who work falls to by default'],
                'edit' => ['name' => 'Change who work falls to by default'],
            ],
        ],

        // `approve` and `receive` are deliberately not the same grant: on a
        // real site the office approves the spend and the storeman signs for
        // the delivery. `approve` obeys the ceiling because approving an order
        // creates the expense; `receive` commits nothing, so it does not.
        'purchase-orders' => [
            'name' => 'Purchase Orders',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'approve' => ['name' => 'Approve', 'limited' => true],
                'receive' => ['name' => 'Record a delivery'],
            ],
        ],

        // Approving a change order is what moves the cost budget, and today
        // anyone who can reach the screen can approve, reject or return one
        // (docs/permissions-notes.md §4b). The four questions that notation
        // asks are answered by holding four things apart:
        //
        //  approve       deciding on a pending change — obeys the ceiling,
        //                because approving is what revises the budget
        //  approve_own   approving one you raised yourself, as N2 and N3
        //  unapprove     pulling an APPROVED change back out of a live budget,
        //                which is a narrower act than approving it was
        //  delete        and an approved change cannot be deleted at all until
        //                somebody has un-approved it — a rule about the
        //                record, so it binds administrators too
        'change-orders' => [
            'name' => 'Change Orders',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit',
                'approve' => ['name' => 'Approve or turn down', 'limited' => true],
                'approve_own' => [
                    'name' => 'Approve their own change orders',
                    'sensitive' => true,
                ],
                'unapprove' => [
                    'name' => 'Undo an approval',
                    'sensitive' => true,
                ],
                'delete' => ['sensitive' => true],
            ],
        ],

        // The same rule as M10's change orders: doing and undoing are the same
        // grant while nothing has moved, and undoing is narrower once it has.
        // `measure` covers confirming work — a measurement, or releasing a
        // scheduled instalment — and undoing those, because neither has paid
        // anybody yet. `pay` actually moves money and obeys the ceiling.
        // `unpay` takes a payment back out, which is the narrow act.
        'contracts' => [
            'name' => 'Contracts',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'measure' => ['name' => 'Measure and release instalments'],
                'pay' => ['name' => 'Release a payment', 'limited' => true],
                'unpay' => [
                    'name' => 'Undo a payment',
                    'sensitive' => true,
                ],
            ],
        ],

        // N5, N7 and `see_internal`. Reading is a grant now, which it never
        // was: `Document::isVisibleTo()` used to return true for every
        // non-internal document to anybody, so the download route — behind
        // `auth` and nothing else — handed any project's files to any signed-in
        // person who guessed an id.
        //
        // `share` stays with admin and manager as it is today (the owner's
        // answer to N7), but as an ordinary toggle rather than a role check:
        // it is the one place in the application where access leaves the
        // application, so it is worth being able to take away.
        'documents' => [
            'name' => 'Documents',
            'module' => 'documents',
            'levels' => ['project', 'job_site'],
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'share' => ['name' => 'Create a share link', 'sensitive' => true],
                'see_internal' => ['name' => 'See internal documents'],
            ],
        ],

        'tasks' => [
            'name' => 'Tasks',
            'module' => 'meetings',
            'levels' => ['global', 'project', 'job_site'],
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'close' => ['name' => 'Close a task'],
                // Added at F2. `edit` is "may you work with tasks here at all"
                // and everybody who works has it; this is the second layer the
                // model guards used to spell `is_admin || is_manager`: may you
                // change a task that is not yours and that you did not raise?
                'edit_any' => ['name' => "Change somebody else's task"],
            ],
        ],

        'meetings' => [
            'name' => 'Meetings',
            'module' => 'meetings',
            'levels' => ['global', 'project', 'job_site'],
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'freeze' => ['name' => 'Freeze the minutes'],
                // Added at F2. Correcting a record that has already been signed
                // off and mailed to every attendee — the narrowest thing in the
                // module, and it is logged. Was a hard-coded `is_admin`.
                'revise' => ['name' => 'Correct a published minute', 'sensitive' => true],
                'manage_series' => ['name' => 'Manage meeting series'],
            ],
        ],

        // A formal question put to the projetista or the owner, with the answer
        // tracked back. In Brazil the person answering is normally external, so
        // this is one of the few areas a guest holds by design — the
        // "Projetista (external)" template in PermissionSeeder is built on it.
        //
        // `money => true` since an RFI carries an estimated cost impact. Two
        // separate protections, and both are wanted:
        //
        //   `view_impact` hides the *fact* that a question costs anything, and
        //   is what keeps it from an outside projetista.
        //
        //   `can_see_money` masks the figure for somebody who may know there
        //   is a cost but not what it is — a site supervisor, say.
        //
        // `revise` is the same shape as `meetings.revise`, for the same reason:
        // an answer is frozen once the RFI closes, and correcting one that has
        // already been sent out is the narrowest thing in the module.
        'rfis' => [
            'name' => 'RFIs',
            'module' => 'collaboration',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'answer' => ['name' => 'Answer an RFI'],
                'close' => ['name' => 'Close an RFI'],
                'view_impact' => ['name' => 'See cost and schedule impact'],
                'export' => ['name' => 'Export and print'],
                'distribute' => ['name' => 'E-mail to the distribution list', 'sensitive' => true],
                'revise' => ['name' => 'Correct a closed RFI', 'sensitive' => true],
            ],
        ],

        // Aprovações — the submittal cycle. Materials, samples, shop drawings
        // and the laudos e certificados that carry most of the weight in BR.
        //
        // `money => true` because an approval hangs off a budget line, and the
        // screen that generates approvals from the orçamento lists those lines
        // with their values.
        'approvals' => [
            'name' => 'Approvals',
            'module' => 'collaboration',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'submit' => ['name' => 'Submit a revision'],
                'respond' => ['name' => 'Record a response'],
                'seed' => ['name' => 'Generate approvals from the budget'],
                'manage_packages' => ['name' => 'Manage approval packages'],
                'export' => ['name' => 'Export and print'],
                'distribute' => ['name' => 'E-mail to the distribution list', 'sensitive' => true],
            ],
        ],

        // The site's diary, and the main screen of the two templates that hold
        // almost nothing else: Site Supervisor and the read-only Client guest.
        //
        // A report closes seven days after its date, or when it is locked. The
        // override used to be a hard-coded `is_admin`; it is a grant now, the
        // same shape as `expenses.edit_paid`.
        'daily-reports' => [
            'name' => 'Daily Reports',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'edit_locked' => [
                    'name' => 'Edit a closed report',
                    'sensitive' => true,
                ],
            ],
        ],

        'budget' => [
            'name' => 'Budget',
            'module' => 'projects',
            'levels' => ['project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'lock' => ['name' => 'Lock the budget', 'sensitive' => true],
            ],
        ],

        'project-report' => [
            'name' => 'Financial Report',
            'module' => 'projects',
            'levels' => ['project', 'job_site'],
            'money' => true,
            'swept' => true,
            'actions' => [
                'view',
                'export' => ['name' => 'Export and print'],
            ],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Company-wide money visibility
    |---------------------------------------------------------------------------
    | The role-level twin of a membership's `can_see_money`. Held, and the
    | figures on company-wide screens are shown; not held, and they are masked.
    | Declared here rather than as an area because it cuts across all of them.
    */

    'finance_ability' => 'finance.view_amounts',
];
