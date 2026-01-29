<?php
declare(strict_types=1);


namespace App\Services\User\Keychain\Value;


readonly class UserKeychainValueToRemove
{
    public function __construct(
        public string                $key,
        public UserKeychainValueType $type,
    )
    {
    }
}
