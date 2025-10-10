<?php
declare(strict_types=1);


namespace App\Services\User\Keychain\Value;


use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;

readonly class UserKeychainValueToSet
{
    public function __construct(
        public string                $key,
        public SymmetricCryptoValue  $value,
        public UserKeychainValueType $type,
    )
    {
    }
}
