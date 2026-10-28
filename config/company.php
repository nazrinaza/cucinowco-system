<?php

return [
    'name' => 'CuciNow.co',
    'legal_name' => 'Thursina Cleaning & Services',
    'registration_number' => 'SA0636500',
    'established' => 2000,
    'phone' => env('COMPANY_PHONE', '601112428593'),
    'email' => env('COMPANY_EMAIL', 'hello@cucinow.co'),
    'notifications_email' => env('NOTIFICATION_EMAIL', env('COMPANY_EMAIL', 'hello@cucinow.co')),
    'whatsapp' => env('COMPANY_WHATSAPP', '601112428593'),
    'address' => env('COMPANY_ADDRESS', 'No.48A, Jalan BRP 1/2 Bukit Rahman Putra, Sungai Buloh. Malaysia'),
    'sst_enabled' => (bool) env('SST_ENABLED', false),
    'sst_rate' => (float) env('SST_RATE', 8),
    'quote_valid_days' => 14,
    'quotation_terms' => [
        'This quotation covers only the services and quantities listed above and remains valid until the stated expiry date.',
        'The project date, working hours, team and machinery are confirmed after written acceptance and remain subject to availability and safe site access.',
        'Additional work, changed site conditions or requests outside the agreed scope may require a revised quotation or written approval before work continues.',
        'Payment is due according to the terms and due date stated on the invoice issued for the accepted work.',
        'The customer must provide agreed access, permits and available utilities required to carry out the work safely and efficiently.',
    ],
];
