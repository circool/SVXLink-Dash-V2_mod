<?php
/**
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @version 0.4.0
 * @filesource /include/reset.php
 * Очистка сессии и перенаправление на главную страницу
 */


require_once $_SERVER["DOCUMENT_ROOT"] . "/include/session_header.php";
$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
	setcookie(session_name(), '', time() - 3600, "/");
}

if (session_status() === PHP_SESSION_ACTIVE) {
	session_destroy();
}

header('Location: /index.php');
exit;
