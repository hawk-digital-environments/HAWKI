<?php
declare(strict_types=1);


namespace App\Services\Encryption;


use Illuminate\Container\Attributes\Config;

class SaltProvider
{
    private array $resolvedSalts = [];
    
    public function __construct(
        #[Config('app.key')]
        private readonly string $appKey,
        #[Config('encryption.salts')]
        private readonly array  $saltConfig
    )
    {
    }

    /**
     * Returns a generic salt based on the type provided.
     * If the salt is not set in the environment, it will generate a semi-static salt
     * @param SaltType $type
     * @return string
     */
    public function getSalt(SaltType $type): string
    {
        if (isset($this->resolvedSalts[$type->value])) {
            return $this->resolvedSalts[$type->value];
        }
        
        $salt = $this->saltConfig[$type->value] ?? null;
        if (empty($salt)) {
            $salt = $this->generateSemiStaticSalt($type->value);
        }
        
        $this->resolvedSalts[$type->value] = $salt;
        return $salt;
    }

    /**
     * Returns the "USERDATA_ENCRYPTION_SALT" salt.
     * @return string
     */
    public function getSaltForUserDataEncryption(): string
    {
        return $this->getSalt(SaltType::USERDATA);
    }

    /**
     * Returns the "INVITATION_SALT" salt.
     * @return string
     */
    public function getSaltForInvitation(): string
    {
        return $this->getSalt(SaltType::INVITATION);
    }

    /**
     * Returns the "AI_CRYPTO_SALT" salt.
     * @return string
     */
    public function getSaltForAiCrypto(): string
    {
        return $this->getSalt(SaltType::AI);
    }

    /**
     * Returns the "PASSKEY_SALT" salt.
     * @return string
     */
    public function getSaltForPasskey(): string
    {
        return $this->getSalt(SaltType::PASSKEY);
    }

    /**
     * Returns the "BACKUP_SALT" salt.
     * @return string
     */
    public function getSaltForBackup(): string
    {
        return $this->getSalt(SaltType::BACKUP);
    }

    protected function generateSemiStaticSalt(string $type): string
    {
        return hash('sha256', $this->appKey . 'semi_static_salt' . hash('sha256', $type));
    }
}
