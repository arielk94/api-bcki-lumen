<?php

return [

    'default' => env('DB_CONNECTION', 'sqlsrv'),

    'connections' => [

        'sqlSrvBCKI' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST_BCKI', 'localhost'),
            'port' => env('DB_PORT_BCKI', '1433'),
            'database' => env('DB_DATABASE_BCKI', 'forge'),
            'username' => env('DB_USERNAME_BCKI', 'forge'),
            'password' => env('DB_PASSWORD_BCKI', ''),
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
