<?php
declare(strict_types=1);


namespace App\Services\System\UserTypes;

use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\Events\UserTypeChangedEvent;
use App\Services\System\UserTypes\Values\RegisteringUser;
use Illuminate\Container\Attributes\Singleton;

/**
 * Singleton that tracks which type of user is currently making the request.
 *
 * The user type controls access rules and determines what data or operations are available
 * for the current caller. The default is {@see WellKnownUserTypes::GUEST} and is raised to
 * {@see WellKnownUserTypes::USER} (or another type) by authentication middleware.
 *
 * Note the distinction from {@see \App\Services\System\UsageTypes\UsageContext}:
 * - **UserContext** answers WHO is making the request (unauthenticated visitor, registering user, authenticated user, external app).
 * - **UsageContext** answers WHAT surface they are using (main HAWKI interface vs. external API integration).
 *
 * Any call to {@see set()} that changes the type immediately dispatches a
 * {@see Events\UserTypeChangedEvent} so listeners can react synchronously.
 *
 * Usage:
 * ```php
 * readonly class SomeService
 * {
 *     public function __construct(private UserContext $userContext) {}
 *
 *     public function doSomething(): void
 *     {
 *         if ($this->userContext->isGuest()) {
 *             throw new \RuntimeException('Authentication required.');
 *         }
 *
 *         if ($this->userContext->isRegisteringUser()) {
 *             $registering = $this->userContext->getRegisteringUser();
 *             // $registering->username, ->name, ->email, ->employeeType
 *         }
 *     }
 * }
 * ```
 *
 * @see WellKnownUserTypes    Built-in user type identifier constants.
 * @see Events\UserTypeChangedEvent  Event dispatched after every successful {@see set()} call.
 */
#[Singleton]
class UserContext
{
    private string $userType = WellKnownUserTypes::GUEST;
    private RegisteringUser|null $registeringUser = null;

    // -------------------------------------------------------
    // Well known user types
    // -------------------------------------------------------

    /**
     * Returns true when the active user type is {@see WellKnownUserTypes::GUEST} (unauthenticated).
     */
    public function isGuest(): bool
    {
        return $this->userType === WellKnownUserTypes::GUEST;
    }

    /**
     * Returns true when the active user type is {@see WellKnownUserTypes::REGISTERING_USER}.
     * In this state, the user is partway through registration and not yet in the Laravel guard.
     * Use {@see getRegisteringUser()} to retrieve the partial registration data.
     */
    public function isRegisteringUser(): bool
    {
        return $this->userType === WellKnownUserTypes::REGISTERING_USER;
    }

    /**
     * Returns true when the active user type is {@see WellKnownUserTypes::USER} (fully authenticated).
     */
    public function isUser(): bool
    {
        return $this->userType === WellKnownUserTypes::USER;
    }

    /**
     * Returns true when the active user type is {@see WellKnownUserTypes::EXTERNAL_APP}.
     * This represents an external application authenticating before the real HAWKI user is resolved.
     */
    public function isExternalApp(): bool
    {
        return $this->userType === WellKnownUserTypes::EXTERNAL_APP;
    }

    /**
     * Returns true when the process is running as a CLI command with no authenticated user.
     * Useful to skip user-specific logic in artisan commands and scheduled jobs.
     */
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
     * If the type is already set to the given value, no event is dispatched.
     *
     * Use the constants on {@see WellKnownUserTypes} for the built-in types,
     * or pass a custom string for application-specific user types.
     */
    public function set(string $userType): void
    {
        if ($this->userType !== $userType) {
            $this->userType = $userType;
            UserTypeChangedEvent::dispatch($this);
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

    /**
     * Returns the partial registration data for the current user, or null when the user
     * is not in the {@see WellKnownUserTypes::REGISTERING_USER} state.
     */
    public function getRegisteringUser(): RegisteringUser|null
    {
        return $this->registeringUser;
    }

    /**
     * Sets or clears the partial registration data.
     *
     * Passing a {@see RegisteringUser} transitions the user type to
     * {@see WellKnownUserTypes::REGISTERING_USER}; passing null resets it to
     * {@see WellKnownUserTypes::GUEST}.
     */
    public function setRegisteringUser(RegisteringUser|null $registeringUser): void
    {
        $this->registeringUser = $registeringUser;
        $this->set($registeringUser ? WellKnownUserTypes::REGISTERING_USER : WellKnownUserTypes::GUEST);
    }
}
