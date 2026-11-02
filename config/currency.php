<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Currency
    |--------------------------------------------------------------------------
    |
    | Name (the unique identifier of a currency) of the reference currency. Its
    | exchange rate is always 1 and every other exchange rate means "units of that
    | currency per 1 USD". It is fixed on purpose: the seeded rates and the API
    | documentation assume USD.
    |
    */

    'base' => 'USD',

];
