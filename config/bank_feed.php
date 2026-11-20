<?php

declare(strict_types=1);

/*
 * The link to the bank. With no provider the feature is off for everyone.
 */
return [
    // `enable_banking`, `fake` (tests and local trials) or empty: off
    'provider' => env('BANK_FEED_PROVIDER', ''),

    // The country the list of banks is read for
    'country' => 'IT',

    // Days of transactions read the first time a bank is connected
    'first_days' => 90,

    // Days read again at every sync, to catch what the bank booked late
    'overlap_days' => 5,

    // Days the consent of the bank lasts
    'consent_days' => 180,

    // Days before the end of the consent when the family is warned
    'warn_days' => 7,

    // Months a transaction not linked to an expense or an income is kept
    'kept_months' => 12,

    // The times of the day (Europe/Rome) the banks are read
    'sync_times' => ['07:00', '13:00', '19:00'],

    'enable_banking' => [
        'app_id' => env('ENABLE_BANKING_APP_ID'),
        'key_path' => env('ENABLE_BANKING_KEY_PATH'),
        'url' => env('ENABLE_BANKING_URL', 'https://api.enablebanking.com'),
    ],
];
