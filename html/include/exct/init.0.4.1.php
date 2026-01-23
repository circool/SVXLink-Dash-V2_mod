<?php

/**
 * @filesource /include/exct/init.0.4.1.php
 * @version 0.4.0
 * @date 2026.01.15
 * @author vladimir@tsurkanenko.ru
 * @description Инициализация системы с WebSocket автостартом
 * @note Исправления:
 * - Сервер запускается на 0.0.0.0 для сетевого доступа
 * - Добавлен вывод конфигурации WebSocket клиента
 * - Синхронизация host между сервером и клиентом
 * @since 0.3.2
 * - Используетя обновленная функция getActualStatus.0.3.1 с параметром forceRebuild для первого запуска
 * @since 0.4.0
 *  Изменен состав переменных и наименование конфига для WS клиента
 * @since 0.4.1
 *  Поддержка макросов из getActualStatus
 */





// Подключаем настройки по умолчанию
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/settings.php';

if (defined("DEBUG") && DEBUG){
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.php';
	$func_start = microtime(true);
	$ver = "init.0.4.0";
	dlog("$ver: Начинаю работу", 4, "INFO");
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getActualStatus.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';



// Определяем ROOT_DIR здесь, после подключения settings
if (!defined('ROOT_DIR')) {
	define('ROOT_DIR', $_SERVER["DOCUMENT_ROOT"]);
}



// Инициализация сессии
if (
	!defined("SESSION_LIFETIME") ||
	!defined("SESSION_PATH") ||
	!defined("SESSION_NAME")
) {
	die("Undefined session params!");
}

if (session_status() === PHP_SESSION_NONE) {
	session_set_cookie_params(SESSION_LIFETIME, SESSION_PATH);
	session_name(SESSION_NAME);
	session_start();
}


// Устанавливаем часовой пояс
if (file_exists('/etc/timezone')) {
	$systemTimezone = trim(file_get_contents('/etc/timezone'));
} else {
	$systemTimezone = 'UTC';
}
// Используем для вычисления временной метки
if (!defined('TIMEZONE')) {
	define("TIMEZONE", $systemTimezone);
}

$_SESSION['TIMEZONE'] = TIMEZONE;
// Устанавливаем в PHP
date_default_timezone_set($systemTimezone);
if (defined("DEBUG") && DEBUG) dlog("$ver: Установил часовой пояс системы $systemTimezone", 4, "DEBUG");

// Проверяем наличие каркаса и при необходимости инициируем его
if (!isset($_SESSION['status'])) {
	if (defined("DEBUG") && DEBUG) {
		dlog("$ver: **********************************************", 1, "INFO");
		dlog("$ver: *     Первоначальная Инициализация           *", 1, "INFO");
		dlog("$ver: **********************************************", 1, "INFO");
	}	

	$actualStatus = getActualStatus(true);
	
} else {
	if (defined("DEBUG") && DEBUG) dlog("$ver: Система уже инициирована, выполняю быстрое обновление статуса", 3, "INFO");
	$actualStatus = getActualStatus(false);
}

$_SESSION['status'] = $actualStatus;

unset($actualStatus);


unset($socket, $command, $serverScript, $logFile, $systemTimezone, $port, $host, $errno, $errstr);

if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
	$func_time = microtime(true) - $func_start;
	dlog("$ver: Закончил работу за $func_time msec", 3, "INFO");
	unset($ver);
}

