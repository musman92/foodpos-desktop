<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super admin platform actions
    |--------------------------------------------------------------------------
    |
    | Whitelisted Artisan commands runnable from the super admin UI.
    | Never pass user-supplied command names — only keys defined here.
    |
    */

    'actions' => [
        'migrate' => [
            'group' => 'database',
            'title' => 'Run migrations',
            'description' => 'Apply pending database migrations. Use after deploying schema changes.',
            'command' => 'migrate',
            'options' => ['--force' => true],
            'icon' => 'fa-database',
            'confirm' => 'Run all pending database migrations?',
            'danger' => true,
        ],

        'permissions_sync' => [
            'group' => 'permissions',
            'title' => 'Sync roles & permissions',
            'description' => 'Refresh permission definitions and default tenant roles for every company.',
            'command' => 'permissions:sync',
            'options' => [],
            'icon' => 'fa-user-shield',
            'confirm' => 'Sync roles and permissions for all companies?',
            'inputs' => [
                [
                    'name' => 'company',
                    'type' => 'text',
                    'label' => 'Company ID or slug (optional — leave blank for all)',
                    'flag' => '--company',
                ],
            ],
        ],

        'cache_clear' => [
            'group' => 'permissions',
            'title' => 'Clear application cache',
            'description' => 'Flush cached config, routes, and application data.',
            'command' => 'cache:clear',
            'options' => [],
            'icon' => 'fa-broom',
            'confirm' => 'Clear the application cache?',
        ],

        'ingredients_sync_costs' => [
            'group' => 'catalog',
            'title' => 'Sync ingredient costs',
            'description' => 'Recalculate ingredient costs from purchase stock and history.',
            'command' => 'ingredients:sync-costs',
            'options' => [],
            'icon' => 'fa-carrot',
            'confirm' => 'Recalculate ingredient costs?',
            'inputs' => [
                [
                    'name' => 'company',
                    'type' => 'text',
                    'label' => 'Company ID (optional — leave blank for all)',
                    'flag' => '--company',
                ],
            ],
        ],

        'catalog_detach_globals' => [
            'group' => 'catalog',
            'title' => 'Detach global catalog',
            'description' => 'Clone or reuse tenant-owned catalog rows and re-point company data away from global categories and ingredients.',
            'command' => 'catalog:detach-globals',
            'options' => [],
            'icon' => 'fa-unlink',
            'confirm' => 'Detach global catalog references for all companies?',
            'danger' => true,
            'inputs' => [
                [
                    'name' => 'company',
                    'type' => 'text',
                    'label' => 'Company ID or slug (optional)',
                    'flag' => '--company',
                ],
                [
                    'name' => 'dry_run',
                    'type' => 'checkbox',
                    'label' => 'Dry run (preview only)',
                    'flag' => '--dry-run',
                ],
            ],
        ],

        'catalog_detach_globals_purge' => [
            'group' => 'catalog',
            'title' => 'Detach global catalog & purge globals',
            'description' => 'Detach catalog references, then delete unreferenced global rows. Run only after verifying detach results.',
            'command' => 'catalog:detach-globals',
            'options' => ['--purge-globals' => true],
            'icon' => 'fa-trash-alt',
            'confirm' => 'Detach global catalog AND purge unreferenced global rows? This cannot be undone easily.',
            'danger' => true,
            'inputs' => [
                [
                    'name' => 'company',
                    'type' => 'text',
                    'label' => 'Company ID or slug (optional)',
                    'flag' => '--company',
                ],
            ],
        ],

        'images_reoptimize' => [
            'group' => 'media',
            'title' => 'Re-optimize stored images',
            'description' => 'Resize and compress existing menu, platform media, and catalog images for faster POS loading.',
            'command' => 'images:reoptimize',
            'options' => [],
            'icon' => 'fa-compress-arrows-alt',
            'confirm' => 'Re-optimize all stored images? This may take a while on large libraries.',
            'inputs' => [
                [
                    'name' => 'dry_run',
                    'type' => 'checkbox',
                    'label' => 'Dry run (preview only)',
                    'flag' => '--dry-run',
                ],
                [
                    'name' => 'force',
                    'type' => 'checkbox',
                    'label' => 'Force re-encode already optimized WebP files',
                    'flag' => '--force',
                ],
            ],
        ],
    ],

    'groups' => [
        'database' => 'Database',
        'permissions' => 'Permissions & cache',
        'catalog' => 'Catalog & inventory',
        'media' => 'Media',
    ],

];
