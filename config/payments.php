<?php

return [
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY'),
        'key_secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'checkout' => [
        'currency' => 'INR',
        'company_name' => env('PAYMENTS_COMPANY_NAME', env('APP_NAME', 'TaskNear')),
    ],
];
