<?php

return [
    /* Public branded login host served by this Laravel application. */
    'branded_host' => env('WEBMAIL_BRANDED_HOST', 'email.cucinow.co'),

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

    /* cPanel adds the authenticated security token before this local path. */
    'destination_path' => env('WEBMAIL_DESTINATION_PATH', '/3rdparty/roundcube/index.php'),
];
