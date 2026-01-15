<?php

/**
 * Утилита для смены пароля и логина
 * @filesource /include/exct/change_password.0.0.1.php
 * @version 0.0.1
 * 
 */



// Только для авторизованных пользователей
include $_SERVER["DOCUMENT_ROOT"] . "/include/init.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/include/fn/dlog.0.2.php";
$ver = 'change_password.php 0.0.1';

if (defined("DEBUG") && DEBUG ) dlog("change_password.php loaded", 4, "AUTH");
if (defined("DEBUG") && DEBUG ) dlog("Request method: " . $_SERVER['REQUEST_METHOD'], 4, "AUTH");
if (defined("DEBUG") && DEBUG ) dlog("Session auth status: " . ($_SESSION['auth'] ?? 'NOT SET'), 4, 'AUTH');

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 'AUTHORISED') {
	header('HTTP/1.0 403 Forbidden');
	echo 'Access denied';
	exit;
}

include_once $_SERVER["DOCUMENT_ROOT"] . "/include/auth_config.php";

// Обработка POST запросов только если есть действие
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	$action = $_POST['action'];

	if ($action === 'change_password') {
		$current_password = $_POST['current_password'] ?? '';
		$new_password = $_POST['new_password'] ?? '';
		$confirm_password = $_POST['confirm_password'] ?? '';

		$response = ['success' => false, 'message' => ''];

		// Проверяем текущий пароль
		if (!password_verify($current_password, PHP_AUTH_PW_HASH)) {
			$response['message'] = 'Current password is incorrect';
		} elseif (empty($new_password)) {
			$response['message'] = 'New password cannot be empty';
		} elseif ($new_password !== $confirm_password) {
			$response['message'] = 'New passwords do not match';
		} elseif (strlen($new_password) < 4) {
			$response['message'] = 'New password must be at least 4 characters long';
		} else {
			// Хешируем новый пароль
			$new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

			// Обновляем файл конфигурации
			$auth_file = '/etc/svxlink/dashboard/auth.ini';
			$auth_content = "[dashboard]\nauth_user = " . PHP_AUTH_USER . "\nauth_pass_hash = " . $new_hashed_password . "\n";

			if (file_put_contents($auth_file, $auth_content) !== false) {
				chmod($auth_file, 0600);
				$response['success'] = true;
				$response['message'] = 'Password changed successfully';
			} else {
				$response['message'] = 'Failed to update password file';
			}
		}

		echo json_encode($response);
		exit;
	}

	if ($action === 'change_credentials') {
		$current_password = $_POST['current_password'] ?? '';
		$new_username = $_POST['new_username'] ?? '';
		$new_password = $_POST['new_password'] ?? '';
		$confirm_password = $_POST['confirm_password'] ?? '';

		$response = ['success' => false, 'message' => ''];

		// Проверяем текущий пароль
		if (!password_verify($current_password, PHP_AUTH_PW_HASH)) {
			$response['message'] = 'Current password is incorrect';
		} elseif (empty($new_username)) {
			$response['message'] = 'New username cannot be empty';
		} elseif (empty($new_password)) {
			$response['message'] = 'New password cannot be empty';
		} elseif ($new_password !== $confirm_password) {
			$response['message'] = 'New passwords do not match';
		} elseif (strlen($new_password) < 4) {
			$response['message'] = 'New password must be at least 4 characters long';
		} else {
			// Хешируем новый пароль
			$new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

			// Обновляем файл конфигурации
			$auth_file = '/etc/svxlink/dashboard/auth.ini';
			$auth_content = "[dashboard]\nauth_user = " . $new_username . "\nauth_pass_hash = " . $new_hashed_password . "\n";

			if (file_put_contents($auth_file, $auth_content) !== false) {
				chmod($auth_file, 0600);
				$response['success'] = true;
				$response['message'] = 'Username and password changed successfully';

				// Обновляем сессию
				$_SESSION['username'] = $new_username;
			} else {
				$response['message'] = 'Failed to update credentials file';
			}
		}

		echo json_encode($response);
		exit;
	}
}
?>

<!-- Стили для модального окна смены пароля -->
<style>
	.password-overlay {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.7);
		z-index: 9998;
		backdrop-filter: blur(3px);
	}

	.password-container {
		position: fixed;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		z-index: 9999;
		width: 450px;
		max-width: 90%;
	}

	.password-form {
		background: #212529;
		border: 2px solid #3c3f47;
		padding: 25px;
		box-shadow: 0 0 25px rgba(0, 0, 0, 0.9);
		border-radius: 12px;
		max-height: 90vh;
		overflow-y: auto;
	}

	.password-title {
		color: #bebebe;
		text-align: center;
		margin-bottom: 20px;
		font-size: 1.5em;
		font-weight: 600;
	}

	.password-switcher {
		display: flex;
		justify-content: center;
		align-items: center;
		margin-bottom: 25px;
		gap: 15px;
	}

	.password-switcher-label {
		color: #bebebe;
		font-size: 14px;
		font-weight: 500;
	}

	.password-switcher-label.active {
		color: #2c7f2c;
		font-weight: 600;
	}

	.password-section {
		display: none;
	}

	.password-section.active {
		display: block;
	}

	.password-field {
		margin-bottom: 20px;
	}

	.password-field label {
		display: block;
		color: #bebebe;
		margin-bottom: 8px;
		font-size: 16px;
	}

	.password-field input[type="text"],
	.password-field input[type="password"] {
		width: 100%;
		padding: 12px 15px;
		font-size: 16px;
		border: 1px solid #3c3f47;
		background: #2e363f;
		color: #ffffff;
		border-radius: 8px;
		box-sizing: border-box;
		transition: all 0.3s ease;
	}

	.password-field input[type="text"]:focus,
	.password-field input[type="password"]:focus {
		outline: none;
		border-color: #65737e;
		box-shadow: 0 0 5px rgba(101, 115, 126, 0.5);
	}

	.password-field input[type="text"]::placeholder,
	.password-field input[type="password"]::placeholder {
		color: #949494;
	}

	.password-buttons {
		display: flex;
		justify-content: space-between;
		margin-top: 25px;
		gap: 10px;
	}

	.password-buttons button {
		flex: 1;
		padding: 12px 20px;
		font-size: 16px;
		font-weight: 600;
		border: none;
		border-radius: 8px;
		cursor: pointer;
		transition: all 0.3s ease;
	}

	.password-buttons button[type="submit"] {
		background: #2c7f2c;
		color: #ffffff;
	}

	.password-buttons button[type="submit"]:hover {
		background: #37803A;
		transform: translateY(-1px);
	}

	.password-buttons button.orange-btn {
		background: #a65d14;
		color: #ffffff;
	}

	.password-buttons button.orange-btn:hover {
		background: #b86b20;
		transform: translateY(-1px);
	}

	.password-buttons button.cancel-btn {
		background: #5C5C5C;
		color: #bebebe;
	}

	.password-buttons button.cancel-btn:hover {
		background: #65737e;
		color: #ffffff;
		transform: translateY(-1px);
	}

	.password-message {
		color: #ff6b6b;
		text-align: center;
		margin-top: 15px;
		font-size: 14px;
		padding: 10px;
		background: rgba(140, 12, 38, 0.2);
		border-radius: 6px;
		display: none;
	}

	.password-message.success {
		color: #2c7f2c;
		background: rgba(44, 127, 44, 0.2);
	}

	.password-close {
		position: absolute;
		top: 15px;
		right: 15px;
		background: none;
		border: none;
		color: #bebebe;
		font-size: 24px;
		cursor: pointer;
		width: 30px;
		height: 30px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.3s ease;
	}

	.password-close:hover {
		color: #ffffff;
		background: rgba(255, 255, 255, 0.1);
	}

	/* Стили для переключателя */
	.toggle {
		display: none;
	}

	.toggle-round-flat {
		width: 60px;
		height: 30px;
		position: relative;
		background: #5C5C5C;
		border-radius: 30px;
		transition: all 0.3s ease;
		cursor: pointer;
	}

	.toggle-round-flat:before {
		content: "";
		position: absolute;
		width: 26px;
		height: 26px;
		background: #fff;
		border-radius: 50%;
		top: 2px;
		left: 2px;
		transition: all 0.3s ease;
	}

	.toggle:checked+.toggle-round-flat {
		background: #2c7f2c;
	}

	.toggle:checked+.toggle-round-flat:before {
		left: 32px;
	}

	/* Анимация появления */
	.password-container {
		animation: passwordSlideIn 0.6s ease-out;
	}

	@keyframes passwordSlideIn {
		from {
			opacity: 0;
			transform: translate(-50%, -60%);
		}

		to {
			opacity: 1;
			transform: translate(-50%, -50%);
		}
	}

	/* Адаптивность для мобильных устройств */
	@media (max-width: 480px) {
		.password-container {
			width: 95%;
		}

		.password-form {
			padding: 20px;
		}

		.password-buttons {
			flex-direction: column;
		}

		.password-buttons button {
			margin-bottom: 10px;
		}

		.password-switcher {
			flex-direction: column;
			gap: 10px;
		}
	}
</style>

<!-- HTML структура модального окна -->
<div class="password-overlay" id="passwordOverlay"></div>

<div class="password-container" id="passwordContainer" style="display: none;">
	<div class="password-form">
		<button class="password-close" onclick="closePasswordForm()" title="Close">&times;</button>

		<div class="password-title">
			<i class="fa fa-key" style="margin-right: 10px;"></i>Change Credentials
		</div>

		<!-- Переключатель режимов -->
		<div class="password-switcher">
			<span class="password-switcher-label active" id="labelPasswordOnly">Password Only</span>

			<div style="display: inline-flex; align-items: center;">
				<input type="hidden" name="mode" value="password_only" id="modeInput">
				<input id="toggle-mode" class="toggle toggle-round-flat" type="checkbox"
					name="mode-toggle" aria-label="Toggle change mode">
				<label for="toggle-mode"></label>
			</div>

			<span class="password-switcher-label" id="labelBoth">Username & Password</span>
		</div>

		<!-- Смена пароля -->
		<div class="password-section active" id="passwordOnlySection">
			<form id="changePasswordForm">
				<div class="password-field">
					<label for="current_password">
						<i class="fa fa-lock" style="margin-right: 8px;"></i>Current Password:
					</label>
					<input type="password" id="current_password" name="current_password" placeholder="Enter current password" required>
				</div>

				<div class="password-field">
					<label for="new_password">
						<i class="fa fa-key" style="margin-right: 8px;"></i>New Password:
					</label>
					<input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
				</div>

				<div class="password-field">
					<label for="confirm_password">
						<i class="fa fa-key" style="margin-right: 8px;"></i>Confirm New Password:
					</label>
					<input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
				</div>

				<div class="password-buttons">
					<button type="submit">
						<i class="fa fa-refresh" style="margin-right: 8px;"></i>Change Password
					</button>
				</div>
			</form>

			<div class="password-message" id="passwordMessage"></div>
		</div>

		<!-- Смена логина и пароля -->
		<div class="password-section" id="bothSection">
			<form id="changeCredentialsForm">
				<div class="password-field">
					<label for="cred_current_password">
						<i class="fa fa-lock" style="margin-right: 8px;"></i>Current Password:
					</label>
					<input type="password" id="cred_current_password" name="current_password" placeholder="Enter current password" required>
				</div>

				<div class="password-field">
					<label for="new_username">
						<i class="fa fa-user" style="margin-right: 8px;"></i>New Username:
					</label>
					<input type="text" id="new_username" name="new_username" value="<?php echo PHP_AUTH_USER; ?>" placeholder="Enter new username" required>
				</div>

				<div class="password-field">
					<label for="cred_new_password">
						<i class="fa fa-key" style="margin-right: 8px;"></i>New Password:
					</label>
					<input type="password" id="cred_new_password" name="new_password" placeholder="Enter new password" required>
				</div>

				<div class="password-field">
					<label for="cred_confirm_password">
						<i class="fa fa-key" style="margin-right: 8px;"></i>Confirm New Password:
					</label>
					<input type="password" id="cred_confirm_password" name="confirm_password" placeholder="Confirm new password" required>
				</div>

				<div class="password-buttons">
					<button type="submit" class="orange-btn">
						<i class="fa fa-user-circle" style="margin-right: 8px;"></i>Change Both
					</button>
				</div>
			</form>

			<div class="password-message" id="credentialsMessage"></div>
		</div>

		<div class="password-buttons" style="margin-top: 20px;">
			<button type="button" class="cancel-btn" onclick="closePasswordForm()">
				<i class="fa fa-times" style="margin-right: 8px;"></i>Close
			</button>
		</div>
	</div>
</div>

<script>
	// Функция открытия формы смены пароля
	function openPasswordForm() {
		document.getElementById('passwordOverlay').style.display = 'block';
		document.getElementById('passwordContainer').style.display = 'block';
		document.body.style.overflow = 'hidden';
		document.getElementById('current_password').focus();

		// Сбрасываем переключатель в исходное состояние
		document.getElementById('toggle-mode').checked = false;
		switchMode(false);
	}

	// Функция закрытия формы смены пароля
	function closePasswordForm() {
		document.getElementById('passwordOverlay').style.display = 'none';
		document.getElementById('passwordContainer').style.display = 'none';
		document.getElementById('passwordMessage').style.display = 'none';
		document.getElementById('credentialsMessage').style.display = 'none';
		document.body.style.overflow = 'auto';
		document.getElementById('changePasswordForm').reset();
		document.getElementById('changeCredentialsForm').reset();
		// Восстанавливаем значение username по умолчанию
		document.getElementById('new_username').value = '<?php echo PHP_AUTH_USER; ?>';
	}

	// Переключение между режимами
	function switchMode(isBothMode) {
		const passwordSection = document.getElementById('passwordOnlySection');
		const bothSection = document.getElementById('bothSection');
		const labelPassword = document.getElementById('labelPasswordOnly');
		const labelBoth = document.getElementById('labelBoth');
		const modeInput = document.getElementById('modeInput');

		if (isBothMode) {
			passwordSection.classList.remove('active');
			bothSection.classList.add('active');
			labelPassword.classList.remove('active');
			labelBoth.classList.add('active');
			modeInput.value = 'both';
			document.getElementById('cred_current_password').focus();
		} else {
			bothSection.classList.remove('active');
			passwordSection.classList.add('active');
			labelBoth.classList.remove('active');
			labelPassword.classList.add('active');
			modeInput.value = 'password_only';
			document.getElementById('current_password').focus();
		}
	}

	// Обработчик переключателя
	document.getElementById('toggle-mode').addEventListener('change', function() {
		switchMode(this.checked);
	});

	// Обработка смены пароля
	document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
		e.preventDefault();

		const formData = new FormData(this);
		formData.append('action', 'change_password');
		const submitButton = this.querySelector('button[type="submit"]');
		const originalText = submitButton.innerHTML;

		// Показываем загрузку
		submitButton.innerHTML = '<i class="fa fa-spinner fa-spin" style="margin-right: 8px;"></i>Changing...';
		submitButton.disabled = true;

		fetch('/include/change_password.php', {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				showMessage('passwordMessage', data.message, data.success);
				if (data.success) {
					this.reset();
					setTimeout(() => {
						closePasswordForm();
					}, 2000);
				}
			})
			.finally(() => {
				submitButton.innerHTML = originalText;
				submitButton.disabled = false;
			});
	});

	// Обработка смены логина и пароля
	document.getElementById('changeCredentialsForm').addEventListener('submit', function(e) {
		e.preventDefault();

		const formData = new FormData(this);
		formData.append('action', 'change_credentials');
		const submitButton = this.querySelector('button[type="submit"]');
		const originalText = submitButton.innerHTML;

		// Показываем загрузку
		submitButton.innerHTML = '<i class="fa fa-spinner fa-spin" style="margin-right: 8px;"></i>Changing...';
		submitButton.disabled = true;

		fetch('/include/change_password.php', {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				showMessage('credentialsMessage', data.message, data.success);
				if (data.success) {
					setTimeout(() => {
						window.location.reload();
					}, 2000);
				}
			})
			.finally(() => {
				submitButton.innerHTML = originalText;
				submitButton.disabled = false;
			});
	});

	function showMessage(elementId, message, isSuccess) {
		const messageDiv = document.getElementById(elementId);
		messageDiv.style.display = 'block';
		messageDiv.textContent = message;
		messageDiv.className = isSuccess ? 'password-message success' : 'password-message';

		setTimeout(() => {
			messageDiv.style.display = 'none';
		}, 5000);
	}

	// Закрытие по клику на оверлей
	document.getElementById('passwordOverlay').addEventListener('click', function(e) {
		if (e.target === this) {
			closePasswordForm();
		}
	});

	// Закрытие по ESC
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' && document.getElementById('passwordContainer').style.display === 'block') {
			closePasswordForm();
		}
	});
</script>