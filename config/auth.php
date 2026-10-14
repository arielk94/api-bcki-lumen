<?php

return [
    'defaults' => [
        'guard' => 'jwt',
    ],

    'guards' => [
        'jwt' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class, // atau model dummy
        ],
    ],
];