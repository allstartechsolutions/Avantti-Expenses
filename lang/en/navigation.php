<?php

/**
 * The words on the menus.
 *
 * The project and job-site tab bar reads its labels from here rather than from
 * the global `en.json` / `pt_BR.json`, because menu wording is the thing that
 * gets revised most often and revising it in a five-thousand-line JSON file is
 * how a tab ends up half-renamed. One key per tab, one per group, in the two
 * files side by side: changing a name means changing two lines in two small
 * files that can be read end to end.
 *
 * The keys are the tab and group keys from `config/permissions.php`. A key that
 * is missing here falls back to the `name` written in that config, so a new tab
 * is never rendered as a raw `navigation.tabs.…` string — but a tab whose name
 * is only in the config is untranslated, which is unfinished work.
 *
 * Referenced as `__('navigation.tabs.expenses')`.
 */

return [

    /*
    |---------------------------------------------------------------------------
    | The groups of the project / job-site tab bar
    |---------------------------------------------------------------------------
    */

    'groups' => [
        'financial' => 'Financial',
        'procurement' => 'Procurement',
        'collaboration' => 'Collaboration',
        'field' => 'Field',
    ],

    /*
    |---------------------------------------------------------------------------
    | The tabs themselves
    |---------------------------------------------------------------------------
    */

    'tabs' => [
        'overview' => 'Overview',
        'jobsites' => 'Job Sites',
        'budget' => 'Budget',
        'expenses' => 'Expenses',
        'income' => 'Income',
        'report' => 'Report',
        'requisitions' => 'Requisitions',
        'quotations' => 'Quotations',
        'purchase-orders' => 'Purchase Orders',
        'contracts' => 'Contracts',
        'change-orders' => 'Change Orders',
        'documents' => 'Documents',
        'rfis' => 'RFIs',
        'approvals' => 'Approvals',
        'daily-reports' => 'Daily Reports',
        'tasks' => 'Tasks',
        'team' => 'Team',
    ],

    /*
    |---------------------------------------------------------------------------
    | The bar itself
    |---------------------------------------------------------------------------
    */

    'sections' => 'Sections',
    'open_section' => 'Open the :section menu',

];
