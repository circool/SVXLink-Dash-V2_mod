<?php

/**
 * @filesource /include/settings.php
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.11
 * @version 0.4.6
 */


// Naming
define('DASHBOARD_VERSION', '0.4');
define('DASHBOARD_NAME', 'SvxLink Dashboard');
define('DASHBOARD_TITLE', 'SvxLink Dashboard by R2ADU');


// Session  
define('SESSION_NAME', 'SVXDASHBOARD');
define('SESSION_LIFETIME', 0);
define('SESSION_PATH', '/');
define("SESSION_ID", 'svxdashb');

// Log
define("SVXLOGPATH", '/var/log/');
define("SVXLOGPREFIX", "svxlink");
define('SVXLINK_LOG_PATH', SVXLOGPATH . SVXLOGPREFIX);

// SVXLINK
define("SERVICE_TITLE", 'SvxLink');
define("SVXCONFPATH", '/etc/svxlink/');
define("SVXCONFIG", 'svxlink.conf');

// Parts
define('SHOW_AUDIO_MONITOR', true);

define('SHOW_MACROS', true);

define('SHOW_AUTH', false);

define('SHOW_CON_DETAILS', true);

define('SHOW_RADIO_ACTIVITY', true);

define('SHOW_REFLECTOR_ACTIVITY', false);
if (defined("SHOW_REFLECTOR_ACTIVITY") && SHOW_REFLECTOR_ACTIVITY) {
	define('REFLECTOR_ACTIVITY_LIMIT', 5);
}

define('SHOW_NET_ACTIVITY', true);
if (defined("SHOW_NET_ACTIVITY") && SHOW_NET_ACTIVITY) {
	define('NET_ACTIVITY_LIMIT', 10);
}

define('SHOW_RF_ACTIVITY', true);
if (defined("SHOW_RF_ACTIVITY") && SHOW_RF_ACTIVITY) {
	define('RF_ACTIVITY_LIMIT', 5);
}

// WebSocket
define("WS_ENABLED", true);
if (WS_ENABLED) {
	define("WS_HOST", '0.0.0.0');
	define("WS_PORT", 8080);
	define("WS_PATH", '/ws');
}

// Cashe
define("USE_CACHE", true);
if (USE_CACHE) {
	// Время жизни кеша
	define("LOG_CACHE_TTL_MS", 1000);
}

// Auth
define("AUTH_FILE", '/etc/svxlink/dashboard/auth.ini');
define("AUTH_SETUP", 'install/setup_auth.php');

// AJAX Updates
define("UPDATE_INTERVAL", 2000);

// DEBUG
define("DEBUG", false);
if (defined("DEBUG")) {

	if (!defined("DEBUG_VERBOSE")) {
		define("DEBUG_VERBOSE", 4);
	}

	if (!defined("DEBUG_WEB_CONSOLE")) {
		define("DEBUG_WEB_CONSOLE", true);
	}
	if (!defined("DEBUG_LOG_CONSOLE")) {
		define("DEBUG_LOG_CONSOLE", false);
	}

	define('DEBUG_LOG_FILE', '/tmp/svxlink-debug.log');
	// Не логируем в журнал apache пока в сообщениях используется кириллица
	// define ('DEBUG_LOG_TO_APACHE', '/var/log/apache2/svxlink_development.error.log');
	if (!defined('LOG_LEVEL')) {
		define('LOG_LEVEL', DEBUG_VERBOSE);
	}
	
}