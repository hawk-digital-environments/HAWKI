<?php
    defined('PRIVATE_PATH') || define('PRIVATE_PATH', $_SERVER['DOCUMENT_ROOT']. '/private');
    defined('APPLICATION_PATH') || define('APPLICATION_PATH', PRIVATE_PATH . '/app');
    defined('BASE_URL') || define('BASE_URL', 'localhost:8000/');

    define('ENV_FILE_PATH', PRIVATE_PATH . '/.env');
    define('LIBRARY_PATH', APPLICATION_PATH . '/'. 'php/');
    define('RESOURCES_PATH', PRIVATE_PATH . '/' . 'resources/');

    define('PAGES_PATH', PRIVATE_PATH . '/pages');
    define('LOGIN_PAGE_PATH', PAGES_PATH . '/login.php');
    define('INTERFACE_PAGE_PATH', PAGES_PATH . '/interface.php');
    define('LOGOUT_PAGE_PATH', PAGES_PATH . '/logout.php');

    define('OIDC_LOGIN_PAGE_PATH', PAGES_PATH . '/oidc_login.php');
    define('OIDC_LOGOUT_PAGE_PATH', PAGES_PATH . '/oidc_logout.php');

    define('VIEWS_PATH', PRIVATE_PATH . '/' . 'views/' );
    define('LAGNUAGE_CONTROLLER_PATH', LIBRARY_PATH . 'language_controller.php');
    define('LANGUAGE_PATH', RESOURCES_PATH . 'language/');
    define('CUSTOM_PATH', PRIVATE_PATH . '/' . 'custom/');
    define('CUSTOM_LANGUAGE_PATH', CUSTOM_PATH . 'language/');

