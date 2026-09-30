<?php

use App\Models\Admin;
use App\Models\Participant;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | 'web'   -> rezervat pentru viitor (ex: eventual sesiune pentru mobil, dacă
    |            partea de mobil rămâne monolit Blade/Livewire în același proiect).
    | 'admin' -> folosit de panoul de admin. Complet separat de 'web'.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        // DXA: adaugat (Aplicația participanților): conturile participanților, separate de cele de admin.
        'participant' => [
            'driver' => 'session',
            'provider' => 'participants',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],

        'participants' => [
            'driver' => 'eloquent',
            'model' => Participant::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
