<?php

/**
 * @filesource /include/settings.php
 * @version 0.2.2.release
 * @date 2026.01.26  
 * @author vladimir@tsurkanenko.ru
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
define('SHOW_CON_DETAILS', true);
define('SHOW_RADIO_ACTIVITY', true);

define('SHOW_REFLECTOR_ACTIVITY', true);
if (defined("SHOW_REFLECTOR_ACTIVITY") && SHOW_REFLECTOR_ACTIVITY) {
	define('REFLECTOR_ACTIVITY_LIMIT', 5);
}

define('SHOW_NET_ACTIVITY', true);
if (defined("SHOW_NET_ACTIVITY") && SHOW_NET_ACTIVITY) {
	define('NET_ACTIVITY_LIMIT', 5);
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
