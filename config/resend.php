<?php

return [
    'api_key' => env('RESEND_API_KEY'),

    'domain' => env('RESEND_DOMAIN'),

    'path' => env('RESEND_PATH', 'resend'),

    'webhook' => [
        // A non-empty fallback keeps the webhook locked until a real secret is configured.
        'secret' => env('RESEND_WEBHOOK_SECRET') ?: 'resend-webhooks-disabled-until-secret-is-set',
        'tolerance' => (int) env('RESEND_WEBHOOK_TOLERANCE', 300),
    ],
];
