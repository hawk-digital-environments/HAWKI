<?php

namespace Tests\Unit;

use App\Services\Auth\Exception\LdapException;
use App\Services\Auth\Value\Ldap\LdapAttributeReader;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LdapAttributeReaderTest extends TestCase
{
    private function createReader(?LoggerInterface $logger = null): LdapAttributeReader
    {
        return new LdapAttributeReader(
            usernameAttribute: 'uid',
            emailAttribute: 'mail',
            displayNameAttribute: 'displayName',
            employeeTypeAttribute: 'employeeType',
            legacyInvertDisplayNameOrder: false,
            logger: $logger
        );
    }

    /**
     * Test that attributes with proper case are read correctly
     */
    public function test_reads_attribute_with_correct_case(): void
    {
        $reader = $this->createReader();
        $ldapEntry = [
            [
                'uid' => ['testuser'],
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff']
            ]
        ];

        $this->assertEquals('testuser', $reader->getUsername($ldapEntry));
        $this->assertEquals('test@example.com', $reader->getEmail($ldapEntry));
        $this->assertEquals('Test User', $reader->getDisplayName($ldapEntry));
        $this->assertEquals('staff', $reader->getEmployeeType($ldapEntry));
    }

    /**
     * Test that lowercase attributes are detected and used gracefully
     */
    public function test_reads_lowercase_attribute_with_warning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                'LDAP attribute found in lowercase - possible misconfiguration',
                $this->callback(function ($context) {
                    return $context['expected_attribute'] === 'UID'
                        && $context['found_attribute'] === 'uid'
                        && isset($context['message']);
                })
            );

        $reader = new LdapAttributeReader(
            usernameAttribute: 'UID',  // Uppercase in config
            emailAttribute: 'mail',
            displayNameAttribute: 'displayName',
            employeeTypeAttribute: 'employeeType',
            legacyInvertDisplayNameOrder: false,
            logger: $logger
        );

        $ldapEntry = [
            [
                'uid' => ['testuser'],  // Lowercase in LDAP
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff']
            ]
        ];

        // Should successfully retrieve the lowercase attribute
        $this->assertEquals('testuser', $reader->getUsername($ldapEntry));
    }

    /**
     * Test that missing attributes throw an exception
     */
    public function test_throws_exception_for_missing_attribute(): void
    {
        $reader = $this->createReader();
        $ldapEntry = [
            [
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff']
                // uid is missing
            ]
        ];

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage("The LDAP entry does not contain an attribute called: 'uid' that has a value.");
        $reader->getUsername($ldapEntry);
    }

    /**
     * Test that empty LDAP entry throws exception
     */
    public function test_throws_exception_for_empty_ldap_entry(): void
    {
        $reader = $this->createReader();
        $ldapEntry = [];

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage('The LDAP entry is empty. Looks like the LDAP query did not return any results.');
        $reader->getUsername($ldapEntry);
    }

    /**
     * Test that non-array LDAP entry throws exception
     */
    public function test_throws_exception_for_non_array_ldap_entry(): void
    {
        $reader = $this->createReader();
        $ldapEntry = 'not an array';

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage('The LDAP entry must be an array. However, string given.');
        $reader->getUsername($ldapEntry);
    }

    /**
     * Test lowercase fallback works for all attribute types
     */
    public function test_lowercase_fallback_works_for_all_attributes(): void
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
            logger: $logger
        );

        $ldapEntry = [
            [
                'uid' => ['testuser'],
                'mail' => ['test@example.com'],
                'displayname' => ['Test User'],
                'employeetype' => ['staff']
            ]
        ];

        $this->assertEquals('testuser', $reader->getUsername($ldapEntry));
        $this->assertEquals('test@example.com', $reader->getEmail($ldapEntry));
        $this->assertEquals('Test User', $reader->getDisplayName($ldapEntry));
        $this->assertEquals('staff', $reader->getEmployeeType($ldapEntry));
    }

    /**
     * Test that already lowercase attributes don't trigger warning
     */
    public function test_already_lowercase_attributes_do_not_trigger_warning(): void
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
            logger: $logger
        );

        $ldapEntry = [
            [
                'uid' => ['testuser'],
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff']
            ]
        ];

        $this->assertEquals('testuser', $reader->getUsername($ldapEntry));
    }

    /**
     * Test that non-string attribute values throw exception
     */
    public function test_throws_exception_for_non_string_attribute_value(): void
    {
        $reader = $this->createReader();
        $ldapEntry = [
            [
                'uid' => [123],  // Integer instead of string
                'mail' => ['test@example.com'],
                'displayName' => ['Test User'],
                'employeeType' => ['staff']
            ]
        ];

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage("LDAP: User info attribute 'uid' is not a string.");
        $reader->getUsername($ldapEntry);
    }
}
