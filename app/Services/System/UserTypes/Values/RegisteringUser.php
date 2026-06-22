<?php
declare(strict_types=1);


namespace App\Services\System\UserTypes\Values;


/**
 * Holds the data collected for a user who is partway through the registration flow.
 *
 * This is a transient value object. The user has been identified (e.g. via LDAP) but has
 * not yet been persisted to the database, so they are not accessible through the Laravel guard.
 * While this object is active, {@see UserContext} reports the user type as
 * {@see WellKnownUserTypes::REGISTERING_USER}.
 */
readonly class RegisteringUser
{
    public function __construct(
        /** The login identifier (e.g. LDAP uid). */
        public string $username,
        /** The display name of the user. */
        public string $name,
        /** The user's e-mail address. */
        public string $email,
        /** The employee type / group coming from the identity provider. */
        public string $employeeType,
    )
    {
    }
}
