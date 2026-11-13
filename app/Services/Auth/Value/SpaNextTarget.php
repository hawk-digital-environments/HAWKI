<?php
declare(strict_types=1);


namespace App\Services\Auth\Value;


/**
 * A `next` redirect target that has been proven safe to hand back to the browser.
 *
 * Redirect targets arrive as user input on `/auth/redirect?next=…`, survive a round trip through
 * an external identity provider, and are then put into a `Location` header — which makes them a
 * textbook open-redirect and header-injection vector. {@see tryFrom()} therefore *drops* anything
 * it cannot prove is an in-app SPA path instead of trying to repair it, so a manipulated target
 * degrades to "land on the default page" rather than to "go wherever the attacker wants".
 *
 * ```php
 * $target = SpaNextTarget::tryFrom($request->query('next'));  // ?SpaNextTarget
 * $target?->path;                                             // '/new/chat/abc'
 * ```
 */
readonly class SpaNextTarget
{
    /**
     * The Svelte SPA is mounted here. A target outside this prefix is either another
     * application's page or not a local path at all — neither is ours to redirect to.
     */
    private const string REQUIRED_PREFIX = '/new/';

    /**
     * Targets are parked in the session across the identity provider round trip, so they are
     * capped to keep a crafted URL from bloating the session store.
     */
    private const int MAX_LENGTH = 512;

    private function __construct(
        public string $path
    )
    {
    }

    /** Decode before validating, including escapes nested by a previous redirect. */
    public static function tryFrom(?string $path): ?self
    {
        if ($path === null || $path === '' || strlen($path) > self::MAX_LENGTH) {
            return null;
        }

        for ($i = 0; $i < 5; $i++) {
            $decoded = rawurldecode($path);
            if ($decoded === $path) break;
            $path = $decoded;
        }

        if (preg_match('/%[0-9a-f]{2}/i', $path) === 1
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || !str_starts_with($path, self::REQUIRED_PREFIX)) {
            return null;
        }

        $pathname = preg_split('/[?#]/', $path, 2)[0];
        if (in_array('..', explode('/', $pathname), true)) return null;

        return new self($path);
    }
}
