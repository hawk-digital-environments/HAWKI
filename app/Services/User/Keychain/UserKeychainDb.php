<?php
declare(strict_types=1);


namespace App\Services\User\Keychain;


use App\Models\PrivateUserData;
use App\Models\User;
use App\Models\UserKeychainValue;
use App\Services\Encryption\EncryptionUtils;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\User\Keychain\Value\UserKeychainValueToRemove;
use App\Services\User\Keychain\Value\UserKeychainValueToSet;
use App\Services\User\Keychain\Value\UserKeychainValueType;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

#[Singleton]
readonly class UserKeychainDb
{
    /**
     * Sets multiple keychain values for a user.
     * If a value with the same key and type already exists, it will be updated.
     * @param User $user The user to set the values for.
     * @param UserKeychainValueToSet ...$values The values to set.
     * @return void
     */
    public function setValues(User $user, UserKeychainValueToSet ...$values): void
    {
        foreach ($values as $value) {
            UserKeychainValue::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'key' => $value->key,
                    'type' => $value->type->value,
                ],
                [
                    'value' => $value->value
                ]
            );
        }
    }
    
    /**
     * Removes multiple keychain values for a user.
     * @param User $user The user to remove the values for.
     * @param UserKeychainValueToRemove ...$values The values to remove.
     * @return void
     */
    public function removeValues(User $user, UserKeychainValueToRemove ...$values): void
    {
        $query = UserKeychainValue::query();
        foreach ($values as $entry) {
            $query->orWhere(function (Builder $query) use ($user, $entry) {
                $query
                    ->where('user_id', $user->id)
                    ->where('key', $entry->key)
                    ->where('type', $entry->type->value);
            });
        }
        
        $query->each(fn(UserKeychainValue $val) => $val->delete());
        
        $this->removeRoomKeysWithoutMembership($user);
    }
    
    /**
     * Removes all keychain values for a user.
     * @param User $user The user to remove the values for.
     * @return void
     */
    public function removeAllOfUser(User $user): void
    {
        UserKeychainValue::where('user_id', $user->id)
            ->each(fn(UserKeychainValue $val) => $val->delete());
    }
    
    /**
     * Returns the count of keychain values for a user.
     * Used for the full sync log generation.
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int
    {
        return UserKeychainValue::where('user_id', $user->id)->count();
    }
    
    /**
     * Finds keychain values for a user based on the given constraints.
     * Used for the full sync log generation.
     * @param SyncLogEntryConstraints $constraints
     * @return Collection<int, UserKeychainValue>
     */
    public function findForSyncLogConstraints(SyncLogEntryConstraints $constraints): Collection
    {
        $query = UserKeychainValue::where('user_id', $constraints->user->id);
        if ($constraints->limit) {
            $query->limit($constraints->limit);
        }
        if ($constraints->offset) {
            $query->offset($constraints->offset);
        }
        return $query->get();
    }
    
    /**
     * Finds a single keychain value by its ID.
     * @param int $id The ID of the keychain value.
     * @return UserKeychainValue|null The found keychain value or null if not found.
     */
    public function findOne(int $id): ?UserKeychainValue
    {
        return UserKeychainValue::find($id);
    }
    
    /**
     * Finds all keychain values for a user.
     * Used for migration purposes.
     * @param User $user The user to find the values for.
     * @return Collection<int, UserKeychainValue> The found keychain values.
     */
    public function findAllOfUser(User $user): Collection
    {
        return UserKeychainValue::where('user_id', $user->id)->get();
    }
    
    /**
     * Returns the first available public key of a user, or null if none exists.
     * This can be used as a possibility to decrypt data via a given passkey in the frontend.
     * @param User $user
     * @return UserKeychainValue|null
     */
    public function findFirstPublicKeyOfUser(User $user): ?UserKeychainValue
    {
        return UserKeychainValue::where('user_id', $user->id)
            ->where('type', 'public_key')
            ->first();
    }
    
    /**
     * Helper to find the legacy keychain of a user.
     * This is only used to migrate to the new data structure.
     * IMPORTANT: This does not work on the {@see UserKeychainValue} model, but on the old {@see PrivateUserData} model.
     * @deprecated This method is only used for migration purposes and will be removed in the future.
     */
    public function findLegacyKeychainOfUser(User $user): ?SymmetricCryptoValue
    {
        $prvUserData = PrivateUserData::where('user_id', $user->id)->first();
        
        if (!$prvUserData) {
            return null;
        }
        
        return EncryptionUtils::symmetricCryptoValueFromStrings(
            $prvUserData->KCIV,
            $prvUserData->KCTAG,
            $prvUserData->keychain
        );
    }
    
    /**
     * A basic housekeeping method that removes all room keys for rooms the user is no longer a member of.
     * This should be run once in a while to keep the database clean.
     * @param User $user
     * @return void
     */
    private function removeRoomKeysWithoutMembership(User $user): void
    {
        $knownSlugs = $user->rooms()
            ->pluck('slug')
            ->toArray();
        
        UserKeychainValue::where('user_id', $user->id)
            ->whereIn('type', [
                UserKeychainValueType::ROOM->value,
                UserKeychainValueType::ROOM_AI->value,
                UserKeychainValueType::ROOM_AI_LEGACY->value
            ])
            ->whereNotIn('key', $knownSlugs)
            ->where('updated_at', '<', now()->subDays(7)) // Give some time to new keys to be used
            ->each(fn(UserKeychainValue $val) => $val->delete());
    }
}
