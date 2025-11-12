<?php
declare(strict_types=1);


namespace App\Services\Auth\Util;


class LdapUtil
{
    /**
     * Really basic check to see if the string looks like a DN
     * This allows us to log potential issues earlier.
     * Note, that this is not a full validation, just a heuristic.
     * @param string $dn
     * @return bool
     */
    public static function looksLikeDn(string $dn): bool
    {
        // Handle common LDAP attribute names and basic escaping
        $pattern = '/^[a-zA-Z][a-zA-Z0-9-]*\s*=\s*(?:[^,\\\\]|\\\\.)+(?:\s*,\s*[a-zA-Z][a-zA-Z0-9-]*\s*=\s*(?:[^,\\\\]|\\\\.)+)*$/';
        return preg_match($pattern, $dn) === 1;
    }

    /**
     * Escapes special characters in LDAP filter values according to RFC 4515.
     * @param string $value
     * @return string
     */
    public static function escapeLdapFilterValue(string $value): string
    {
        return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
    }
}
