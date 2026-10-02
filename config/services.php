<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Staff Accounts
    |--------------------------------------------------------------------------
    |
    | Used by AdminUserSeeder. The super admin password is deliberately NOT given a
    | default: with no value set, the seeder generates a strong one and prints it once.
    | Publishing a known super admin password in the repository would leave a permanent
    | backdoor, however convenient for a demo.
    |
    */

    'super_admin_email' => env('PARKEASY_SUPER_ADMIN_EMAIL'),

    'super_admin_password' => env('PARKEASY_SUPER_ADMIN_PASSWORD'),

    'lot_admin_password' => env('PARKEASY_LOT_ADMIN_PASSWORD'),

    'operator_password' => env('PARKEASY_OPERATOR_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
