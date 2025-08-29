<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Value;


use App\Models\ExtAppUserRequest;
use Hawk\HawkiCrypto\Value\AsymmetricPublicKey;
use Hawk\HawkiCrypto\Value\HybridCryptoValue;
use JsonException;

readonly class AppUserRequestSessionValue implements \Stringable
{
    protected function __construct(
        /**
         * @var AsymmetricPublicKey The user's public key (for encrypting data to the user)
         */
        public AsymmetricPublicKey $userPublicKey,
        /**
         * @var HybridCryptoValue The user's private key, this value is encrypted by the app's public key (for decrypting data from the app)
         */
        public HybridCryptoValue   $userPrivateKey,
        /**
         * @var string The external user ID, as provided by the app
         */
        public string              $extUserId,
        /**
         * @var int The ID of the app making the request
         */
        public int                 $appId,
    )
    {
    }
    
    /**
     * @throws JsonException
     */
    public function __toString(): string
    {
        return json_encode([
            'userPublicKey' => (string)$this->userPublicKey,
            'userPrivateKey' => (string)$this->userPrivateKey,
            'extUserId' => $this->extUserId,
            'appId' => $this->appId,
        ], JSON_THROW_ON_ERROR);
    }
    
    /**
     * @throws JsonException
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        
        return new self(
            AsymmetricPublicKey::fromString($data['userPublicKey']),
            HybridCryptoValue::fromString($data['userPrivateKey']),
            $data['extUserId'],
            $data['appId']
        );
    }
    
    public static function fromRequestModel(ExtAppUserRequest $request): self
    {
        return new self(
            $request->user_public_key,
            $request->user_private_key,
            $request->ext_user_id,
            $request->app_id
        );
    }
}
