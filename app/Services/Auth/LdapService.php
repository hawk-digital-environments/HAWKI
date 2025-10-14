<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Log;

class LdapService
{
    public function authenticate($username, $password)
    {
        try {
            $debug_mode   = config('ldap.debug_mode', false);
            $connection   = config('ldap.connections.default');
            $host         = $connection['ldap_host'];
            $port         = $connection['ldap_port'];
            $baseDn       = $connection['ldap_base_dn'];
            $bindPw       = $connection['ldap_bind_pw'];
            $searchDn     = $connection['ldap_search_dn'];
            $filter       = $connection['ldap_filter'];
            $attributeMap = $connection['attribute_map'];
            $invertName   = $connection['invert_name'] ?? false;

            if (!$username || !$password) {
                if ($debug_mode) {
                    Log::error('LDAP: Empty username or password');
                }
                return false;
            }

            // bypassing certificate validation (can be adjusted per config)
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

            // Connect to LDAP
            $ldapUri = $host . ':' . $port;
            $ldapConn = ldap_connect($ldapUri);
            if (!$ldapConn) {
                Log::error("LDAP: Failed to connect to {$ldapUri}");
                return false;
            }

            // Set protocol version
            if (!ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
                if($debug_mode) $this->logLdapError($ldapConn, 'Failed to set LDAP protocol version');
                return false;
            }

            // Bind with service account (baseDn + bindPw)
            if (!@ldap_bind($ldapConn, $baseDn, $bindPw)) {
                if($debug_mode) $this->logLdapError($ldapConn, 'Service account bind failed');
                return false;
            }

            // Search for user
            $searchFilter = str_replace("username", $username, $filter);
            $sr = ldap_search($ldapConn, $searchDn, $searchFilter);
            if (!$sr) {
                if($debug_mode) $this->logLdapError($ldapConn, 'LDAP search failed');
                return false;
            }

            $entryId = ldap_first_entry($ldapConn, $sr);
            if (!$entryId) {
                if($debug_mode) $this->logLdapError($ldapConn, 'No LDAP entries found for user');
                return false;
            }

            $userDn = ldap_get_dn($ldapConn, $entryId);
            if (!$userDn) {
                if($debug_mode) $this->logLdapError($ldapConn, 'Failed to retrieve DN for user');
                return false;
            }

            // Validate user credentials by binding with their DN + password
            if (!@ldap_bind($ldapConn, $userDn, $password)) {
                if($debug_mode) $this->logLdapError($ldapConn, "Invalid password for {$username}");
                return false;
            }

            // Fetch user attributes
            $info = ldap_get_entries($ldapConn, $sr);
            ldap_close($ldapConn);

            $userInfo = [];
            foreach ($attributeMap as $appAttr => $ldapAttr) {
                $userInfo[$appAttr] = $info[0][$ldapAttr][0] ?? 'Unknown';
            }

            // Handle display name inversion (e.g., "Lastname, Firstname")
            if (isset($userInfo['displayname']) && $invertName) {
                $parts = explode(", ", $userInfo['displayname']);
                $userInfo['name'] = ($parts[1] ?? '') . ' ' . ($parts[0] ?? '');
            }

            if($debug_mode){
                Log::info("LDAP LOGIN: user info: " . json_encode($userInfo));
            }
            return $userInfo;
        } catch (\Exception $e) {
            Log::error('Unexpected LDAP exception during authentication', [
                'username' => $username,
                'message'  => $e->getMessage(),
                'code'     => $e->getCode(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * Helper to log LDAP errors with context.
     */
    private function logLdapError($ldapConn, $message): void
    {
        $error = ldap_error($ldapConn);
        $errno = ldap_errno($ldapConn);
        Log::error("{$message} (Error {$errno}: {$error})");
    }
}
