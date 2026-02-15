<?php

/**
 * @filesource /include/websocket_client_config.php
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.11
 * @version 0.4.6
 */

if (!defined('DASHBOARD_HOST')) {
	define('DASHBOARD_HOST', $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_ADDR'] ?? 'localhost'));
}

$wsConnectedText = getTranslation('Realtime');
$wsDisconnectedText = getTranslation('Periodic');
$wsConnectingText = getTranslation('Connecting');


if (defined('WS_ENABLED') && WS_ENABLED): ?>
	<script>
		window.DASHBOARD_CONFIG = window.DASHBOARD_CONFIG || {};
		window.DASHBOARD_CONFIG.websocket = {
			enabled: true,
			host: "<?php echo DASHBOARD_HOST; ?>",
			port: <?php echo defined('WS_PORT') ? WS_PORT : 8080; ?>,
			path: "<?php echo defined('WS_PATH') ? WS_PATH : '/ws'; ?>",
			autoConnect: true,
			reconnectDelay: 3000,
			maxReconnectAttempts: 5,
			pingInterval: 30000,
			debugConsole: <?php echo defined('DEBUG_LOG_CONSOLE') && DEBUG_LOG_CONSOLE ? "true" : "false"; ?>,
			debugLevel: "<?php echo defined('DEBUG_VERBOSE') ? DEBUG_VERBOSE : 2; ?>",
			debugWebConsole: <?php echo defined('DEBUG_WEB_CONSOLE') && DEBUG_WEB_CONSOLE ? "true" : "false"; ?>,
			translations: {
				connected: "<?php echo $wsConnectedText; ?>",
				disconnected: "<?php echo $wsDisconnectedText; ?>",
				connecting: "<?php echo $wsConnectingText; ?>",
			}
		};
	</script>
<?php endif; ?>