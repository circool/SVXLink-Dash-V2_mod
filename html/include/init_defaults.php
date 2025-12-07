<?php
/** Инициализация основных переменных
 * SvxLink Dashboard
 * @file include/init_defaults.php
 * @author vladimir@tsurkanenko.ru
 * @version 0.1.6
 * @date 2025-12-07
 * @since 0.1.6
 * 
 */


// ENVIRONMENT
define('AUTHORISED', 'AUTHORISED');
define('UNAUTHORISED', 'UNAUTHORISED');

// INSTALLATION & AUTH
define('AUTH_FILE', '/etc/svxlink/dashboard/auth.ini');
define('INSTALL_PATH', '/install/setup_auth.php');
define('DASHBOARD_NAME', 'SvxLink Dashboard');
define('DASHBOARD_TITLE', 'SvxLink Dashboard by R2ADU');
define('DASHBOARD_VERSION', '0.1.6');
define('DASHBOARD_DESCRIPTION', 'SvxLink Dashboard v 0.1.6');
define('AUTHOR', 'vladimir@tsurkanenko.ru');

// SVXLINK
define("SERVICE_TITLE", "SvxLink");
define("SVXCONFPATH", "/etc/svxlink/");
define("SVXCONFIG", "svxlink.conf");
define("SVXLOGPATH", "/var/log/");
define("SVXLOGPREFIX", "svxlink");

// SESSION
define("SESSION_NAME", "SVX_DASHBOARD");
define("SESSION_LIFETIME", 0);
define("SESSION_PATH", "/");

// DASHBOARD
define("DASHBOARD_TIME_FORMAT", "d-m-Y"); // Time format for AJAX time 


// DEBUG: Уровни логирования: 1-Error, 2-Warning, 3-Info, 4-Debug, 5-Trace
define("DEBUG", true);
define("LOG_BOOTSTRAP", false);

define("DEBUG_VERBOSE", 3);
define("DEBUG_LOG_FILE", "/tmp/svxlink-debug.log");
define("DEBUG_LOG_TO_APACHE", false);

?>