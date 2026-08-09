<?php

/**
 * Offline edition flags for the FoodPOS copy inside the Tauri desktop project.
 * Cloud SaaS (/Users/usman/Sites/foodpos) is never modified — only this copy.
 */
return [
    /*
    | When true: single company, no super-admin platform, no multi-branch UI,
    | no secret login, no cloud print bridge API.
    */
    'enabled' => (bool) env('OFFLINE_EDITION', true),

    /*
    | Display name seeded for the single local company.
    */
    'company_name' => env('OFFLINE_COMPANY_NAME', 'My Restaurant'),

    /*
    | Hidden default branch name (schema still uses branch_id).
    */
    'branch_name' => env('OFFLINE_BRANCH_NAME', 'Main'),

    /*
    | Default admin created by OfflineCompanySeeder.
    */
    'admin_email' => env('OFFLINE_ADMIN_EMAIL', 'admin@local'),
    'admin_username' => env('OFFLINE_ADMIN_USERNAME', 'admin'),
    'admin_password' => env('OFFLINE_ADMIN_PASSWORD', 'admin123'),
];
