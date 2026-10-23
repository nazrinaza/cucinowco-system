<?php

return [
    // Leave empty locally to use /agents. Set the hostname only in production.
    'domain' => env('AGENT_PORTAL_DOMAIN'),
    'public_url' => env('APP_URL', 'http://localhost'),
    'attribution_days' => 90,
    'media_max_kb' => 10240,
];
