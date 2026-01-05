<?php

/**
 * @filesource /include/exct/index_debug.0.3.2.php
 * @version 0.3.2
 * @date 2026.01.03
 * @author vladimir@tsurkanenko.ru
 * @description Главная страница отладочного режима
 * @note Изменения в 0.3.2:
 * - Удалён WebSocket кнопки управления WS, стартер, конфигурация и подключение клиента (в init.php)
 */

require_once $_SERVER["DOCUMENT_ROOT"] . "/include/init.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/include/fn/getTranslation.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/include/fn/dlog.0.2.php";

$ver = "index_debug.php 0.3.2-fixed";
if (defined("DEBUG") && DEBUG) dlog("$ver: Начинаю работу", 3, "WARNING");

// Проверяем авторизацию
$auth_file = AUTH_FILE;
if (!file_exists($auth_file)) {
	$current_page = $_SERVER['PHP_SELF'] ?? '';
	if (strpos($current_page, AUTH_SETUP) === false) {
		header('Location: /' . AUTH_SETUP);
		exit;
	}
}


?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= DASHBOARD_NAME ?> v. <?= DASHBOARD_VERSION ?></title>

	<!-- jQuery -->
	<script src="/scripts/jquery.min.js"></script>



	<!-- Стили -->
	<link rel="stylesheet" type="text/css" href="/css/font-awesome-4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="/fonts/stylesheet.css">
	<link rel="stylesheet" href="/css/css.php">
	<link rel="stylesheet" href="/css/menu.php">
	<?php
	if (!defined("WS_ENABLED") || constant("WS_ENABLED") === true) {
	echo '<link rel="stylesheet" href="/css/websocket_control.css">';
	}?>

</head>

<body>
	<div class="container">
		<div class="header">
			<div class="SmallHeader shLeft noMob">
				<a style="border-bottom: 1px dotted;" class="tooltip" href="#"><?php echo getTranslation('Node Name'); ?>: <span><strong><?php echo getTranslation('Node IP'); ?><br></strong><?php echo str_replace(',', ',<br />', exec("hostname -I | awk '{print $1}'")); ?></span><?php echo exec('cat /etc/hostname'); ?></a>
			</div>
			<h1><?= DASHBOARD_NAME ?><code style="font-weight:550;">(<?php echo (isset($_SESSION['status']['callsign'])) ? $_SESSION['status']['callsign'] : "NO CALLSIGN" ?>) </code><?php echo DEBUG ? '<span class="debug">ОТЛАДКА</span>' : ''; ?></h1>

			<div class="navbar">
				<div class="headerClock">
					<span id="DateTime"><?= date('d-m-Y H:i:s') ?></span>
				</div>

				<?php
				require_once $_SERVER["DOCUMENT_ROOT"] . "/include/top_menu.php";
				// Дополнительные кнопки управления WebSocket удалены
				?>

			</div>
		</div>
		<br class="noMob">
		<!-- далее блоки nav и content -->
		<div class="nav">
			<div id="leftPanel">
				<?php include $_SERVER["DOCUMENT_ROOT"] . "/include/left_panel.php"; ?>
			</div>
		</div>
		<div class="content">
			<div id="lcmsg" style="background:#d6d6d6;color:black; margin:0 0 10px 0;"></div>

			<!-- Функциональные блоки -->
			<div id="radio_activity">
				<?php
				if (!defined("SHOW_RADIO_ACTIVITY") || constant("SHOW_RADIO_ACTIVITY") === true) {
					include $_SERVER["DOCUMENT_ROOT"] . "/include/radio_activity.php";
				}
				?>
			</div>
			<br>
			<div id="connection_details">
				<?php
				if (!defined("SHOW_CON_DETAILS") || constant("SHOW_CON_DETAILS") === true) {
					include $_SERVER["DOCUMENT_ROOT"] . "/include/connection_details.php";
				}
				?>
			</div>
			<br>
			<div id="reflector_activity">
				<?php
				if (!defined("SHOW_REFLECTOR_ACTIVITY") || constant("SHOW_REFLECTOR_ACTIVITY") === true) {
					include $_SERVER["DOCUMENT_ROOT"] . "/include/reflector_activity.php";
				}
				?>
			</div>
			<br>
			<div id="net_activity">
				<?php
				if (!defined("SHOW_NET_ACTIVITY") || constant("SHOW_NET_ACTIVITY") === true) {
					include $_SERVER["DOCUMENT_ROOT"] . "/include/net_activity.php";
				}
				?>
			</div>
			<br>
			<div id="local_activity">
				<?php
				if (!defined("SHOW_RF_ACTIVITY") || constant("SHOW_RF_ACTIVITY") === true) {
					include $_SERVER["DOCUMENT_ROOT"] . "/include/rf_activity.php";
				}
				?>
			</div>

			<!-- Отладка -->
			<?php if (defined("DEBUG") && DEBUG): ?>

				<div style="margin-top: 20px; padding: 10px; border: 1px solid #ddd;">
					<h4>Debug Console Websocket</h4>
					<div id="debugLog" style="height: 300px; overflow-y: auto;"></div>
				</div>

				<div class="debug" id="session_debug">
					<?php
					include $_SERVER["DOCUMENT_ROOT"] . "/include/debug_page.php";
					?>
				</div>

			<?php endif; ?>
		</div>
	</div>

	<script>
		// Обновление часов
		function updateClock() {
			const now = new Date();
			document.getElementById('DateTime').textContent =
				now.toLocaleDateString('ru-RU') + ' ' + now.toLocaleTimeString('ru-RU');
		}
		setInterval(updateClock, 1000);

		function addDebugLog(message) {
			const log = document.getElementById('debugLog');
			if (log) {
				const time = new Date().toLocaleTimeString();
				log.innerHTML = `[${time}] ${message}<br>` + log.innerHTML;

				// Ограничиваем количество сообщений
				if (log.children.length > 50) {
					log.removeChild(log.lastChild);
				}
			}
		}

		// Экспортируем для отладки
		window.addDebugLog = addDebugLog;
	</script>
</body>

</html>