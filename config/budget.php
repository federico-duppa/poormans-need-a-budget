<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed emails (whitelist)
    |--------------------------------------------------------------------------
    |
    | Only the emails in this list can log in via Google. It is defined
    | in the ALLOWED_EMAILS environment variable, comma-separated. The first
    | one in the list is marked as the administrator of the family budget.
    |
    */

    'allowed_emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ALLOWED_EMAILS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    |
    | The budget's base currency (in which the money to assign and reports are
    | consolidated) and the supported secondary currency for accounts/transactions.
    |
    */

    'base_currency' => env('BUDGET_BASE_CURRENCY', 'ARS'),

    'secondary_currency' => env('BUDGET_SECONDARY_CURRENCY', 'USD'),

    'currencies' => [
        'ARS' => ['symbol' => '$', 'name' => 'Peso argentino', 'decimals' => 2],
        'USD' => ['symbol' => 'US$', 'name' => 'Dólar estadounidense', 'decimals' => 2],
    ],

];
