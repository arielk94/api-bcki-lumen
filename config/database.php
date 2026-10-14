<?php

return [

    'default' => env('DB_CONNECTION', 'sqlsrv'),

    'connections' => [

        'sqlSrvFPI' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST_FPI', 'localhost'),
            'port' => env('DB_PORT_FPI', '1433'),
            'database' => env('DB_DATABASE_FPI', 'forge'),
            'username' => env('DB_USERNAME_FPI', 'forge'),
            'password' => env('DB_PASSWORD_FPI', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'trust_server_certificate' => true
        ],

        'sqlsrvHrd' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST_HRD', 'localhost'),
            'port' => env('DB_PORT_HRD', '1433'),
            'database' => env('DB_DATABASE_HRD', 'forge'),
            'username' => env('DB_USERNAME_HRD', 'forge'),
            'password' => env('DB_PASSWORD_HRD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'trust_server_certificate' => true
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'trust_server_certificate' => true
        ],
    ],

];