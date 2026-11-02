<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Currency
    |--------------------------------------------------------------------------
    |
    | ISO 4217 code of the reference currency. Its exchange rate is always 1 and
    | every other exchange rate means "units of that currency per 1 base unit".
    |
    */

    'base' => env('BASE_CURRENCY', 'USD'),

];
