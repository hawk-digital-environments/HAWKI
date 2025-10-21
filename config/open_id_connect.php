<?php

return [

    /**
     * The OpenID Connect Identity Provider (IdP) URL.
     * Example: 'https://idp.example.com'
     */
    'oidc_idp' => env('OIDC_IDP', ''),
    /**
     * The Client ID registered with the OIDC provider.
     */
    'oidc_client_id' => env('OIDC_CLIENT_ID', ''),
    /**
     * The Client Secret associated with the Client ID.
     */
    'oidc_client_secret' => env('OIDC_CLIENT_SECRET', ''),
    /**
     * The logout endpoint URL for the OIDC provider.
     * This is used to redirect users to the IdP's logout page when they log out of the application.
     * The "?post_logout_redirect_uri=" parameter will be appended automatically to redirect users back to the application after logout.
     */
    'oidc_logout_path' => env('OIDC_LOGOUT_URI', ''),

    /**
     * Defines the scopes to request during OIDC authentication.
     * Default is 'profile,email'.
     * Those scopes should be sufficient to retrieve the standard claims needed for user identification.
     */
    'oidc_scopes' => explode(',', env('OIDC_SCOPES', 'profile,email')),

    'attribute_map' => [
        /**
         * The username attribute.
         * Defaults to 'preferred_username', which is normally part of the OIDC standard claims.
         * Other common values are 'sub' (subject) or 'email'.
         * This value MUST be unique for your installation.
         */
        'username' => env('OIDC_USERNAME_VAR', 'preferred_username'),
        /**
         * The email address for the user for contact and notifications.
         * Defaults to 'email', which is commonly used in the OIDC profile claim.
         *  Another common value is 'mail'.
         * This value MUST be unique for your installation.
         */
        'email' => env('OIDC_EMAIL_VAR', 'email'),
        /**
         * This is a group identifier that is currently primarily used for logging purposes,
         * but will, in the future, also be used for permission management.
         * Defaults to 'employeetype', but depending on your OIDC provider, you might want to set it to 'group', or 'roles'.
         * If you don't have a suitable attribute, you can also set it to a fixed value like 'employee' or 'member'.
         * This value does NOT need to be unique.
         */
        'employeetype' => env('OIDC_EMPLOYEETYPE_VAR', 'employeetype'),
        /**
         * The full name of the user for display purposes.
         * Historically, this was configured as "OIDC_FIRSTNAME_VAR" and "OIDC_LASTNAME_VAR" for first and last name,
         * that were concatenated with a space in between. This is still supported for backward compatibility,
         * however it is recommended to use "OIDC_NAME_VAR" instead.
         * The current default is: If OIDC_NAME_VAR is set, use it; otherwise, if either OIDC_FIRSTNAME_VAR or OIDC_LASTNAME_VAR is set,
         * concatenate them; if none of them are set, default to 'preferred_username'.
         * If you need to concatenate multiple attributes (e.g. firstname, lastname, title), you can provide a comma-separated list of attribute names.
         * If a list is given, the attributes will be concatenated with a space in between. If any of them are missing, they will be skipped.
         */
        'name' => (static function () {
            $name = env('OIDC_NAME_VAR');
            if (!empty($name)) {
                return $name;
            }
            $firstnameVar = env('OIDC_FIRSTNAME_VAR');
            $lastnameVar = env('OIDC_LASTNAME_VAR');
            if (!empty($firstnameVar) || !empty($lastnameVar)) {
                return ($firstnameVar ?? 'firstname') . ',' . ($lastnameVar ?? 'lastname');
            }

            return 'preferred_username';
        })(),
    ],

];
