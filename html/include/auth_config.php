<?php
/**
 * 
 * @filesource /include/auth_config.php
 * @version 0.0.1.release
 * @note Preliminary version.
 */

if (!defined('PHP_AUTH_USER')) {
	$auth_file = '/etc/svxlink/dashboard/auth.ini';

	if (file_exists($auth_file) && is_readable($auth_file)) {
		$auth_data = parse_ini_file($auth_file);
		define('PHP_AUTH_USER', $auth_data['auth_user'] ?? 'svxlink');
		define('PHP_AUTH_PW_HASH', $auth_data['auth_pass_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
	} else {
		define('PHP_AUTH_USER', 'svxlink');
		define('PHP_AUTH_PW_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
	}
} 

?>