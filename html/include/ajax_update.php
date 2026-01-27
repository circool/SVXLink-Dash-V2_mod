<?php

/**
 * @filesource /include/ajax_update.php
 * @version 0.1.0.release
 * @description AJAX handler for dynamic blocks
 * @author vladimir@tsurkanenko.ru
 * @date 2026.01.22
 */


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(200);
	exit;
}

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
$block = $_GET['block'] ?? '';
$allowedBlocks = ['rf_activity', 'net_activity', 'reflector_activity', 'connection_details'];

if (!in_array($block, $allowedBlocks)) {
	http_response_code(400);
	header('Content-Type: text/plain; charset=utf-8');
	exit('Invalid block');
}

if (session_status() === PHP_SESSION_NONE) {
	require_once $docRoot . '/include/settings.php';
	session_name(SESSION_NAME);
	session_start();
}

if (!isset($_SESSION['TIMEZONE'])) {
	$_SESSION['TIMEZONE'] = "UTC";
}
date_default_timezone_set($_SESSION['TIMEZONE']);

require_once $docRoot . '/include/fn/getActualStatus.php';

if (!isset($_SESSION['status'])) {
	$actualStatus = getActualStatus(true);
} else {
	$actualStatus = getActualStatus(false);
}

$_SESSION['status'] = $actualStatus;
unset($actualStatus);

session_write_close();

$_GET['ajax'] = 1;

$filepath = $docRoot . "/include/$block.php";
if (!file_exists($filepath)) {
	http_response_code(404);
	header('Content-Type: text/plain; charset=utf-8');
	exit('Block file not found: ' . $block);
}

require_once $filepath;
