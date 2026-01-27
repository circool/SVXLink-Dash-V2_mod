<?php

/**
 * Обработчик авторизации
 * @filesource /include/auth_handler.php
 * @author vladimir@tsurkanenko.ru
 * @version 0.0.1
 * @note Preliminary version
 */

include_once $_SERVER["DOCUMENT_ROOT"] . "/include/init.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/include/auth_config.php";

if (session_status() === PHP_SESSION_NONE) {
	session_name(SESSION_NAME);
	session_set_cookie_params(SESSION_LIFETIME, SESSION_PATH);
	session_start();
}


$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if ($username === PHP_AUTH_USER && password_verify($password, PHP_AUTH_PW_HASH)) {

	$important_session_data = [];
	if (isset($_SESSION['status'])) {
		$important_session_data['status'] = $_SESSION['status'];
	}


	unset($_SESSION['auth']);
	unset($_SESSION['username']);
	unset($_SESSION['login_time']);



	$_SESSION['auth'] = 'AUTHORISED';
	$_SESSION['username'] = $username;
	$_SESSION['login_time'] = time();

	foreach ($important_session_data as $key => $value) {
		$_SESSION[$key] = $value;
	}

	echo json_encode(['success' => true]);
} else {
	$_SESSION['auth'] = 'UNAUTHORISED';
	echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
}
