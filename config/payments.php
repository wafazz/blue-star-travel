<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active payment gateway
    |--------------------------------------------------------------------------
    | Which driver handles online payments. `sandbox` is the built-in simulator
    | (dev only — it never auto-credits a booking). Add further drivers to the
    | list below and switch this value; nothing else in the app changes.
    */

    'default' => env('PAYMENT_GATEWAY', 'sandbox'),

    'drivers' => [

        'sandbox' => [
            'class' => App\Services\Gateways\SandboxGateway::class,
            'label' => 'FPX Sandbox (simulated)',
        ],

        'billplz' => [
            'class'         => App\Services\Gateways\BillplzGateway::class,
            'label'         => 'FPX / Online Banking (Billplz)',
            'key'           => env('BILLPLZ_KEY'),
            'x_signature'   => env('BILLPLZ_X_SIGNATURE'),
            'collection_id' => env('BILLPLZ_COLLECTION_ID'),
            // Sandbox portal: https://www.billplz-sandbox.com
            'sandbox'       => env('BILLPLZ_SANDBOX', true),
        ],

    ],

];
