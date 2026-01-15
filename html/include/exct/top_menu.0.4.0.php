<?php

/**
 * Меню команд
 * @file /include/exct/top_menu.0.4.0.php
 * @version 0.3.2
 * @date 2026.01.15
 * @author vladimir@tsurkanenko.ru
 * @note Новое в 0.2.2
 * - WebSocket статус заменен на элемент в стиле меню с иконкой FontAwesome
 * - Убрано цветовое выделение, добавлены 3 состояния: отключено, подключено, ошибка
 * @note Новое в 0.3.1
 * - Добавлена поддержка константы SHOW_AUDIO_MONITOR
 * - Убраны кнопки переподключения к ws
 * @note Новое в 0.4.0
 * - Добавлено управление видимостью блока Reflectors при клике на кнопку
 */
$ver = 'top_menu 0.4.0';
include_once $_SERVER["DOCUMENT_ROOT"] . '/include/init.php';
$is_authorised = false;

if (isset($_SESSION['auth']) && $_SESSION['auth'] === "AUTHORISED") {
	// Дополнительная проверка - есть ли username и не истекло ли время
	if (isset($_SESSION['username']) && isset($_SESSION['login_time'])) {
		$session_age = time() - $_SESSION['login_time'];
		// Сессия действительна 24 часа
		if ($session_age < 86400) {
			$is_authorised = true;
		} else {
			// Сессия истекла
			$_SESSION['auth'] = 'UNAUTHORISED';
			unset($_SESSION['username']);
			unset($_SESSION['login_time']);
		}
	}
}

if (isset($_SESSION['auth']) && $_SESSION['auth'] === "AUTHORISED") {
	echo '<a href="javascript:void(0)" class="menuadmin" onclick="openLogoutModal()">' . (getTranslation('Logout') ?? 'Logout') . ' (' . ($_SESSION['username'] ?? 'user') . ')</a>';

	// Включаем скрипты модальных окон
	include $_SERVER["DOCUMENT_ROOT"] . "/include/change_password.php";
} else {
	// Включаем скрипт модального окна авторизации
	include $_SERVER["DOCUMENT_ROOT"] . "/include/authorise.php";
	echo '<a class="menuadmin" href="javascript:void(0)" onclick="openAuthForm()">' . (getTranslation('Login') ?? 'Login') . '</a>';
}

// Включаем DTMF keypad
if (isset($_SESSION['DTMF_CTRL_PTY'])) {
	include $_SERVER["DOCUMENT_ROOT"] . "/include/keypad.php";
	echo '<a class="menukeypad" href="javascript:void(0)" onclick="openKeypad()">';
	echo getTranslation('DTMF') ?? 'DTMF';
	echo '</a>';
}

// @since 0.3.1
// Включаем мониторинг
if (defined("SHOW_AUDIO_MONITOR") && SHOW_AUDIO_MONITOR) {
	include $_SERVER["DOCUMENT_ROOT"] . "/include/monitor.php";
}

?>
<a class="menureset" href="/">Reset Session</a>

<?php
// if (defined("SHOW_CON_DETAILS") && SHOW_CON_DETAILS) {
// 	echo '<a class="menuconnection" href="#">';
// 	echo getTranslation('Connect Details');
// 	echo '</a>';
// }
?>

<?php
if (defined("SHOW_REFLECTOR_ACTIVITY") && SHOW_REFLECTOR_ACTIVITY) {
	echo '<a class="menureflector" href="javascript:void(0)" onclick="toggleReflectorsVisibility()">';
	echo getTranslation('Reflectors') ?? 'Reflectors';
	echo '</a>';
}


?>

<?php if (defined("SHOW_AUDIO_MONITOR") && SHOW_AUDIO_MONITOR): ?>
	<a href="#" class="menuaudio" onclick="toggleMonitor(); return false;">Monitor</span></a>
<?php endif; ?>

<a class="menudashboard" href="/index_debug.php"><?php echo getTranslation('Dashboard') ?? 'Dashboard'; ?></a>

<!-- 
@deprecated -> dashboard_ws_client.0.3.2
WebSocket статус в стиле меню 

<a href="javascript:void(0)" class="menuwebsocket menuwebsocket-disconnected" id="websocketStatus">
	<span id="websocketStatusText">WebSocket Offline</span>
</a>
-->



<!-- Модальное окно для выхода -->
<div id="logoutModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;">
	<div style="background: #2e363f; padding: 20px; border-radius: 5px; border: 1px solid #3c3f47; min-width: 300px; text-align: center;">
		<h3 style="color: #bebebe; margin-bottom: 20px;">Adminisration</h3>
		<div style="display: flex; flex-direction: column; gap: 10px;">
			<a class="menuadmin" href="javascript:void(0)" onclick="openPasswordForm(); closeLogoutModal();" style="display: block; padding: 10px; text-align: center;">Change Password</a>
			<a class="menuconfig" href="/include/settings.php" style="display: block; padding: 10px; text-align: center;"><?php echo getTranslation('Settings') ?? 'Settings'; ?></a>
			<a href="/include/logout.php" class="menuadmin" style="display: block; padding: 10px; text-align: center;"><?php echo getTranslation('Logout') ?? 'Logout'; ?></a>
			<?php

			?>
		</div>
		<button onclick="closeLogoutModal()" style="margin-top: 20px; padding: 8px 16px; background: #65737e; color: #bebebe; border: 1px solid #3c3f47; cursor: pointer;">Cancel</button>
	</div>
</div>

<script>
	function openLogoutModal() {
		document.getElementById('logoutModal').style.display = 'flex';
	}

	function closeLogoutModal() {
		document.getElementById('logoutModal').style.display = 'none';
	}

	// Закрытие модального окна при клике вне его
	document.addEventListener('click', function(event) {
		const modal = document.getElementById('logoutModal');
		if (event.target === modal) {
			closeLogoutModal();
		}
	});

	// Закрытие модального окна при нажатии Escape
	document.addEventListener('keydown', function(event) {
		if (event.key === 'Escape') {
			closeLogoutModal();
		}
	});

	// Функция для обновления статуса WebSocket (будет вызываться из UpdateManager)
	// @deprecated - > dashboard_ws_client .0 .3 .2
	// function updateWebSocketStatus(status, message) {
	// 	const element = document.getElementById('websocketStatus');
	// 	const textElement = document.getElementById('websocketStatusText');

	// 	if (element && textElement) {
	// 		// Удаляем все классы состояний
	// 		element.classList.remove('menuwebsocket-disconnected', 'menuwebsocket-connected', 'menuwebsocket-error', 'menuwebsocket-connecting');

	// 		// Добавляем соответствующий класс состояния
	// 		element.classList.add('menuwebsocket-' + status);

	// 		// Обновляем текст (Убрал префикс)
	// 		textElement.textContent = '' + message;
	// 	}
	// }

	// Экспортируем функцию для использования в UpdateManager
	// window.updateWebSocketStatus = updateWebSocketStatus;

	// Функция для переключения видимости блока Reflectors
	function toggleReflectorsVisibility() {
		const reflectorBlock = document.getElementById('reflector_activity');
		if (reflectorBlock) {
			// Переключаем класс hidden
			reflectorBlock.classList.toggle('hidden');

			// Сохраняем состояние в localStorage для сохранения между перезагрузками страницы
			const isHidden = reflectorBlock.classList.contains('hidden');
			localStorage.setItem('reflectorsHidden', isHidden);

			// Обновляем текст кнопки для индикации состояния
			const reflectorLink = document.querySelector('a.menureflector');
			if (reflectorLink) {
				if (isHidden) {
					reflectorLink.style.opacity = '0.6';
					reflectorLink.title = 'Показать информацию о рефлекторах';
				} else {
					reflectorLink.style.opacity = '1';
					reflectorLink.title = 'Скрыть информацию о рефлекторах';
				}
			}
		}
	}

	// Функция для восстановления состояния при загрузке страницы
	function restoreReflectorsVisibility() {
		const reflectorBlock = document.getElementById('reflector_activity');
		const reflectorLink = document.querySelector('a.menureflector');

		if (reflectorBlock && reflectorLink) {
			// Проверяем сохраненное состояние
			const wasHidden = localStorage.getItem('reflectorsHidden') === 'true';

			if (wasHidden) {
				reflectorBlock.classList.add('hidden');
				reflectorLink.style.opacity = '0.6';
				reflectorLink.title = 'Показать информацию о рефлекторах';
			} else {
				reflectorBlock.classList.remove('hidden');
				reflectorLink.style.opacity = '1';
				reflectorLink.title = 'Скрыть информацию о рефлекторах';
			}
		}
	}

	// Инициализируем при загрузке DOM
	document.addEventListener('DOMContentLoaded', restoreReflectorsVisibility);
</script>