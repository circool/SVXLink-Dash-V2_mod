<?php

/**
 * Session updater & block proxy
 * @filesource /include/ajax_update.js
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.11
 * @version 0.4.6
 */
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
	require_once __DIR__ . '/session_header.php';
}

if (isset($_GET['update_session'])) {
	require_once __DIR__ . '/fn/getActualStatus.php';
	$_SESSION['status'] = getActualStatus();
	session_write_close();
	echo json_encode(['status' => 'ok', 'timestamp' => time()]);
	exit;
}

if (isset($_GET['block'])) {
	$blockFile = __DIR__ . '/' . $_GET['block'] . '.php';
	if (file_exists($blockFile)) {
		$_GET['ajax'] = 1;
		ob_start();
		include $blockFile;
		$html = ob_get_clean();
		echo json_encode(['html' => $html]);
		exit;
	}
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
exit;
