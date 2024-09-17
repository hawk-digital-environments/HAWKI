<?php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once BOOTSTRAP_PATH;
    require_once  LIBRARY_PATH . 'csrf.php';

    function setLanguage(){
        //LANGUAGE CHANGE...
        if(isset($_SESSION['language'])){
            $language = $_SESSION['language'];
        }
        else{
            //try to get cookie from last use
            if (isset($_COOKIE['lastLanguage_cookie']) && $_COOKIE['lastLanguage_cookie'] != '') {
                $language = $_COOKIE['lastLanguage_cookie'];
            }
            //If theres not cookie try env default language
            elseif((file_exists(ENV_FILE_PATH) && parse_ini_file(ENV_FILE_PATH)['DEFAULT_LANGUAGE'] != '')) {
                $env = parse_ini_file(ENV_FILE_PATH);
                $language = $env['DEFAULT_LANGUAGE'];
            }
            else{
                //hard code to german
                $languages = ['de_DE', 'en_US', 'es_ES', 'fr_FR', 'it_IT'];
                $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
                $matchingLang = substr($acceptLang, 0, 2);
                foreach ($languages as $lang) {
                    if (strpos($lang, $matchingLang) === 0) {
                        $defaultLanguage = $lang;
                        break;
                    }
                }
                if ($defaultLanguage === null) {
                    $defaultLanguage = 'de_DE';
                }
                $language = $defaultLanguage;
            }
        }
        $_SESSION['language'] = $language;
        $languageSanitized = preg_replace('~[^a-zA-Z_]~', '', $language);
        $langFile = file_get_contents(LANGUAGE_PATH . $languageSanitized . '.json');
        $translation = json_decode($langFile, true);

        $customLangFile = CUSTOM_LANGUAGE_PATH . $languageSanitized . '.json';

        if (file_exists ($customLangFile)) {

            $customTranslation = json_decode(file_get_contents($customLangFile), true);
            $translation = array_merge($translation, $customTranslation);

        }

        $_SESSION['translation'] = $translation;
    }



    // Check if the request is POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF Protection
        if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
            $csrf_token = generate_csrf_token();
            $response = array(
                'success' => false,
                'csrf_token' => $csrf_token
            );
            http_response_code(403);
            exit(json_encode($response));
        }

        // Check if the requested language is valid
        $languages = ['de_DE', 'en_US', 'es_ES', 'fr_FR', 'it_IT'];

        $jsonString = file_get_contents("php://input");

        $lang = json_decode($jsonString, true)['inputLang'];

        if (!in_array($lang, $languages)) {
            http_response_code(400);
            exit(json_encode(array('success' => false, 'error' => 'Invalid language')));
        }

        // Store the new language in session
        $_SESSION['language'] = $lang;
        $langFile = file_get_contents(RESOURCES_PATH . "language/{$lang}.json");
        $translation = json_decode($langFile, true);

        $customLangFile = CUSTOM_LANGUAGE_PATH . "{$lang}.json";

        if (file_exists ($customLangFile)) {

            $customTranslation = json_decode(file_get_contents($customLangFile), true);
            $translation = array_merge($translation, $customTranslation);

        }

        $_SESSION['translation'] = $translation;

        setcookie('lastLanguage_cookie', $_SESSION['language'], strtotime('2038-01-01'), '/', '', true, true);

        // Prepare the JSON response
        $response = array(
            'success' => true,
            'csrf_token' => generate_csrf_token()
        );

        // Return the JSON string
        exit(json_encode($response));
    }
