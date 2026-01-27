<?php

/**
 * @filesource /include/init.php
 * @version 0.4.1.release
 * @date 2026.01.26
 * @author vladimir@tsurkanenko.ru
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/settings.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getActualStatus.php';
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

if (!isset($_SESSION['status'])) {
	$actualStatus = getActualStatus(true);	
} else {
	$actualStatus = getActualStatus(false);
}

$_SESSION['status'] = $actualStatus;

unset($actualStatus, $systemTimezone);