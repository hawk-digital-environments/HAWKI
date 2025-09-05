<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default LDAP Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the LDAP connections below you wish
    | to use as your default connection for all LDAP operations. Of
    | course you may add as many connections you'd like below.
    |
    */

    'default' => env('LDAP_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | LDAP Connections
    |--------------------------------------------------------------------------
    |
    | Below you may configure each LDAP connection your application requires
    | access to. Be sure to include a valid base DN - otherwise you may
    | not receive any results when performing LDAP search operations.
    |
    */

    'connections' => [
        'default' =>[
            'ldap_host' => env('LDAP_HOST'),
            'ldap_port' => env('LDAP_PORT'),
            'ldap_base_dn' => env('LDAP_BASE_DN'),
            'ldap_bind_pw' => env('LDAP_BIND_PW'),
            'ldap_search_dn' => env('LDAP_SEARCH_DN'),
            'ldap_filter'=> env('LDAP_FILTER'),

            'attribute_map' => [
                'username' => env("LDAP_ATTR_USERNAME", "cn"),
                'email' => env("LDAP_ATTR_EMAIL", "mail"),
                'employeetype' => env("LDAP_ATTR_EMPLOYEETYPE", "employeetype"),
                'name' => env("LDAP_ATTR_NAME", "displayname"),
            ],
            'invert_name' => env('LDAP_INVERT_NAME', true),
        ],
    ],
    'debug_mode' => env('LDAP_DEBUG_MODE', false),

];
