<?php

return [
    'driver' => 'gd', // atau 'imagick' jika server kamu mendukung
    'avatar' => env('APP_URL') . '/storage/avatar-thumb/',
    'loc' => env('APP_URL') . '/storage/loc/',
    'loc-thumb' => env('APP_URL') . '/storage/loc-thumb/',
    'loc-doc' => env('APP_URL') . '/storage/loc-doc/',
    'rab' => env('APP_URL') . '/storage/rabs/',
    'bcki' => env('APP_URL') . '/storage/fpis/',
    'visitor' => env('APP_URL') . '/storage/visitors/',
];