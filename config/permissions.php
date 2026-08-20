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
    | The project and job-site tabs
    |---------------------------------------------------------------------------
    | The two nav bars, written out once, in the order each of them uses today —
    | they differ, and both orders are kept. `App\Services\Navigation` renders
    | them, dropping any tab whose module is switched off or whose ability the
    | person does not hold on that project or job site.
    |
    | `job_site_route` is null for a tab that only exists at project level.
    */

    'tabs' => [
        [
            'key' => 'overview',
            'name' => 'Overview',
            'ability' => 'project.view',
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
            'project_route' => 'projects.jobsites',
            'project_order' => 20,
            'job_site_route' => null,
            'job_site_order' => null,
            'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
        ],
        [
            'key' => 'expenses',
            'name' => 'Expenses',
            'ability' => 'expenses.view',
            'project_route' => 'projects.expenses',
            'project_order' => 30,
            'job_site_route' => 'jobsites.expenses',
            'job_site_order' => 20,
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'key' => 'income',
            'name' => 'Income',
            'ability' => 'income.view',
            'project_route' => 'projects.income',
            'project_order' => 40,
            'job_site_route' => 'jobsites.income',
            'job_site_order' => 30,
            'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        ],
        [
            'key' => 'requisitions',
            'name' => 'Requisitions',
            'ability' => 'requisitions.view',
            'project_route' => 'projects.requisitions',
            'project_order' => 50,
            'job_site_route' => 'jobsites.requisitions',
            'job_site_order' => 60,
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        ],
        [
            'key' => 'quotations',
            'name' => 'Quotations',
            'ability' => 'quotations.view',
            'project_route' => 'projects.quotations',
            'project_order' => 60,
            'job_site_route' => 'jobsites.quotations',
            'job_site_order' => 70,
            'icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
        ],
        [
            'key' => 'purchase-orders',
            'name' => 'Purchase Orders',
            'ability' => 'purchase-orders.view',
            'project_route' => 'projects.purchase-orders',
            'project_order' => 70,
            'job_site_route' => 'jobsites.purchase-orders',
            'job_site_order' => 80,
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'key' => 'change-orders',
            'name' => 'Change Orders',
            'ability' => 'change-orders.view',
            'project_route' => 'projects.change-orders',
            'project_order' => 80,
            'job_site_route' => 'jobsites.change-orders',
            'job_site_order' => 40,
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'key' => 'contracts',
            'name' => 'Contracts',
            'ability' => 'contracts.view',
            'project_route' => 'projects.contracts',
            'project_order' => 90,
            'job_site_route' => 'jobsites.contracts',
            'job_site_order' => 50,
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'key' => 'documents',
            'name' => 'Documents',
            'ability' => 'documents.view',
            'project_route' => 'projects.documents',
            'project_order' => 100,
            'job_site_route' => 'jobsites.documents',
            'job_site_order' => 90,
            'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        ],
        [
            'key' => 'tasks',
            'name' => 'Tasks',
            'ability' => 'tasks.view',
            'project_route' => 'projects.tasks',
            'project_order' => 110,
            'job_site_route' => 'jobsites.tasks',
            'job_site_order' => 100,
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        ],
        [
            'key' => 'daily-reports',
            'name' => 'Daily Reports',
            'ability' => 'daily-reports.view',
            'project_route' => 'projects.daily-reports',
            'project_order' => 120,
            'job_site_route' => 'jobsites.daily-reports',
            'job_site_order' => 110,
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        ],
        [
            'key' => 'budget',
            'name' => 'Budget',
            'ability' => 'budget.view',
            'project_route' => 'projects.budget',
            'project_order' => 130,
            'job_site_route' => 'jobsites.budget',
            'job_site_order' => 120,
            'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        ],
        [
            'key' => 'team',
            'name' => 'Team',
            'ability' => 'team.view',
            'project_route' => 'projects.team',
            'project_order' => 150,
            'job_site_route' => 'jobsites.team',
            'job_site_order' => 150,
            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        ],

        [
            'key' => 'report',
            'name' => 'Report',
            'ability' => 'project-report.view',
            'project_route' => 'projects.report',
            'project_order' => 140,
            'job_site_route' => 'jobsites.report',
            'job_site_order' => 130,
            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
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

        'dashboard' => [
            'name' => 'Dashboard',
            'module' => 'dashboard',
            'levels' => ['global'],
            'money' => true,
            'swept' => false,
            'actions' => ['view'],
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
            'swept' => false,
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        // Suppliers and subcontractors are one table since the vendor
        // unification; they stay one area with one set of abilities.
        'vendors' => [
            'name' => 'Vendors',
            'module' => 'projects',
            'levels' => ['global'],
            'swept' => false,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'merge' => ['name' => 'Merge duplicates', 'sensitive' => true],
            ],
        ],

        'cost-codes' => [
            'name' => 'Cost Codes',
            'module' => 'projects',
            'levels' => ['global'],
            'swept' => false,
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        'payments' => [
            'name' => 'Payments',
            'module' => 'projects',
            'levels' => ['global'],
            'money' => true,
            'swept' => false,
            'actions' => [
                'view',
                'pay' => ['name' => 'Record a payment'],
                'batch' => ['name' => 'Build payment batches'],
                'refund' => ['name' => 'Refund a payment', 'sensitive' => true],
            ],
        ],

        'catalog' => [
            'name' => 'Catalog',
            'module' => 'catalog',
            'levels' => ['global'],
            'money' => true,
            'swept' => false,
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        'estimates' => [
            'name' => 'Estimates',
            'module' => 'estimates',
            'levels' => ['global'],
            'money' => true,
            'swept' => false,
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
            'swept' => false,
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
            'swept' => false,
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

        'documentation' => [
            'name' => 'Documentation',
            'module' => 'documentation',
            'levels' => ['global'],
            'swept' => false,
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
            'swept' => false,
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
            'swept' => false,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'distribute' => ['name' => 'Distribute across job sites'],
            ],
        ],

        'requisitions' => [
            'name' => 'Requisitions',
            'module' => 'quotations',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => false,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'submit' => ['name' => 'Submit for approval'],
                'approve' => ['name' => 'Approve or reject', 'limited' => true],
                'duplicate' => ['name' => 'Duplicate into a new draft'],
            ],
        ],

        'quotations' => [
            'name' => 'Quotations',
            'module' => 'quotations',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => false,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'award' => ['name' => 'Award a round', 'limited' => true],
                'convert' => ['name' => 'Convert to a PO or contract', 'limited' => true],
            ],
        ],

        'purchase-orders' => [
            'name' => 'Purchase Orders',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => false,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'approve' => ['name' => 'Approve', 'limited' => true],
                'receive' => ['name' => 'Record receipt'],
            ],
        ],

        'change-orders' => [
            'name' => 'Change Orders',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => false,
            'actions' => [
                'view', 'create', 'edit',
                'approve' => ['name' => 'Approve', 'limited' => true],
                'unapprove' => ['name' => 'Reject or return to pending', 'sensitive' => true],
                'delete' => ['sensitive' => true],
            ],
        ],

        'contracts' => [
            'name' => 'Contracts',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'money' => true,
            'swept' => false,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'measure' => ['name' => 'Record a measurement'],
                'pay' => ['name' => 'Release a payment', 'limited' => true],
            ],
        ],

        'documents' => [
            'name' => 'Documents',
            'module' => 'documents',
            'levels' => ['project', 'job_site'],
            'swept' => false,
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
            'swept' => false,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'close' => ['name' => 'Close a task'],
            ],
        ],

        'meetings' => [
            'name' => 'Meetings',
            'module' => 'meetings',
            'levels' => ['global', 'project', 'job_site'],
            'swept' => false,
            'actions' => [
                'view', 'create', 'edit', 'delete',
                'freeze' => ['name' => 'Freeze the minutes'],
                'manage_series' => ['name' => 'Manage meeting series'],
            ],
        ],

        'daily-reports' => [
            'name' => 'Daily Reports',
            'module' => 'projects',
            'levels' => ['global', 'project', 'job_site'],
            'swept' => false,
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        'budget' => [
            'name' => 'Budget',
            'module' => 'projects',
            'levels' => ['project', 'job_site'],
            'money' => true,
            'swept' => false,
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
            'swept' => false,
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
