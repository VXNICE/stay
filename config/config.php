<?php

return [
    'database' => [
        'mysql' => [
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT', 3306),
            'dbname' => env('DB_NAME'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
        ],
    ],
    'payment' => [
        'paymong' => [
            'base_url' => env('PAYMONGO_API_BASE_URL_V1'),
            'public_key' => env('PAYMONGO_PUBLIC_KEY'),
            'secret_key' => env('PAYMONGO_SECRET_KEY')
        ]
    ]
];
