<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Global kill switch for the SOP gate. When false, the control panel is
    | never blocked — the SOP management screens stay available.
    |
    */

    'enabled' => env('SOP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | SOPs and consents live in their own database, registered as the `sop`
    | connection. Point this at your primary connection instead if you would
    | rather keep everything in one place.
    |
    */

    'database' => [
        'driver' => 'sqlite',
        'database' => env('SOP_DATABASE', storage_path('app/sop/sop.sqlite')),
        'prefix' => '',
        'foreign_key_constraints' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bypass
    |--------------------------------------------------------------------------
    |
    | Role and group handles whose users skip the gate entirely. Super admins
    | always bypass. This is resolved without touching the SOP database, so a
    | broken database can never lock these users out.
    |
    */

    'bypass' => [
        'roles' => [],
        'groups' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    |
    | Whether a consent additionally records the client IP and user agent.
    | Both are personal data under the GDPR, and user + version + timestamp
    | already prove the acknowledgment — so both default to off. Enable them
    | only with a documented legal basis.
    |
    */

    'audit' => [
        'ip' => false,
        'user_agent' => false,
    ],

];
