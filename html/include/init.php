<?php
/**
 * SvxLink Dashboard
 * @file include/init.php
 * @author vladimir@tsurkanenko.ru
 * @version 0.1.6
 * @date 2025-12-07
 * @since 0.1.6
 * @description Инициализация основных переменных программы
 */

// Инициализация ======================================================================

if (isset($_SESSION['status'])) {
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Config already initialized, exiting", 4, 'DEBUG');
    return;
}

$defaultsPath = __DIR__ . "/init_defaults.php";

if (!file_exists($defaultsPath)) {
    error_log("[SVXLINK-DASHBOARD-FATAL] Configuration file missing: " . $defaultsPath);
    header('HTTP/1.1 500 Internal Server Error');
    die('System configuration error');
} else {
    include_once $defaultsPath;
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) error_log("[SVXLINK-DEBUG] Default configuration file found & loaded: " . $defaultsPath);   
}


if (defined('LOG_BOOTSTRAP') && LOG_BOOTSTRAP) {
    error_log("[BOOTSTRAP] " . basename(__FILE__) . " loaded");
}

$functionsPath = __DIR__ . "/functions.php";
if (!file_exists($functionsPath)) {
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) error_log("[SVXLINK-ERROR] functions.php not found");
    header('HTTP/1.1 500 Internal Server Error');
    die('System functions error');
}
include_once $functionsPath;

// Проверяем наличие каркаса и при необходимости инициируем его
if (!isset($_SESSION['status'])) {
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("******************************************", 4, 'INIT');
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("First time initialization, try init config", 4, 'INIT');
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("******************************************", 4, 'INIT');
    initSessionStatus(SVXCONFPATH . SVXCONFIG);
}

// Определяем язык
if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Continnue first time initialization: Language", 4, 'LANG');

if (!isset($_SESSION['dashboard_lang'])) {
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Accept-Language header: " . $acceptLang, 4, 'LANG');
    
    if (strpos($acceptLang, 'ru') !== false) {
        $_SESSION['dashboard_lang'] = 'ru';
    } else {
        $_SESSION['dashboard_lang'] = 'en';
    }
    
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Language set to: " . $_SESSION['dashboard_lang'], 4, 'LANG');

} else {
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Language already detected: " . $_SESSION['dashboard_lang'], 4, 'LANG');
}

$lang = $_SESSION['dashboard_lang'];

// Подключаем переводы
$translations = [];
$ru_translations = []; // Для обратной совместимости
$langFile = __DIR__ . "/languages/{$lang}.php";
if (file_exists($langFile)) {
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Loading language file: " . $langFile, 4, 'LANG');
    $loadedTranslations = include $langFile;
    if (is_array($loadedTranslations)) {
        $translations = $loadedTranslations;
        if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Loaded " . count($translations) . " translations", 4, 'LANG');
        if ($lang === 'ru') {
            $ru_translations = $translations;
        }
    } else {
        if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Language file returned non-array", 3, 'WARNING');
    }
} else {
    if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Language file not found: " . $langFile, 3, 'WARNING');
}

if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("Fitst time initialization completed successfully", 4, 'INIT');
if (defined('DEBUG') && DEBUG && function_exists('dlog')) dlog("************************************************", 4, 'INIT');
?>