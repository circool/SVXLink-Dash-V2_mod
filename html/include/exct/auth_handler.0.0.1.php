<?php

/**
 * Обработчик авторизации
 * @filesource /include/exct/auth_handler.0.0.1.php
 * @version 0.0.1
 */

include_once $_SERVER["DOCUMENT_ROOT"] . "/include/init.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/include/auth_config.php";

if (defined("DEBUG") && DEBUG) {
    include_once $_SERVER["DOCUMENT_ROOT"] . "/include/fn/dlog.0.2.php";
    $ver = 'auth_handler.php 0.0.1';
}

if (session_status() === PHP_SESSION_NONE) {
	session_name(SESSION_NAME);
	session_set_cookie_params(SESSION_LIFETIME, SESSION_PATH);
    session_start();
}
if (defined("DEBUG") && DEBUG ) dlog("$ver started", 4, "DEBUG");

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (defined("DEBUG") && DEBUG ) dlog("Login attempt - Username: " . $username, 4, "DEBUG");
if (defined("DEBUG") && DEBUG ) dlog("PHP_AUTH_USER defined: " . (defined('PHP_AUTH_USER') ? PHP_AUTH_USER : 'NOT DEFINED'), 4, 'AUTH');
if (defined("DEBUG") && DEBUG ) dlog("PHP_AUTH_PW_HASH defined: " . (defined('PHP_AUTH_PW_HASH') ? 'YES' : 'NO'), 4, 'AUTH');

// Проверяем логин и хеш пароля
if ($username === PHP_AUTH_USER && password_verify($password, PHP_AUTH_PW_HASH)) {
    if (defined("DEBUG") && DEBUG ) dlog("Password verification SUCCESS for user: " . $username, 4, "DEBUG");
    
    // СОХРАНЯЕМ важные данные сессии перед перезаписью
    $important_session_data = [];
    if (isset($_SESSION['status'])) {
        $important_session_data['status'] = $_SESSION['status'];
        if (defined("DEBUG") && DEBUG ) dlog("Preserved session logic data", 4, "DEBUG");
    }

    // ОЧИЩАЕМ сессию от старых данных авторизации
    unset($_SESSION['auth']);
    unset($_SESSION['username']);
    unset($_SESSION['login_time']);
    if (defined("DEBUG") && DEBUG ) dlog("Cleared old auth session data", 4, "DEBUG");

    // Устанавливаем новые данные авторизации
    $_SESSION['auth'] = 'AUTHORISED';
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
    if (defined("DEBUG") && DEBUG ) dlog("Set new auth session data for user: " . $username, 4, "DEBUG");

    // ВОССТАНАВЛИВАЕМ важные данные
    foreach ($important_session_data as $key => $value) {
        $_SESSION[$key] = $value;
        if (defined("DEBUG") && DEBUG ) dlog("Restored session key: " . $key, 4, "DEBUG");
    }

    if (defined("DEBUG") && DEBUG ) dlog("Authentication SUCCESS - sending JSON response", 4, "DEBUG");
    echo json_encode(['success' => true]);

} else {
    if (defined("DEBUG") && DEBUG ) dlog("Authentication FAILED - Username match: " . ($username === PHP_AUTH_USER ? 'YES' : 'NO'), 4, 'AUTH');
    if (defined("DEBUG") && DEBUG ) dlog("Password verify result: " . (password_verify($password, PHP_AUTH_PW_HASH) ? 'MATCH' : 'NO MATCH'), 4, 'AUTH');
    
    $_SESSION['auth'] = 'UNAUTHORISED';
    if (defined("DEBUG") && DEBUG ) dlog("Set session auth to UNAUTHORISED", 4, "DEBUG");
    
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
}

if (defined("DEBUG") && DEBUG ) dlog("auth_handler.php completed", 4, "DEBUG");
?>
