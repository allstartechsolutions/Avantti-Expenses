<?php

return [
    'dashboard' => [
        'name' => 'Dashboard',
        'description' => 'Main dashboard with overview and analytics.',
        'is_core' => true,
        'route_prefixes' => ['dashboard'],
    ],

    'company' => [
        'name' => 'Company',
        'description' => 'Company info, users, and system settings.',
        'is_core' => true,
        'route_prefixes' => ['company.*', 'users.*', 'system-settings.*'],
    ],

    // Declared before 'projects': the module check stops at the first
    // matching prefix, and 'projects.*' would otherwise claim these routes.
    'quotations' => [
        'name' => 'Quotations',
        'description' => 'Purchase requisitions and vendor quotations (cotações) — the buy-side chain.',
        'route_prefixes' => [
            'projects.requisitions',
            'jobsites.requisitions',
            'projects.quotations',
            'jobsites.quotations',
            'requisitions.*',
            'quotations.*',
        ],
    ],

    // Declared before 'projects' for the same reason as 'quotations': the
    // module check stops at the first matching prefix, and 'projects.*' would
    // otherwise claim projects.documents.
    'documents' => [
        'name' => 'Documents',
        'description' => 'File repository for projects and job sites — folders, versions and share links.',
        'route_prefixes' => [
            'projects.documents',
            'jobsites.documents',
            'documents.*',
        ],
    ],

    'documentation' => [
        'name' => 'Documentation',
        'description' => 'Guides and tutorials — the ones shipped with the product and the ones this company writes.',
        'route_prefixes' => ['documentation.*'],
    ],

    // Declared before 'projects' for the same reason as 'documents': the
    // module check stops at the first matching prefix, and 'projects.*' would
    // otherwise claim projects.tasks.
    'meetings' => [
        'name' => 'Meetings',
        'description' => 'Meeting minutes (atas), agendas and the task system behind them.',
        'route_prefixes' => [
            'projects.tasks',
            'jobsites.tasks',
            'meetings.*',
            'meeting-series.*',
            'tasks.*',
        ],
    ],

    'projects' => [
        'name' => 'Projects',
        'description' => 'Project management including job sites, expenses, daily reports, budgets, and purchase orders.',
        'route_prefixes' => [
            'projects.*',
            'subcontractors.*',
            'clients.*',
            'cost-codes.*',
            'payments.*',
            'jobsites.*',
            'expenses.*',
            'dailyreports.*',
            'purchase-orders.*',
            'budgets.*',
            'job-sites.*',
            'projects.budgets.*',
            // vendors.* (merge tool) is owned by this module; the button on
            // the Suppliers page (catalog module) checks this module's state
            // before rendering.
            'vendors.*',
        ],
    ],

    'catalog' => [
        'name' => 'Catalog',
        'description' => 'Product catalog and supplier management.',
        'route_prefixes' => ['catalog.*', 'suppliers.*'],
    ],

    'estimates' => [
        'name' => 'Estimates',
        'description' => 'Create and manage project estimates.',
        'route_prefixes' => ['estimates.*'],
    ],

    'invoices' => [
        'name' => 'Invoices',
        'description' => 'Create and manage invoices and payments.',
        'route_prefixes' => ['invoices.*'],
    ],

    'reports' => [
        'name' => 'Reports',
        'description' => 'Sales tax and other financial reports.',
        'route_prefixes' => ['reports.*'],
    ],
];
