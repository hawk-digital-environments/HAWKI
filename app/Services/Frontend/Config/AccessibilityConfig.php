<?php
declare(strict_types=1);


namespace App\Services\Frontend\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

/**
 * Frontend config for accessibility related links, exposed to the frontend under the `accessibility` key.
 *
 * Carries the public URL of the operator's accessibility statement (Barrierefreiheitserklärung),
 * which the frontend links to from the sidebar. Without a configured URL the link is not shown.
 */
class AccessibilityConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * Absolute URL of the accessibility statement, or null when none is configured.
     */
    public readonly string|null $statementUrl;

    /**
     * Reads the statement URL from the `hawki.accessibility` config namespace (`ACCESSIBILITY_STATEMENT_URL`).
     */
    public static function make(Repository $repo): static
    {
        $url = $repo->get('hawki.accessibility.statement_url');
        return self::fromArray([
            'statementUrl' => is_string($url) && trim($url) !== '' ? trim($url) : null,
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'accessibility';
    }

    /**
     * Returns the accessibility links. Safe to expose publicly — it contains no secrets.
     */
    public function toPublicArray(Request $request): array|null
    {
        return [
            'statementUrl' => $this->statementUrl,
        ];
    }
}
