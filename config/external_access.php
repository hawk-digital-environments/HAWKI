<?php

return [
    /**
     * This setting enables or disables external API access to HAWKI models.
     * When set to "true", API requests through the external API endpoints are permitted. When set to "false",
     * all external API requests will be blocked. This is the master switch for API functionality.
     */
    'enabled' => env('ALLOW_EXTERNAL_COMMUNICATION', false),
    /**
     * This setting controls whether users can create their own API tokens via the web interface.
     * When set to "true", users can create, view, and revoke their own API tokens through the profile page.
     * When set to "false", only system administrators can create API tokens through command line tools.
     * This provides finer control over who can create access tokens.
     */
    'allow_user_token' => env('ALLOW_USER_TOKEN_CREATION', false),
    /**
     * If true, the creation of app tokens is allowed.
     * Currently, you are required to create an app manually through the CLI interface.
     * IMPORTANT: If you use this, ALLOW_EXTERNAL_COMMUNICATION must be set to true as well.
     * NOTE: If you use this it is HIGHLY recommended to set ALLOW_USER_TOKEN_CREATION to true as well.
     * Because this allows users to "remove" tokens created for external apps, and thus prevent them from accessing the API in the future.
     */
    'apps' => env('ALLOW_EXTERNAL_APPS', false),
    /**
     * The duration in seconds for which an app connection request is valid.
     * After this time has passed, the request will be considered invalid and the user will need to create a new request.
     * Default is 15 minutes (60 seconds * 15).
     */
    'app_connect_request_timeout' => (int)env('ALLOW_EXTERNAL_APPS_CONNECT_REQUEST_TIMEOUT', 60 * 15),
    /**
     * If true, group chats in external applications can use the "@hawki"(configureable) AI handle,
     * otherwise the chat only works like a normal chat without AI integration.
     * IMPORTANT: If you use this, ALLOW_EXTERNAL_CHAT as well as ALLOW_EXTERNAL_APPS must be set to true.
     */
    'apps_groups_ai' => env('ALLOW_EXTERNAL_APPS_GROUPS_AI', true),
];
