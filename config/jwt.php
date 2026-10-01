<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared Django JWT Secret Key & Algorithm
    |--------------------------------------------------------------------------
    |
    | This secret key must match the secret key used by Django REST Framework
    | SimpleJWT to sign access tokens (HS256 by default).
    |
    */

    'secret' => env('JWT_SECRET', 'django-insecure-shared-secret-change-me-in-production'),

    'algo' => env('JWT_ALGO', 'HS256'),
];
