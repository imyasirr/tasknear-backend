<?php

return [
    /*
    | Demo / staging OTP when SMS is not wired yet.
    | Set OTP_DEMO_CODE=123456 on the server .env — request + verify will use this code.
    | Leave empty in real production once SMS is live.
    */
    'demo_code' => env('OTP_DEMO_CODE'),

    /*
    | Return OTP in /auth/otp/request JSON (no SMS). Use on staging/live dev only.
    */
    'show_in_response' => filter_var(env('OTP_SHOW_IN_RESPONSE', false), FILTER_VALIDATE_BOOL),

    'expires_minutes' => (int) env('OTP_EXPIRES_MINUTES', 10),
];
