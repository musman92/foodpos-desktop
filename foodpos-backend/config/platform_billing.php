<?php

return [

    'currency' => env('PLATFORM_BILLING_CURRENCY', 'USD'),

    'vendor' => [
        'name' => env('PLATFORM_VENDOR_NAME', env('APP_NAME', 'Food POS')),
        'email' => env('PLATFORM_VENDOR_EMAIL'),
        'phone' => env('PLATFORM_VENDOR_PHONE'),
        'address' => env('PLATFORM_VENDOR_ADDRESS'),
        'tax_id' => env('PLATFORM_VENDOR_TAX_ID'),
    ],

    'payment_methods' => [
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
        'card' => 'Card',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ],

    'default_due_days' => 14,

    /*
    | Recurring billing intervals per tenant. Yearly = one invoice for the full year.
    */
    'intervals' => [
        'monthly' => ['label' => 'Monthly', 'months' => 1],
        'quarterly' => ['label' => 'Quarterly', 'months' => 3],
        'semi_annual' => ['label' => 'Semi-annual', 'months' => 6],
        'yearly' => ['label' => 'Yearly (full year)', 'months' => 12],
    ],

    'currencies' => ['USD', 'EUR', 'GBP', 'AED', 'SAR', 'PKR', 'INR', 'CAD', 'AUD'],

    'default_trial_days' => 14,

    'trial_options' => [
        0 => 'No trial — charge from day one',
        7 => '7 days',
        14 => '14 days (default)',
        30 => '30 days',
        60 => '60 days',
        90 => '90 days',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'partial' => 'Partially paid',
        'paid' => 'Paid',
        'void' => 'Void',
    ],

];
