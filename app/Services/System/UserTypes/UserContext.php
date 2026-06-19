<?php
declare(strict_types=1);


namespace App\Services\System\UserTypes;

use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\Values\RegisteringUser;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class UserContext
{
    private string $userType = WellKnownUserTypes::GUEST;
    private RegisteringUser|null $registeringUser = null;

    // -------------------------------------------------------
    // Well known user types
    // -------------------------------------------------------

    public function isGuest(): bool
    {
        return $this->userType === WellKnownUserTypes::GUEST;
    }

    public function isRegisteringUser(): bool
    {
        return $this->userType === WellKnownUserTypes::REGISTERING_USER;
    }

    public function isUser(): bool
    {
        return $this->userType === WellKnownUserTypes::USER;
    }

    public function isExternalApp(): bool
    {
        return $this->userType === WellKnownUserTypes::EXTERNAL_APP;
    }

    public function isCli(): bool
    {
        return $this->isGuest() && strtolower(PHP_SAPI) === 'cli';
    }

    // -------------------------------------------------------
    // Generics
    // -------------------------------------------------------

    /**
     * Returns true when the active user type matches the given identifier.
     * Use the constants on {@see WellKnownUserTypes} for the built-in types,
     * or pass a custom string for application-specific user types.
     */
    public function is(string $userType): bool
    {
        return $this->userType === $userType;
    }

    /**
     * Sets the active user type and dispatches {@see Events\UserTypeChangedEvent}.
     * Use the constants on {@see WellKnownUserTypes} for the built-in types,
     * or pass a custom string for application-specific user types.
     */
    public function set(string $userType): void
    {
        if ($this->userType !== $userType) {
            $this->userType = $userType;
            // @todo event $this
        }
    }

    /**
     * Returns the current user type identifier string.
     */
    public function get(): string
    {
        return $this->userType;
    }

    // -------------------------------------------------------
    // Registering user
    // -------------------------------------------------------

    public function getRegisteringUser(): RegisteringUser|null
    {
        return $this->registeringUser;
    }

    public function setRegisteringUser(RegisteringUser|null $registeringUser): void
    {
        $this->registeringUser = $registeringUser;
        $this->set($registeringUser ? WellKnownUserTypes::REGISTERING_USER : WellKnownUserTypes::GUEST);
    }
}
