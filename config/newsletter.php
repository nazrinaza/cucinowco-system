<?php

return [
    'batch_size' => (int) env('NEWSLETTER_BATCH_SIZE', 100),
    'queue' => env('NEWSLETTER_QUEUE', 'newsletters'),
];
