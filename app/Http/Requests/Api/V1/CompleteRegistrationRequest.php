<?php
declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Auth\RegistrationPayloadFingerprint;
use App\Http\Errors\CodedError;
use App\Models\User;
use App\Services\System\UserTypes\Values\RegisteringUser;
use App\Services\Profile\Values\PasskeyBackupSecret;
use App\Services\Users\Keychain\Value\KeychainBatch;
use App\Services\Users\Keychain\Value\UserKeychainValueToSet;
use App\Services\Users\Keychain\Value\UserKeychainValueType;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

class CompleteRegistrationRequest extends FormRequest
{
    private const array REQUIRED_KEYCHAIN_ENTRIES = [
        'privateKey|private_key',
        'publicKey|public_key',
        'aiConvKey|ai_conv',
    ];

    public function rules(): array
    {
        return [
            'policy' => ['present', 'nullable', 'array'],
            'policy.id' => ['required_with:policy', 'integer', 'min:1'],
            'policy.hash' => ['required_with:policy', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'keychain' => ['required', 'array'],
            'keychain.publicKey' => ['required', 'string', 'min:1', 'max:65535'],
            'keychain.set' => ['required', 'array', 'min:3'],
            'keychain.set.*.key' => ['required', 'string', 'min:1', 'max:255'],
            'keychain.set.*.type' => ['required', 'string', 'in:' . implode(',', array_column(UserKeychainValueType::cases(), 'value'))],
            'keychain.set.*.value' => ['required', 'string', 'min:1', 'max:65535'],
            'backup' => ['required', 'array'],
            'backup.ciphertext' => ['required', 'string', 'max:65535', $this->base64Rule()],
            'backup.iv' => ['required', 'string', 'max:255', $this->base64Rule(12)],
            'backup.tag' => ['required', 'string', 'max:255', $this->base64Rule(16)],
        ];
    }

    private User|RegisteringUser $registrationActor;

    public function authorize(): bool
    {
        $actor = $this->user();
        if (!$actor instanceof User) {
            $actor = $this->getUserContext()->getRegisteringUser();
        }
        if ($actor === null) {
            CodedError::abort('registration_not_in_progress', 403, 'No registration in progress');
        }
        if (!$this->hasSession()) {
            CodedError::abort('auth_session_required', 403, 'A browser session is required');
        }
        $this->registrationActor = $actor;

        return true;
    }

    public function actor(): User|RegisteringUser
    {
        return $this->registrationActor;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $found = [];
            foreach ($this->input('keychain.set', []) as $index => $entry) {
                if (!is_array($entry) || !isset($entry['key'], $entry['type'], $entry['value'])) {
                    continue;
                }

                $identity = $entry['key'] . '|' . $entry['type'];
                if (isset($found[$identity])) {
                    $validator->errors()->add("keychain.set.$index", 'Duplicate keychain entry.');
                }
                $found[$identity] = true;

                try {
                    SymmetricCryptoValue::fromString((string)$entry['value']);
                } catch (Throwable) {
                    $validator->errors()->add("keychain.set.$index.value", 'The encrypted value is malformed.');
                }
            }

            foreach (self::REQUIRED_KEYCHAIN_ENTRIES as $required) {
                if (!isset($found[$required])) {
                    $validator->errors()->add('keychain.set', "Missing required keychain entry $required.");
                }
            }
        });
    }

    public function keychainBatch(): KeychainBatch
    {
        $set = array_map(
            static fn(array $entry): UserKeychainValueToSet => new UserKeychainValueToSet(
                key: $entry['key'],
                value: SymmetricCryptoValue::fromString($entry['value']),
                type: UserKeychainValueType::from($entry['type'])
            ),
            $this->validated('keychain.set')
        );

        return KeychainBatch::forFreshKeychain(
            $this->validated('keychain.publicKey'),
            $set
        );
    }

    public function backup(): PasskeyBackupSecret
    {
        return PasskeyBackupSecret::fromArray($this->validated('backup'));
    }

    /** @return array{id: int, hash: string}|null */
    public function policyReference(): ?array
    {
        $policy = $this->validated('policy');

        return $policy === null ? null : ['id' => (int)$policy['id'], 'hash' => $policy['hash']];
    }

    public function payloadFingerprint(RegistrationPayloadFingerprint $fingerprints): string
    {
        return $fingerprints->make([
            'policy' => $this->validated('policy'),
            'keychain' => $this->validated('keychain'),
            'backup' => $this->validated('backup'),
        ]);
    }

    private function base64Rule(?int $expectedBytes = null): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($expectedBytes): void {
            if (!is_string($value)) {
                return;
            }

            $decoded = base64_decode($value, true);
            if ($decoded === false || $decoded === '') {
                $fail("The $attribute field must contain base64-encoded bytes.");
                return;
            }

            if ($expectedBytes !== null && strlen($decoded) !== $expectedBytes) {
                $fail("The $attribute field must encode exactly $expectedBytes bytes.");
            }
        };
    }
}
