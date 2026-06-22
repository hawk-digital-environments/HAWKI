<?php
declare(strict_types=1);


namespace App\Services\System\UserTypes\Contracts;


/**
 * Defines the built-in user type identifiers used across HAWKI.
 *
 * A "user type" is a string token that identifies who is making the current request.
 * {@see \App\Services\System\UserTypes\UserContext} holds the active user type for
 * the current request; listeners react to changes via {@see Events\UserTypeChangedEvent}.
 *
 * @see \App\Services\System\UserTypes\UserContext  Stores and updates the active user type.
 * @see \App\Services\System\UsageTypes\WellKnownUsageTypes  The parallel concept for WHAT surface is used.
 */
interface WellKnownUserTypes
{
    /**
     * A user that is not authenticated, e.g. a visitor to the website.
     */
    public const string GUEST = 'guest';
    /**
     * A state between GUEST and USER, when a user is in the process of registering an account, but has not completed the registration yet.
     * The user information is not yet available through the Laravel guard, so the user still acts like a GUEST in most parts of the system.
     */
    public const string REGISTERING_USER = 'registering_user';
    /**
     * An authenticated user that is using the system for personal use, e.g. a regular user of the website.
     * Can be a user of the "internal" web iterface or a user of an external app
     */
    public const string USER = 'user';
    /**
     * An authenticated user that is NOT a real HAWKI user.
     * This is the external app itself, trying to establish a connection to the system.
     * This is the credential exchange before the real HAWKI user is resolved and authenticated.
     */
    public const string EXTERNAL_APP = 'external_app';
}
