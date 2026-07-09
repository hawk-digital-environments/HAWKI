<?php
declare(strict_types=1);


namespace App\Services\Encryption\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

class SaltConfig extends AbstractConfig implements PublicConfigInterface
{
    public readonly string $userdata;
    public readonly string $invitation;
    public readonly string $ai;
    public readonly string $passkey;
    public readonly string $backup;

    /**
     * @inheritDoc
     */
    public static function make(Repository $repo): static
    {
        $appKey = $repo->get('app.key');
        $makeSemiStaticSalt = static fn(string $type) => hash(
            'sha256',
            $appKey . 'semi_static_salt' . hash('sha256', $type)
        );

        $loadMap = [
            'userdata' => 'USERDATA_ENCRYPTION_SALT',
            'invitation' => 'INVITATION_SALT',
            'ai' => 'AI_CRYPTO_SALT',
            'passkey' => 'PASSKEY_SALT',
            'backup' => 'BACKUP_SALT',
        ];

        $salts = [];

        foreach ($loadMap as $property => $configKey) {
            $saltValue = $repo->get("encryption.salts.$configKey");
            if (empty($saltValue)) {
                $saltValue = $makeSemiStaticSalt($configKey);
            }
            $salts[$property] = $saltValue;
        }

        return self::fromArray($salts);
    }

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'salts';
    }

    /**
     * @inheritDoc
     */
    public function toPublicArray(Request $request): array|null
    {
        if ($request->user()) {
            return [
                'userdata' => $this->userdata,
                'invitation' => $this->invitation,
                'ai' => $this->ai,
                'passkey' => $this->passkey,
                'backup' => $this->backup,
            ];
        }
        if ($request->getUserContext()->isRegisteringUser()) {
            return [
                'userdata' => $this->userdata,
                'passkey' => $this->passkey,
                'backup' => $this->backup,
            ];
        }

        return null;
    }
}
