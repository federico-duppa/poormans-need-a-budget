<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Emails habilitados (whitelist)
    |--------------------------------------------------------------------------
    |
    | Solo los emails de esta lista pueden iniciar sesión vía Google. Se define
    | en la variable de entorno ALLOWED_EMAILS, separados por coma. El primero
    | de la lista queda marcado como administrador del presupuesto familiar.
    |
    */

    'allowed_emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ALLOWED_EMAILS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Monedas
    |--------------------------------------------------------------------------
    |
    | Moneda base del presupuesto (en la que se consolidan el dinero por asignar y
    | reportes) y moneda secundaria soportada para cuentas/transacciones.
    |
    */

    'base_currency' => env('BUDGET_BASE_CURRENCY', 'ARS'),

    'secondary_currency' => env('BUDGET_SECONDARY_CURRENCY', 'USD'),

    'currencies' => [
        'ARS' => ['symbol' => '$', 'name' => 'Peso argentino', 'decimals' => 2],
        'USD' => ['symbol' => 'US$', 'name' => 'Dólar estadounidense', 'decimals' => 2],
    ],

];
