<?php

/**
 * @filesource /include/exct/reset_auth.0.0.1.php
 * @version 0.0.1
 * Сброс авторизации
 */

// Добавим отладочное логирование
if (!defined("DEBUG")) define("DEBUG", true);
if (!defined('DEBUG_VERBOSE')) define('DEBUG_VERBOSE', 5);
if (defined("DEBUG") && DEBUG ) dlog("reset_auth.php started", 4, "AUTH");

// Принудительный сброс пароля
$auth_file = '/etc/svxlink/dashboard/auth.ini';
$auth_dir = dirname($auth_file);

if (defined("DEBUG") && DEBUG ) dlog("Auth file path: " . $auth_file, 4, "AUTH");
if (defined("DEBUG") && DEBUG ) dlog("Auth directory: " . $auth_dir, 4, "AUTH");

// Создаем директорию если не существует
if (!is_dir($auth_dir)) {
	if (defined("DEBUG") && DEBUG ) dlog("Creating directory: " . $auth_dir, 4, "AUTH");
	mkdir($auth_dir, 0755, true);
	echo "<p>Created directory: $auth_dir</p>";
} else {
	if (defined("DEBUG") && DEBUG ) dlog("Directory already exists: " . $auth_dir, 4, "AUTH");
}

// Создаем файл с дефолтными учетками
$default_user = 'svxlink';
$default_password = 'svxlink';
$hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

if (defined("DEBUG") && DEBUG ) dlog("Default user: " . $default_user, 4, "AUTH");
if (defined("DEBUG") && DEBUG ) dlog("Default password: " . $default_password, 4, "AUTH");
if (defined("DEBUG") && DEBUG ) dlog("Hashed password length: " . strlen($hashed_password), 4, 'AUTH');

$auth_content = "[dashboard]\nauth_user = $default_user\nauth_pass_hash = $hashed_password\n";
if (defined("DEBUG") && DEBUG ) dlog("Auth content to write: \n" . $auth_content, 5, "AUTH");

if (file_put_contents($auth_file, $auth_content) !== false) {
	chmod($auth_file, 0600);
	if (defined("DEBUG") && DEBUG ) dlog("Auth file created successfully: " . $auth_file, 4, "AUTH");
	echo "<p style='color: green;'>Auth file created/reset successfully!</p>";
	echo "<p>Username: <strong>$default_user</strong></p>";
	echo "<p>Password: <strong>$default_password</strong></p>";
	echo "<p>File: $auth_file</p>";
} else {
	if (defined("DEBUG") && DEBUG ) dlog("Failed to create auth file: " . $auth_file, 1, "ERROR");
	echo "<p style='color: red;'>Failed to create auth file!</p>";
}

// Проверяем
if (file_exists($auth_file)) {
	if (defined("DEBUG") && DEBUG ) dlog("Verifying created auth file", 4, "AUTH");
	$auth_data = parse_ini_file($auth_file);
	if (defined("DEBUG") && DEBUG ) dlog("Parsed auth data: " . print_r($auth_data, true), 5, 'AUTH');

	echo "<pre>";
	print_r($auth_data);
	echo "</pre>";

	// Проверяем пароль
	$test_password = 'svxlink';
	$is_valid = password_verify($test_password, $auth_data['auth_pass_hash']);
	if (defined("DEBUG") && DEBUG ) dlog("Password verification result: " . ($is_valid ? 'SUCCESS' : 'FAILED'), 4, 'AUTH');
	echo "<p>Password verification: " . ($is_valid ? 'SUCCESS' : 'FAILED') . "</p>";
} else {
	if (defined("DEBUG") && DEBUG ) dlog("Auth file does not exist after creation attempt", 1, "ERROR");
}

if (defined("DEBUG") && DEBUG ) dlog("reset_auth.php completed", 4, "AUTH");
?>