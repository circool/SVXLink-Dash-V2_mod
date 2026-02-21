<?php

/**
 * @filesource /include/settings.php
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.20
 * @version 0.4.2
 */

// Dashboard Title & Name
define('DASHBOARD_VERSION', '1.0');
define('DASHBOARD_NAME', 'SvxLink Dashboard');
define('DASHBOARD_TITLE', 'SvxLink Dashboard by R2ADU');


/** 
 * Dashboard session - Do not change without fully understanding!  
 */ 
define('SESSION_NAME', 'SVXDASHBOARD');
define('SESSION_LIFETIME', 0);
define('SESSION_PATH', '/');
define("SESSION_ID", 'svxdashb');

// SVXLINK & LOG - Default svxlink settings. 
define("SVXLOGPATH", '/var/log/');
define("SVXLOGPREFIX", "svxlink");
define('SVXLINK_LOG_PATH', SVXLOGPATH . SVXLOGPREFIX);
define("SERVICE_TITLE", 'SvxLink');
define("SVXCONFPATH", '/etc/svxlink/');
define("SVXCONFIG", 'svxlink.conf');

// Cashing log search results. Strongle recomended stay TRUE!
define("USE_CACHE", true);
define("LOG_CACHE_TTL_MS", 1000);

/**
 * Default UI settings. Do not touch - use UI settings for customizing
 */ 

// Parts
if (!defined('SHOW_AUDIO_MONITOR')) define('SHOW_AUDIO_MONITOR', true);
if (!defined('SHOW_MACROS')) define('SHOW_MACROS', true);
if (!defined('SHOW_CON_DETAILS')) define('SHOW_CON_DETAILS', true);
if (!defined('SHOW_RADIO_ACTIVITY')) define('SHOW_RADIO_ACTIVITY', true);

if (!defined('SHOW_NET_ACTIVITY')) define('SHOW_NET_ACTIVITY', true);
if (defined("SHOW_NET_ACTIVITY") && SHOW_NET_ACTIVITY) {
	// Number of rows in the table
	if (!defined('NET_ACTIVITY_LIMIT')) define('NET_ACTIVITY_LIMIT', 10);
}

if (!defined('SHOW_RF_ACTIVITY')) define('SHOW_RF_ACTIVITY', true);
if (defined("SHOW_RF_ACTIVITY") && SHOW_RF_ACTIVITY) {
	// Number of rows in the table 
	if (!defined('RF_ACTIVITY_LIMIT')) define('RF_ACTIVITY_LIMIT', 5);
}

// WS transport for DOM updates (real-time)
if (!defined('WS_ENABLED')) define("WS_ENABLED", true);
if (WS_ENABLED) {
	define("WS_HOST", '0.0.0.0');
	define("WS_PORT", 8080);
	define("WS_PATH", '/ws');
}

// AJAX transport for DOM updates (periodic)
if (!defined('UPDATE_INTERVAL')) define("UPDATE_INTERVAL", 3000);




// Multilanguage support
if (!defined('LANGUAGE_SUPPORT')) define("LANGUAGE_SUPPORT", false);

// Auth
if (!defined('SHOW_AUTH')) define('SHOW_AUTH', true);
if (!defined('AUTH_FILE')) define("AUTH_FILE", '/etc/svxlink/dashboard/auth.ini');
if (!defined('AUTH_SETUP')) define("AUTH_SETUP", 'install/setup_auth.php');


// DEBUG
if (!defined('DEBUG')) define("DEBUG", false);
if (defined("DEBUG")) {
	if (!defined("DEBUG_VERBOSE")) define("DEBUG_VERBOSE", 2);
	if (!defined("DEBUG_WEB_CONSOLE")) define("DEBUG_WEB_CONSOLE", true);
	if (!defined("DEBUG_LOG_CONSOLE")) define("DEBUG_LOG_CONSOLE", false);
	if (!defined('DEBUG_LOG_FILE')) define('DEBUG_LOG_FILE', '/tmp/svxlink-debug.log');
	if (!defined('DEBUG_LOG_TO_APACHE'))define ('DEBUG_LOG_TO_APACHE', '/var/log/apache2/svxlink_dashboard.error.log');
	if (!defined('LOG_LEVEL')) define('LOG_LEVEL', DEBUG_VERBOSE);
}