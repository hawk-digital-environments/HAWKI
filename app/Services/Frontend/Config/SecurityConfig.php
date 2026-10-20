<?php
declare(strict_types=1);


namespace App\Services\Frontend\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

/**
 * Frontend config for passkey input restrictions, exposed to the frontend under the `security` key.
 *
 * Controls how strictly the passkey input behaves: whether users may paste their passkey
 * or must type it manually, and whether the input enforces an ASCII character set.
 */
class SecurityConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * When true, the user may paste their passkey into the input box.
     * When false, the input rejects clipboard paste events so the user must type the passkey manually.
     */
    public readonly bool $passkeyAllowPaste;

    /**
     * When true (default), only printable ASCII characters [A-Za-z0-9!@#$%^&*()_+-] are accepted.
     * When false, all characters the user can type — including spaces and Unicode — are allowed.
     */
    public readonly bool $passkeyRestrictCharacters;

    /**
     * Reads passkey settings from the `hawki.security.passkey` config namespace.
     */
    public static function make(Repository $repo): static
    {
        $repo->get('hawki.security.passkey.allow_paste');
        return self::fromArray([
            'passkeyAllowPaste' => $repo->get('hawki.security.passkey.allow_paste', true),
            'passkeyRestrictCharacters' => $repo->get('hawki.security.passkey.char_limitation', true),
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'security';
    }

    /**
     * Returns the full passkey configuration.
     * This config is safe to expose publicly — it contains no secrets.
     */
    public function toPublicArray(Request $request): array|null
    {
        return [
            'passkeyAllowPaste' => $this->passkeyAllowPaste,
            'passkeyRestrictCharacters' => $this->passkeyRestrictCharacters,
        ];
    }
}
