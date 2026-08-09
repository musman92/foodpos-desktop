<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image optimization presets
    |--------------------------------------------------------------------------
    |
    | Uploaded images are resized and compressed before storage. Presets are
    | tuned for POS grids, admin thumbnails, and future tenant websites.
    |
    */

    'disk' => 'public',

    'presets' => [
        // Menu items — POS loads many at once; keep files small.
        'menu' => [
            'max_width' => 800,
            'max_height' => 800,
            'quality' => 82,
            'format' => 'webp',
        ],

        // Super-admin platform library — slightly larger for future web menus.
        'platform_media' => [
            'max_width' => 1024,
            'max_height' => 1024,
            'quality' => 82,
            'format' => 'webp',
        ],

        // Categories, cuisines, deals — smaller admin / catalog tiles.
        'catalog' => [
            'max_width' => 600,
            'max_height' => 600,
            'quality' => 80,
            'format' => 'webp',
        ],
    ],

];
