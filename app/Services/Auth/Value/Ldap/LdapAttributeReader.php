<?php
declare(strict_types=1);


namespace App\Services\Auth\Value\Ldap;


use App\Services\Auth\Exception\LdapException;
use App\Services\Auth\Util\DisplayNameBuilder;
use Psr\Log\LoggerInterface;

readonly class LdapAttributeReader
{
    /**
     * True if the display name is built from multiple LDAP attributes
     * @var bool
     */
    private bool $displayNameAttributeIsArray;
    /**
     * True if we need to apply the legacy inversion of display name order (e.g., "Lastname, Firstname"), becoming "Firstname Lastname"
     * @var bool
     */
    private bool $legacyInvertDisplayNameOrder;
    public string $usernameAttribute;
    public string $emailAttribute;
    public string $displayNameAttribute;
    public string $employeeTypeAttribute;

    public function __construct(
        mixed                    $usernameAttribute,
        mixed                    $emailAttribute,
        mixed                    $displayNameAttribute,
        mixed                    $employeeTypeAttribute,
        mixed                    $legacyInvertDisplayNameOrder,
        private ?LoggerInterface $logger = null
    )
    {
        if (!is_string($usernameAttribute) || empty($usernameAttribute)) {
            throw new LdapException('The LDAP "username" attribute must be a non-empty string.');
        }
        $this->usernameAttribute = $usernameAttribute;

        if (!is_string($emailAttribute) || empty($emailAttribute)) {
            throw new LdapException('The LDAP "email" attribute must be a non-empty string.');
        }
        $this->emailAttribute = $emailAttribute;

        if (!is_string($displayNameAttribute) || empty($displayNameAttribute)) {
            throw new LdapException('The LDAP "display name" attribute must be a non-empty string.');
        }
        $this->displayNameAttribute = $displayNameAttribute;

        $this->displayNameAttributeIsArray = str_contains($displayNameAttribute, ',');
        if (!is_string($employeeTypeAttribute) || empty($employeeTypeAttribute)) {
            throw new LdapException('The LDAP "employee type" attribute must be a non-empty string.');
        }
        $this->employeeTypeAttribute = $employeeTypeAttribute;

        $this->legacyInvertDisplayNameOrder = (bool)$legacyInvertDisplayNameOrder;
    }

    public function getUsername(mixed $ldapEntry): string
    {
        return $this->getLdapAttributeValue($ldapEntry, $this->usernameAttribute);
    }

    public function getEmail(mixed $ldapEntry): string
    {
        return $this->getLdapAttributeValue($ldapEntry, $this->emailAttribute);
    }

    public function getEmployeeType(mixed $ldapEntry): string
    {
        return $this->getLdapAttributeValue($ldapEntry, $this->employeeTypeAttribute);
    }

    public function getDisplayName(mixed $ldapEntry): string
    {
        if ($this->displayNameAttributeIsArray) {
            return DisplayNameBuilder::build(
                definition: $this->displayNameAttribute,
                valueResolver: fn(string $attribute) => $this->getLdapAttributeValue($ldapEntry, $attribute),
                logger: $this->logger
            );
        }

        $displayName = $this->getLdapAttributeValue($ldapEntry, $this->displayNameAttribute);
        // Handle display name inversion (e.g., "Lastname, Firstname")
        if ($this->legacyInvertDisplayNameOrder) {
            $parts = explode(", ", $displayName);
            $displayName = ($parts[1] ?? '') . ' ' . ($parts[0] ?? '');
        }
        return $displayName;
    }

    private function getLdapAttributeValue(mixed $ldapEntry, string $attribute): string
    {
        if (!is_array($ldapEntry)) {
            throw new LdapException('The LDAP entry must be an array. However, ' . gettype($ldapEntry) . ' given.');
        }
        if (empty($ldapEntry)) {
            throw new LdapException('The LDAP entry is empty. Looks like the LDAP query did not return any results.');
        }
        if (empty($ldapEntry[0][$attribute][0])) {
            $this->logger?->debug('LDAP misses attribute value', [
                'attribute' => $attribute,
                'attribute_node' => $ldapEntry[0][$attribute] ?? null,
                'available_attributes' => array_keys($ldapEntry[0] ?? [])
            ]);
            throw new LdapException("The LDAP entry does not contain an attribute called: '{$attribute}' that has a value.");
        }
        if (!is_string($ldapEntry[0][$attribute][0])) {
            $this->logger?->debug('LDAP attribute value is not a string', [
                'attribute' => $attribute,
                'attribute_value' => $ldapEntry[0][$attribute][0],
                'attribute_value_type' => gettype($ldapEntry[0][$attribute][0]),
            ]);
            throw new LdapException("LDAP: User info attribute '{$attribute}' is not a string.");
        }
        return $ldapEntry[0][$attribute][0];
    }
}
