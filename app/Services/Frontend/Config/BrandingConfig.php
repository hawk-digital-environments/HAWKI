<?php
declare(strict_types=1);


namespace App\Services\Frontend\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

/**
 * Frontend config for the deployment's naming and logo, exposed to the frontend under the `branding` key.
 *
 * Colors and corner rounding are not part of it: they are applied to the design tokens while the
 * page is rendered ({@see \App\Services\Frontend\View\BrandTheme}), so the first paint is already branded.
 */
class BrandingConfig extends AbstractConfig implements PublicConfigInterface
{
    /** Name of the application as users see it (`APP_NAME`). */
    public readonly string $appName;

    /**
     * Logo replacing the HAWKI wordmark as a `data:` URI, or null to keep the wordmark. The frontend
     * draws it in the accent color like the wordmark, so one file serves light and dark mode.
     */
    public readonly string|null $logoUrl;

    /** Operating institution shown on the sign-in page, or null when none is configured. */
    public readonly string|null $organizationName;

    /** Website of the operating institution, or null when none is configured. */
    public readonly string|null $organizationUrl;

    /**
     * Wraps uploaded SVG markup in a `data:` URI for `<img src>` and `<link rel="icon">`, where
     * browsers render it without running scripts. The markup was validated when it was saved.
     */
    public static function svgUri(mixed $svg): ?string
    {
        return is_string($svg) && '' !== trim($svg) ? 'data:image/svg+xml;base64,' . base64_encode($svg) : null;
    }

    /**
     * Reads the naming and logo values from the `app` and `hawki.branding` config namespaces.
     */
    public static function make(Repository $repo): static
    {
        return self::fromArray([
            'appName' => (string) $repo->get('app.name'),
            'logoUrl' => self::svgUri($repo->get('hawki.branding.logo')),
            'organizationName' => trim((string) $repo->get('hawki.branding.organization_name')) ?: null,
            'organizationUrl' => LegalConfig::link($repo->get('hawki.branding.organization_url')),
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'branding';
    }

    /**
     * Returns the naming and logo values. Safe to expose publicly — it contains no secrets.
     */
    public function toPublicArray(Request $request): array|null
    {
        return [
            'appName' => $this->appName,
            'logoUrl' => $this->logoUrl,
            'organizationName' => $this->organizationName,
            'organizationUrl' => $this->organizationUrl,
        ];
    }
}
