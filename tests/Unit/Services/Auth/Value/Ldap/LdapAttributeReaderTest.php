<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth\Value\Ldap;

use App\Services\Auth\Exception\LdapException;
use App\Services\Auth\Value\Ldap\LdapAttributeReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(LdapAttributeReader::class)]
class LdapAttributeReaderTest extends TestCase
{
    /**
     * Test that attributes with proper case are read correctly.
     */
    public function testReadsAttributeWithCorrectCase(): void
    {
        $reader = self::createReader();
        $ldapEntry = [
            [
                'uid' => ['testuser'],
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff'],
            ],
        ];

        self::assertEquals('testuser', $reader->getUsername($ldapEntry));
        self::assertEquals('test@example.com', $reader->getEmail($ldapEntry));
        self::assertEquals('Test User', $reader->getDisplayName($ldapEntry));
        self::assertEquals('staff', $reader->getEmployeeType($ldapEntry));
    }

    /**
     * Test that lowercase attributes are detected and used gracefully.
     */
    public function testReadsLowercaseAttributeWithWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                'LDAP attribute found in lowercase - possible misconfiguration',
                self::callback(static function ($context) {
                    return 'UID' === $context['expected_attribute']
                        && 'uid' === $context['found_attribute']
                        && isset($context['message']);
                }),
            );

        $reader = new LdapAttributeReader(
            usernameAttribute: 'UID',  // Uppercase in config
            emailAttribute: 'mail',
            displayNameAttribute: 'displayName',
            employeeTypeAttribute: 'employeeType',
            legacyInvertDisplayNameOrder: false,
            logger: $logger,
        );

        $ldapEntry = [
            [
                'uid' => ['testuser'],  // Lowercase in LDAP
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff'],
            ],
        ];

        // Should successfully retrieve the lowercase attribute
        self::assertEquals('testuser', $reader->getUsername($ldapEntry));
    }

    /**
     * Test that missing attributes throw an exception.
     */
    public function testThrowsExceptionForMissingAttribute(): void
    {
        $reader = self::createReader();
        $ldapEntry = [
            [
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff'],
                // uid is missing
            ],
        ];

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage("The LDAP entry does not contain an attribute called: 'uid' that has a value.");
        $reader->getUsername($ldapEntry);
    }

    /**
     * Test that empty LDAP entry throws exception.
     */
    public function testThrowsExceptionForEmptyLdapEntry(): void
    {
        $reader = self::createReader();
        $ldapEntry = [];

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage('The LDAP entry is empty. Looks like the LDAP query did not return any results.');
        $reader->getUsername($ldapEntry);
    }

    /**
     * Test that non-array LDAP entry throws exception.
     */
    public function testThrowsExceptionForNonArrayLdapEntry(): void
    {
        $reader = self::createReader();
        $ldapEntry = 'not an array';

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage('The LDAP entry must be an array. However, string given.');
        $reader->getUsername($ldapEntry);
    }

    /**
     * Test lowercase fallback works for all attribute types.
     */
    public function testLowercaseFallbackWorksForAllAttributes(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        // Expect debug to be called for each uppercase attribute
        $logger->expects($this->exactly(4))
            ->method('debug')
            ->with('LDAP attribute found in lowercase - possible misconfiguration');

        $reader = new LdapAttributeReader(
            usernameAttribute: 'UID',
            emailAttribute: 'MAIL',
            displayNameAttribute: 'DISPLAYNAME',
            employeeTypeAttribute: 'EMPLOYEETYPE',
            legacyInvertDisplayNameOrder: false,
            logger: $logger,
        );

        $ldapEntry = [
            [
                'uid' => ['testuser'],
                'mail' => ['test@example.com'],
                'displayname' => ['Test User'],
                'employeetype' => ['staff'],
            ],
        ];

        self::assertEquals('testuser', $reader->getUsername($ldapEntry));
        self::assertEquals('test@example.com', $reader->getEmail($ldapEntry));
        self::assertEquals('Test User', $reader->getDisplayName($ldapEntry));
        self::assertEquals('staff', $reader->getEmployeeType($ldapEntry));
    }

    /**
     * Test that already lowercase attributes don't trigger warning.
     */
    public function testAlreadyLowercaseAttributesDoNotTriggerWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        // Should not call debug for already lowercase attributes
        $logger->expects($this->never())
            ->method('debug');

        $reader = new LdapAttributeReader(
            usernameAttribute: 'uid',  // Already lowercase
            emailAttribute: 'mail',
            displayNameAttribute: 'displayName',
            employeeTypeAttribute: 'employeeType',
            legacyInvertDisplayNameOrder: false,
            logger: $logger,
        );

        $ldapEntry = [
            [
                'uid' => ['testuser'],
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff'],
            ],
        ];

        self::assertEquals('testuser', $reader->getUsername($ldapEntry));
    }

    /**
     * Test that non-string attribute values throw exception.
     */
    public function testThrowsExceptionForNonStringAttributeValue(): void
    {
        $reader = self::createReader();
        $ldapEntry = [
            [
                'uid' => [123],  // Integer instead of string
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff'],
            ],
        ];

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage("LDAP: User info attribute 'uid' is not a string.");
        $reader->getUsername($ldapEntry);
    }

    private static function createReader(?LoggerInterface $logger = null): LdapAttributeReader
    {
        return new LdapAttributeReader(
            usernameAttribute: 'uid',
            emailAttribute: 'mail',
            displayNameAttribute: 'displayName',
            employeeTypeAttribute: 'employeeType',
            legacyInvertDisplayNameOrder: false,
            logger: $logger,
        );
    }
}
