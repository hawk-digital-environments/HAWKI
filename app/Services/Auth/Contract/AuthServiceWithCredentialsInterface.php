<?php
declare(strict_types=1);


namespace App\Services\Auth\Contract;

/**
 * An extension of the {@see AuthServiceInterface} that supports authentication using username and password credentials.
 */
interface AuthServiceWithCredentialsInterface
{
    /**
     * Set the credentials to be used for the next authentication.
     * @param string $username The username to be used for authentication.
     * @param string $password The given password in clear text.
     * @return void
     */
    public function useCredentials(string $username, string $password): void;

    /**
     * Clear any previously set credentials.
     * This will be called after an authentication attempt to ensure no credentials are retained.
     * @return void
     */
    public function forgetCredentials(): void;
}
