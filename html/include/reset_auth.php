<?php

/**
 * @filesource /include/reset_auth.php
 * @version 0.0.1.release
 * @author vladimir@tsurkanenko.ru
 */

$auth_file = '/etc/svxlink/dashboard/auth.ini';
$auth_dir = dirname($auth_file);

if (!is_dir($auth_dir)) {
	mkdir($auth_dir, 0755, true);
	echo "<p>Created directory: $auth_dir</p>";
}

$default_user = 'svxlink';
$default_password = 'svxlink';
$hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
$auth_content = "[dashboard]\nauth_user = $default_user\nauth_pass_hash = $hashed_password\n";
if (file_put_contents($auth_file, $auth_content) !== false) {
	chmod($auth_file, 0600);
	echo "<p style='color: green;'>Auth file created/reset successfully!</p>";
	echo "<p>Username: <strong>$default_user</strong></p>";
	echo "<p>Password: <strong>$default_password</strong></p>";
	echo "<p>File: $auth_file</p>";
} else {
	echo "<p style='color: red;'>Failed to create auth file!</p>";
}

// Проверяем
if (file_exists($auth_file)) {
	$auth_data = parse_ini_file($auth_file);
	echo "<pre>";
	print_r($auth_data);
	echo "</pre>";

	// Проверяем пароль
	$test_password = 'svxlink';
	$is_valid = password_verify($test_password, $auth_data['auth_pass_hash']);
	echo "<p>Password verification: " . ($is_valid ? 'SUCCESS' : 'FAILED') . "</p>";
} 

?>