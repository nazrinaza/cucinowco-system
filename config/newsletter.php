<?php

return [
    'batch_size' => (int) env('NEWSLETTER_BATCH_SIZE', 50),
    'batch_delay_seconds' => (int) env('NEWSLETTER_BATCH_DELAY_SECONDS', 60),
    'batch_lease_seconds' => (int) env('NEWSLETTER_BATCH_LEASE_SECONDS', 300),
    'queue' => env('NEWSLETTER_QUEUE', 'newsletters'),
];
