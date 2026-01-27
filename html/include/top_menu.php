<?php

/**
 * @file /include/top_menu.0.4.0.php
 * @version 0.4.0.release
 * @date 2026.01.26  
 * @author vladimir@tsurkanenko.ru
 */

include_once $_SERVER["DOCUMENT_ROOT"] . '/include/init.php';
$is_authorised = false;

if (isset($_SESSION['auth']) && $_SESSION['auth'] === "AUTHORISED") {

	if (isset($_SESSION['username']) && isset($_SESSION['login_time'])) {
		$session_age = time() - $_SESSION['login_time'];

		if ($session_age < 86400) {
			$is_authorised = true;
		} else {

			$_SESSION['auth'] = 'UNAUTHORISED';
			unset($_SESSION['username']);
			unset($_SESSION['login_time']);
		}
	}
}

if (isset($_SESSION['auth']) && $_SESSION['auth'] === "AUTHORISED") {
	echo '<a href="javascript:void(0)" class="menuadmin" onclick="openLogoutModal()">' . (getTranslation('Logout') ?? 'Logout') . ' (' . ($_SESSION['username'] ?? 'user') . ')</a>';

	include $_SERVER["DOCUMENT_ROOT"] . "/include/change_password.php";
} else {

	include $_SERVER["DOCUMENT_ROOT"] . "/include/authorise.php";
	echo '<a class="menuadmin" href="javascript:void(0)" onclick="openAuthForm()">' . (getTranslation('Login') ?? 'Login') . '</a>';
}

if (isset($_SESSION['DTMF_CTRL_PTY'])) {
	include $_SERVER["DOCUMENT_ROOT"] . "/include/keypad.php";
	echo '<a class="menukeypad" href="javascript:void(0)" onclick="openKeypad()">';
	echo getTranslation('DTMF') ?? 'DTMF';
	echo '</a>';
}

if (defined("SHOW_AUDIO_MONITOR") && SHOW_AUDIO_MONITOR) {
	include $_SERVER["DOCUMENT_ROOT"] . "/include/monitor.php";
}
if (isset($_SESSION['auth']) && $_SESSION['auth'] == 'AUTHORISED') {
	echo '<a class="menusettings" href="#">';
	echo getTranslation('Settings');
	echo '</a>';
}

if (defined("SHOW_MACROS") && SHOW_MACROS) {
	echo '<a class="menumacros" href="#">';
	echo getTranslation('Macros');
	echo '</a>';
}
?>

<?php
if (defined("SHOW_CON_DETAILS") && SHOW_CON_DETAILS) : ?>
	<a class="menuconnection" href="#"> <?= getTranslation('Connect Details') ?></a>
<?php endif ?>

<?php
if (defined("SHOW_REFLECTOR_ACTIVITY") && SHOW_REFLECTOR_ACTIVITY) : ?>
	<a class="menureflector" href="#"><?= getTranslation('Reflectors'); ?></a>
<?php endif ?>

<?php if (defined("SHOW_AUDIO_MONITOR") && SHOW_AUDIO_MONITOR): ?>
	<a href="#" class="menuaudio" onclick="toggleMonitor(); return false;"><?php echo getTranslation('Monitor') ?></span></a>
<?php endif; ?>

<a class="menudashboard" href="/index.php"><?php echo getTranslation('Dashboard'); ?></a>


<div id="logoutModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;">
	<div style="background: #2e363f; padding: 20px; border-radius: 5px; border: 1px solid #3c3f47; min-width: 300px; text-align: center;">
		<h3 style="color: #bebebe; margin-bottom: 20px;"><?php echo getTranslation('Adminisration') ?></h3>
		<div style="display: flex; flex-direction: column; gap: 10px;">
			<a class="menuadmin" href="javascript:void(0)" onclick="openPasswordForm(); closeLogoutModal();" style="display: block; padding: 10px; text-align: center;"><?php echo getTranslation('Change password') ?></a>
			<a class="menuconfig" href="/include/settings.php" style="display: block; padding: 10px; text-align: center;"><?php echo getTranslation('Settings') ?? 'Settings'; ?></a>
			<a href="/include/logout.php" class="menuadmin" style="display: block; padding: 10px; text-align: center;"><?php echo getTranslation('Logout') ?? 'Logout'; ?></a>
			<?php

			?>
		</div>
		<button onclick="closeLogoutModal()" style="margin-top: 20px; padding: 8px 16px; background: #65737e; color: #bebebe; border: 1px solid #3c3f47; cursor: pointer;"><?php echo getTranslation('Cancel') ?></button>
	</div>
</div>

<script>
	function openLogoutModal() {
		document.getElementById('logoutModal').style.display = 'flex';
	}

	function closeLogoutModal() {
		document.getElementById('logoutModal').style.display = 'none';
	}

	document.addEventListener('click', function(event) {
		const modal = document.getElementById('logoutModal');
		if (event.target === modal) {
			closeLogoutModal();
		}
	});

	document.addEventListener('keydown', function(event) {
		if (event.key === 'Escape') {
			closeLogoutModal();
		}
	});

	document.addEventListener('DOMContentLoaded', () => {
		const blocks = {
			'reflector_activity': 'menureflector',
			'macros_panel': 'menumacros',
			'connection_details': 'menuconnection',
			'debug_section': 'menudebug',
		};

		for (const blockId in blocks) {
			const block = document.getElementById(blockId);
			const menuClass = blocks[blockId];
			const link = document.querySelector(`a.${menuClass}`);
			if (block && link) {
				try {
					const isHidden = localStorage.getItem(`${blockId}_hidden`) === 'true';

					if (isHidden) {
						block.classList.add('hidden');
					} else {
						link.classList.add('icon-active');
					}
				} catch (e) {
					console.error('Log read error from localStorage:', e);
				}

				link.addEventListener('click', (e) => {
					e.preventDefault();
					const isNowHidden = block.classList.toggle('hidden');
					link.classList.toggle('icon-active');

					try {
						localStorage.setItem(`${blockId}_hidden`, isNowHidden);
					} catch (e) {
						console.error('Error saving to localStorage:', e);
					}
				});
			}
		}
	});
</script>