<?php
declare(strict_types=1);


namespace App\Services\User;


use App\Models\PrivateUserData;
use App\Models\User;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;

readonly class UserKeychainDb
{
    /**
     * Finds the server side backup of the user's keychain.
     * @param User $user
     * @return SymmetricCryptoValue
     */
    public function findByUser(User $user): SymmetricCryptoValue
    {
        $prvUserData = PrivateUserData::where('user_id', $user->id)->firstOrFail();
        return new SymmetricCryptoValue(
            $prvUserData->KCIV,
            $prvUserData->KCTAG,
            $prvUserData->keychain
        );
    }
}
