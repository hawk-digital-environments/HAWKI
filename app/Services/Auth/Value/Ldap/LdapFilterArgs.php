<?php
declare(strict_types=1);


namespace App\Services\Auth\Value\Ldap;


use App\Services\Auth\Exception\LdapException;
use App\Services\Auth\Util\LdapUtil;
use Psr\Log\LoggerInterface;

readonly class LdapFilterArgs
{
    private const USERNAME_PLACEHOLDER = 'username';

    public string $baseDn;
    public string $filterTemplate;

    public function __construct(
        mixed            $baseDn,
        mixed            $filterTemplate,
        ?LoggerInterface $logger = null
    )
    {
        if (!is_string($baseDn) || empty($baseDn)) {
            throw new LdapException('The LDAP base DN must be a non-empty string.');
        }
        if (!LdapUtil::looksLikeDn($baseDn)) {
            $logger?->warning("The provided LDAP base DN '{$baseDn}' does not look like a valid DN. There might be issues finding users.");
        }
        if (!is_string($filterTemplate) || empty($filterTemplate)) {
            throw new LdapException('The LDAP filter template must be a non-empty string.');
        }
        // The "=" is included to avoid false positives like "(username=*)"
        if (!str_contains($filterTemplate, '=' . self::USERNAME_PLACEHOLDER)) {
            $logger?->warning("The provided LDAP filter template '{$filterTemplate}' does not contain the placeholder '" . self::USERNAME_PLACEHOLDER . "'. There might be issues finding users.");
        }
        $this->baseDn = $baseDn;
        $this->filterTemplate = $filterTemplate;
    }

    /**
     * Generates an LDAP filter by replacing the placeholder with the escaped username.
     * @param string $username
     * @return string
     */
    public function getFilterForUser(string $username): string
    {
        // The "=" is included to avoid false positives like "(username=*)"
        return str_replace(
            '=' . self::USERNAME_PLACEHOLDER,
            '=' . LdapUtil::escapeLdapFilterValue($username),
            $this->filterTemplate
        );
    }

}
