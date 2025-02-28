<?php

return [
    
    'oidc_idp' => env('OIDC_IDP', ''),
    'oidc_client_id' => env('OIDC_CLIENT_ID', ''),
    'oidc_client_secret' => env('OIDC_CLIENT_SECRET', ''),
    'oidc_logout_path' => env('OIDC_LOGOUT_URI', ''),

    'oidc_scopes' => explode(',', env('OIDC_SCOPES', 'profile,email')),

    'attribute_map' => [
        'firstname' => env('OIDC_FIRSTNAME_VAR', 'firstname'),
        'lastname' => env('OIDC_LASTNAME_VAR', 'lastname'),
        'email' => env('OIDC_EMAIL_VAR', 'email'),
        'employeetype' => env('OIDC_EMPLOYEETYPE_VAR', 'employeetype'),
    ],

];