<?php

return [
    
    'login_path' => env('SHIBBOLETH_LOGIN_URL', ''),
    'logout_path' => env('SHIBBOLETH_LOGOUT_URL', ''),

    'attribute_map' => [
        'email' => env('SHIBBOLETH_EMAIL_VAR', 'mail'),
        'employeetype' => env('SHIBBOLETH_EMPLOYEETYPE_VAR', 'employee'),
        'name' => env('SHIBBOLETH_NAME_VAR', 'displayname'),
    ],

];