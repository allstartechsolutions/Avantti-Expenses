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
