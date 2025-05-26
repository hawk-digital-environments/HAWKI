<?php

return [
    
    'login_path' => env('SHIBBOLETH_LOGIN_URL', ''),
    'logout_path' => env('SHIBBOLETH_LOGOUT_URL', ''),

    'attribute_map' => [
        'email' => env('SHIBBOLETH_EMAIL_VAR', 'mail'),
        'employeetype' => env('SHIBBOLETH_EMPLOYEETYPE_VAR', 'affiliation'),
        'gname' => env('SHIBBOLETH_GNAME_VAR', 'givenName'),
        'sname' => env('SHIBBOLETH_SNAME_VAR', 'sn'),
    ],

];
