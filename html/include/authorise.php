<?php

/**
 * Форма авторизации в модальном окне
 * @filesource /include/authorise.php
 * @author vladimir@tsurkanenko.ru
 * @version 0.0.1
 * @note Preliminary version.
 */




require_once $_SERVER["DOCUMENT_ROOT"] . "/include/init.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/include/fn/getTranslation.php";

?>
<link rel="stylesheet" href="/css/menu.css">
<div class="auth-overlay" id="authOverlay"></div>
<div class="auth-container" id="authContainer" style="display: none;">
	<div class="auth-form">
		<button class="auth-close" onclick="closeAuthForm()" title="Close">&times;</button>

		<div class="auth-title">
			<i class="fa fa-lock" style="margin-right: 10px;"></i><?= getTranslation('Authorization') ?>
		</div>

		<form id="authForm" method="POST" action="/include/auth_handler.php">
			<div class="auth-field">
				<label for="username">
					<i class="fa fa-user" style="margin-right: 8px;"></i><?= getTranslation('Username') ?>:
				</label>
				<input type="text" id="username" name="username" placeholder="<?= getTranslation('Enter your username') ?>" required autocomplete="username">
			</div>

			<div class="auth-field">
				<label for="password">
					<i class="fa fa-key" style="margin-right: 8px;"></i><?= getTranslation('Password') ?>:
				</label>
				<input type="password" id="password" name="password" placeholder="<?= getTranslation('Enter your password') ?>" required autocomplete="current-password">
			</div>

			<div class="auth-error" id="authError">
				<i class="fa fa-exclamation-triangle" style="margin-right: 8px;"></i><?= getTranslation('Invalid credentials. Please try again.') ?>
			</div>

			<div class="auth-buttons">
				<button type="submit">
					<i class="fa fa-sign-in" style="margin-right: 8px;"></i><?= getTranslation('Login') ?>
				</button>
				<button type="button" onclick="closeAuthForm()">
					<i class="fa fa-times" style="margin-right: 8px;"></i><?= getTranslation('Cancel') ?>
				</button>
			</div>
		</form>
	</div>
</div>

<script>
	
	function openAuthForm() {
		document.getElementById('authOverlay').style.display = 'block';
		document.getElementById('authContainer').style.display = 'block';
		document.body.style.overflow = 'hidden';
		document.getElementById('username').focus();
	}

	function closeAuthForm() {
		document.getElementById('authOverlay').style.display = 'none';
		document.getElementById('authContainer').style.display = 'none';
		document.getElementById('authError').style.display = 'none';
		document.body.style.overflow = 'auto';
		document.getElementById('authForm').reset();
	}

	document.getElementById('authForm').addEventListener('submit', function(e) {
		e.preventDefault();
		const formData = new FormData(this);
		const submitButton = this.querySelector('button[type="submit"]');
		const originalText = submitButton.innerHTML;

		submitButton.innerHTML = '<i class="fa fa-spinner fa-spin" style="margin-right: 8px;"></i><?= getTranslation('Logging in') ?>...';
		submitButton.disabled = true;

		fetch('/include/auth_handler.php', {
				method: 'POST',
				body: formData,
				credentials: 'include'
			})
			.then(response => {
				console.log("Responce received");
				console.log("Status:", response.status);
				console.log("Status text:", response.statusText);
				console.log("Headers:", Object.fromEntries(response.headers.entries()));

				if (!response.ok) {
					console.error("Response not OK:", response.statusText);
					throw new Error('HTTP error: ' + response.status);
				}

				return response.text().then(text => {
					
					try {
						const data = JSON.parse(text);
						return data;
					} catch (e) {
						console.error("JSON parse error:", e);
						console.error("Response text was:", text);
						throw new Error('Invalid JSON response');
					}
				});
			})
			.then(data => {
				if (data.success) {
					
					closeAuthForm();
					setTimeout(() => {
						window.location.reload();
					}, 100);
				} else {
					console.log("Login failed:", data.message);
					document.getElementById('authError').style.display = 'block';
					document.getElementById('password').value = '';
					document.getElementById('username').focus();
				}
			})
			.catch(error => {
				console.error("FETCH ERROR");
				console.error("Error:", error);
				console.error("Error message:", error.message);
				console.error("Stack:", error.stack);

				document.getElementById('authError').style.display = 'block';
				document.getElementById('authError').textContent = 'Error: ' + error.message;
			})
			.finally(() => {
				submitButton.innerHTML = originalText;
				submitButton.disabled = false;
			});
	});


	document.getElementById('authOverlay').addEventListener('click', function(e) {
		if (e.target === this) {
			closeAuthForm();
		}
	});


	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' && document.getElementById('authContainer').style.display === 'block') {
			closeAuthForm();
		}
	});
</script>