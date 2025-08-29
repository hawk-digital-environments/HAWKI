<?php
declare(strict_types=1);


namespace App\Services;


class SaltProvider
{
    public const TYPE_USERDATA_ENCRYPTION = 'USERDATA_ENCRYPTION_SALT';
    public const TYPE_INVITATION = 'INVITATION_SALT';
    public const TYPE_AI_CRYPTO = 'AI_CRYPTO_SALT';
    public const TYPE_PASSKEY = 'PASSKEY_SALT';
    public const TYPE_BACKUP = 'BACKUP_SALT';

    /**
     * Returns a generic salt based on the type provided.
     * If the salt is not set in the environment, it will generate a semi-static salt
     * @param string $type
     * @return string
     */
    public function getSalt(string $type): string
    {
        return $this->getSaltFromEnvOrReturnFallback($type);
    }

    /**
     * Returns the "USERDATA_ENCRYPTION_SALT" salt.
     * @return string
     */
    public function getSaltForUserDataEncryption(): string
    {
        return $this->getSalt(self::TYPE_USERDATA_ENCRYPTION);
    }

    /**
     * Returns the "INVITATION_SALT" salt.
     * @return string
     */
    public function getSaltForInvitation(): string
    {
        return $this->getSalt(self::TYPE_INVITATION);
    }

    /**
     * Returns the "AI_CRYPTO_SALT" salt.
     * @return string
     */
    public function getSaltForAiCrypto(): string
    {
        return $this->getSalt(self::TYPE_AI_CRYPTO);
    }

    /**
     * Returns the "PASSKEY_SALT" salt.
     * @return string
     */
    public function getSaltForPasskey(): string
    {
        return $this->getSalt(self::TYPE_PASSKEY);
    }

    /**
     * Returns the "BACKUP_SALT" salt.
     * @return string
     */
    public function getSaltForBackup(): string
    {
        return $this->getSalt(self::TYPE_BACKUP);
    }

    protected function getSaltFromEnvOrReturnFallback(string $type): string
    {
        $envValue = env($type);

        if ($envValue !== false && $envValue !== null) {
            return $envValue;
        }

        return $this->generateSemiStaticSalt($type);
    }

    protected function generateSemiStaticSalt(string $type): string
    {
        return hash('sha256', config('app.key') . 'semi_static_salt' . hash('sha256', $type));
    }
}
