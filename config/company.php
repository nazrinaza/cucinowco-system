<?php

return [
    'name' => 'CuciNow.co',
    'legal_name' => 'Thursina Land & Services',
    'registration_number' => '0001232756-P',
    'established' => 2000,
    'phone' => env('COMPANY_PHONE', '601151471145'),
    'email' => env('COMPANY_EMAIL', 'hello@cucinow.co'),
    'whatsapp' => env('COMPANY_WHATSAPP', '601151471145'),
    'address' => env('COMPANY_ADDRESS', 'No. 28B, Jalan BRP 1/6, Bukit Rahman Putra, 40160 Sungai Buloh, Selangor'),
    'sst_enabled' => (bool) env('SST_ENABLED', false),
    'sst_rate' => (float) env('SST_RATE', 8),
    'quote_valid_days' => 14,
];
