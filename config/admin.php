<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admins
    |--------------------------------------------------------------------------
    |
    | Email addresses that are granted super-admin access (the admin users
    | area, Pulse, user impersonation). Provide a comma-separated list via
    | the ADMIN_EMAILS environment variable to override the default.
    |
    */

    'super_admins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_EMAILS', 'hasbellaoui.faycal@gmail.com'))
    ))),

];
