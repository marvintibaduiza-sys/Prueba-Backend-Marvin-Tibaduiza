<?php

/*
|--------------------------------------------------------------------------
| Seeded Accounts
|--------------------------------------------------------------------------
|
| Credentials for the accounts created by "php artisan db:seed". They are
| read from the environment so no password is ever stored in the code.
| An account without email or password is skipped.
|
*/

return [

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'seller' => [
        'name' => env('SELLER_NAME', 'Seller'),
        'email' => env('SELLER_EMAIL'),
        'password' => env('SELLER_PASSWORD'),
    ],

];
