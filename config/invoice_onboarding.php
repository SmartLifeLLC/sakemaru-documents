<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Onboarding
    |--------------------------------------------------------------------------
    |
    | documents 側から invoice DB へ直接書き込む初回オンボーディング設定。
    |
    */
    'enabled' => env('INVOICE_ONBOARDING_ENABLED', true),

    'connection' => env('INVOICE_ONBOARDING_CONNECTION', 'invoice'),

    'table' => env('INVOICE_ONBOARDING_TABLE', 'onboarding_requests'),

    'source_system' => env('INVOICE_ONBOARDING_SOURCE_SYSTEM', 'documents'),

    'default_status' => env('INVOICE_ONBOARDING_DEFAULT_STATUS', 'requested'),
];
