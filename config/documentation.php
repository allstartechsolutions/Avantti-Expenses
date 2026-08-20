<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    |
    | The sections the library is grouped into. Custom guides written inside
    | the app choose one of these too, so both sources sit in one index.
    |
    */

    'categories' => [
        'getting-started' => ['label' => 'Getting Started', 'icon' => 'book', 'order' => 10],
        'meetings' => ['label' => 'Meetings & Tasks', 'icon' => 'clipboard', 'order' => 20],
        'projects' => ['label' => 'Projects & Job Sites', 'icon' => 'building', 'order' => 30],
        'money' => ['label' => 'Money', 'icon' => 'currency', 'order' => 40],
        'general' => ['label' => 'Company Procedures', 'icon' => 'document', 'order' => 90],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipped guides
    |--------------------------------------------------------------------------
    |
    | Written as markdown in docs/ and rendered by the app, so a guide is never
    | out of step with the release it describes. The key is the slug used in
    | the URL; `file` is relative to the project root.
    |
    | Images referenced relatively from the markdown are served through
    | DocumentationImageController — nothing under docs/ is public.
    |
    */

    'guides' => [

        'meetings-and-tasks' => [
            'title' => 'Meetings, Minutes and Tasks',
            'summary' => 'How the series, the agenda, the minute and the tasks fit together — and how to run a meeting from start to finish.',
            'category' => 'meetings',
            'file' => 'docs/meetings-module-guide.md',
            'order' => 10,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Where images may be read from
    |--------------------------------------------------------------------------
    |
    | Only these directories, and only image files. Anything else 404s, so a
    | guide can never be used to read the rest of the repository.
    |
    */

    'image_roots' => ['docs/images'],

    'image_extensions' => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'],

];
