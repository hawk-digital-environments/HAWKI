<?php

namespace App\Services\User\Keychain\Http;

use App\Services\User\Keychain\Value\UserKeychainValueToRemove;
use App\Services\User\Keychain\Value\UserKeychainValueToSet;
use App\Services\User\Keychain\Value\UserKeychainValueType;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Illuminate\Foundation\Http\FormRequest;

class UserKeychainUpdateValuesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'clean' => 'boolean|sometimes',
            'publicKey' => 'string|sometimes',
            'set' => 'array|sometimes',
            'set.*.key' => 'string|required',
            'set.*.value' => 'string|required',
            'set.*.type' => 'string|required|in:' . implode(',',
                    array_map(static fn($e) => $e->value, UserKeychainValueType::cases())
                ),
            'remove' => 'array|sometimes',
            'remove.*.key' => 'string|required',
            'remove.*.type' => 'string|required|in:' . implode(',',
                    array_map(static fn($e) => $e->value, UserKeychainValueType::cases())
                ),
        ];
    }
    
    public function authorize(): bool
    {
        return true;
    }
    
    /**
     * Returns true, if the request contained a new decrypted public key for the user.
     * @return bool
     */
    public function hasNewPublicKey(): bool
    {
        return $this->filled('publicKey');
    }
    
    /**
     * If the request contained a new decrypted public key for the user, it is returned here.
     * @return string|null
     */
    public function getNewPublicKey(): ?string
    {
        return $this->validated('publicKey');
    }
    
    /**
     * Returns true, if all existing values should be removed before applying the changes.
     * @return bool
     */
    public function isCleaning(): bool
    {
        return $this->validated('clean', false);
    }
    
    /**
     * Returns true if there are values to set.
     * @return bool
     */
    public function hasSetList(): bool
    {
        return !empty($this->validated('set', []));
    }
    
    /**
     * @return iterable<UserKeychainValueToSet>
     */
    public function getSetList(): iterable
    {
        foreach ($this->validated('set', []) as $item) {
            yield new UserKeychainValueToSet(
                key: $item['key'],
                value: SymmetricCryptoValue::fromString($item['value']),
                type: UserKeychainValueType::from($item['type'])
            );
        }
    }
    
    /**
     * Returns true if there are values to remove.
     * @return bool
     */
    public function hasRemoveList(): bool
    {
        return !empty($this->validated('remove', []));
    }
    
    /**
     * @return iterable<UserKeychainValueToRemove>
     */
    public function getRemoveList(): iterable
    {
        foreach ($this->validated('remove', []) as $item) {
            yield new UserKeychainValueToRemove(
                key: $item['key'],
                type: UserKeychainValueType::from($item['type'])
            );
        }
    }
}
