<?php

/**
 * @filesource /include/dtmf_handler.php
 * @version 0.1.0.release
 * @date 2026.01.16
 * @author vladimir@tsurkanenko.ru
 * @description DTMF handler
 * @note Preliminary version.
 */

if (session_status() === PHP_SESSION_NONE) {
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/settings.php';
	session_name(SESSION_NAME);
	session_id(SESSION_ID);
	session_start();
}

function sendDtmfCommand(string $command, string $dtmfPath): bool
{
	if (empty($command) || empty($dtmfPath)) {
		return false;
	}

	if (!file_exists($dtmfPath)) {
		return false;
	}

	if (!is_writable($dtmfPath)) {
		return false;
	}

	$result = file_put_contents($dtmfPath, $command . PHP_EOL);
	return $result !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$response = ['status' => 'error', 'message' => 'Unknown error'];

	try {
		$command = $_POST['command'] ?? '';
		$source = $_POST['source'] ?? 'unknown';

		if (empty($command)) {
			$response = ['status' => 'error', 'message' => 'Empty command'];
			echo json_encode($response);
			exit;
		}

		if (isset($_SESSION['DTMF_CTRL_PTY'])) {
			$dtmfPath = $_SESSION['DTMF_CTRL_PTY'];
		} else {
			error_log("dtmf_handler: Session not initialised!");
		}

		if (empty($dtmfPath)) {
			$response = ['status' => 'error', 'message' => 'DTMF path not configured'];
			echo json_encode($response);
			exit;
		}

		if (sendDtmfCommand($command, $dtmfPath)) {
			$response = [
				'status' => 'success',
				'message' => 'Command sent: ' . htmlspecialchars($command),
				'source' => $source
			];
		} else {
			$response = [
				'status' => 'error',
				'message' => 'Failed to send command to: ' . htmlspecialchars($dtmfPath),
				'source' => $source
			];
		}
	} catch (Exception $e) {
		$response = [
			'status' => 'error',
			'message' => 'Server error: ' . $e->getMessage(),
			'source' => $source ?? 'unknown'
		];
	}

	echo json_encode($response);
	exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
