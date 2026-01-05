<?php

/**
 * @filesource /include/exct/init.0.3.1.php
 * @version 0.3.2
 * @date 2026.01.03
 * @author vladimir@tsurkanenko.ru
 * @description Инициализация системы с WebSocket автостартом
 * @note Исправления:
 * - Сервер запускается на 0.0.0.0 для сетевого доступа
 * - Добавлен вывод конфигурации WebSocket клиента
 * - Синхронизация host между сервером и клиентом
 * Новое в 0.3.2
 * - Используетя обновленная фкнуция getActualStatus.0.3.1 с параметром forceRebuild для первого запуска
 */
$func_start = microtime(true);
// Подключаем настройки по умолчанию
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/settings.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.0.2.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getActualStatus.0.3.1.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';

$ver = "init.0.3.2";

// Определяем ROOT_DIR здесь, после подключения settings
if (!defined('ROOT_DIR')) {
	define('ROOT_DIR', $_SERVER["DOCUMENT_ROOT"]);
}

if (defined("DEBUG") && DEBUG) dlog("$ver: Начинаю работу", 3, "WARNING");

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
	if (defined("DEBUG") && DEBUG) dlog("$ver: Запустил сессию", 3, "DEBUG");
}

if (defined("DEBUG") && DEBUG) {
	dlog("Система инициирована, версия " . DASHBOARD_VERSION, 1, "INFO");
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
	$_SESSION['status'] = $actualStatus;

} else {
	if (defined("DEBUG") && DEBUG) dlog("$ver: Система уже инициирована, выполняю быстрое обновление статуса", 3, "INFO");
	$actualStatus = getActualStatus(false);
	$_SESSION['status'] = $actualStatus;
}
unset($actualStatus);

// Сбрасываем кеш логов
trackNewLogLines(1);
if (defined("DEBUG") && DEBUG) dlog("$ver: Указатель последней записи лога сброшен в конец журнала", 4, "INFO");


// ======================================================================
// === WebSocket АВТОСТАРТ С ЛОГИРОВАНИЕМ ===
// ======================================================================

if (defined('WS_ENABLED') && WS_ENABLED) {

	$port = defined('WS_PORT') ? WS_PORT : 8080;
	// ИСПРАВЛЕНО: Сервер слушает на всех интерфейсах для сетевого доступа
	$host = defined('WS_HOST') ? WS_HOST : '0.0.0.0';

	// Простая проверка порта
	$socket = @fsockopen($host, $port, $errno, $errstr, 0.5);

	if (!$socket) {
		// Порт свободен = запускаем сервер
		if (defined("DEBUG") && DEBUG) dlog("$ver: Запускаю сервер WebSocket на хосле $host и порту $port", 3, "INFO");

		$serverScript = ROOT_DIR . '/scripts/dashboard_ws_server.js';
		if (file_exists($serverScript)) {
			// Запускаем с логированием в файл
			$logFile = ROOT_DIR . '/websocket_server.log';
			// ИСПРАВЛЕНО: Передаем host как переменную окружения
			$command = 'cd ' . escapeshellarg(ROOT_DIR) .
				' && WS_HOST="' . $host . '" /usr/bin/node ' . escapeshellarg('scripts/dashboard_ws_server.js') .
				' >> ' . escapeshellarg($logFile) . ' 2>&1 &';

			exec($command);

			if (defined("DEBUG") && DEBUG) dlog("$ver: Сервер WebSocket запущен командой <$command>(лог в файле: $logFile)", 3, "INFO");
		} else {
			if (defined("DEBUG") && DEBUG) dlog("$ver: Server script not found: $serverScript", 1, "ERROR");
		}
	} else {
		// Сервер уже запущен
		fclose($socket);
		if (defined("DEBUG") && DEBUG) dlog("$ver: Сервер WebSocket server уже работает на порту $port", 3, "INFO");
	}

	// ======================================================================
	// === WebSocket КОНФИГУРАЦИЯ КЛИЕНТА ===
	// ======================================================================
	if (defined("DEBUG") && DEBUG) dlog("$ver: Инициирую клиента WebSocket", 3, "INFO");
	// ИСПРАВЛЕНО: Добавляем конфигурацию WebSocket клиента
	// Клиент будет использовать тот же хост, что и страница (DASHBOARD_HOST)
	echo '<script>
        window.DASHBOARD_CONFIG = window.DASHBOARD_CONFIG || {};
        window.DASHBOARD_CONFIG.websocket = {
            enabled: true,
            // Важно: Клиент подключается к хосту страницы, не к WS_HOST!
            host: "' . DASHBOARD_HOST . '",
            port: ' . (defined('WS_PORT') ? WS_PORT : 8080) . ',
            path: "' . (defined('WS_PATH') ? WS_PATH : '/ws') . '",
            autoConnect: true,
            reconnectDelay: 3000,
            maxReconnectAttempts: 5,
            pingInterval: 30000,
            debugToConsole: ' . (defined('DEBUG') && DEBUG ? 'true' : 'false') . ',
            debugLevel: "info"
        };
    </script>';
	echo '<script src="/scripts/dashboard_ws_client.js"></script>';
}
if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
	$func_time = microtime(true) - $func_start;
	dlog("$ver: Закончил работу за $func_time msec", 3, "WARNING");
}
unset($ver);
