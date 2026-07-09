<?php
declare(strict_types=1);

namespace App\Services\Frontend\Connection\Values;

/**
 * Describes both the origin (native HAWKI vs. external app) and the authentication state
 * of a frontend connection. The frontend uses this to determine which UI and API flows
 * are available to the current visitor.
 */
enum ConnectionType: string
{
    /**
     * The connection (native HAWKI instance) before authentication (not logged in)
     */
    case INTERNAL = 'internal';
    /**
     * The connection (native HAWKI instance) when a user is in the process of registering an account, but has not completed the registration yet.
     * The user information is not yet available through the Laravel guard, so the user still acts like a GUEST in most parts of the system.
     */
    case INTERNAL_REGISTERING_USER = 'internal_registering_user';
    /**
     * The connection (native HAWKI instance) after authentication (logged in)
     */
    case INTERNAL_AUTHENTICATED = 'internal_authenticated';
    /**
     * The connection (external application) before authentication (not connected to a user account, not logged in)
     */
    case EXTERNAL_APP = 'external_app';
    /**
     * The connection (external application) after authentication (user account connected, logged in)
     */
    case EXTERNAL_APP_AUTHENTICATED = 'external_app_authenticated';
}
