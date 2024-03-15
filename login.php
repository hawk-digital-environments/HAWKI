<?php
/*
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
*/
  	session_start();
  	// Redirect user to interface if already logged in
	if (isset($_SESSION['username'])) {
		header("Location: interface.php");
		exit;
	}

	if (isset($_POST["account"]) && isset($_POST["password"])) {
		function auth(
		$username,
		$password
		) {
			// Load LDAP configurations from .env file if exists
			if (file_exists(".env")){
				$env = parse_ini_file('.env');
				$ldap_host = $env['LDAP_HOST'];
				$ldap_port = $env['LDAP_PORT'];
				$ldap_binddn = $env['LDAP_BASE_DN'];
				$ldap_bindpw = $env['LDAP_BIND_PW'];
				$ldap_base = $env['LDAP_SEARCH_DN'];
			}

			if (!$username || !$password) {
				echo "Invalid input.";
				header("Location: login.php");
				exit;
			}

			// *** ACTIVATES TEST ACCESS ***
			// Please set a unique test username and password in .env
			if(isset($env['TESTUSER']) && isset($env['TESTPASSWORD']) && $username == $env['TESTUSER'] && $password == $env['TESTPASSWORD']) {
				$_SESSION['username'] = $env['TESTUSER'];
				$_SESSION['employeetype'] = "Tester";
				return true;
			}

			$ldapConn = ldap_connect($ldap_host, $ldap_port);
			if (!$ldapConn) {
				echo "Unable to connect to LDAP server.";
				header("Location: login.php");
				exit;
			}

			if (!ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
				echo "Unable to set LDAP protocol version.";
				header("Location: login.php");
				exit;
			}

			if (!ldap_bind($ldapConn, $ldap_binddn, $ldap_bindpw)) {
				echo "Unable to bind to LDAP server with provided DN and password.";
				header("Location: login.php");
				exit;
			}

			$filter = "(|(sAMAccountName=$username)(mail=$username))";

			$sr = ldap_search($ldapConn, $ldap_base, $filter);
			if (!$sr) {
				echo "LDAP search failed.";
				header("Location: login.php");
				exit;
			}

			$entryId = ldap_first_entry($ldapConn, $sr);
			if (!$entryId) {
				echo "Unable to get the first entry from the search results.";
				header("Location: login.php");
				exit;
			}

			$userDn = ldap_get_dn($ldapConn, $entryId);
			if (!$userDn) {
				echo "Unable to get DN from the entry.";
				header("Location: login.php");
				exit;
			}

			$passValid = ldap_bind($ldapConn, $userDn, $password);
			if (!$passValid) {
				echo "Unable to bind with provided user DN and password.";
				header("Location: login.php");
				exit;
			}

			$info = ldap_get_entries($ldapConn, $sr);

			ldap_close($ldapConn);

		$name = $info[0]["displayname"][0];
		$parts = explode(", ", $name);
		$initials = substr($parts[1], 0, 1) . substr($parts[0], 0, 1);
		$_SESSION['username'] = $initials;
		$_SESSION['employeetype'] = $info[0]["employeetype"][0];
		return true;
		}

		if (auth($_POST["account"], $_POST["password"])) {
			header("Location: interface.php");
			header("Location: login.php");
		} else {
			echo "Anmelden fehlgeschlagen";
		}
	} else {
		// Handling for GET request (do nothing)
	}
?>

<!DOCTYPE html>
<html lang="en" >
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>HAWKI</title>

		<link rel="stylesheet" href="./style.css">
		<link rel="stylesheet" href="https://sibforms.com/forms/end-form/build/sib-styles.css">
	</head>
	<body>
		<!-- partial:index.partial.html -->
		<div class="wrapper">
			<aside>
				<div class="loginPanel">
					<img id="HAWK_logo" src="/img/logo.svg" alt="">
					<h3>Willkommen zurück!</h3>
					<?php
						if (file_exists(".env")){
							$env = parse_ini_file('.env');
						}
						$login_available = false;
						// Check for OIDC authentication method
						if ((isset($env) ? $env["Authentication"] : getenv("Authentication")) == "OIDC") {
							// Open ID Connect
							$login_available = true;
							$oidc_login = isset($env) ? $env["OIDC_LOGIN_BUTTON"] ??'Login' : getenv("OIDC_LOGIN_BUTTON"); // Option for changing login button
							echo
							"<form action='oidc_login.php' class='column' method='post'>
								<button>$oidc_login</button>
							</form>";
						}
						// Check for LDAP authentication method
						if ((isset($env) ? $env["Authentication"] : getenv("Authentication")) == "LDAP") {
							$login_available = true;
							$server = $_SERVER['PHP_SELF'];
							$ldap_login = isset($env) ? $env["LDAP_LOGIN_BUTTON"] ??'Login' : getenv("LDAP_LOGIN_BUTTON");
							echo
								'<form action = "' . $server . '" class="column" method = "post" >
								<label for="account" > Benutzername</label >
								<input type = "text" name = "account" id = "account" >
								<label for="password" > Kennwort</label >
								<input type = "password" name = "password" id = "password" >
								<button>' . $ldap_login . '</button >
							</form>';
						}
						// If no authentication method defined, display error
						if (!$login_available) {
							echo 'No authentication method defined';
							die;
						}
					?>
				</div>
			</aside>
			<main>
				<div class="infoPanel">
					<div class="textPanel">
						<div class="page">
							<h1 class="headerLine"><span class="accentText">GPT</span> FÜR DIE HOCHSCHULE</h1>
							<p>
								HAWKI ist ein didaktisches Interface für Hochschulen, das auf der API von OpenAI basiert. Für die Nutzerinnen und Nutzer ist es nicht notwendig, einen Account anzulegen, die Hochschul-ID reicht für den Login aus - es werden keine nutzerbezogenen Daten gespeichert.<br>
								Das Angebot wurde im Interaction Design Lab der <a href="https://www.hawk.de/de/hochschule/fakultaeten-und-standorte/fakultaet-gestaltung/werkstaetten/interaction-design-lab" target="_blank"><b>HAWK</b></a>  entwickelt, um allen Hochschulangehörigen die Möglichkeit zu geben, Künstliche Intelligenz in ihre Arbeitsprozesse zu integrieren und einen Begegnungsraum zu haben, damit sich eventuell neue Arbeitsweisen ergeben und eine hochschulinterne Diskussion über den Einsatz von K.I. entstehen kann. Derzeit ist die Oberfläche in drei Bereiche unterteilt:<br>
							</p>

							<h3>Konversation</h3>
							<p>Ein Chatbereich wie bei ChatGPT, für einen schnellen Einstieg in jede beliebige Aufgabe.<br>
							</p>

							<h3>Virtuelles Büro</h3>
							<p>Gespräche mit fiktiven Expertinnen und Experten als mentales Modell, um sich in fachfremde Bereiche einzuarbeiten und gezieltere Anfragen an echte Hochschul-Expertinnen und -Experten zu stellen.
							</p>

							<h3>Lernraum</h3>
							<p>Die Lernräume sollen helfen, die verschiedenen Unterstützungsmöglichkeiten zu verstehen und zu lernen, was einen effektiven Prompt ausmacht.<br><br>
							</p>
						</div>
					</div>
				</div>
				<div class="backgroundImageContainer">
					<video class="image_preview_container" src="./img/HAWKIBG.m4v" type="video/m4v" autoplay loop muted></video>
				</div>
			</main>
		</div>
	</body>
</html>
