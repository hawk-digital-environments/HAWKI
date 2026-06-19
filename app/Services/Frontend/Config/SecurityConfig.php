<?php
declare(strict_types=1);


namespace App\Services\Frontend\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

class SecurityConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * True if it is allowed to paste the passkey value into the input box.
     * If false, the user must type the passkey manually.
     * @var bool
     */
    public readonly bool $passkeyAllowPaste;

    /**
     * When true (default): Only allows [A-Za-z0-9!@#$%^&*()_+-]
     * When false: Allows all characters that the user can input, including spaces and unicode characters.
     * @var bool
     */
    public readonly bool $passkeyRestrictCharacters;

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
     * @inheritDoc
     */
    public function toPublicArray(Request $request): array|null
    {
        return [
            'passkeyAllowPaste' => $this->passkeyAllowPaste,
            'passkeyRestrictCharacters' => $this->passkeyRestrictCharacters,
        ];
    }
}
