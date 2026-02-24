<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Partner Bootstrap
    |--------------------------------------------------------------------------
    |
    | documents から invoice に帳票公開する際、partner 未登録なら自動で作成し、
    | 初期 partner_user を生成するための設定。
    |
    */
    'email_domain' => env('INVOICE_PARTNER_BOOTSTRAP_EMAIL_DOMAIN', 'sakemaru.ai'),

    'initial_password' => env('INVOICE_PARTNER_BOOTSTRAP_INITIAL_PASSWORD', '12345678'),

    'default_role' => env('INVOICE_PARTNER_BOOTSTRAP_DEFAULT_ROLE', 'admin'),
];
