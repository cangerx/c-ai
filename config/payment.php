<?php

return [
    'default' => env('PAYMENT_DRIVER', 'tianque'),

    'providers' => [
        'tianque' => [
            'driver' => 'tianque',
            'sandbox' => env('TIANQUE_SANDBOX', true),
            'host' => env('TIANQUE_HOST', 'https://openapi-test.tianquetech.com'),
            'host_production' => env('TIANQUE_HOST_PROD', 'https://openapi.tianquetech.com'),
            'org_id' => env('TIANQUE_ORG_ID', ''),
            'mno' => env('TIANQUE_MNO', ''),
            'sub_mech_id' => env('TIANQUE_SUB_MECH_ID', ''),
            'private_key' => env('TIANQUE_PRIVATE_KEY', ''),
            'public_key' => env('TIANQUE_PUBLIC_KEY', ''),
            'sign_type' => env('TIANQUE_SIGN_TYPE', 'RSA'),
            'version' => env('TIANQUE_VERSION', '1.0'),
            'notify_url' => env('TIANQUE_NOTIFY_URL', env('APP_URL') . '/api/payment/notify/tianque'),
            'order_expires_minutes' => 10,
        ],
    ],
];
