<?php

/**
 * @filesource /include/exct/ajax_update.0.1.0.php
 * @version 0.1.0
 * @description Единый обработчик AJAX обновлений для динамических блоков
 * @date 2026.01.22
 */

// Разрешаем CORS запросы
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');

// Обработка preflight OPTIONS запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(200);
	exit;
}

// Базовые проверки
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
$block = $_GET['block'] ?? '';

// Разрешенные блоки
$allowedBlocks = ['rf_activity', 'net_activity', 'reflector_activity', 'connection_details'];

if (!in_array($block, $allowedBlocks)) {
	http_response_code(400);
	header('Content-Type: text/plain; charset=utf-8');
	exit('Invalid block');
}

// Начинаем сессию
if (session_status() === PHP_SESSION_NONE) {
	require_once $docRoot . '/include/settings.php';
	session_name(SESSION_NAME);
	session_start();
}
if (!isset($_SESSION['TIMEZONE'])) {
	$_SESSION['TIMEZONE'] = "Europe/Moscow";
}
date_default_timezone_set($_SESSION['TIMEZONE']);


// 3. Обновляем статус
require_once $docRoot . '/include/fn/getActualStatus.php';

if (!isset($_SESSION['status'])) {
	// Первоначальная инициализация
	$actualStatus = getActualStatus(true);
} else {
	// Быстрое обновление статуса
	$actualStatus = getActualStatus(false);
}

$_SESSION['status'] = $actualStatus;
unset($actualStatus);

if (!isset($_SESSION['TIMEZONE'])) {
	$_SESSION['TIMEZONE'] = "Europe/Moscow";
}
date_default_timezone_set($_SESSION['TIMEZONE']);

session_write_close();

// Устанавливаем флаг AJAX
$_GET['ajax'] = 1;

// Подключаем нужный файл через симлинк
$filepath = $docRoot . "/include/$block.php";
if (!file_exists($filepath)) {
	http_response_code(404);
	header('Content-Type: text/plain; charset=utf-8');
	exit('Block file not found: ' . $block);
}

// Выполняем блок
require_once $filepath;
