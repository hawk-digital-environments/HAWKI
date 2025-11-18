<?php
declare(strict_types=1);


namespace App\Services\Auth\Value\Ldap;


use App\Services\Auth\Exception\LdapException;
use Psr\Log\LoggerInterface;

readonly class LdapConnectUri implements \Stringable
{
    public array $uris;

    public function __construct(
        mixed                    $uri,
        mixed                    $port,
        private ?LoggerInterface $logger = null
    )
    {
        $globalPort = $this->loadPort($port);
        $this->uris = $this->loadUris($uri, $globalPort);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return implode(' ', $this->uris);
    }

    private function loadPort(mixed $port): ?int
    {
        if (empty($port)) {
            return null;
        }
        if (!is_int($port) && !ctype_digit($port)) {
            throw new LdapException('The LDAP port must be an integer, ' . gettype($port) . ' given.');
        }
        $port = (int)$port;
        if ($port <= 0 || $port > 65535) {
            throw new LdapException('The LDAP port must be between 1 and 65535, ' . $port . ' given.');
        }
        return $port;
    }

    private function loadUris(mixed $uri, ?int $globalPort): array
    {
        // According to the docs: You can also provide multiple LDAP-URIs separated by a space as one string
        if (!is_string($uri)) {
            throw new LdapException('The LDAP URI must be a string, ' . gettype($uri) . ' given.');
        }
        $uri = trim($uri);
        if (empty($uri)) {
            throw new LdapException('The LDAP URI cannot be empty.');
        }

        $uris = explode(' ', $uri);
        foreach ($uris as &$u) {
            $u = $this->loadSingleUri($u, $globalPort);
        }

        return $uris;
    }

    private function loadSingleUri(string $uri, ?int $globalPort): string
    {
        $uri = trim($uri);
        $uriParts = parse_url($uri);
        if ($uriParts === false || !isset($uriParts['host'])) {
            $lowerUri = strtolower($uri);
            if (!str_starts_with($lowerUri, 'ldap://') && !str_starts_with($lowerUri, 'ldaps://')) {
                throw new LdapException("The LDAP URI '{$uri}' is not a valid URL. It must start with 'ldap://' or 'ldaps://'.");
            }

            throw new LdapException("The LDAP URI '{$uri}' is not a valid URL.");
        }

        $scheme = $uriParts['scheme'] ?? '';
        if (!in_array(strtolower($scheme), ['ldap', 'ldaps'], true)) {
            throw new LdapException("The LDAP URI '{$uri}' must start with 'ldap://' or 'ldaps://'.");
        }

        $isSsl = strtolower($scheme) === 'ldaps';

        $port = $this->loadPort($uriParts['port'] ?? $globalPort ?? ($isSsl ? 636 : 389));

        if ($isSsl && $port === 389) {
            $this->logger?->warning("LDAP URI '{$uri}' uses LDAPS scheme but port is 389. This might lead to connection issues.");
        } else if (!$isSsl && $port === 636) {
            $this->logger?->warning("LDAP URI '{$uri}' uses LDAP scheme but port is 636. This might lead to connection issues.");
        }

        return "{$scheme}://{$uriParts['host']}:{$port}";
    }
}
