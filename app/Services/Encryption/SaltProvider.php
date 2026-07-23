<?php
declare(strict_types=1);


namespace App\Services\Encryption;


use App\Services\Encryption\Value\Salt;
use Illuminate\Container\Attributes\Config;

class SaltProvider
{
    /**
     * @var Salt[]
     */
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
     * @return Salt
     */
    public function getSalt(SaltType $type): Salt
    {
        foreach ($this->resolvedSalts as $resolvedSalt) {
            if ($resolvedSalt->type === $type) {
                return $resolvedSalt;
            }
        }

        $saltValue = $this->saltConfig[$type->value] ?? null;
        if (empty($saltValue)) {
            $saltValue = $this->generateSemiStaticSalt($type);
        }

        $salt = new Salt($type, $saltValue);
        $this->resolvedSalts[] = $salt;
        return $salt;
    }

    /**
     * Returns the "USERDATA_ENCRYPTION_SALT" salt.
     */
    public function getSaltForUserDataEncryption(): Salt
    {
        return $this->getSalt(SaltType::USERDATA);
    }

    /**
     * Returns the "INVITATION_SALT" salt.
     */
    public function getSaltForInvitation(): Salt
    {
        return $this->getSalt(SaltType::INVITATION);
    }

    /**
     * Returns the "AI_CRYPTO_SALT" salt.
     */
    public function getSaltForAiCrypto(): Salt
    {
        return $this->getSalt(SaltType::AI);
    }

    /**
     * Returns the "PASSKEY_SALT" salt.
     */
    public function getSaltForPasskey(): Salt
    {
        return $this->getSalt(SaltType::PASSKEY);
    }

    /**
     * Returns the "BACKUP_SALT" salt.
     */
    public function getSaltForBackup(): Salt
    {
        return $this->getSalt(SaltType::BACKUP);
    }

    protected function generateSemiStaticSalt(SaltType $type): string
    {
        return hash('sha256', $this->appKey . 'semi_static_salt' . hash('sha256', $type->value));
    }
}
