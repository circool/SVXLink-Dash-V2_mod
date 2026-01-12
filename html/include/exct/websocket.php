<?php
// ======================================================================
// === WebSocket АВТОСТАРТ С ЛОГИРОВАНИЕМ ===
// ======================================================================



$port = defined('WS_PORT') ? WS_PORT : 8080;
// Сервер слушает на всех интерфейсах для сетевого доступа
$host = defined('WS_HOST') ? WS_HOST : '0.0.0.0';

// Проверка порта
$socket = @fsockopen($host, $port, $errno, $errstr, 0.5);

if (!$socket) {
	// Порт свободен = запускаем сервер
	if (defined("DEBUG") && DEBUG) dlog("$ver: Запускаю сервер WebSocket на хосле $host и порту $port", 3, "INFO");

	$serverScript = ROOT_DIR . '/scripts/dashboard_ws_server.js';
	if (file_exists($serverScript)) {
		// Запускаем с логированием в файл
		$logFile = ROOT_DIR . '/websocket_server.log';
		// ИСПРАВЛЕНО: Передаем host как переменную окружения
		$command = 'cd ' . escapeshellarg(ROOT_DIR) .
			' && WS_HOST="' . $host . '" /usr/bin/node ' . escapeshellarg('scripts/dashboard_ws_server.js') .
			' >> ' . escapeshellarg($logFile) . ' 2>&1 &';

		exec($command);

		if (defined("DEBUG") && DEBUG) dlog("$ver: Сервер WebSocket запущен командой <$command>(лог в файле: $logFile)", 3, "INFO");
	} else {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Server script not found: $serverScript", 1, "ERROR");
	}
} else {
	// Сервер уже запущен
	fclose($socket);
	if (defined("DEBUG") && DEBUG) dlog("$ver: Сервер WebSocket server уже работает на порту $port", 3, "INFO");
}

// ======================================================================
// === WebSocket КОНФИГУРАЦИЯ КЛИЕНТА ===
// ======================================================================
if (defined("DEBUG") && DEBUG) dlog("$ver: Инициирую клиента WebSocket", 3, "INFO");

define('DASHBOARD_HOST', $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_ADDR'] ?? 'localhost'));
echo '<script>
	window.DASHBOARD_CONFIG = window.DASHBOARD_CONFIG || {};
	window.DASHBOARD_CONFIG.websocket = {
		enabled: true,
		host: "' . DASHBOARD_HOST . '",
		port: ' . (defined('
		WS_PORT ') ? WS_PORT : 8080) . ',
		path: "' . (defined('WS_PATH') ? WS_PATH : '/ws') . '",
		autoConnect: true,
		reconnectDelay: 3000,
		maxReconnectAttempts: 5,
		pingInterval: 30000,
		debugConsole: false,
		debugLevel: ' . (defined('
		DEBUG_VERBOSE ') ? DEBUG_VERBOSE : 3) . ',
		debugWebConsole: true,
	};
</script>';
echo '<script src="/scripts/dashboard_ws_client.js"></script>';
?>
