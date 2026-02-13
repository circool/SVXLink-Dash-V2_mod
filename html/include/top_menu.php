<?php

/**
 * @file /include/top_menu.php
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.11
 * @version 0.4.6
 */

if (SHOW_AUTH) {
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
}

$is_dtmf_available = false;
if (isset($_SESSION['status'])) {
	foreach ($_SESSION['status']['logic'] as $logics) {
		if ($logics['type'] === "Reflector") continue;
		if (isset($logics['dtmf_cmd'])) {
			$is_dtmf_available = true;
			break;
		}
	}
	if ($is_dtmf_available) {
		include $_SERVER["DOCUMENT_ROOT"] . "/include/keypad.php";
		echo '<a class="menukeypad" href="javascript:void(0)" onclick="showKeypad()">';
		echo getTranslation('DTMF') ?? 'DTMF';
		echo '</a>';
	}
}

if (defined("SHOW_AUDIO_MONITOR") && SHOW_AUDIO_MONITOR) {
	include $_SERVER["DOCUMENT_ROOT"] . "/include/monitor.php";
}

if (SHOW_AUTH) {
	if (isset($_SESSION['auth']) && $_SESSION['auth'] == 'AUTHORISED') {
		echo '<a class="menusettings" href="#">';
		echo getTranslation('Settings');
		echo '</a>';
	}
}

if (defined("SHOW_MACROS") && SHOW_MACROS) {
	echo '<a class="menumacros" href="#">';
	echo getTranslation('Macros');
	echo '</a>';
}
?>
<?php if (defined("DEBUG") && DEBUG): ?>
	<a class="menudebug" href="#"><?php echo getTranslation('Debug'); ?></a>
<?php endif ?>

<?php
if (defined("SHOW_CON_DETAILS") && SHOW_CON_DETAILS) : ?>
	<a class="menuconnection" href="#"> <?= getTranslation('Connect Details') ?></a>
<?php endif ?>



<?php if (defined("SHOW_AUDIO_MONITOR") && SHOW_AUDIO_MONITOR): ?>
	<a href="#" class="menuaudio" onclick="toggleMonitor(); return false;"><?php echo getTranslation('Monitor') ?></span></a>
<?php endif; ?>

<a class="menudashboard" href="/index.php"><?php echo getTranslation('Dashboard'); ?></a>

<?php if (SHOW_AUTH): ?>
	<div id="logoutModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;">
		<div style="background: #2e363f; padding: 20px; border-radius: 5px; border: 1px solid #3c3f47; min-width: 300px; text-align: center;">
			<h3 style="color: #bebebe; margin-bottom: 20px;"><?php echo getTranslation('Adminisration') ?></h3>
			<div style="display: flex; flex-direction: column; gap: 10px;">
				<a class="menuadmin" href="javascript:void(0)" onclick="openPasswordForm(); closeLogoutModal();" style="display: block; padding: 10px; text-align: center;"><?php echo getTranslation('Change password') ?></a>
				<a class="menuconfig" href="/include/settings.php" style="display: block; padding: 10px; text-align: center;"><?php echo getTranslation('Settings') ?? 'Settings'; ?></a>
				<a href="/include/logout.php" class="menuadmin" style="display: block; padding: 10px; text-align: center;"><?php echo getTranslation('Logout') ?? 'Logout'; ?></a>
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
		</script>
		<?php endif; ?>

			<script>
			document.addEventListener('DOMContentLoaded', () => {
				const blocks = [];

				

				<?php if (defined("SHOW_MACROS") && SHOW_MACROS): ?>
					blocks.push({
						id: 'macros_panel',
						class: 'menumacros'
					});
				<?php endif; ?>

				<?php if (defined("SHOW_CON_DETAILS") && SHOW_CON_DETAILS): ?>
					blocks.push({
						id: 'connection_details',
						class: 'menuconnection'
					});
				<?php endif; ?>

				<?php if (defined("DEBUG") && DEBUG): ?>
					blocks.push({
						id: 'debug_block',
						class: 'menudebug'
					});
				<?php endif; ?>

				blocks.forEach(blockInfo => {
					const block = document.getElementById(blockInfo.id);
					const link = document.querySelector(`a.${blockInfo.class}`);

					if (block && link) {
						try {
							const isHidden = localStorage.getItem(`${blockInfo.id}_hidden`) === 'true';

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

							const isHidden = block.classList.contains('hidden');
							const willBeHidden = !isHidden;

							if (block.dataset.animating) return;
							block.dataset.animating = 'true';

							if (willBeHidden) {
								block.classList.remove('hidden');

								setTimeout(() => {
									const height = block.offsetHeight;

									block.style.transition = 'max-height 0.3s ease, opacity 0.3s ease';
									block.style.maxHeight = height + 'px';
									block.style.opacity = '1';
									block.style.overflow = 'hidden';

									setTimeout(() => {
										block.style.maxHeight = '0';
										block.style.opacity = '0';

										setTimeout(() => {
											block.classList.add('hidden');
											block.style.cssText = '';
											delete block.dataset.animating;
										}, 300);
									}, 10);
								}, 10);

							} else {
								block.classList.remove('hidden');
								block.style.transition = 'max-height 0.3s ease, opacity 0.3s ease';
								block.style.maxHeight = '0';
								block.style.opacity = '0';
								block.style.overflow = 'hidden';

								setTimeout(() => {
									const height = block.scrollHeight;
									block.style.maxHeight = height + 'px';
									block.style.opacity = '1';

									setTimeout(() => {
										block.style.cssText = '';
										delete block.dataset.animating;
									}, 300);
								}, 10);
							}

							link.classList.toggle('icon-active');

							try {
								localStorage.setItem(`${blockInfo.id}_hidden`, willBeHidden);
							} catch (e) {
								console.error('Error saving to localStorage:', e);
							}
						});
					}
				});
			});
	</script>