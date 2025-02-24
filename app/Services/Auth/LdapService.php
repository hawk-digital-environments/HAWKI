<?php

namespace App\Services\Auth;

use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;

class LdapService
{
    public function authenticate($username, $password)
    {
        try {
            $ldap_host = config('ldap.custom_connection.ldap_host');
            $ldap_port = config('ldap.custom_connection.ldap_port');
            $ldap_binddn = config('ldap.custom_connection.ldap_base_dn');
            $ldap_bindpw = config('ldap.custom_connection.ldap_bind_pw');
            $ldap_base = config('ldap.custom_connection.ldap_search_dn');
            $ldap_filter = config('ldap.custom_connection.ldap_filter');
            $ldap_attributeMap = config('ldap.custom_connection.attribute_map');

            // Check if username or password is empty
            if (!$username || !$password) {
                return false;
            }
            
            // bypassing certificate validation
            ldap_set_option(NULL, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
            // Connect to LDAP server
            $ldapUri = $ldap_host . ':' . $ldap_port;
            $ldapConn = ldap_connect($ldapUri);
            if (!$ldapConn) {
                return false;
            }

            // Set LDAP protocol version
            if (!ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
                return false;
            }
        
            // Bind to LDAP server
            if (!@ldap_bind($ldapConn, $ldap_binddn, $ldap_bindpw)) {
                return false;
            }

            // Search LDAP for user
            $filter = str_replace("username", $username, $ldap_filter);
            $sr = ldap_search($ldapConn, $ldap_base, $filter);
            if (!$sr) {
                return false;
            }
            
            // Get first entry from search results
            $entryId = ldap_first_entry($ldapConn, $sr);
            if (!$entryId) {
                return false;
            }
            
            // Get DN from entry
            $userDn = ldap_get_dn($ldapConn, $entryId);
            if (!$userDn) {
                return false;
            }
            
            // Bind with user DN and password
            $passValid = ldap_bind($ldapConn, $userDn, $password); 
            if (!$passValid) {
                return false;
            }
            $info = ldap_get_entries($ldapConn, $sr);
            ldap_close($ldapConn);


            $userInfo = [];
            foreach ($ldap_attributeMap as $appAttr => $ldapAttr) {
                if (isset($info[0][$ldapAttr][0])) {
                    $userInfo[$appAttr] = $info[0][$ldapAttr][0];
                } else {
                    $userInfo[$appAttr] = 'Unknown';
                }
            }

            // Example specific logic for display name
            if (isset($userInfo['displayname'])) {
                $parts = explode(", ", $userInfo['displayname']);
                $userInfo['name'] = (isset($parts[1]) ? $parts[1] : '') . " " . (isset($parts[0]) ? $parts[0] : '');
            }
            return $userInfo;
        } catch (\Exception $e) {

            Log::error('ERROR LOGIN LDAP');
            Log::error($e);
            return false;
        }
    }
}
