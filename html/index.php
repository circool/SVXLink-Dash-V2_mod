<?php

/**
 * @filesource /index.php
 * @version 0.4.1.release
 * @date 2026.01.26
 * @author vladimir@tsurkanenko.ru
 */



require_once $_SERVER["DOCUMENT_ROOT"] . "/include/init.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/include/fn/getTranslation.php";
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $_SESSION['dashboard_lang'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];


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
	<script src="/scripts/jquery.min.js"></script>
	<link rel="stylesheet" type="text/css" href="/css/font-awesome-4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="/fonts/stylesheet.css">
	<link rel="stylesheet" href="/css/css.php">
	<link rel="stylesheet" href="/css/menu.php">
	<?php if (!defined("WS_ENABLED") || constant("WS_ENABLED") === true): ?>
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
			<h1><?= DASHBOARD_NAME ?><code style="font-weight:550;">(<?php echo (isset($_SESSION['status']['callsign'])) ? $_SESSION['status']['callsign'] : "NO CALLSIGN" ?>) </code></h1>

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

		</div>

		<div id="footer" class="footer">
			<?php include $_SERVER['DOCUMENT_ROOT'] . "/include/footer.php"; ?>
		</div>

		<script>
			function updateClock() {
				const now = new Date();
				document.getElementById('DateTime').textContent =
					now.toLocaleDateString('ru-RU') + ' ' + now.toLocaleTimeString('ru-RU');
			}
			setInterval(updateClock, 1000);
		</script>

		<?php if (!defined("WS_ENABLED") || constant("WS_ENABLED") === true): ?>
			<?php include_once $_SERVER["DOCUMENT_ROOT"] . '/include/websocket_server.php'; ?>
			<script src="/scripts/dashboard_ws_client.js"></script>
		<?php endif; ?>

		<script>
			window.UPDATE_INTERVAL = <?php echo defined('UPDATE_INTERVAL') ? UPDATE_INTERVAL : 3000; ?>;
		</script>
		<script src="/scripts/block_updater.js"></script>
	
	</div>
</body>

</html>