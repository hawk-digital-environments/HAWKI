<?php
declare(strict_types=1);


namespace App\Services\Encryption;


use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;

class EncryptionUtils
{
    /**
     * Helper to create a SymmetricCryptoValue from base64 (possibly multiple times) encoded strings.
     * This is useful for legacy data where the values may be stored in multiple fields.
     * @param string $iv
     * @param string $tag
     * @param string $ciphertext
     * @return SymmetricCryptoValue
     */
    public static function symmetricCryptoValueFromStrings(
        string $iv,
        string $tag,
        string $ciphertext
    ): SymmetricCryptoValue
    {
        return new SymmetricCryptoValue(
            self::decodeBase64Recursive($iv),
            self::decodeBase64Recursive($tag),
            self::decodeBase64Recursive($ciphertext)
        );
    }
    
    /**
     * Helper to create a SymmetricCryptoValue from a JSON string.
     * The JSON string must contain the keys 'ciphertext', 'iv', and 'tag'.
     * Each value may be base64 (possibly multiple times) encoded.
     * @param string|null $json
     * @return SymmetricCryptoValue|null
     * @throws \JsonException
     */
    public static function symmetricCryptoValueFromJson(?string $json): ?SymmetricCryptoValue
    {
        if ($json === null) {
            return null;
        }
        
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        
        if (!is_array($data) || !isset($data['ciphertext'], $data['iv'], $data['tag'])) {
            throw new \InvalidArgumentException("Invalid JSON for SymmetricCryptoValue: $json");
        }
        
        return self::symmetricCryptoValueFromStrings(
            $data['iv'],
            $data['tag'],
            $data['ciphertext']
        );
    }
    
    private static function decodeBase64Recursive(string $value): string
    {
        // The value might be base64 encoded multiple times, so we decode it until it is no longer base64 encoded.
        $decoded = base64_decode($value, true);
        while ($decoded !== false && base64_encode($decoded) === $value) {
            $value = $decoded;
            $decoded = base64_decode($value, true);
        }
        return $value;
    }
}
