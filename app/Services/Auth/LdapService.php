<?php

namespace App\Services\Auth;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Util\AuthServiceWithCredentialsTrait;
use App\Services\Auth\Util\DisplayNameBuilder;
use App\Services\Auth\Value\AuthenticatedUserInfo;
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
            throw new \RuntimeException('LDAP functions are not available. Please ensure the PHP LDAP extension is installed and enabled.');
        }

        try {
            $host = $this->connection['ldap_host'];
            $port = $this->connection['ldap_port'];
            $bindDn = $this->connection['ldap_bind_dn'];
            $bindPw = $this->connection['ldap_bind_pw'];
            $baseDn = $this->connection['ldap_base_dn'];
            $filter = $this->connection['ldap_filter'];
            $attributeMap = $this->connection['attribute_map'];
            $usernameAttribute = $attributeMap['username'] ?? 'cn';
            $nameAttribute = $attributeMap['name'] ?? 'displayname';
            $employeeTypeAttribute = $attributeMap['employeetype'] ?? 'employeetype';
            $emailAttribute = $attributeMap['email'] ?? 'mail';
            $invertName = $this->connection['invert_name'] ?? false;

            if (empty($this->username) || empty($this->password)) {
                $this->logger->warning('LDAP authentication attempted without credentials');
                throw new AuthFailedException('To authenticate, username and password must be provided.');
            }

            // bypassing certificate validation (can be adjusted per config)
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

            // Connect to LDAP
            $ldapUri = $host . ':' . $port;
            $ldapConn = ldap_connect($ldapUri);
            if (!$ldapConn) {
                $this->logger->error("LDAP: Failed to connect to {$ldapUri}");
                throw new AuthFailedException('Failed to connect to LDAP server.');
            }

            // Set protocol version
            if (!ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
                $this->logLdapError($ldapConn, 'Failed to set LDAP protocol version');
            }

            // Bind with service account (bindDn + bindPw)
            if (strtolower((string)$bindDn) !== 'anonymous' && !@ldap_bind($ldapConn, $bindDn, $bindPw)) {
                $this->logLdapError($ldapConn, 'Service account bind failed');
            }

            // Search for user
            $searchFilter = str_replace("username", $this->username, $filter);
            $sr = ldap_search($ldapConn, $baseDn, $searchFilter);
            if (!$sr) {
                $this->logLdapError($ldapConn, 'LDAP search failed');
            }

            $entryId = ldap_first_entry($ldapConn, $sr);
            if (!$entryId) {
                $this->logLdapError($ldapConn, 'No LDAP entries found for user');
            }

            $userDn = ldap_get_dn($ldapConn, $entryId);
            if (!$userDn) {
                $this->logLdapError($ldapConn, 'Failed to retrieve DN for user');
            }

            // Validate user credentials by binding with their DN + password
            if (!@ldap_bind($ldapConn, $userDn, $this->password)) {
                $this->logLdapError($ldapConn, "Invalid password for {$this->username}");
            }

            // Fetch user attributes
            $info = ldap_get_entries($ldapConn, $sr);

            $getLdapValue = static function (string $attribute) use ($info) {
                if (empty($info[0][$attribute][0])) {
                    throw new \RuntimeException("LDAP: User info attribute '{$attribute}' is missing or empty.");
                }
                if (!is_string($info[0][$attribute][0])) {
                    throw new \RuntimeException("LDAP: User info attribute '{$attribute}' is not a string.");
                }
                return $info[0][$attribute][0];
            };

            // Check if comma separated list
            if (str_contains($getLdapValue($usernameAttribute), ',')) {
                $displayName = DisplayNameBuilder::build(
                    definition: $nameAttribute,
                    valueResolver: $getLdapValue,
                    logger: $this->logger
                );
            } else {
                $displayName = $getLdapValue($nameAttribute);
                // Handle display name inversion (e.g., "Lastname, Firstname")
                if ($invertName) {
                    $parts = explode(", ", $displayName);
                    $displayName = ($parts[1] ?? '') . ' ' . ($parts[0] ?? '');
                }
            }

            return new AuthenticatedUserInfo(
                username: $getLdapValue($usernameAttribute),
                displayName: $displayName,
                email: $getLdapValue($emailAttribute),
                employeeType: $getLdapValue($employeeTypeAttribute)
            );
        } catch (\Exception $e) {
            if ($e instanceof \ErrorException && preg_match('~ldap_.*\(\):~', $e->getMessage())) {
                $this->logger->error('LDAP Error: ' . $e->getMessage(), ['exception' => $e]);
            }
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
    private function logLdapError($ldapConn, $message): never
    {
        $error = ldap_error($ldapConn);
        $errno = ldap_errno($ldapConn);
        $this->logger->error("LDAP Error: {$message} (Error {$errno}: {$error})");
        throw new AuthFailedException('LDAP authentication failed');
    }
}
