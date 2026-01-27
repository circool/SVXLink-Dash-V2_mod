<?php

/**
 * @filesource /include/websocket_server.php
 * @author vladimir@tsurkanenko.ru  
 * @version 0.4.0_release
 * @date 2026.01.26
 */

if (!defined('WS_ENABLED') || !WS_ENABLED) {
    return;
}

$port = defined('WS_PORT') ? WS_PORT : 8080;
$host = defined('WS_HOST') ? WS_HOST : '0.0.0.0';
$socket = @fsockopen($host, $port, $errno, $errstr, 0.5);

if (!$socket) {
    $serverScript = ROOT_DIR . '/scripts/dashboard_ws_server.js';
    if (file_exists($serverScript)) {
        $logFile = ROOT_DIR . '/websocket_server.log';
        $command = 'cd ' . escapeshellarg(ROOT_DIR) .
            ' && WS_HOST="' . $host . '" /usr/bin/node ' . escapeshellarg('scripts/dashboard_ws_server.js') .
            ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
        exec($command);
    }
} else {

    fclose($socket);
}