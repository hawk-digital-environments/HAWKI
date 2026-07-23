<?php
declare(strict_types=1);


namespace App\Services\System\Http;


use App\Services\System\Http\Exceptions\SsrfBlockedException;
use App\Services\System\Http\Exceptions\TooManyRedirectsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Implements the {@see PendingRequest::getSsrfSafe()} macro, registered in AppServiceProvider.
 *
 * Performs an HTTP GET while preventing Server-Side Request Forgery (SSRF) by
 * validating every URL — including each redirect hop — against a public-IP allowlist
 * before the connection is opened. Redirects are followed manually so every
 * {@code Location} header can be inspected; Guzzle's built-in redirect following
 * is disabled to make this per-hop check possible.
 *
 * Usage (via the registered macro):
 *
 *     $response = Http::timeout(10)
 *         ->withHeaders(['User-Agent' => 'MyBot/1.0'])
 *         ->getSsrfSafe('https://example.com/resource', ['q' => 'search']);
 *
 * @see \App\Providers\AppServiceProvider::bootHttpMacros() where the macro is registered
 */
class SsrfSafeGetterMacro
{
    /**
     * Execute an SSRF-safe GET request.
     *
     * The initial URL and every redirect destination are validated before connecting.
     * Query parameters are forwarded only on the first request; redirect hops receive none.
     *
     * @param array|null|string $query Forwarded as-is to Guzzle on the first request only.
     */
    public static function execute(
        PendingRequest    $request,
        string            $url,
        array|null|string $query = null
    ): Response
    {
        $maxRedirects = $request->getOptions()['allow_redirects']['max'] ?? 5;

        // Clone the request to avoid mutating the original, which may be used for other calls.
        $localRequest = clone $request;
        unset($request);

        self::assertPublicUrl($url);

        $localRequest->withoutRedirecting();

        $currentUrl = $url;
        $finalResponse = null;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            $hop = $localRequest->get($currentUrl, $query);
            $query = null; // Only send query parameters on the initial request

            if ($hop->header('Location') && in_array($hop->status(), [301, 302, 303, 307, 308], true)) {
                $location = UrlResolver::resolve($currentUrl, $hop->header('Location'));
                self::assertPublicUrl($location);
                $currentUrl = $location;
                continue;
            }

            $finalResponse = $hop;
            break;
        }

        if ($finalResponse === null) {
            throw TooManyRedirectsException::forUrl($url, $maxRedirects);
        }

        return $finalResponse;
    }

    /**
     * Ensure the URL targets a public, routable http(s) address.
     * Resolves the host and rejects loopback, private, link-local, and other
     * non-global ranges so that decimal/octal/dotless/IPv4-mapped-IPv6 encodings
     * are all normalised to the address actually connected to.
     *
     * @throws SsrfBlockedException when the target is not allowed.
     */
    private static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw SsrfBlockedException::malformedUrl($url);
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw SsrfBlockedException::unsupportedScheme($parts['scheme']);
        }

        // Reject embedded credentials (user:pass@host).
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw SsrfBlockedException::credentialsInUrl();
        }

        $host = trim($parts['host'], '[]');

        // Canonicalise any numeric host literal (dotted-quad, dotless-decimal,
        // octal, hex) to the address the connect layer will actually dial, then
        // validate that. This closes octal/decimal/0x and ::ffff: bypasses.
        $literal = self::canonicalizeIpLiteral($host);
        if ($literal !== null) {
            if (!self::isPublicIp($literal)) {
                throw SsrfBlockedException::nonPublicAddress($host);
            }
            return;
        }

        // Otherwise treat the host as a DNS name and validate every address it maps to.
        $ips = gethostbynamel($host);
        if (!$ips) {
            // gethostbynamel only returns IPv4; fall back for IPv6-only names.
            $records = @dns_get_record($host, DNS_AAAA);
            if ($records) {
                foreach ($records as $r) {
                    if (!empty($r['ipv6'])) {
                        $ips[] = $r['ipv6'];
                    }
                }
            }
        }

        if (empty($ips)) {
            throw SsrfBlockedException::unresolvableHost($host);
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                throw SsrfBlockedException::nonPublicAddress($host);
            }
        }
    }

    /**
     * If the host is a numeric IP literal in any common encoding (dotted-quad,
     * dotless decimal, octal, or hex — IPv4 — or a bracketed/plain IPv6 literal),
     * return its canonical string form. Returns null for DNS names.
     */
    private static function canonicalizeIpLiteral(string $host): ?string
    {
        // Already a valid IP (IPv4 dotted-quad or IPv6).
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        // Pure-decimal dotless form, e.g. 2130706433 == 127.0.0.1
        if (preg_match('/^\d+$/', $host)) {
            $n = (int)$host;
            if ($n >= 0 && $n <= 4294967295) {
                return long2ip($n);
            }
        }

        // Dotted form whose octets may be octal (0177) or hex (0x7f). PHP's
        // inet_pton rejects these, but curl/getaddrinfo will accept them, so we
        // parse each octet with the same C-style rules and rebuild the address.
        if (preg_match('/^[0-9a-fx.]+$/i', $host) && substr_count($host, '.') === 3) {
            $octets = explode('.', $host);
            $parsed = [];
            foreach ($octets as $oct) {
                if ($oct === '') {
                    return null;
                }
                if (stripos($oct, '0x') === 0) {
                    $val = hexdec(substr($oct, 2));
                } elseif (strlen($oct) > 1 && $oct[0] === '0') {
                    $val = octdec($oct);
                } elseif (ctype_digit($oct)) {
                    $val = (int)$oct;
                } else {
                    return null;
                }
                if ($val < 0 || $val > 255) {
                    return null;
                }
                $parsed[] = $val;
            }
            return implode('.', $parsed);
        }

        return null;
    }

    /**
     * True only for globally routable unicast addresses.
     */
    private static function isPublicIp(string $ip): bool
    {
        // Normalise IPv4-mapped IPv6 (::ffff:127.0.0.1) to its IPv4 form.
        if (stripos($ip, '::ffff:') === 0) {
            $mapped = substr($ip, 7);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip = $mapped;
            }
        }

        // FILTER_FLAG_NO_PRIV_RANGE + FILTER_FLAG_NO_RES_RANGE reject private,
        // reserved, loopback, and link-local ranges for both IPv4 and IPv6.
        return filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
    }
}
