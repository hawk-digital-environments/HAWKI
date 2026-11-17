<?php
declare(strict_types=1);


namespace App\Services\Profile\Values;


/**
 * The client-side encrypted passkey backup: AES-256-GCM ciphertext plus its IV and auth tag.
 *
 * The server only ever transports these three parts. The key is derived in the browser from the
 * user's recovery code, which never reaches the server — so neither the passkey nor the recovery
 * code can be recovered from this value object.
 */
readonly class PasskeyBackupSecret
{
    public function __construct(
        public string $ciphertext,
        public string $iv,
        public string $tag
    )
    {
    }

    /**
     * @param array{ciphertext?: string, cipherText?: string, iv: string, tag: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            // The legacy frontend posts `cipherText`, the JSON:API payload uses `ciphertext`.
            ciphertext: (string)($data['ciphertext'] ?? $data['cipherText'] ?? ''),
            iv: (string)$data['iv'],
            tag: (string)$data['tag']
        );
    }

    /**
     * @return array{ciphertext: string, iv: string, tag: string}
     */
    public function toArray(): array
    {
        return [
            'ciphertext' => $this->ciphertext,
            'iv' => $this->iv,
            'tag' => $this->tag,
        ];
    }
}
