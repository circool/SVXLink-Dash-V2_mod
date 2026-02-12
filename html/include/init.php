<?php

/**
 * @filesource /include/init.php
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.11
 * @version 0.4.6
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/settings.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getActualStatus.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getConfig.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';

if (!defined('ROOT_DIR')) {
	define('ROOT_DIR', $_SERVER["DOCUMENT_ROOT"]);
}

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

if (!isset($_SESSION['TIMEZONE'])) {
	if (file_exists('/etc/timezone')) {
		$systemTimezone = trim(file_get_contents('/etc/timezone'));
	} else {
		$systemTimezone = 'UTC';
	}
	$_SESSION['TIMEZONE'] = $systemTimezone;
} else {
	$systemTimezone = $_SESSION['TIMEZONE'];
}
date_default_timezone_set($systemTimezone);

$config = getConfig();
$_SESSION['status'] = getActualStatus($config);
unset($config, $systemTimezone);