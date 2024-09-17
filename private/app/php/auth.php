<?php
    if (isset($_POST["submit"])) {
        // Check if csrf tocken from the user is euqal to the session csrf token.
        if (!isset($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'])) {
            die('Invalid CSRF token');
        }
        //REGENERATE CSRF TOKEN FOR MORE SECURITY
        generate_csrf_token();
        
        if (array_key_exists('REMOTE_USER', $_SERVER) && !empty($_SERVER['REMOTE_USER'])) {
            // If user is already authenticated via shibboleth.
            $_SESSION['username'] = $_SERVER['REMOTE_USER'];
            
            //REGENERATE SESSION ID
            session_regenerate_id();

            header("Location: interface");
            exit;
        } else {
            // Redirect user to shibboleth login page.
            if (file_exists(ENV_FILE_PATH)){
                $env = parse_ini_file(ENV_FILE_PATH);
                $loginPath = $env['SHIBBOLETH_LOGIN_PATH'];
                $loginPage = $env['SHIBBOLETH_LOGIN_PAGE'];

                //REQUEST SCHEME MAY BE UNDEFINED BASED ON THE SERVER CONFIG
                //MAKE SURE TO PREPARE THE SERVER MODS.
                $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
                $shibLogin = 'Location: /' . $loginPath . $scheme. '://' . $_SERVER['HTTP_HOST'] . '/' . $loginPage;
                header($shibLogin);
                exit;
            }
            else{
                echo 'Shibbolet authentication not defined.';
            }
        }
    }

    if (isset($_POST["account"]) && isset($_POST["password"])) {

        function handleLdapLogin($username, $password){
            // Check if .env file exists for LDAP configuration
            if (file_exists(ENV_FILE_PATH)){
                $env = parse_ini_file(ENV_FILE_PATH);
                $ldap_host = $env['LDAP_HOST'];
                $ldap_port = $env['LDAP_PORT'];
                $ldap_binddn = $env['LDAP_BASE_DN'];
                $ldap_bindpw = $env['LDAP_BIND_PW'];
                $ldap_base = $env['LDAP_SEARCH_DN'];	
                $ldap_filter = $env['LDAP_FILTER'];
            }

            // Check if username or password is empty
            if (!$username || !$password) {
                // echo "Invalid input.";
                return false;
            }
            
            // *** ACTIVATES TEST ACCESS ***
            // Please set a unique test username and password in .env
            if(isset($env['TESTUSER']) && isset($env['TESTPASSWORD']) &&
                     !empty($env['TESTUSER']) && !empty($env['TESTPASSWORD']) &&
                     $username == $env['TESTUSER'] && $password == $env['TESTPASSWORD']) {
                $_SESSION['username'] = $env['TESTUSER'];
                $_SESSION['employeetype'] = "Tester";
                return true;
            }
            
            // Connect to LDAP server
            $ldapUri = $ldap_host . ':' . $ldap_port;
            $ldapConn = ldap_connect($ldapUri);
            if (!$ldapConn) {
                // echo "Unable to connect to LDAP server.";
                return false;
            }
            
            // Set LDAP protocol version
            if (!ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
                // echo "Unable to set LDAP protocol version.";
                return false;
            }
        
            // Bind to LDAP server
            if (!@ldap_bind($ldapConn, $ldap_binddn, $ldap_bindpw)) {
                // echo "Unable to bind to LDAP server with provided DN and password.";
                return false;
            }

            // Search LDAP for user
            $filter = str_replace("username", $username, $ldap_filter);
        
            $sr = ldap_search($ldapConn, $ldap_base, $filter);
            if (!$sr) {
                // echo "LDAP search failed.";
                return false;
            }
            
            // Get first entry from search results
            $entryId = ldap_first_entry($ldapConn, $sr);
            if (!$entryId) {
                // echo "Unable to get the first entry from the search results.";
                return false;
            }
            
            // Get DN from entry
            $userDn = ldap_get_dn($ldapConn, $entryId);
            if (!$userDn) {
                // echo "Unable to get DN from the entry.";
                return false;
            }
            
            // Bind with user DN and password
            $passValid = ldap_bind($ldapConn, $userDn, $password); 
            if (!$passValid) {
                // echo "Unable to bind with provided user DN and password.";
                return false;
            }
            // Get user information
            $info = ldap_get_entries($ldapConn, $sr);
            
            // Close LDAP connection
            ldap_close($ldapConn);

            // Get username
            if (isset($env['LDAP_DEFAULT_INITIALS']) && !empty($env['LDAP_DEFAULT_INITIALS'])) {
                // Use default initials
                $initials = $env['LDAP_DEFAULT_INITIALS'];
            } else {
                // Extract initials from user's display name
                $name = $info[0]["displayname"][0];
                $parts = explode(", ", $name);
                $initials = substr($parts[1], 0, 1) . substr($parts[0], 0, 1);
            }

            // Set session variables
            $_SESSION['username'] = $initials;
            $_SESSION['employeetype'] = $info[0]["employeetype"][0];
            return true;
        }


        // filter username to prevent unwanted inputs.
        $username = filter_var($_POST["account"], FILTER_UNSAFE_RAW);
        $username = ldap_escape($username, "", LDAP_ESCAPE_FILTER);
        
        // Use hashed password if LDAP Server is configured accordingly.
        // $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $password = $_POST["password"];

        // Validate CSRF token on login request
        // Check if csrf tocken from the user is euqal to the session csrf token.
        if (!isset($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'])) {
            $csrf = generate_csrf_token();
            echo json_encode(array("success" => false, 'csrf_token' => $csrf, "message" => "BAD CSRF..."));
            exit;
        }
        else{
            // Authenticate user with submitted credentials
            if (handleLdapLogin($username, $password)) {
                $csrf = generate_csrf_token();
                echo json_encode(array("success" => true, 'csrf_token' => $csrf, "message" => "logging in..."));
                exit;
            } else {
                $csrf = generate_csrf_token();
                echo json_encode(array("success" => false, 'csrf_token' => $csrf, "message" => $_SESSION['translation']['LoginFailedMSG']));
                exit;
            }
        }
    }
 
    