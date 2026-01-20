<?php

/**
 * @filesource /include/exct/index_debug.0.4.1.php
 * @version 0.4.1
 * @date 2026.01.12
 * @author vladimir@tsurkanenko.ru
 * @description Главная страница отладочного режима
 * @since 0.3.2:
 * - Удалён WebSocket кнопки управления WS, стартер, конфигурация и подключение клиента (в init.php)
 * @since 0.4.1
 * Запуск ws сервера и клиента вернул в index
 */



require_once $_SERVER["DOCUMENT_ROOT"] . "/include/init.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/include/fn/getTranslation.php";
// $_SESSION['dashboard_lang'] = 'ru';
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $_SESSION['dashboard_lang'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

if (defined("DEBUG") && DEBUG) {
	require_once $_SERVER["DOCUMENT_ROOT"] . "/include/fn/dlog.php";
	$main_funct_start = microtime(true);
	$main_ver = "index_debug.php 0.4.1";
	dlog("----------------------------------------------------", 3, "INFO");
	dlog("$main_ver: Начинаю работу", 3, "INFO");
	dlog("----------------------------------------------------", 3, "INFO");
}





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
	<?php if (!defined("WS_ENABLED") || constant("WS_ENABLED") === true): ?>
		<!-- Конфигурация WebSocket (в head для ранней доступности) -->
		<?php include_once $_SERVER["DOCUMENT_ROOT"] . '/include/websocket_client_config.php'; ?>
		<link rel="stylesheet" href="/css/websocket_control.css">
	<?php endif; ?>

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
				?>

			</div>
		</div>
		<div class="contentwide">
			<?php
			if (!defined("SHOW_MACROS") || constant("SHOW_MACROS") === true) {
				include $_SERVER["DOCUMENT_ROOT"] . "/include/macros.php";
			}
			?>
			<div id="radioInfo">
				<div class="divTable">
					<div class="divTableBody">
						<div class="divTableRow center">
							<!-- <div class="divTableHeadCell noMob" style="width:250px;">Radio Status</div>
							<div class="divTableHeadCell noMob">TX Freq.</div>
							<div class="divTableHeadCell noMob">RX Freq.</div>
							<div class="divTableHeadCell noMob">Radio Mode</div>
							<div class="divTableHeadCell noMob">Modem Port</div>
							<div class="divTableHeadCell noMob">Modem Speed</div>
							<div class="divTableHeadCell noMob">TCXO Freq.</div>
							<div class="divTableHeadCell noMob">Modem Type</div> -->
						</div>
						<div class="divTableRow center">
							<!-- <div class="divTableCell middle cell_content" style="font-weight:bold;padding:2px;">IDLE</div>
							<div class="divTableCell cell_content middle noMob" style="background: #949494;">145.575 MHz</div>
							<div class="divTableCell cell_content middle noMob" style="background: #949494;">144.975 MHz</div>
							<div class="divTableCell cell_content middle noMob" style="background: #949494;">Duplex</div>
							<div class="divTableCell cell_content middle noMob" style="background: #949494;">/dev/ttyAMA0</div>
							<div class="divTableCell cell_content middle noMob" style="background: #949494;">115,200 bps</div>
							<div class="divTableCell cell_content middle noMob" style="background: #949494;">14.7456 MHz</div>
							<div class="divTableCell cell_content middle noMob" style="background: #949494;">MMDVM_HS_Dual_Hat-v.1.6.1</div> -->
						</div>
					</div>
				</div>

			</div>
			<!-- <br class="noMob"> -->
		</div>
		<br class="noMob">
		<div class="leftnav">
			<div id="leftPanel">
				<?php include $_SERVER["DOCUMENT_ROOT"] . "/include/left_panel.php"; ?>
			</div>
		</div>
		<div class="content">
			<div id="lcmsg" style="background:#d6d6d6;color:black; margin:0 0 10px 0;"></div>


			<?php
			if (!defined("SHOW_RADIO_ACTIVITY") || constant("SHOW_RADIO_ACTIVITY") === true) {
				include $_SERVER["DOCUMENT_ROOT"] . "/include/radio_activity.php";
			}		
			?>

			<?php
			if (!defined("SHOW_CON_DETAILS") || constant("SHOW_CON_DETAILS") === true) {
				include $_SERVER["DOCUMENT_ROOT"] . "/include/connection_details.php";
			}
			?>



			<?php
			if (!defined("SHOW_REFLECTOR_ACTIVITY") || constant("SHOW_REFLECTOR_ACTIVITY") === true) {
				include $_SERVER["DOCUMENT_ROOT"] . "/include/reflector_activity.php";
			}
			?>

			
			<?php
			if (!defined("SHOW_NET_ACTIVITY") || constant("SHOW_NET_ACTIVITY") === true) {
				include $_SERVER["DOCUMENT_ROOT"] . "/include/net_activity.php";
			}
			?>
			
			
			
			<?php	
			if (!defined("SHOW_RF_ACTIVITY") || constant("SHOW_RF_ACTIVITY") === true) {
				include $_SERVER["DOCUMENT_ROOT"] . "/include/rf_activity.php";
			}	
			?>
			

			<?php
			if (defined("DEBUG") && DEBUG) {
				$main_funct_time = microtime(true) - $main_funct_start;
				dlog("----------------------------------------------------", 3, "INFO");
				dlog("$main_ver: Закончил работу за $main_funct_time мсек", 3, "INFO");
				dlog("----------------------------------------------------", 3, "INFO");
				unset($main_ver, $main_funct_start, $main_funct_time);
			}
			?>

			<?php if (defined("DEBUG") && DEBUG): ?>
				<div id="debug_section">
					<div  style="margin-top: 20px; padding: 10px; border: 1px solid #ddd;">
						<h4>Debug Console Websocket</h4>
						<div id="debugLog" style="height: 300px; overflow-y: auto;"></div>				
					</div>

					<div class="debug" id="session_debug">
						<?php
						include $_SERVER["DOCUMENT_ROOT"] . "/include/debug_page.php";
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		
		<div id="footer" class="footer">
			<?php include $_SERVER['DOCUMENT_ROOT'] . "/include/footer.php"; ?>
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
		<?php if (!defined("WS_ENABLED") || constant("WS_ENABLED") === true): ?>
			<!-- Серверная логика WebSocket -->
			<?php include_once $_SERVER["DOCUMENT_ROOT"] . '/include/websocket_server.php'; ?>

			<!-- Клиентский скрипт (подключается в конце) -->
			<script src="/scripts/dashboard_ws_client.js"></script>
		<?php endif; ?>
	</div>
</body>

</html>