<?php

/**
 * @filesource /include/exct/settings.0.2.2.php
 * @version 0.2.2
 * @date 2025.12.23  
 * @author vladimir@tsurkanenko.ru
 * @description Константы по умолчанию для системы
 */



// Настройки отладки
define("DEBUG", true);
if (defined("DEBUG")) {

    if (!defined("DEBUG_VERBOSE")) {
        define("DEBUG_VERBOSE", 3);
    }
    define('DEBUG_LOG_FILE', '/tmp/svxlink-debug.log');
    // define ('DEBUG_LOG_TO_APACHE', '/var/log/apache2/svxlink_development.error.log');
    if (!defined('LOG_LEVEL')) {
        define('LOG_LEVEL', DEBUG_VERBOSE);
    }
}
// Версия и название
define('DASHBOARD_VERSION', '0.4');
define('DASHBOARD_NAME', 'SvxLink Dashboard');
define('DASHBOARD_TITLE', 'SvxLink Dashboard by R2ADU');


// Настройки времени
define('DASHBOARD_TIME_FORMAT', 'H:i:s');
define('DASHBOARD_DATE_FORMAT', 'd-m-Y');


// Настройки сессии  
define('SESSION_NAME', 'SVXDASHBOARD');
define('SESSION_LIFETIME', 0);
define('SESSION_PATH', '/');
define("SESSION_ID", 'svxdashb');

// Пути к логам
define("SVXLOGPATH", '/var/log/');
define("SVXLOGPREFIX", "svxlink");
define('SVXLINK_LOG_PATH', SVXLOGPATH . SVXLOGPREFIX);

// SVXLINK
define("SERVICE_TITLE", 'SvxLink');
define("SVXCONFPATH", '/etc/svxlink/');
define("SVXCONFIG", 'svxlink.conf');

define("TIMESTAMP_FORMAT", "%d %b %Y %H:%M:%S.%f");


// Настройки отображения частей
define('SHOW_AUDIO_MONITOR', true);

define('SHOW_MACROS', true);
define('SHOW_CON_DETAILS', true);
define('SHOW_RADIO_ACTIVITY', true);
define('SHOW_REFLECTOR_ACTIVITY', true); 
define('REFLECTOR_ACTIVITY_LIMIT', 6); 
define('SHOW_NET_ACTIVITY', true);
define('NET_ACTIVITY_LIMIT', 6);
define('SHOW_RF_ACTIVITY', true);
define('RF_ACTIVITY_LIMIT', 6);
// WebSocket
define("WS_ENABLED", true);
if (WS_ENABLED) {
    define("WS_HOST", '0.0.0.0');
    define("WS_PORT", 8080);
    define("WS_PATH", '/ws');
}

// Глобально, использовать кеш или нет
define("USE_CACHE", true);
// Настройки времени жизни кеша для различных функций
define("LOG_CACHE_TTL_MS", 1000);

// Настройка аутентификации
define("AUTH_FILE", '/etc/svxlink/dashboard/auth.ini');
define("AUTH_SETUP", 'install/setup_auth.php');

// Интервал обновления для динамических элементов
define("SLOW_UPDATE_INTERVAL", 5000);