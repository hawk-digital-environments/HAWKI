<?php
declare(strict_types=1);


namespace App\Services\Auth\Util;


use SensitiveParameter;

trait AuthServiceWithCredentialsTrait
{
    private string $username = '';
    private string $password = '';

    /**
     * @see \App\Services\Auth\Contract\AuthServiceWithCredentialsInterface::useCredentials()
     */
    public function useCredentials(
        string $username,
        #[SensitiveParameter]
        string $password
    ): void
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @see \App\Services\Auth\Contract\AuthServiceWithCredentialsInterface::forgetCredentials()
     */
    public function forgetCredentials(): void
    {
        $this->username = '';
        $this->password = '';
    }

    public function __debugInfo(): ?array
    {
        $data = get_object_vars($this);
        if (isset($data['password'])) {
            $data['password'] = '***REDACTED***';
        }
        return $data;
    }
}
