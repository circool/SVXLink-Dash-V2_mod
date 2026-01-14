<?php
/**
 * @filesource /include/websocket_server.0.4.0.php
 * @version 0.4.0
 * @date 2026.01.14
 * @description Проверка и запуск WebSocket сервера
 */

if (!defined('WS_ENABLED') || !WS_ENABLED) {
    return;
}

$port = defined('WS_PORT') ? WS_PORT : 8080;
// Сервер слушает на всех интерфейсах для сетевого доступа
$host = defined('WS_HOST') ? WS_HOST : '0.0.0.0';

// Проверка порта
$socket = @fsockopen($host, $port, $errno, $errstr, 0.5);

if (!$socket) {
    // Порт свободен = запускаем сервер
    if (defined("DEBUG") && DEBUG) dlog("WebSocket: Запускаю сервер на хосте $host, порту $port", 3, "INFO");

    $serverScript = ROOT_DIR . '/scripts/dashboard_ws_server.js';
    if (file_exists($serverScript)) {
        // Запускаем с логированием в файл
        $logFile = ROOT_DIR . '/websocket_server.log';
        $command = 'cd ' . escapeshellarg(ROOT_DIR) .
            ' && WS_HOST="' . $host . '" /usr/bin/node ' . escapeshellarg('scripts/dashboard_ws_server.js') .
            ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';

        exec($command);

        if (defined("DEBUG") && DEBUG) dlog("WebSocket: Сервер запущен (лог: $logFile)", 3, "INFO");
    } else {
        if (defined("DEBUG") && DEBUG) dlog("WebSocket: Скрипт сервера не найден: $serverScript", 1, "ERROR");
    }
} else {
    // Сервер уже запущен
    fclose($socket);
    if (defined("DEBUG") && DEBUG) dlog("WebSocket: Сервер уже работает на порту $port", 3, "INFO");
}