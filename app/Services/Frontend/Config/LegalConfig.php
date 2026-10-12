<?php
declare(strict_types=1);


namespace App\Services\Frontend\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

/**
 * Frontend config for the operator's legal pages, exposed to the frontend under the `legal` key.
 *
 * Each link is shown on the sign-in page and in the profile menu. Without a valid value the link
 * is not shown. The accessibility statement lives in {@see AccessibilityConfig}.
 */
class LegalConfig extends AbstractConfig implements PublicConfigInterface
{
    /** Imprint / legal notice (`IMPRINT_LOCATION`), or null when none is configured. */
    public readonly string|null $imprintUrl;

    /** Privacy policy (`PRIVACY_POLICY_URL`), or null when none is configured. */
    public readonly string|null $privacyUrl;

    /** Terms of use (`TERMS_OF_USE_URL`), or null when none is configured. */
    public readonly string|null $termsUrl;

    /**
     * Returns the value when it is an absolute HTTP(S) URL or a path on this host, null otherwise.
     * Values from `.env` skip the admin panel's validation, so they are checked again here.
     */
    public static function link(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return 1 === preg_match('~^(?:https?:/)?/[^/\s"\'<>][^\s"\'<>]*$~', $value) ? $value : null;
    }

    /**
     * Reads the legal links from the `hawki.legal` config namespace.
     */
    public static function make(Repository $repo): static
    {
        return self::fromArray([
            'imprintUrl' => self::link($repo->get('hawki.legal.imprint_url')),
            'privacyUrl' => self::link($repo->get('hawki.legal.privacy_url')),
            'termsUrl' => self::link($repo->get('hawki.legal.terms_url')),
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'legal';
    }

    /**
     * Returns the legal links. Safe to expose publicly — it contains no secrets.
     */
    public function toPublicArray(Request $request): array|null
    {
        return [
            'imprintUrl' => $this->imprintUrl,
            'privacyUrl' => $this->privacyUrl,
            'termsUrl' => $this->termsUrl,
        ];
    }
}
