<?php

namespace App\Services\Auth;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Util\AuthServiceWithCredentialsTrait;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use App\Services\Auth\Value\Ldap\LdapAttributeReader;
use App\Services\Auth\Value\Ldap\LdapBindCredentials;
use App\Services\Auth\Value\Ldap\LdapConnectUri;
use App\Services\Auth\Value\Ldap\LdapFilterArgs;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

#[Singleton]
class LdapService implements AuthServiceWithCredentialsInterface, AuthServiceInterface
{
    use AuthServiceWithCredentialsTrait;

    private readonly array $connection;

    public function __construct(
        #[Config('ldap.connections')]
        array  $connections,
        #[Config('ldap.default')]
        string $connectionName,
        private readonly LoggerInterface $logger
    )
    {
        if (!isset($connections[$connectionName])) {
            throw new \InvalidArgumentException("LDAP connection configuration '{$connectionName}' not found.");
        }
        $this->connection = $connections[$connectionName];
    }

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        if (!function_exists('ldap_set_option')) {
            $this->logger->error('LDAP functions are not available. Please ensure the PHP LDAP extension is installed and enabled');
            throw new \RuntimeException('LDAP functions are not available.');
        }

        try {
            $connectUri = new LdapConnectUri(
                $this->connection['ldap_host'],
                $this->connection['ldap_port'],
                $this->logger
            );
            $bindCredentials = new LdapBindCredentials(
                bindDn: $this->connection['ldap_bind_dn'],
                bindPw: $this->connection['ldap_bind_pw']
            );
            $filterArgs = new LdapFilterArgs(
                baseDn: $this->connection['ldap_base_dn'],
                filterTemplate: $this->connection['ldap_filter'],
                logger: $this->logger
            );
            $attributeMap = $this->connection['attribute_map'];
            $attributeReader = new LdapAttributeReader(
                usernameAttribute: $attributeMap['username'],
                emailAttribute: $attributeMap['email'],
                displayNameAttribute: $attributeMap['name'],
                employeeTypeAttribute: $attributeMap['employeeType'],
                legacyInvertDisplayNameOrder: $this->connection['invert_name'] ?? false,
                logger: $this->logger
            );
        } catch (\Throwable $e) {
            $this->logger->error('LDAP configuration error: ' . $e->getMessage());
            throw new AuthFailedException('LDAP server is misconfigured.', 500, $e);
        }

        try {
            if (empty($this->username) || empty($this->password)) {
                if (empty($this->username)) {
                    $this->logger->warning('LDAP authentication attempted with empty username.');
                } else {
                    $this->logger->warning("LDAP authentication attempted for user '{$this->username}' with empty password.");
                }
                throw new AuthFailedException('To authenticate, username and password must be provided.');
            }

            $globalOptions = [
                LDAP_OPT_NETWORK_TIMEOUT => 5,
                LDAP_OPT_TIMELIMIT => 5,
                LDAP_OPT_REFERRALS => 0,
                // bypassing certificate validation (can be adjusted per config)
                // @todo should this really be hardcoded like this?
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_NEVER,
            ];

            foreach ($globalOptions as $option => $value) {
                if (!@ldap_set_option(null, $option, $value)) {
                    $this->logger->error("LDAP: Failed to set global option {$option} to value {$value}");
                    throw new AuthFailedException('Failed to set LDAP global options.');
                }
            }

            $ldapConn = @ldap_connect((string)$connectUri);
            if (!$ldapConn) {
                $this->logger->error("LDAP: Failed to connect to {$connectUri}");
                throw new AuthFailedException('Failed to connect to LDAP server.');
            }

            if (!@ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
                $this->logLdapError($ldapConn, 'Failed to set LDAP protocol version');
            }

            if (!$bindCredentials->isAnonymousBind && !@ldap_bind($ldapConn, $bindCredentials->bindDn, $bindCredentials->bindPw)) {
                $this->logLdapError($ldapConn, 'Failed to bind to LDAP server with configured bind DN as: ' . $bindCredentials->bindDn . ' with password: ' . ($bindCredentials->bindPwRedacted ?? 'without password'));
            }

            $sr = @ldap_search($ldapConn, $filterArgs->baseDn, $filterArgs->getFilterForUser($this->username));
            if ($sr === false) {
                $this->logLdapError($ldapConn, 'LDAP search failed. Used filter: ' . $filterArgs->getFilterForUser($this->username) . ' in base DN: ' . $filterArgs->baseDn);
            }

            $entryId = @ldap_first_entry($ldapConn, $sr);
            if ($entryId === false) {
                $this->logLdapError(
                    $ldapConn,
                    'User: ' . $this->username . ' not found in LDAP directory with filter: ' . $filterArgs->getFilterForUser($this->username) . ' in base DN: ' . $filterArgs->baseDn
                );
            }

            $userDn = @ldap_get_dn($ldapConn, $entryId);
            if ($userDn === false) {
                $this->logLdapError($ldapConn, 'Failed to retrieve DN for user');
            }

            // Validate user credentials by binding with their DN + password
            if (!@ldap_bind($ldapConn, $userDn, $this->password)) {
                $this->logLdapError($ldapConn, "Invalid password for {$this->username}");
            }

            $ldapEntry = @ldap_get_entries($ldapConn, $sr);
            if ($ldapEntry === false) {
                $this->logLdapError($ldapConn, 'Failed to retrieve LDAP entry for user');
            }

            return new AuthenticatedUserInfo(
                username: $attributeReader->getUsername($ldapEntry),
                displayName: $attributeReader->getdisplayName($ldapEntry),
                email: $attributeReader->getemail($ldapEntry),
                employeeType: $attributeReader->getEmployeeType($ldapEntry)
            );
        } catch (\Exception $e) {
            throw new AuthFailedException('LDAP authentication failed', 500, $e);
        } finally {
            if (isset($ldapConn)) {
                ldap_unbind($ldapConn);
            }
        }
    }

    /**
     * Helper to log LDAP errors with context.
     */
    private function logLdapError(
        $ldapConn,
        string $message
    ): never
    {
        $errno = ldap_errno($ldapConn);

        if ($errno === 0) {
            $this->logger->info("LDAP Info: {$message} (No error)");
        } else {
            $error = ldap_error($ldapConn);
            $this->logger->error("LDAP Error: {$message} (Error {$errno}: {$error})");
        }
        throw new AuthFailedException('LDAP authentication failed');
    }
}
