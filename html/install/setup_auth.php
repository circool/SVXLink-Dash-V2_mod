<?php

/**
 * Скрипт установки авторизации
 * @filesource setup_auth.php
 */

function createAuthFile()
{
	// Используем только один путь
	$auth_file = '/etc/svxlink/dashboard/auth.ini';
	$auth_dir = dirname($auth_file);

	echo "<p>Target: $auth_file</p>";

	// Пробуем создать директорию
	if (!is_dir($auth_dir)) {
		if (@mkdir($auth_dir, 0755, true)) {
			echo "<p class='success'>✓ Created directory: $auth_dir</p>";
		} else {
			echo "<p class='error'>✗ Cannot create directory: $auth_dir</p>";
			echo "<p class='warning'>Try creating manually: <code>sudo mkdir -p $auth_dir</code></p>";
			return false;
		}
	} else {
		echo "<p class='success'>✓ Directory exists: $auth_dir</p>";
	}

	// Пробуем создать файл
	if (!file_exists($auth_file)) {
		$default_user = 'svxlink';
		$default_password = 'svxlink';
		$hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

		$auth_content = "[dashboard]\nauth_user = $default_user\nauth_pass_hash = $hashed_password\n";

		if (@file_put_contents($auth_file, $auth_content) !== false) {
			@chmod($auth_file, 0644);
			echo "<p class='success'>✓ Created auth file: $auth_file</p>";

			// Проверяем что файл действительно создан и читается
			if (file_exists($auth_file) && is_readable($auth_file)) {
				echo "<p class='success'>✓ File verified and readable</p>";
				return $auth_file;
			} else {
				echo "<p class='error'>✗ File created but not readable</p>";
				return false;
			}
		} else {
			echo "<p class='error'>✗ Cannot create file: $auth_file</p>";
			echo "<p class='warning'>Try creating manually: <code>sudo cp config/sample.auth.ini $auth_file</code></p>";
			return false;
		}
	} else {
		echo "<p class='success'>✓ Auth file already exists: $auth_file</p>";
		return $auth_file;
	}
}

// HTML страница установки
?>
<!DOCTYPE html>
<html>

<head>
	<title>SVXLink Dashboard - Setup</title>
	<style>
		body {
			font-family: 'PT Sans', sans-serif;
			margin: 0;
			padding: 20px;
			background: #212529;
			color: #bebebe;
		}

		.container {
			max-width: 800px;
			margin: 0 auto;
			background: #2e363f;
			padding: 30px;
			border-radius: 12px;
			border: 1px solid #3c3f47;
		}

		.success {
			color: #2c7f2c;
			font-weight: bold;
		}

		.error {
			color: #ff6b6b;
		}

		.warning {
			color: #a65d14;
		}

		button {
			background: #2c7f2c;
			color: white;
			border: none;
			padding: 12px 24px;
			border-radius: 8px;
			cursor: pointer;
			font-size: 16px;
			font-family: 'PT Sans', sans-serif;
		}

		button:hover {
			background: #37803A;
		}

		.info-box {
			background: #212529;
			padding: 15px;
			border-radius: 8px;
			margin: 15px 0;
			border-left: 4px solid #2c7f2c;
		}

		code {
			background: #212529;
			padding: 10px;
			border-radius: 4px;
			display: block;
			margin: 10px 0;
			font-family: 'Roboto Mono', monospace;
		}
	</style>
</head>

<body>
	<div class="container">
		<h1>🎛️ SVXLink Dashboard Setup</h1>
		<p>This setup will create the authentication configuration file for your dashboard.</p>

		<div class="info-box">
			<h3>📋 What will be created:</h3>
			<ul>
				<li>Authentication file: <code>/etc/svxlink/dashboard/auth.ini</code></li>
				<li>Default user account: <strong>svxlink / svxlink</strong></li>
			</ul>
		</div>

		<?php
		if ($_POST['run_setup'] ?? false) {
			echo "<h2>Setup Results:</h2>";
			$auth_file = createAuthFile();

			if ($auth_file) {
				echo "<div class='info-box'>";
				echo "<p class='success'>✅ Setup completed successfully!</p>";
				echo "<p>Authentication file created: <code>$auth_file</code></p>";
				echo "<h3>🔐 Default Credentials:</h3>";
				echo "<p><strong>Username:</strong> svxlink</p>";
				echo "<p><strong>Password:</strong> svxlink</p>";
				echo "<p class='warning'>⚠️ Please change the password after first login!</p>";
				echo "</div>";

				// Проверяем доступность файла для index.php
				echo "<h3>🔍 File Access Test:</h3>";
				if (file_exists($auth_file) && is_readable($auth_file)) {
					echo "<p class='success'>✓ File exists and is readable by web server</p>";
					echo '<p><a href="/index.php"><button>🚀 Go to Dashboard</button></a></p>';
				} else {
					echo "<p class='error'>✗ File created but not accessible by web server</p>";
					echo "<p>Try setting permissions manually:</p>";
					echo "<code>sudo chown www-data:www-data $auth_file<br>sudo chmod 644 $auth_file</code>";
				}
			} else {
				echo "<p class='error'>❌ Setup failed. Unable to create auth file.</p>";
				echo "<div class='info-box'>";
				echo "<h3>🔧 Manual Setup Required:</h3>";
				echo "<p>Run these commands in terminal:</p>";
				echo "<code>sudo mkdir -p /etc/svxlink/dashboard<br>";
				echo "sudo cp config/sample.auth.ini /etc/svxlink/dashboard/auth.ini<br>";
				echo "sudo chown www-data:www-data /etc/svxlink/dashboard/auth.ini<br>";
				echo "sudo chmod 644 /etc/svxlink/dashboard/auth.ini</code>";
				echo "</div>";
				echo '<form method="POST" style="margin-top: 20px;">';
				echo '<button type="submit" name="run_setup" value="1">🔄 Try Again</button>';
				echo '</form>';
			}
		} else {
			echo '<form method="POST">';
			echo '<button type="submit" name="run_setup" value="1">🔧 Run Setup</button>';
			echo '</form>';
		}
		?>
	</div>
</body>

</html>