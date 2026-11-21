<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Official Webmail sign-in
    |--------------------------------------------------------------------------
    |
    | This must point to the HTTPS cPanel Webmail service. CuciNow only links
    | users there and never receives or stores mailbox credentials.
    |
    */
    'login_url' => env('WEBMAIL_LOGIN_URL', 'https://webmail.cucinow.co'),
];
