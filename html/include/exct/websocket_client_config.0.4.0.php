<?php
/**
 * @filesource /include/websocket_client_config.0.4.0.php
 * @version 0.4.0
 * @date 2026.01.14
 * @description Конфигурация WebSocket клиента для вывода в <head>
 */

// Определяем хост для WebSocket
if (!defined('DASHBOARD_HOST')) {
    define('DASHBOARD_HOST', $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_ADDR'] ?? 'localhost'));
}

// Выводим конфигурацию только если WebSocket включен
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
        debugConsole: false,
        debugLevel: <?php echo defined('DEBUG_VERBOSE') ? DEBUG_VERBOSE : 2; ?>,
        debugWebConsole: true,
    };
</script>
<?php endif; ?>