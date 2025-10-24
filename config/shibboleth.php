<?php

return [
    /**
     * The path to redirect the user when a login is required.
     * This should point to the Shibboleth login handler. The "?target=" parameter will be
     * automatically applied to redirect back after login.
     * Default: /Shibboleth.sso/Login
     */
    'login_path' => env('SHIBBOLETH_LOGIN_URL', '/Shibboleth.sso/Login'),
    /**
     * The path to redirect the user for logout.
     * This should point to the Shibboleth logout handler.
     * The "?return=" parameter will be automatically applied to redirect back after logout.
     * Default: /Shibboleth.sso/Logout
     */
    'logout_path' => env('SHIBBOLETH_LOGOUT_URL', '/Shibboleth.sso/Logout'),

    /**
     * Maps attributes provided to the $_SERVER superglobal to user model fields.
     * The keys are the user model fields, the values are the corresponding $_SERVER keys.
     * The attribute names are case-sensitive.
     * They will be searched in the $_SERVER array; make sure your web server is configured to pass them to PHP
     * Also, Shibboleth is sometimes weird, so the names will also be found if they are prefixed with "REDIRECT_"
     * Meaning if you set SHIBBOLETH_USERNAME_VAR to "uid", the code will search for $_SERVER['uid'] and $_SERVER['REDIRECT_uid']
     */
    'attribute_map' => [
        /**
         * The username attribute.
         * Defaults to 'REMOTE_USER', which is usually set by the web server to the authenticated user's identifier.
         * Other common values are 'uid' or 'eppn' (eduPersonPrincipalName).
         * This value MUST be unique for your installation.
         */
        'username' => env('SHIBBOLETH_USERNAME_VAR', 'REMOTE_USER'),
        /**
         * The email address for the user for contact and notifications.
         * Defaults to 'mail', which is commonly used in Shibboleth installations.
         * Other common values are 'email' or 'mail'.
         * This value MUST be unique for your installation.
         */
        'email' => env('SHIBBOLETH_EMAIL_VAR', 'mail'),
        /**
         * This is a group identifier that is currently primarily used for logging purposes,
         * but will, in the future, also be used for permission management.
         * Defaults (historically) to 'employee', but you might want to set it to 'affiliation' or 'eduPersonAffiliation'
         * depending on your Shibboleth setup.
         * Common values are 'affiliation', 'eduPersonAffiliation', 'role' or 'isMemberOf'.
         * If you don't have a suitable attribute, you can also set it to a fixed value like 'employee' or 'member'.
         * This value does NOT need to be unique.
         */
        'employeetype' => env('SHIBBOLETH_EMPLOYEETYPE_VAR', 'employee'),
        /**
         * The full name of the user for display purposes.
         * Defaults to 'displayName', which is commonly used in Shibboleth installations.
         * Other common values are 'cn' (common name), 'givenName' (first name) or 'sn' (surname).
         * This value does NOT need to be unique.
         * If you need to concatenate multiple attributes (e.g. givenName and sn), you can provide a comma-separated list of attribute names.
         * If a list is given, the attributes will be concatenated with a space in between. If any of them are missing, they will be skipped.
         */
        'name' => env('SHIBBOLETH_NAME_VAR', 'displayname'),
    ],
];
