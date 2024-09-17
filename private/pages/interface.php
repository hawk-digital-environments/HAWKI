<?php
	session_start();

	require_once BOOTSTRAP_PATH;
	require_once LIBRARY_PATH . 'language_controller.php';


	if(!isset($_SESSION['translation'])){
		setLanguage();
	}
	$translation = $_SESSION['translation'];
    
	if (!isset($_SESSION['username'])) {
		header("Location: login");
		exit;
	}


	// IF CSRF IS NOT SET -> user is in Interface without logging in. -> logout to remove variables and set a new session.
	if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
		header("Location: logout");
		exit;
	}

    // Temporary forward for now private views
    $requested_page = ($_GET['page'] ?? null);
    if(is_string($requested_page)){
        $requested_page = preg_replace('~(\.php$|[^a-zA-Z_])~', '', $requested_page);
        $viewFile = VIEWS_PATH . '/' . $requested_page . '.php';
        if(!file_exists($viewFile)){
            http_response_code(404);
            echo 'Page not found.';
            exit();
        }

        include_once $viewFile;
        exit();

    }
?>
<!DOCTYPE html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no" />

	<!-- SAVE CSRF TOKEN ON THE CLIENT SIDE. -->
	<?php if (isset($_SESSION['csrf_token'])) : ?>
		<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
	<?php endif; ?>
	<title>HAWKI</title>


	<link rel="stylesheet" href="public/style/style.css">
	<link rel="stylesheet" href="public/style/interface_style.css">
	<link rel="stylesheet" href="public/style/settings_style.css">

	<!-- COMMON SCRIPTS -->
	<script src="public/js/scripts.js"></script>
	<script src="public/js/interface_functions.js"></script>
	<script src="public/js/syntax_modifier.js"></script>

	<!-- HLJS -->
	<link id="hljsTheme" type="text/css" rel="stylesheet" href="public/assets/highlightJS/hljsLight.css">
	<script src="public/assets/highlightJS/highlight.min.js"></script>
	<script src="public/assets/highlightJS/go.min.js"></script>

	<!-- KaTex detects and renders math formulas -->
	<link rel="stylesheet" href="public/assets/katex/katex.min.css">
	<script defer src="public/assets/katex/katex.min.js"></script>
	<script defer src="public/assets/katex/contrib/auto-render.min.js"></script>

	<!-- Jquery v3.7.1 -->
	<script src="public/assets/jquery/jquery.min.js"></script>

	<script src="public/assets/lz-string.min.js"></script>
	<script src="public/assets/crypto-js.min.js"></script>


	<!-- TO PREVENT FOUC WHEN RELOADING THE PAGE IN DARK MODE
		 THE SETTINGS AND IT'S START FUNCTIONS SHOULD BE INCLUDED IN THE HEADER BEFORE THE PAGE IS LOADED -->
	<?php include VIEWS_PATH . 'settings.php'; ?>
  	<script>
		SwitchDarkMode(false);
		UpdateSettingsLanguage(`<?php echo $_SESSION['language'] ?>`);
	</script>
	<?php 
		require_once LIBRARY_PATH . 'chatlog_management.php';
	?>
</head>
	<body>
		<div class="wrapper">
			<div class="sidebar">
				<div class="logo">
					<img id="HAWK_logo" src="public/img/logo.svg" alt="">
				</div>
				<div class="menu">
					<details>
						<summary>
							<h3><?php echo $translation["Conversation"]; ?>
								<svg viewBox="0 0 50 50"><path d="M 25 2 C 12.309295 2 2 12.309295 2 25 C 2 37.690705 12.309295 48 25 48 C 37.690705 48 48 37.690705 48 25 C 48 12.309295 37.690705 2 25 2 z M 25 4 C 36.609824 4 46 13.390176 46 25 C 46 36.609824 36.609824 46 25 46 C 13.390176 46 4 36.609824 4 25 C 4 13.390176 13.390176 4 25 4 z M 25 11 A 3 3 0 0 0 22 14 A 3 3 0 0 0 25 17 A 3 3 0 0 0 28 14 A 3 3 0 0 0 25 11 z M 21 21 L 21 23 L 22 23 L 23 23 L 23 36 L 22 36 L 21 36 L 21 38 L 22 38 L 23 38 L 27 38 L 28 38 L 29 38 L 29 36 L 28 36 L 27 36 L 27 21 L 26 21 L 22 21 L 21 21 z"/></svg>
							</h3>
						</summary>
						<?php echo $translation["Conversation_Info"]; ?>
					</details>
					<div class="menu-item" id="chatMenuButton" onclick="load(this, 'chat.php')">
						<svg viewBox="0 0 24 24"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2M20 16H5.2L4 17.2V4H20V16Z" /></svg>
						<?php echo $translation["Chat"]; ?>
					</div>

					<details>
						<summary>
							<h3><?php echo $translation["VirtualOffice"]; ?>
								<svg viewBox="0 0 50 50"><path d="M 25 2 C 12.309295 2 2 12.309295 2 25 C 2 37.690705 12.309295 48 25 48 C 37.690705 48 48 37.690705 48 25 C 48 12.309295 37.690705 2 25 2 z M 25 4 C 36.609824 4 46 13.390176 46 25 C 46 36.609824 36.609824 46 25 46 C 13.390176 46 4 36.609824 4 25 C 4 13.390176 13.390176 4 25 4 z M 25 11 A 3 3 0 0 0 22 14 A 3 3 0 0 0 25 17 A 3 3 0 0 0 28 14 A 3 3 0 0 0 25 11 z M 21 21 L 21 23 L 22 23 L 23 23 L 23 36 L 22 36 L 21 36 L 21 38 L 22 38 L 23 38 L 27 38 L 28 38 L 29 38 L 29 36 L 28 36 L 27 36 L 27 21 L 26 21 L 22 21 L 21 21 z"/></svg>
							</h3>
						</summary>
						<?php echo $translation["VirtualOffice_Info"]; ?>
					</details>
					<div class="menu-item" onclick="submenu(this)">
						<svg viewBox="0 0 24 24"><path d="M13.07 10.41A5 5 0 0 0 13.07 4.59A3.39 3.39 0 0 1 15 4A3.5 3.5 0 0 1 15 11A3.39 3.39 0 0 1 13.07 10.41M5.5 7.5A3.5 3.5 0 1 1 9 11A3.5 3.5 0 0 1 5.5 7.5M7.5 7.5A1.5 1.5 0 1 0 9 6A1.5 1.5 0 0 0 7.5 7.5M16 17V19H2V17S2 13 9 13 16 17 16 17M14 17C13.86 16.22 12.67 15 9 15S4.07 16.31 4 17M15.95 13A5.32 5.32 0 0 1 18 17V19H22V17S22 13.37 15.94 13Z" /></svg>
						Team
					</div>
					<div class="submenu">
						<div class="submenu-item" onclick="load(this, 'finance.php')"><?php echo $translation["Finance"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'science.php')"><?php echo $translation["Research"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'marketing.php')"><?php echo $translation["Marketing"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'programming.php')"><?php echo $translation["Programming"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'law.php')"><?php echo $translation["LegalConsultation"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'socialmedia.php')"><?php echo $translation["SocialMedia"]; ?></div>
					</div>

					<details>
						<summary>
							<h3><?php echo $translation["LearnSpace"]; ?>
								<svg viewBox="0 0 50 50"><path d="M 25 2 C 12.309295 2 2 12.309295 2 25 C 2 37.690705 12.309295 48 25 48 C 37.690705 48 48 37.690705 48 25 C 48 12.309295 37.690705 2 25 2 z M 25 4 C 36.609824 4 46 13.390176 46 25 C 46 36.609824 36.609824 46 25 46 C 13.390176 46 4 36.609824 4 25 C 4 13.390176 13.390176 4 25 4 z M 25 11 A 3 3 0 0 0 22 14 A 3 3 0 0 0 25 17 A 3 3 0 0 0 28 14 A 3 3 0 0 0 25 11 z M 21 21 L 21 23 L 22 23 L 23 23 L 23 36 L 22 36 L 21 36 L 21 38 L 22 38 L 23 38 L 27 38 L 28 38 L 29 38 L 29 36 L 28 36 L 27 36 L 27 21 L 26 21 L 22 21 L 21 21 z"/></svg>
							</h3>
						</summary>
						<?php echo $translation["LearnSpace_Info"]; ?>
					</details>
					<div class="menu-item" onclick="submenu(this)">
						<svg viewBox="0 0 24 24"><path d="M14.6,16.6L19.2,12L14.6,7.4L16,6L22,12L16,18L14.6,16.6M9.4,16.6L4.8,12L9.4,7.4L8,6L2,12L8,18L9.4,16.6Z" /></svg>
						<?php echo $translation["ResearchWork"]; ?>
					</div>
					<div class="submenu">
						<div class="submenu-item" onclick="load(this, 'datascience.php')"><?php echo $translation["DataAnalysis"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'feedback.php')"><?php echo $translation["FeedBack"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'methodologie.php')"><?php echo $translation["Methodology"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'literature.php')"><?php echo $translation["LiteratureSearch"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'research.php')"><?php echo $translation["ResearchSupport"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'writing.php')"><?php echo $translation["WritingHelp"]; ?></div>
					</div>

					<div class="menu-item" onclick="submenu(this)">
						<svg viewBox="0 0 24 24"><path d="M6,3A1,1 0 0,1 7,4V4.88C8.06,4.44 9.5,4 11,4C14,4 14,6 16,6C19,6 20,4 20,4V12C20,12 19,14 16,14C13,14 13,12 11,12C8,12 7,14 7,14V21H5V4A1,1 0 0,1 6,3M7,7.25V11.5C7,11.5 9,10 11,10C13,10 14,12 16,12C18,12 18,11 18,11V7.5C18,7.5 17,8 16,8C14,8 13,6 11,6C9,6 7,7.25 7,7.25Z" /></svg>
						<?php echo $translation["Organization"]; ?>
					</div>
					<div class="submenu">
						<div class="submenu-item" onclick="load(this, 'eventmanagement.php')"><?php echo $translation["EventManagement"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'learning.php')"><?php echo $translation["LearnStrategy"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'motivation.php')"><?php echo $translation["Motivation"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'stressmanagement.php')"><?php echo $translation["StressManagement"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'tables.php')"><?php echo $translation["Tables"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'timemanagement.php')"><?php echo $translation["TimeManagement"]; ?></div>
					</div>

					<div class="menu-item" onclick="submenu(this)">
						<svg viewBox="0 0 24 24"><path d="M15.54,3.5L20.5,8.47L19.07,9.88L14.12,4.93L15.54,3.5M3.5,19.78L10,13.31C9.9,13 9.97,12.61 10.23,12.35C10.62,11.96 11.26,11.96 11.65,12.35C12.04,12.75 12.04,13.38 11.65,13.77C11.39,14.03 11,14.1 10.69,14L4.22,20.5L14.83,16.95L18.36,10.59L13.42,5.64L7.05,9.17L3.5,19.78Z" /></svg>
						<?php echo $translation["Creativity"]; ?>
					</div>
					<div class="submenu">
						<div class="submenu-item" onclick="load(this, 'copywriting.php')"><?php echo $translation["Copywriting"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'designthinking.php')"><?php echo $translation["DesignThinking"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'gamification.php')"><?php echo $translation["Gamification"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'ideageneration.php')"><?php echo $translation["BrainStorming"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'interview.php')"><?php echo $translation["InterviewQuestions"]; ?></div>
						<div class="submenu-item" onclick="load(this, 'prototyping.php')"><?php echo $translation["Prototyping"]; ?></div>
					</div>

				</div>
				<div class="settings-btn" onclick="toggleSettingsPanel(true)">
					<svg  viewBox="0 0 24 24" width="24">
						<path fill-rule="evenodd" clip-rule="evenodd" d="M12 8.00002C9.79085 8.00002 7.99999 9.79088 7.99999 12C7.99999 14.2092 9.79085 16 12 16C14.2091 16 16 14.2092 16 12C16 9.79088 14.2091 8.00002 12 8.00002ZM9.99999 12C9.99999 10.8955 10.8954 10 12 10C13.1046 10 14 10.8955 14 12C14 13.1046 13.1046 14 12 14C10.8954 14 9.99999 13.1046 9.99999 12Z"/>
						<path fill-rule="evenodd" clip-rule="evenodd" d="M12 8.00002C9.79085 8.00002 7.99999 9.79088 7.99999 12C7.99999 14.2092 9.79085 16 12 16C14.2091 16 16 14.2092 16 12C16 9.79088 14.2091 8.00002 12 8.00002ZM9.99999 12C9.99999 10.8955 10.8954 10 12 10C13.1046 10 14 10.8955 14 12C14 13.1046 13.1046 14 12 14C10.8954 14 9.99999 13.1046 9.99999 12Z"/>
						<path fill-rule="evenodd" clip-rule="evenodd" d="M10.7673 1.01709C10.9925 0.999829 11.2454 0.99993 11.4516 1.00001L12.5484 1.00001C12.7546 0.99993 13.0075 0.999829 13.2327 1.01709C13.4989 1.03749 13.8678 1.08936 14.2634 1.26937C14.7635 1.49689 15.1915 1.85736 15.5007 2.31147C15.7454 2.67075 15.8592 3.0255 15.9246 3.2843C15.9799 3.50334 16.0228 3.75249 16.0577 3.9557L16.1993 4.77635L16.2021 4.77788C16.2369 4.79712 16.2715 4.81659 16.306 4.8363L16.3086 4.83774L17.2455 4.49865C17.4356 4.42978 17.6693 4.34509 17.8835 4.28543C18.1371 4.2148 18.4954 4.13889 18.9216 4.17026C19.4614 4.20998 19.9803 4.39497 20.4235 4.70563C20.7734 4.95095 21.0029 5.23636 21.1546 5.4515C21.2829 5.63326 21.4103 5.84671 21.514 6.02029L22.0158 6.86003C22.1256 7.04345 22.2594 7.26713 22.3627 7.47527C22.4843 7.7203 22.6328 8.07474 22.6777 8.52067C22.7341 9.08222 22.6311 9.64831 22.3803 10.1539C22.1811 10.5554 21.9171 10.8347 21.7169 11.0212C21.5469 11.1795 21.3428 11.3417 21.1755 11.4746L20.5 12L21.1755 12.5254C21.3428 12.6584 21.5469 12.8205 21.7169 12.9789C21.9171 13.1653 22.1811 13.4446 22.3802 13.8461C22.631 14.3517 22.7341 14.9178 22.6776 15.4794C22.6328 15.9253 22.4842 16.2797 22.3626 16.5248C22.2593 16.7329 22.1255 16.9566 22.0158 17.14L21.5138 17.9799C21.4102 18.1535 21.2828 18.3668 21.1546 18.5485C21.0028 18.7637 20.7734 19.0491 20.4234 19.2944C19.9803 19.6051 19.4613 19.7901 18.9216 19.8298C18.4954 19.8612 18.1371 19.7852 17.8835 19.7146C17.6692 19.6549 17.4355 19.5703 17.2454 19.5014L16.3085 19.1623L16.306 19.1638C16.2715 19.1835 16.2369 19.2029 16.2021 19.2222L16.1993 19.2237L16.0577 20.0443C16.0228 20.2475 15.9799 20.4967 15.9246 20.7157C15.8592 20.9745 15.7454 21.3293 15.5007 21.6886C15.1915 22.1427 14.7635 22.5032 14.2634 22.7307C13.8678 22.9107 13.4989 22.9626 13.2327 22.983C13.0074 23.0002 12.7546 23.0001 12.5484 23H11.4516C11.2454 23.0001 10.9925 23.0002 10.7673 22.983C10.5011 22.9626 10.1322 22.9107 9.73655 22.7307C9.23648 22.5032 8.80849 22.1427 8.49926 21.6886C8.25461 21.3293 8.14077 20.9745 8.07542 20.7157C8.02011 20.4967 7.97723 20.2475 7.94225 20.0443L7.80068 19.2237L7.79791 19.2222C7.7631 19.2029 7.72845 19.1835 7.69396 19.1637L7.69142 19.1623L6.75458 19.5014C6.5645 19.5702 6.33078 19.6549 6.11651 19.7146C5.86288 19.7852 5.50463 19.8611 5.07841 19.8298C4.53866 19.7901 4.01971 19.6051 3.57654 19.2944C3.2266 19.0491 2.99714 18.7637 2.84539 18.5485C2.71718 18.3668 2.58974 18.1534 2.4861 17.9798L1.98418 17.14C1.87447 16.9566 1.74067 16.7329 1.63737 16.5248C1.51575 16.2797 1.36719 15.9253 1.32235 15.4794C1.26588 14.9178 1.36897 14.3517 1.61976 13.8461C1.81892 13.4446 2.08289 13.1653 2.28308 12.9789C2.45312 12.8205 2.65717 12.6584 2.82449 12.5254L3.47844 12.0054V11.9947L2.82445 11.4746C2.65712 11.3417 2.45308 11.1795 2.28304 11.0212C2.08285 10.8347 1.81888 10.5554 1.61972 10.1539C1.36893 9.64832 1.26584 9.08224 1.3223 8.52069C1.36714 8.07476 1.51571 7.72032 1.63732 7.47528C1.74062 7.26715 1.87443 7.04347 1.98414 6.86005L2.48605 6.02026C2.58969 5.84669 2.71714 5.63326 2.84534 5.45151C2.9971 5.23637 3.22655 4.95096 3.5765 4.70565C4.01966 4.39498 4.53862 4.20999 5.07837 4.17027C5.50458 4.1389 5.86284 4.21481 6.11646 4.28544C6.33072 4.34511 6.56444 4.4298 6.75451 4.49867L7.69141 4.83775L7.69394 4.8363C7.72844 4.8166 7.7631 4.79712 7.79791 4.77788L7.80068 4.77635L7.94225 3.95571C7.97723 3.7525 8.02011 3.50334 8.07542 3.2843C8.14077 3.0255 8.25461 2.67075 8.49926 2.31147C8.80849 1.85736 9.23648 1.49689 9.73655 1.26937C10.1322 1.08936 10.5011 1.03749 10.7673 1.01709ZM14.0938 4.3363C14.011 3.85634 13.9696 3.61637 13.8476 3.43717C13.7445 3.2858 13.6019 3.16564 13.4352 3.0898C13.2378 3.00002 12.9943 3.00002 12.5073 3.00002H11.4927C11.0057 3.00002 10.7621 3.00002 10.5648 3.0898C10.3981 3.16564 10.2555 3.2858 10.1524 3.43717C10.0304 3.61637 9.98895 3.85634 9.90615 4.3363L9.75012 5.24064C9.69445 5.56333 9.66662 5.72467 9.60765 5.84869C9.54975 5.97047 9.50241 6.03703 9.40636 6.13166C9.30853 6.22804 9.12753 6.3281 8.76554 6.52822C8.73884 6.54298 8.71227 6.55791 8.68582 6.57302C8.33956 6.77078 8.16643 6.86966 8.03785 6.90314C7.91158 6.93602 7.83293 6.94279 7.70289 6.93196C7.57049 6.92094 7.42216 6.86726 7.12551 6.7599L6.11194 6.39308C5.66271 6.2305 5.43809 6.14921 5.22515 6.16488C5.04524 6.17811 4.87225 6.23978 4.72453 6.34333C4.5497 6.46589 4.42715 6.67094 4.18206 7.08103L3.72269 7.84965C3.46394 8.2826 3.33456 8.49907 3.31227 8.72078C3.29345 8.90796 3.32781 9.09665 3.41141 9.26519C3.51042 9.4648 3.7078 9.62177 4.10256 9.9357L4.82745 10.5122C5.07927 10.7124 5.20518 10.8126 5.28411 10.9199C5.36944 11.036 5.40583 11.1114 5.44354 11.2504C5.47844 11.379 5.47844 11.586 5.47844 12C5.47844 12.414 5.47844 12.621 5.44354 12.7497C5.40582 12.8887 5.36944 12.9641 5.28413 13.0801C5.20518 13.1875 5.07927 13.2876 4.82743 13.4879L4.10261 14.0643C3.70785 14.3783 3.51047 14.5352 3.41145 14.7349C3.32785 14.9034 3.29349 15.0921 3.31231 15.2793C3.33461 15.501 3.46398 15.7174 3.72273 16.1504L4.1821 16.919C4.4272 17.3291 4.54974 17.5342 4.72457 17.6567C4.8723 17.7603 5.04528 17.8219 5.2252 17.8352C5.43813 17.8508 5.66275 17.7695 6.11199 17.607L7.12553 17.2402C7.42216 17.1328 7.5705 17.0791 7.7029 17.0681C7.83294 17.0573 7.91159 17.064 8.03786 17.0969C8.16644 17.1304 8.33956 17.2293 8.68582 17.427C8.71228 17.4421 8.73885 17.4571 8.76554 17.4718C9.12753 17.6719 9.30853 17.772 9.40635 17.8684C9.50241 17.963 9.54975 18.0296 9.60765 18.1514C9.66662 18.2754 9.69445 18.4367 9.75012 18.7594L9.90615 19.6637C9.98895 20.1437 10.0304 20.3837 10.1524 20.5629C10.2555 20.7142 10.3981 20.8344 10.5648 20.9102C10.7621 21 11.0057 21 11.4927 21H12.5073C12.9943 21 13.2378 21 13.4352 20.9102C13.6019 20.8344 13.7445 20.7142 13.8476 20.5629C13.9696 20.3837 14.011 20.1437 14.0938 19.6637L14.2499 18.7594C14.3055 18.4367 14.3334 18.2754 14.3923 18.1514C14.4502 18.0296 14.4976 17.963 14.5936 17.8684C14.6915 17.772 14.8725 17.6719 15.2344 17.4718C15.2611 17.4571 15.2877 17.4421 15.3141 17.427C15.6604 17.2293 15.8335 17.1304 15.9621 17.0969C16.0884 17.064 16.167 17.0573 16.2971 17.0681C16.4295 17.0791 16.5778 17.1328 16.8744 17.2402L17.888 17.607C18.3372 17.7696 18.5619 17.8509 18.7748 17.8352C18.9547 17.8219 19.1277 17.7603 19.2754 17.6567C19.4502 17.5342 19.5728 17.3291 19.8179 16.919L20.2773 16.1504C20.536 15.7175 20.6654 15.501 20.6877 15.2793C20.7065 15.0921 20.6721 14.9034 20.5885 14.7349C20.4895 14.5353 20.2921 14.3783 19.8974 14.0643L19.1726 13.4879C18.9207 13.2876 18.7948 13.1875 18.7159 13.0801C18.6306 12.9641 18.5942 12.8887 18.5564 12.7497C18.5215 12.6211 18.5215 12.414 18.5215 12C18.5215 11.586 18.5215 11.379 18.5564 11.2504C18.5942 11.1114 18.6306 11.036 18.7159 10.9199C18.7948 10.8126 18.9207 10.7124 19.1725 10.5122L19.8974 9.9357C20.2922 9.62176 20.4896 9.46479 20.5886 9.26517C20.6722 9.09664 20.7065 8.90795 20.6877 8.72076C20.6654 8.49906 20.5361 8.28259 20.2773 7.84964L19.8179 7.08102C19.5728 6.67093 19.4503 6.46588 19.2755 6.34332C19.1277 6.23977 18.9548 6.1781 18.7748 6.16486C18.5619 6.14919 18.3373 6.23048 17.888 6.39307L16.8745 6.75989C16.5778 6.86725 16.4295 6.92093 16.2971 6.93195C16.167 6.94278 16.0884 6.93601 15.9621 6.90313C15.8335 6.86965 15.6604 6.77077 15.3142 6.57302C15.2877 6.55791 15.2611 6.54298 15.2345 6.52822C14.8725 6.3281 14.6915 6.22804 14.5936 6.13166C14.4976 6.03703 14.4502 5.97047 14.3923 5.84869C14.3334 5.72467 14.3055 5.56332 14.2499 5.24064L14.0938 4.3363Z"/>
					</svg>
				</div>
				<div class="info">
					<a href="#" id="feedback" onclick="load(this, 'feedback_loader.php')"><?php echo $translation["FeedBack"]; ?></a>
					<a href="logout"><?php echo $translation["SignOut"]; ?></a>
					<br>
					<!-- CHANGE THIS PART TO ONCLICK EVENT TO LOAD THE PAGE IN MESSAGES PANEL.
						DON'T FORGET TO ADD A PROPER PAGE IN VIEWS FOLDER. -->
					<a href="dataprotection"><?php echo $translation["DataSecurity"]; ?></a>
					<a href="impressum" target="_blank"><?php echo $translation["Impressum"]; ?></a>
				</div>

			</div>


			<div class="main">
				<div></div>
			<div class="messages">
				<!-- THE VIEW WILL BE LOADED HERE... -->
			</div>

			<div class="input-container">
				<div class="input">

					<div class="input-controlbar">
						<?php
							if(isset($env) ? array_key_exists("MODEL_SELECTOR_ACTIVATION", $env) && $env["MODEL_SELECTOR_ACTIVATION"] === "true" : strtolower(getenv("MODEL_SELECTOR_ACTIVATION")) === "true"){
								echo
									'<select id="model-selector" onchange="OnDropdownModelSelection()">
										<option value="gpt-4o">OpenAI GPT-4o</option>
										<option value="meta-llama-3.1-8b-instruct">meta-llama-3.1-8b-instruct</option>
										<option value="meta-llama-3.1-70b-instruct">meta-llama-3.1-70b-instruct</option>
										<option value="llama-3-sauerkrautlm-70b-instruct">Llama 3 70B Sauerkraut</option>
										<option value="mixtral-8x7b-instruct">Mixtral-8x7b-instruct</option>
										<option value="qwen2-72b-instruct">Qwen 2 72B Instruct</option>
									</select>';
							}
							else{
								echo '<div></div>';
							}
						?>
						<div id="system-prompt-btn" onclick="ToggleSystemPrompt(true)">
							<svg viewBox="0 0 50 50"><path d="M 25 2 C 12.309295 2 2 12.309295 2 25 C 2 37.690705 12.309295 48 25 48 C 37.690705 48 48 37.690705 48 25 C 48 12.309295 37.690705 2 25 2 z M 25 4 C 36.609824 4 46 13.390176 46 25 C 46 36.609824 36.609824 46 25 46 C 13.390176 46 4 36.609824 4 25 C 4 13.390176 13.390176 4 25 4 z M 25 11 A 3 3 0 0 0 22 14 A 3 3 0 0 0 25 17 A 3 3 0 0 0 28 14 A 3 3 0 0 0 25 11 z M 21 21 L 21 23 L 22 23 L 23 23 L 23 36 L 22 36 L 21 36 L 21 38 L 22 38 L 23 38 L 27 38 L 28 38 L 29 38 L 29 36 L 28 36 L 27 36 L 27 21 L 26 21 L 22 21 L 21 21 z"/></svg>
						</div>
						<div id="delete-chat-btn" onclick="openDeletePanel()">	
								<svg  viewBox="0 0 50 50"><path d="M 21 2 C 19.354545 2 18 3.3545455 18 5 L 18 7 L 10.154297 7 A 1.0001 1.0001 0 0 0 9.984375 6.9863281 A 1.0001 1.0001 0 0 0 9.8398438 7 L 8 7 A 1.0001 1.0001 0 1 0 8 9 L 9 9 L 9 45 C 9 46.645455 10.354545 48 12 48 L 38 48 C 39.645455 48 41 46.645455 41 45 L 41 9 L 42 9 A 1.0001 1.0001 0 1 0 42 7 L 40.167969 7 A 1.0001 1.0001 0 0 0 39.841797 7 L 32 7 L 32 5 C 32 3.3545455 30.645455 2 29 2 L 21 2 z M 21 4 L 29 4 C 29.554545 4 30 4.4454545 30 5 L 30 7 L 20 7 L 20 5 C 20 4.4454545 20.445455 4 21 4 z M 11 9 L 18.832031 9 A 1.0001 1.0001 0 0 0 19.158203 9 L 30.832031 9 A 1.0001 1.0001 0 0 0 31.158203 9 L 39 9 L 39 45 C 39 45.554545 38.554545 46 38 46 L 12 46 C 11.445455 46 11 45.554545 11 45 L 11 9 z M 18.984375 13.986328 A 1.0001 1.0001 0 0 0 18 15 L 18 40 A 1.0001 1.0001 0 1 0 20 40 L 20 15 A 1.0001 1.0001 0 0 0 18.984375 13.986328 z M 24.984375 13.986328 A 1.0001 1.0001 0 0 0 24 15 L 24 40 A 1.0001 1.0001 0 1 0 26 40 L 26 15 A 1.0001 1.0001 0 0 0 24.984375 13.986328 z M 30.984375 13.986328 A 1.0001 1.0001 0 0 0 30 15 L 30 40 A 1.0001 1.0001 0 1 0 32 40 L 32 15 A 1.0001 1.0001 0 0 0 30.984375 13.986328 z"/></svg>
						</div>
					</div>	



					<div class="input-wrapper">
						<textarea class="input-field" type="text" placeholder="<?php echo $translation["InputField_Placeholder"]; ?>" oninput="resize(this),resize(document.getElementsByClassName('input-wrapper')[0])" onkeypress="handleKeydown(event)"></textarea>
					</div>
					<div class="input-send" onclick="OnSendClick()">
						<svg viewBox="2 2 21 21" width="80" height="80">
							<g class="send-button" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="rgba(35, 48, 176)">
								<path d="M12,2c5.5,0,10,4.5,10,10s-4.5,10-10,10S2,17.5,2,12S6.5,2,12,2z"  />
								<path id="input-send-icon" d="M16,12l-4-4l-4,4 M12,16V8"/>
							</g>
						</svg>
					</div>

					<div id="system-prompt-panel">
						<div>
							<div id="system-prompt-title">
								<p><b>System Prompt:</b></p>
								<div onclick="toggleSystemPromptInfo()">
									<svg viewBox="0 0 32 32" >
										<path  d="M16 0c-8.836 0-16 7.163-16 16s7.163 16 16 16c8.837 0 16.001-7.163 16.001-16s-7.163-16-16.001-16zM16 30.032c-7.72 0-14-6.312-14-14.032s6.28-14 14-14 14.001 6.28 14.001 14-6.281 14.032-14.001 14.032zM14.53 25.015h2.516v-2.539h-2.516zM15.97 6.985c-1.465 0-2.672 0.395-3.62 1.184s-1.409 2.37-1.386 3.68l0.037 0.073h2.295c0-0.781 0.261-1.904 0.781-2.308s1.152-0.604 1.893-0.604c0.854 0 1.511 0.232 1.971 0.696s0.689 1.127 0.689 1.989c0 0.725-0.17 1.343-0.512 1.855-0.343 0.512-0.916 1.245-1.721 2.198-0.831 0.749-1.344 1.351-1.538 1.806s-0.297 1.274-0.305 2.454h2.405c0-0.74 0.047-1.285 0.14-1.636s0.36-0.744 0.799-1.184c0.945-0.911 1.703-1.802 2.277-2.674 0.573-0.87 0.86-1.831 0.86-2.881 0-1.465-0.443-2.607-1.331-3.424s-2.134-1.226-3.736-1.226z"></path>
									</svg>
								</div>
								
								<div id="system-prompt-editBar">
									<div id="system-prompt-editButton" onclick="toggleSystemPromptEdit()">
										<svg viewBox="0 0 383.26 383.54">
											<path class="button-path-fill-color" class="cls-1" d="M35.18,383.54c-2.47-.74-4.98-1.39-7.41-2.24C10.88,375.35.02,360.14.02,342.26,0,262.41-.01,182.56.02,102.71c.01-22.87,18.22-41.08,41.06-41.1,54.51-.04,109.02-.02,163.53-.01,7.6,0,12.5,4.47,12.45,11.32-.05,6.78-4.97,11.12-12.68,11.12-53.51,0-107.03,0-160.54,0-13.93,0-21.41,7.52-21.41,21.53,0,77.98,0,155.96,0,233.94,0,14.01,7.49,21.57,21.39,21.57,78.09,0,156.17,0,234.26,0,13.72,0,21.33-7.58,21.33-21.25,0-53.77,0-107.55,0-161.32,0-9.29,8.5-14.96,16.33-10.89,4.27,2.22,6.14,5.88,6.13,10.71-.05,23.46-.02,46.91-.02,70.37,0,30.57-.39,61.14.13,91.7.39,23.45-16.96,40.26-34.38,42.57-.33.04-.62.38-.93.57H35.18Z"/>
											<path class="button-path-fill-color" class="cls-1" d="M383.26,52.61c-2.18,8.17-7.82,13.85-13.58,19.6-53.49,53.39-106.93,106.83-160.27,160.36-3.79,3.8-7.92,6.11-13.13,7.1-16.11,3.07-32.19,6.36-48.28,9.54-8.47,1.67-15.37-5.3-13.68-13.78,3.41-17.07,6.71-34.16,10.34-51.18.59-2.75,2.13-5.71,4.11-7.7,55.98-56.19,112.05-112.29,168.18-168.33,11.1-11.08,26.89-10.9,38.07.1,5.15,5.07,10.12,10.35,15.38,15.3,5.79,5.45,10.91,11.26,12.86,19.23v9.73ZM303.37,52.69c-.88,1.24-1.6,2.61-2.65,3.66-43.28,43.35-86.57,86.69-129.96,129.93-3.05,3.04-4.98,6.16-5.61,10.47-.94,6.39-2.46,12.69-3.73,19.04-.49,2.47-.96,4.94-1.54,7.92,10.46-2.07,20.11-3.86,29.7-5.97,2.02-.44,4.16-1.67,5.63-3.14,44.17-44.05,88.26-88.17,132.36-132.29.94-.94,1.84-1.92,2.64-2.77-8.82-8.82-17.37-17.37-26.84-26.84ZM346.95,63.16c4.05-4.14,8.05-8.24,12.06-12.32,1.95-1.98,2.47-4,.31-6.16-6.86-6.87-13.73-13.74-20.6-20.6-1.66-1.65-3.57-1.99-5.29-.31-4.44,4.32-8.75,8.77-12.91,12.97,8.82,8.82,17.43,17.42,26.43,26.42Z"/>
										</svg>
									</div>
									<div id="system-prompt-edit-control">
										<div id="system-prompt-confrimBtn" onclick="confirmSystemPromptEdit()">
											<svg  class="button-path-fill-color" viewBox="0 0 50 50" width="100px" height="100px">
												<path class="button-path-fill-color" d="M 41.9375 8.625 C 41.273438 8.648438 40.664063 9 40.3125 9.5625 L 21.5 38.34375 L 9.3125 27.8125 C 8.789063 27.269531 8.003906 27.066406 7.28125 27.292969 C 6.5625 27.515625 6.027344 28.125 5.902344 28.867188 C 5.777344 29.613281 6.078125 30.363281 6.6875 30.8125 L 20.625 42.875 C 21.0625 43.246094 21.640625 43.410156 22.207031 43.328125 C 22.777344 43.242188 23.28125 42.917969 23.59375 42.4375 L 43.6875 11.75 C 44.117188 11.121094 44.152344 10.308594 43.78125 9.644531 C 43.410156 8.984375 42.695313 8.589844 41.9375 8.625 Z"/>
											</svg>
										</div>
										<div id="system-prompt-abortBtn" onclick="abortSystemPromptEdit()" >
											<svg viewBox="0 0 100 100">
												<path class="button-path-fill-color" d="M 19.52 19.52 a 6.4 6.4 90 0 1 9.0496 0 L 51.2 42.1504 L 73.8304 19.52 a 6.4 6.4 90 0 1 9.0496 9.0496 L 60.2496 51.2 L 82.88 73.8304 a 6.4 6.4 90 0 1 -9.0496 9.0496 L 51.2 60.2496 L 28.5696 82.88 a 6.4 6.4 90 0 1 -9.0496 -9.0496 L 42.1504 51.2 L 19.52 28.5696 a 6.4 6.4 90 0 1 0 -9.0496 z"/>
											</svg>
										</div>
									</div>

								</div>
								

							</div>
				
							<p id="system-prompt-info" style="display: none">
								<?php echo $translation["SystemPromptInfo"]; ?> 
							</p>
						</div>


						<p >
							<span>&Prime;</span>
							<span contenteditable="true" id="system-prompt" oninput="calculateSystemPromptMaxHeight()"></span>
							<span>&rdquo;</span>
						</p>
					</div>
					
				</div>
				<div class="betaMessage">
					<?php echo $translation["BetaMessage"]; ?>
				</div>
			</div>


			<div class="userpost-container">
				<div class="userpost">
					<div class="userpost-wrapper">
						<textarea class="userpost-field" type="text" placeholder="<?php echo $translation["Feedback_Placeholder"]; ?>" oninput="resize(this)" onkeypress="handleKeydownUserPost(event)"></textarea>
					</div>
					<div class="userpost-send" onclick="send_feedback()">
						<svg viewBox="2 2 21 21" width="80" height="80">
							<g class="send-button" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M12,2c5.5,0,10,4.5,10,10s-4.5,10-10,10S2,17.5,2,12S6.5,2,12,2z"  />
								<path d="M16,12l-4-4l-4,4 M12,16V8"/>
							</g>
						</svg>
					</div>
				</div>
				<div class="betaMessage">
					<?php echo $translation["BetaMessage"]; ?>
				</div>
			</div>

			<template id="message">
				<div class="message">
					<div class="message-content">
						<div class="message-icon">
							<?php echo $translation["AI_Icon"]; ?>
						</div>
						<div class="message-text">
							Lorem ipsum dolor sit amet consectetur, adipisicing elit. Quos incidunt, quidem soluta excepturi, ullam enim tempora.
						</div>
						<div class="message-copypanel " >
							<div class="message-copyButton">
								<svg viewBox="0 0 24 24">
									<path d="M 17.01 14.91 H 8.47 c -0.42 0 -0.7 -0.35 -0.7 -0.7 V 2.8 c 0 -0.42 0.35 -0.7 0.7 -0.7 H 14.7 l 3.01 3.01 v 9.03 C 17.71 14.56 17.43 14.91 17.01 14.91 z M 8.47 17.01 h 8.47 c 1.54 0 2.8 -1.26 2.8 -2.8 V 5.11 c 0 -0.56 -0.21 -1.12 -0.63 -1.47 l -3.01 -3.01 C 15.82 0.21 15.26 0 14.7 0 h -6.23 c -1.54 0 -2.8 1.26 -2.8 2.8 v 11.34 C 5.67 15.75 6.93 17.01 8.47 17.01 z M 2.8 5.67 c -1.54 0 -2.8 1.26 -2.8 2.8 v 11.34 c 0 1.54 1.26 2.8 2.8 2.8 h 8.47 c 1.54 0 2.8 -1.26 2.8 -2.8 v -1.4 h -2.1 v 1.4 c 0 0.42 -0.35 0.7 -0.7 0.7 H 2.8 c -0.42 0 -0.7 -0.35 -0.7 -0.7 V 8.47 c 0 -0.42 0.35 -0.7 0.7 -0.7 h 1.4 v -2.1 H 2.8 z" />
								</svg>
								<span class="tooltiptext">
									<?php echo $translation["copyTooTip"]; ?>
								</span>
							</div>
						</div>
					</div>
				</div>
			</template>
		</div>

		<?php
			include( VIEWS_PATH . 'guidelines.php');
		?>
	</body>
</html>
<script>

	visualViewport.addEventListener("resize", update);
	visualViewport.addEventListener("scroll", update);
	addEventListener("scroll", update);
	addEventListener("load", update);

	let abortCtrl = new AbortController();
	let isReceivingData = false;

	const sendicon = document.querySelector('#input-send-icon');
	const startIcon = 'M16,12l-4-4l-4,4 M12,16V8';
	const stopIcon = 'M9,9h6v6H9V9z'

	CheckModals();

	//Load chat by default when the page is loaded.
	window.addEventListener('DOMContentLoaded', (event) => {
		const chatBtn = document.querySelector("#chatMenuButton");
		load(chatBtn ,'chat.php');
    });
	window.addEventListener('DOMContentLoaded', cleanupStoredLogs());




	document.querySelector('.messages').addEventListener('scroll', function() {
    	isScrolling = true;
	});
	document.querySelector('.messages').addEventListener('scroll', function() {
		setTimeout(function() {
			isScrolling = false;
		}, 800); // Adjust the threshold
	});



	let activeModel = "gpt-4o";
	let streamAPI = "";
	window.addEventListener('DOMContentLoaded', (event) => {
		if(localStorage.getItem("definedModel")){
			SwitchModel(localStorage.getItem("definedModel"));
		}
		else{
			SwitchModel("gpt-4o");
		}
		document.getElementById("model-selector").value = activeModel;
    });

	function OnDropdownModelSelection(){
		const dropdown = document.getElementById("model-selector");
		SwitchModel(dropdown.value);
		localStorage.setItem("definedModel", dropdown.value);
	}

	function SwitchModel(model){
		activeModel = model;
		switch(activeModel){
			case('gpt-4o'):
				streamAPI = "api/stream-api";
				break;

			case('meta-llama-3.1-8b-instruct'):
			case('meta-llama-3.1-70b-instruct'):
			case('llama-3-sauerkrautlm-70b-instruct'):
			case('mixtral-8x7b-instruct'):
			case('qwen2-72b-instruct'):
				streamAPI = 'api/GWDG-api';
				break;
		}
	}


	//#region HANDLE MESSAGES...
	//----------------------------------------------------------------------------------------//
	function handleKeydown(event){
		if(isReceivingData) return;
		if(event.key == "Enter" && !event.shiftKey){
			event.preventDefault();
			request();
		}
	}

	function handleKeydownUserPost(event){
		if(isReceivingData) return;
		if(event.key == "Enter" && !event.shiftKey){
			event.preventDefault();
			send_feedback();
		}
	}

	function OnSendClick(){
		if(!isReceivingData){
			request();
		} else{
			abortCtrl.abort();
		}
	}

	async function request(){
		const messagesElement = document.querySelector(".messages");
		const messageTemplate = document.querySelector('#message');
		const inputField = document.querySelector(".input-field");
		const inputWrapper = document.querySelector(".input-wrapper");

		if(inputField.value.trim() == ""){
			return;
		}

		InitializeMessage();

		//handle input-send button.
		isReceivingData = true;
		sendicon.setAttribute('d', stopIcon);
		sendicon.style.color = "red";

		let message = {};
		message.role = "user";
		//prevent html input to be rendered as html elements.
		message.content = escapeHTML(inputField.value.trim());
		inputField.value = "";
		addMessage(message);
		resize(inputField);
		resize(inputWrapper);

		document.querySelector('.limitations')?.remove();

		const requestObject = {};
		requestObject.model = activeModel;
		requestObject.stream = true;
		requestObject.messages = [];
		const messageElements = messagesElement.querySelectorAll(".message");
		messageElements.forEach(messageElement => {
			let messageObject = {};
			messageObject.role = messageElement.dataset.role;
			messageObject.content = messageElement.querySelector(".message-text").textContent;
			requestObject.messages.push(messageObject);
		})

		

		postData(streamAPI, requestObject)
		.then(stream => processStream(stream))
		.catch(error => console.error('Error:', error));
	}

	async function postData(url = '', data = {}) {
		try{
			abortCtrl = new AbortController();

			const response = await fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify(data),
				signal: abortCtrl.signal
			});
			return response.body;

		} catch(error){
			console.log('Fetching Aborted $error');
		}
	}

	async function processStream(stream) {
		// if fetching is aborted before it's complete the stream will be empty.
		// stream should be checked to avoid throwing error.
		if (!stream) {
			isReceivingData = false;
			sendicon.setAttribute('d', startIcon)

			return;
		}

		const reader = stream.getReader();

		const messagesElement = document.querySelector(".messages");
		const messageTemplate = document.querySelector('#message');
		const messageElement = messageTemplate.content.cloneNode(true);

		messageElement.querySelector(".message-text").innerHTML = "";
		messageElement.querySelector(".message").dataset.role = "assistant";
		messagesElement.appendChild(messageElement);

		const messageText = messageElement.querySelector(".message-text");

		// Throws error if the read operation on the response body stream is aborted while the reader.read() operation is still active.
		// Try Catch block will handle the error.
		let rawMsg = "";
		
		try {
			//NOTE: Niklas Wode
			let incompleteSlice = "";

			while (true) {
				const { done, value } = await reader.read();

				if (done) {
					console.log('Stream closed.');

					isReceivingData = false;
					sendicon.setAttribute('d', startIcon)

					const msg = document.querySelector(".message:last-child").querySelector(".message-text");
					msg.setAttribute('rawContent', rawMsg);
					saveMessagesToLocalStorage();
					ShowCopyButton();
					break;
				}

				//Parsing error from json "Chunks" corrected
				let decodedData = new TextDecoder().decode(value);
				decodedData = incompleteSlice + decodedData;

				const delimiter = '\n\n';
				const delimiterPosition = decodedData.lastIndexOf(delimiter);
				if (delimiterPosition > -1) {
					incompleteSlice = decodedData.substring(delimiterPosition + delimiter.length);
					decodedData = decodedData.substring(0,delimiterPosition + delimiter.length);
				} else {
					incompleteSlice = decodedData;
					continue;
				}
				// end of inserted code

				let chunks = decodedData.split("data: ");
				chunks.forEach((chunk, index) => {

					if(!isJSON(chunk)){
						return;
					}
					if(chunk.indexOf('finish_reason":"stop"') > 0) return false;
					if(chunk.indexOf('DONE') > 0) return false;
					if(chunk.indexOf('role') > 0) return false;
					if(chunk.length == 0) return false;
					
					rawMsg += JSON.parse(chunk)["choices"][0]["delta"].content;
					document.querySelector(".message:last-child").querySelector(".message-text").innerHTML =  FormatChunk(JSON.parse(chunk)["choices"][0]["delta"].content);

				})

				FormatMathFormulas();
				hljs.highlightAll();
				scrollToLast();
			}
		} catch (error) {
			// Check if the error is due to aborting the request
			if (error.name == 'AbortError') {
				console.log('Fetch aborted while reading response body stream.');
				const msg = document.querySelector(".message:last-child").querySelector(".message-text");
				msg.setAttribute('rawContent', rawMsg);

			} else {
				console.error('Error:', error);
			}
			isReceivingData = false;
			sendicon.setAttribute('d', startIcon);
			ShowCopyButton();
			saveMessagesToLocalStorage();

		}
	}

	//MESSAGE SENT FROM STREAM MANAGER...
	function addMessage(message){
		const messagesElement = document.querySelector(".messages");
		const messageTemplate = document.querySelector('#message');
		const inputField = document.querySelector(".input-field");
		const messageElement = messageTemplate.content.cloneNode(true);

		messageElement.querySelector(".message-text").innerHTML = message.content;
		messageElement.querySelector(".message").dataset.role = message.role;

		if(message.role == "assistant"){
			messageElement.querySelector(".message-icon").textContent = "AI";
		}else{
			messageElement.querySelector(".message-icon").textContent = '<?= htmlspecialchars($_SESSION['username']) ?>';
			messageElement.querySelector(".message").classList.add("me");
		}

		messagesElement.appendChild(messageElement);

		scrollToLast(true);
		return messageElement;
	}

	//#endregion



	//#region Copy Button
	//----------------------------------------------------------------------------------------//
	function ShowCopyButton() {
		const copyPanel = document.querySelector(".message:last-child").querySelector(".message-copypanel");
		if (copyPanel !== null) {
			copyPanel.style.display = "flex";
			const copyButton = copyPanel.querySelector(".message-copyButton");
			copyButton.dataset.clicked = "false"; // Initialize the clicked state for this button
			AddEventListenersToCopyButton(copyButton);
		}
		AddCodeCopyButton();
	}

	function AddCodeCopyButton(){
		const lastMsg = document.querySelector(".message:last-child").querySelector(".message-text");
		const codes = lastMsg.querySelectorAll('pre');

		for (let i = 0; i < codes.length; i++) {
			const code = codes[i];
			if (!code.querySelector('.code-copypanel')) {
				const copyPanel = document.createElement('div');
				copyPanel.classList.add('code-copypanel');

				const copyButton = document.createElement('div');
				copyButton.classList.add('code-copyButton', 'ignoreTextCopy');
				copyButton.innerHTML = `
					<svg viewBox="0 0 24 24">
						<path d="M 17.01 14.91 H 8.47 c -0.42 0 -0.7 -0.35 -0.7 -0.7 V 2.8 c 0 -0.42 0.35 -0.7 0.7 -0.7 H 14.7 l 3.01 3.01 v 9.03 C 17.71 14.56 17.43 14.91 17.01 14.91 z M 8.47 17.01 h 8.47 c 1.54 0 2.8 -1.26 2.8 -2.8 V 5.11 c 0 -0.56 -0.21 -1.12 -0.63 -1.47 l -3.01 -3.01 C 15.82 0.21 15.26 0 14.7 0 h -6.23 c -1.54 0 -2.8 1.26 -2.8 2.8 v 11.34 C 5.67 15.75 6.93 17.01 8.47 17.01 z M 2.8 5.67 c -1.54 0 -2.8 1.26 -2.8 2.8 v 11.34 c 0 1.54 1.26 2.8 2.8 2.8 h 8.47 c 1.54 0 2.8 -1.26 2.8 -2.8 v -1.4 h -2.1 v 1.4 c 0 0.42 -0.35 0.7 -0.7 0.7 H 2.8 c -0.42 0 -0.7 -0.35 -0.7 -0.7 V 8.47 c 0 -0.42 0.35 -0.7 0.7 -0.7 h 1.4 v -2.1 H 2.8 z" />
					</svg>
					<span class="tooltiptext">Copy</span>`;

				copyButton.dataset.clicked = "false";
				AddEventListenersToCopyButton(copyButton);
				copyPanel.appendChild(copyButton);
				code.appendChild(copyPanel);
			}
		}
	}


	//Event listeners help control the tooltip box transitions
	function AddEventListenersToCopyButton(TargetButton){
		let activationTimer;
		TargetButton.addEventListener("mouseenter", function() {
			activationTimer = setTimeout(function() {
				TargetButton.querySelector(".tooltiptext").classList.add("active");
			}, 1000);
		});

		TargetButton.addEventListener("mouseleave", function () {
			clearTimeout(activationTimer);
			if (TargetButton.dataset.clicked !== "true") { // Check the clicked state of this button
				TargetButton.querySelector(".tooltiptext").classList.remove("active");
			}
		});

		TargetButton.addEventListener("mousedown", function () {
			TargetButton.dataset.clicked = "true"; // Set the clicked state of this button to true
			CopyContentToClipboard(TargetButton);
		});

		TargetButton.addEventListener("mouseup", function() {
			CopyBtnRelease(TargetButton);
		});
	}

	// Copies content of the message box without the css attributes
	function CopyContentToClipboard(target) {

		const parentElement = target.parentElement.previousElementSibling;
		const clone = parentElement.cloneNode(true);

		// Remove elements with the "ignoreTextCopy" class from the clone (copy buttons for instance)
		clone.querySelectorAll('.ignoreTextCopy').forEach(element => {
			element.parentNode.removeChild(element);
		});

		    // Convert tables to plain text
			clone.querySelectorAll('table').forEach(table => {
				const plainTextTable = tableToPlainText(table);
				const textNode = document.createTextNode(plainTextTable);
				table.parentNode.replaceChild(textNode, table);
			});

			// Remove code language from copy content
			if(clone.tagName === "CODE") {
				const firstChildNode = clone.firstChild;
				const lastChildNode = clone.lastChild;

				if(firstChildNode && firstChildNode.nodeName === "#text") {
					// Remove the first node, as it contains the code language
					// It is possible that the code language is followed by actual code, so check for that
					if(firstChildNode.textContent.includes("\n")) {
						firstChildNode.textContent = firstChildNode.textContent.split("\n")[1];
					} else {
						clone.removeChild(firstChildNode);
					}
				}

				if (lastChildNode && lastChildNode.nodeName === "#text") {
					// In some cases, there might be markdown content at the end of the code block due to formatting errors
					const re = "\n?```"
					if(lastChildNode.textContent.match(re)) {
						lastChildNode.textContent = lastChildNode.textContent.replace("\n```", "");
					}
				}
			}


		// Get the text content of the modified clone
		const msgTxt = clone.textContent.trim();

		const trimmedMsg = msgTxt.trim();
		navigator.clipboard.writeText(trimmedMsg);

		target.style.opacity = "1";
		target.style.scale = "1.1";
		target.querySelector(".tooltiptext").classList.add("active");
		target.querySelector(".tooltiptext").innerHTML = '<?php echo $translation["copiedToolTip"]; ?>'
	}

	function CopyBtnRelease(target) {
		target.style.scale = "1";

		setTimeout(function () {
			target.style.opacity = "0.5";

			target.querySelector(".tooltiptext").classList.remove("active");
			target.querySelector(".tooltiptext").innerHTML = '<?php echo $translation["copyTooTip"]; ?>';
			target.dataset.clicked = "false"; // Reset the clicked state of this button
		}, 2000);
	}

	// Helper function to convert HTML table to plain text
	function tableToPlainText(table) {
		let plainText = '';
		table.querySelectorAll('tr').forEach(row => {
			row.querySelectorAll('th, td').forEach(cell => {
				plainText += cell.textContent.trim() + '\t';
			});
			plainText += '\n';
		});
		return plainText;
	}
	//#endregion

	//#region USER FEEDBACK
	//----------------------------------------------------------------------------------------//
	//save users feedback on server.
	//other users can up or downvote othes feedback
	async function send_feedback(){
		const messagesElement = document.querySelector(".messages");
		const inputField = document.querySelector(".userpost-field");

		if(inputField.value == ''){
			return;
		}

		let message = {};
		message.role = '<?= htmlspecialchars($_SESSION['username']) ?>';
		message.content = inputField.value.trim();

		// const feedback_send = "../private/app/php/feedback_send.php";
		const feedback_send = "api/feedback_send"
		const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

		fetch(feedback_send, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json', // Set content type to application/json
				'X-CSRF-TOKEN': csrfToken, // Include CSRF token in the request headers
			},
			body: JSON.stringify({message: message}),
		})
		.then(response => response.json())
		.then(data => {
			//UPDATE NEW TOKEN
			document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);

			if(data.success){
				load(document.querySelector("#feedback"), 'feedback_loader.php');
				inputField.value = "";
			}
		})
		.catch(error => console.error(error));
	}

	function SubmitVote(element, action) {
		if (localStorage.getItem(element.dataset.id)) {
			return;
		}

		const pureId = element.dataset.id.replace('.json', ''); // assuming all IDs end with '.json'
		const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
		const submit_vote = "api/submit_vote";

		fetch(submit_vote, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json', // Set content type to application/json
				'X-CSRF-TOKEN': csrfToken, // Include CSRF token in the request headers
			},
			body: JSON.stringify({ id: pureId, action: action }), // Send the action and CSRF token in the request body
		})
		.then(response => {
			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}
			return response.json();
		})
		.then(data => {

			document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);
			if(data.success){

				// Update the UI accordingly
				if (action === "upvote") {
					element.querySelector("span").textContent = data.content.up || 0; // Assuming 'data.up' contains the updated upvote count
				}
				if (action === "downvote") {
					element.querySelector("span").textContent = data.content.down || 0; // Assuming 'data.down' contains the updated downvote count
				}
			}
		})
		.catch(error => {
			console.error('Fetch error:', error);
		});
		localStorage.setItem(element.dataset.id, "true");

		voteHover();
	}

	async function voteHover(){
		let messages = document.querySelectorAll(".message");

		messages.forEach((message)=>{
			let voteButtons = message.querySelectorAll(".vote")

			voteButtons.forEach((voteButton)=>{
				if(localStorage.getItem(voteButton.dataset.id)){
					voteButton.classList.remove("vote-hover");
				}else{
					voteButton.classList.add("vote-hover");
				}
			})
		})
	}
	//#endregion
</script>
