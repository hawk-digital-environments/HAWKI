<?php

namespace App\Services\Auth;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Util\AuthServiceWithCredentialsTrait;
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

    public function __construct(
        #[Config('ldap.connections.default')]
        private readonly array           $connection,
        private readonly LoggerInterface $logger
    )
    {
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
            $baseDn = $this->connection['ldap_base_dn'];
            $bindPw = $this->connection['ldap_bind_pw'];
            $searchDn = $this->connection['ldap_search_dn'];
            $filter = $this->connection['ldap_filter'];
            $attributeMap = $this->connection['attribute_map'];
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

            // Bind with service account (baseDn + bindPw)
            if (!@ldap_bind($ldapConn, $baseDn, $bindPw)) {
                $this->logLdapError($ldapConn, 'Service account bind failed');
            }

            // Search for user
            $searchFilter = str_replace("username", $this->username, $filter);
            $sr = ldap_search($ldapConn, $searchDn, $searchFilter);
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

            $userInfo = [];
            foreach ($attributeMap as $appAttr => $ldapAttr) {
                $userInfo[$appAttr] = $info[0][$ldapAttr][0] ?? 'Unknown';
            }

            // Handle display name inversion (e.g., "Lastname, Firstname")
            if (isset($userInfo['displayname']) && $invertName) {
                $parts = explode(", ", $userInfo['displayname']);
                $userInfo['name'] = ($parts[1] ?? '') . ' ' . ($parts[0] ?? '');
            }

            return new AuthenticatedUserInfo(
                username: $userInfo['username'],
                displayName: $userInfo['name'],
                email: $userInfo['email'],
                employeeType: $userInfo['employeetype']
            );
        } catch (\Exception $e) {
            throw new AuthFailedException('LDAP authentication failed', 500, $e);
        } finally {
            if ($ldapConn) {
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
