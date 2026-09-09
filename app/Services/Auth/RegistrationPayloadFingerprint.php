<?php
declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Container\Attributes\Singleton;
use JsonException;

#[Singleton]
readonly class RegistrationPayloadFingerprint
{
    /**
     * @param array<string, mixed> $payload
     * @throws JsonException
     */
    public function make(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn(mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(fn(mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
