<?php
/**
 * Конфигурация авторизации
 * @filesource /include/exct/auth_config.0.0.1.php
 * @version 0.0.1
 * Предварительная версия
 */

$ver = 'auth_config.php 0.0.1';



if (defined("DEBUG") && DEBUG ) dlog("auth_config.php loaded", 4, "AUTH");

// Защита от повторного включения файла
if (!defined('PHP_AUTH_USER')) {
	$auth_file = '/etc/svxlink/dashboard/auth.ini';

	if (defined("DEBUG") && DEBUG ) dlog("Checking auth file: " . $auth_file, 4, "AUTH");
	if (defined("DEBUG") && DEBUG ) dlog("Auth file exists: " . (file_exists($auth_file) ? 'YES' : 'NO'), 4, 'AUTH');
	if (defined("DEBUG") && DEBUG ) dlog("Auth file readable: " . (is_readable($auth_file) ? 'YES' : 'NO'), 4, 'AUTH');

	if (file_exists($auth_file) && is_readable($auth_file)) {
		$auth_data = parse_ini_file($auth_file);
		if (defined("DEBUG") && DEBUG ) dlog("Parsed auth data: " . print_r($auth_data, true), 5, 'AUTH');

		define('PHP_AUTH_USER', $auth_data['auth_user'] ?? 'svxlink');
		define('PHP_AUTH_PW_HASH', $auth_data['auth_pass_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

		if (defined("DEBUG") && DEBUG ) dlog("Defined PHP_AUTH_USER: " . PHP_AUTH_USER, 4, "AUTH");
		if (defined("DEBUG") && DEBUG ) dlog("Defined PHP_AUTH_PW_HASH length: " . strlen(PHP_AUTH_PW_HASH), 4, 'AUTH');
	} else {
		// Fallback to defaults
		if (defined("DEBUG") && DEBUG ) dlog("Using fallback defaults", 3, "WARNING");
		define('PHP_AUTH_USER', 'svxlink');
		define('PHP_AUTH_PW_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
	}
} else {
	if (defined("DEBUG") && DEBUG ) dlog("auth_config.php already loaded - constants defined", 4, "AUTH");
}

if (defined("DEBUG") && DEBUG ) dlog("auth_config.php completed", 4, "AUTH");
?>