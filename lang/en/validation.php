<?php

/*
|--------------------------------------------------------------------------
| Validation attribute names — en
|--------------------------------------------------------------------------
|
| Deliberately partial. The FileLoader merges [framework/lang, lang/] with
| array_replace_recursive, so the framework's English rule messages are kept
| and only `attributes` is added here. Do not copy the rule messages in — an
| out-of-date duplicate would silently override the framework on upgrade.
|
| Without this, :attribute falls back to the humanised column name and the
| user reads "The job site id field is required."
|
| A $validationAttributes declared on a Livewire component still takes
| precedence over this map. See docs/pt-br-translation-audit.md.
|
*/

return [

    'attributes' => [

        // Identification
        'name' => 'name',
        'title' => 'title',
        'code' => 'code',
        'description' => 'description',
        'notes' => 'notes',
        'body' => 'content',
        'type' => 'type',
        'status' => 'status',
        'sku' => 'SKU',

        // People and contact
        'email' => 'email address',
        'contact_email' => 'contact email',
        'employee_email' => 'email address',
        'phone' => 'phone',
        'employee_phone' => 'phone',
        'website' => 'website',
        'contact_name' => 'contact name',
        'contact_person' => 'contact person',
        'company_name' => 'company name',
        'employee_name' => 'name',
        'employee_title' => 'title',
        'employee_notes' => 'notes',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'role_id' => 'role',

        // Address
        'street' => 'street',
        'address_2' => 'address line 2',
        'neighborhood' => 'neighborhood',
        'city' => 'city',
        'state' => 'state',
        'postal_code' => 'postal code',
        'latitude' => 'latitude',
        'longitude' => 'longitude',

        // Projects and sites
        'project_id' => 'project',
        'project_name' => 'project name',
        'job_site_id' => 'job site',
        'job_site_name' => 'job site name',
        'job_amount' => 'job amount',
        'project_manager_id' => 'project manager',
        'supervisor_id' => 'supervisor',
        'client_id' => 'client',
        'report_date' => 'report date',

        // Vendors and catalog
        'supplier_id' => 'preferred supplier',
        'vendor_id' => 'vendor',
        'category_id' => 'category',
        'parent_id' => 'parent category',
        'applicable_types' => 'applicable types',
        'current_cost' => 'cost',
        'purchase_unit' => 'purchase unit',
        'usage_unit' => 'usage unit',
        'units_per_purchase' => 'units per purchase',
        'billing_type' => 'billing type',
        'tax_rate_id' => 'tax rate',
        'rate' => 'rate',

        // Money and dates
        'amount' => 'amount',
        'amount_source' => 'amount source',
        'budgeted_amount' => 'budgeted amount',
        'due_date' => 'due date',
        'expiration_date' => 'expiration date',
        'payment_method' => 'payment method',

        // Documents and files
        'document_type_id' => 'document type',
        'document_file' => 'document file',
        'document_notes' => 'notes',
        'source_template_id' => 'template',

        // Ordering and flags
        'sort_order' => 'sort order',
        'display_order' => 'display order',
        'is_active' => 'active',
        'is_default' => 'default',

    ],

];
