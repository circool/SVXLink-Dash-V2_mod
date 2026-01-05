<?php

/**
 * @filesource /include/exct/settings.0.2.2.php
 * @version 0.2.2
 * @date 2025.12.23  
 * @author vladimir@tsurkanenko.ru
 * @description Константы по умолчанию для системы
 */

// Базовые пути


// Настройки отладки
define("DEBUG", true);
if(defined("DEBUG")) {

    if (!defined("DEBUG_VERBOSE")) {
        define("DEBUG_VERBOSE", 3);
    }
    define ('DEBUG_LOG_FILE', '/tmp/svxlink-debug.log');
    // define ('DEBUG_LOG_TO_APACHE', '/var/log/apache2/svxlink_development.error.log');
    if (!defined('LOG_LEVEL')) {
        define('LOG_LEVEL', DEBUG_VERBOSE);
    }

}
// Версия и название
define('DASHBOARD_VERSION', '0.2');
define('DASHBOARD_NAME', 'SvxLink Dashboard');
define('DASHBOARD_TITLE', 'SvxLink Dashboard by R2ADU');


// Настройки времени
define('DASHBOARD_TIME_FORMAT', 'H:i:s');
define('DASHBOARD_DATE_FORMAT', 'd-m-Y');

// @deprecated see AJAX_TIMEOUT
define('UPDATE_INTERVAL', 5000); // мс

// @deprecated see AJAX_TIMEOUT
if (!defined('DASHBOARD_FAST_INTERVAL')) {
    define('DASHBOARD_FAST_INTERVAL', 2000); // мс
}
// @deprecated see AJAX_TIMEOUT
if (!defined('DASHBOARD_SLOW_INTERVAL')) {
    define('DASHBOARD_SLOW_INTERVAL', 30000); // мс
}



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
define('SHOW_AUDIO_MONITOR', false);

define('SHOW_CON_DETAILS', true);
define('SHOW_RADIO_ACTIVITY', true);
define('SHOW_REFLECTOR_ACTIVITY', true);
define('SHOW_NET_ACTIVITY', true);
define('NET_ACTIVITY_LIMIT', 6);
define('SHOW_RF_ACTIVITY', true);
define('RF_ACTIVITY_LIMIT', 6);
// 
// Запуск WS
// define ("WS_AUTO_START",true);
define ("WS_ENABLED",true);
define ("WS_HOST", 'localhost');
define ("WS_PORT",8080);
define ("WS_PATH", '/ws');
define ("LOCK_TIMEOUT", 5);
define ("TMP_PATH", "/tmp");
define ("LOG_PARSER", 'include/log_parser_dispatcher.php'); // @deprecated version 0.3
define ("WS_SERVER", 'dashboard_ws_server.js');
define ("WS_CLIENT", 'dashboard_ws_client.js');
define ("WS_STARTER", '/include/websocket_starter.php');
define ("WS_SERVER_LOG", 'websocket_server.log');
define ("WS_PID_FILE", 'dashboard_websocket.pid');
define ("LOCK_FILE", TMP_PATH. '/svxlink_update.lock');

// Предварительно, не точно
/** Константы используемые dashboard_ws_client:
WS_ENABLED
WS_AUTO_START
WS_PORT
WS_PATH
CURRENT_PROTOCOL
DASHBOARD_HOST
BASE_URL
UPDATE_INTERVAL
FALLBACK_URL
LOCK_TIMEOUT
TMP_PATH
LOCK_FILE
DEBUG
*/

// Предварительно, не точно
/** Константы для сервера
WS_PORT → порт сервера
WS_HOST → хост сервера
SVXLINK_LOG_PATH → путь к лог-файлу
LOG_PARSER → путь к PHP парсеру
SVXLOGPATH → путь к логам
SVXLOGPREFIX → префикс лог-файла
TMP_PATH → временный путь
LOCK_FILE → файл блокировки
LOCK_TIMEOUT → таймаут блокировки
 */

// Константы на удаление (сначела переписать ajax_parser)
define ("INIT_HANDLER", '/include/fn/getActualStatus.0.2.1.php');
define("BLOCK_RENDER", '/include/fn/htmlDivTable.0.1.1.php');
define ("CHANGES_HANDLER", '/include/fn/getNewState.php');
// @todo update_handler.php использовался до того как был создан ajax_parser - удалить его как только разберусь с остальным
define("FALLBACK_URL", '/include/update_handler.php?mode=incremental');

// Дополнительные константы -> в init
define('DASHBOARD_HOST', $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_ADDR'] ?? 'localhost'));
define('CURRENT_PROTOCOL', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?: '/');

// Настройки кеширования для обновлений AJAX
// Глобально, оспользовать кеш или нет
define ("USE_CACHE", false);
// Настройки времени жизни кеша для различных функций
define("LOG_CACHE_TTL_MS", 1000);
define ("AJAX_TIMEOUT", 5);

define("AUTH_FILE", '/etc/svxlink/dashboard/auth.ini');
define("AUTH_SETUP", 'install/setup_auth.php');