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
            'ldap_bind_dn' => (static function () {
                $bindDn = env('LDAP_BIND_DN');
                if (!empty($bindDn)) {
                    return $bindDn;
                }
                // Historically the BASE_DN was used as BIND_DN if BIND_DN was not set
                // We keep this behavior for backward compatibility
                $baseDn = env('LDAP_BASE_DN');
                if (!empty($baseDn)) {
                    return $baseDn;
                }

                return null;
            })(),
            'ldap_bind_pw' => env('LDAP_BIND_PW'),
            'ldap_base_dn' => (static function () {
                $searchDn = env('LDAP_SEARCH_DN');
                if (!empty($searchDn)) {
                    return $searchDn;
                }

                // If the LDAP_BIND_DN is set, we assume that LDAP_BASE_DN is now correctly pointing to the base
                $bindDn = env('LDAP_BIND_DN');
                if (!empty($bindDn)) {
                    $baseDn = env('LDAP_BASE_DN');
                    if (!empty($baseDn)) {
                        return $baseDn;
                    }
                }

                // If the LDAP_BIND_DN is NOT set, we assume that LDAP_BASE_DN is still the BIND_DN for backward compatibility
                return null;
            })(),
            'ldap_filter'=> env('LDAP_FILTER'),

            'attribute_map' => [
                'username' => env("LDAP_ATTR_USERNAME", "cn"),
                'email' => env("LDAP_ATTR_EMAIL", "mail"),
                'employeeType' => env("LDAP_ATTR_EMPLOYEETYPE", "employeetype"),
                'name' => env("LDAP_ATTR_NAME", "displayname"),
            ],
            'invert_name' => env('LDAP_INVERT_NAME', true),
        ],
    ],
];
