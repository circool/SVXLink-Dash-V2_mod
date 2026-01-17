#!/usr/bin/php
<?php
/**
 * CLI Setup Script
 * Usage: php cli_setup.php
 * @version 0.1.1
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/../include/auth_config.php';

function createAuthFileCli()
{
	$auth_file = '/etc/svxlink/dashboard/auth.ini';
	$auth_dir = dirname($auth_file);

	// Create directory
	if (!is_dir($auth_dir)) {
		if (!mkdir($auth_dir, 0755, true)) {
			echo "ERROR: Cannot create directory: $auth_dir\n";
			return false;
		}
		echo "Created directory: $auth_dir\n";
	}

	// Create file
	$default_user = 'svxlink';
	$default_password = 'svxlink';
	$hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

	$auth_content = "[dashboard]\nauth_user = $default_user\nauth_pass_hash = $hashed_password\n";

	if (file_put_contents($auth_file, $auth_content) !== false) {
		chmod($auth_file, 0644);
		echo "Created auth file: $auth_file\n";
		return true;
	} else {
		echo "ERROR: Cannot create file: $auth_file\n";
		return false;
	}
}

// Run setup
if (createAuthFileCli()) {
	echo "✅ Setup completed successfully!\n";
	echo "🔐 Default credentials: svxlink / svxlink\n";
	echo "⚠️  Please change the password after first login!\n";
} else {
	echo "❌ Setup failed!\n";
	exit(1);
}
?>