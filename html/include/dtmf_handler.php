<?php

/**
 * @filesource /include/dtmf_handler.php
 * @version 0.4.0.release
 * @date 2026.01.30
 * @author vladimir@tsurkanenko.ru
 * @description DTMF handler with clean center-positioned toast notifications
 */

if (session_status() === PHP_SESSION_NONE) {
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/settings.php';
	session_name(SESSION_NAME);
	session_id(SESSION_ID);
	session_start();
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
$translationFunction = null;
if (function_exists('getTranslation')) {
	$translationFunction = 'getTranslation';
} else {

	function getTranslationFallback($key)
	{
		return $key;
	}
	$translationFunction = 'getTranslationFallback';
}

/**
 * Формирует JavaScript код для toast-уведомления
 */
function getToastNotification(string $message, string $type = 'info', array $data = []): string
{
	$types = [
		'success' => ['color' => '#2c7f2c', 'duration' => 3000],
		'error'   => ['color' => '#8C0C26', 'duration' => 5000],
		'warning' => ['color' => '#cc9900', 'duration' => 4000],
		'info'    => ['color' => '#0066cc', 'duration' => 3000]
	];

	$config = $types[$type] ?? $types['info'];
	$toastId = 'toast_' . md5($message . microtime());
	$dataJson = !empty($data) ? json_encode($data) : 'null';
	$jsMessage = addslashes($message);
	$jsType = addslashes($type);
	$jsColor = addslashes($config['color']);
	$jsToastId = addslashes($toastId);

	return <<<JS
(function() {
    let initToastContainer = function() {
        let container = document.getElementById('dtmfGlobalToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'dtmfGlobalToastContainer';
            container.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10001; display: flex; flex-direction: column; align-items: center; gap: 10px; pointer-events: none;';
            document.body.appendChild(container);
        }
        return container;
    };
    

    let showToast = function(message, type, color, duration, toastId, data) {
        const container = initToastContainer();     
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = 'dtmf-global-toast ' + type;
        toast.style.cssText = 'padding: 20px 40px; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 6px 20px rgba(0,0,0,0.4); color: white; background: ' + color + '; opacity: 0; transform: translateY(-20px) scale(0.9); transition: opacity 0.3s ease, transform 0.3s ease; min-width: 300px; max-width: 500px; text-align: center; word-wrap: break-word; pointer-events: auto; cursor: pointer; border: 1px solid rgba(255,255,255,0.1);';
        toast.innerHTML = message;
        
        if (data) {
            try {
                const dataStr = JSON.stringify(data);
                toast.setAttribute('data-context', dataStr);
            } catch(e) {
                console.warn('Could not serialize toast data:', e);
            }
        }
        
        toast.addEventListener('click', function() {
            removeToast(toast);
        });
        
        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }
        
        container.appendChild(toast);
        
        setTimeout(function() {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0) scale(1)';
        }, 10);
        
        const autoClose = setTimeout(function() {
            removeToast(toast);
        }, duration);
        
        function removeToast(toastElement) {
            clearTimeout(autoClose);
            toastElement.style.opacity = '0';
            toastElement.style.transform = 'translateY(20px) scale(0.9)';
            setTimeout(function() {
                if (toastElement.parentNode === container) {
                    container.removeChild(toastElement);
                }
            }, 300);
        }
        
        toast.removeToast = removeToast;
        
        return toast;
    };
    
    showToast(
        "{$jsMessage}",
        "{$jsType}",
        "{$jsColor}",
        {$config['duration']},
        "{$jsToastId}",
        {$dataJson}
    );
})();
JS;
}

/**
 * Send DTMF command
 */
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
	$command = $_POST['command'] ?? '';
	$source = $_POST['source'] ?? 'unknown';
	$ajaxLink = $_POST['ajax_link'] ?? false;

	if (empty($command)) {
		echo getToastNotification($translationFunction('Empty command'), 'error', ['source' => $source]);
		exit;
	}

	$dtmfPath = null;
	if (isset($_SESSION['status']['logic'][$source]['dtmf_cmd'])) {
		$dtmfPath = $_SESSION['status']['logic'][$source]['dtmf_cmd'];
	}

	if (empty($dtmfPath)) {
		$message = sprintf($translationFunction('DTMF not configured for %s'), $source);
		echo getToastNotification($message, 'error', [
			'source' => $source,
			'reason' => 'not_configured'
		]);
		exit;
	}

	if (sendDtmfCommand($command, $dtmfPath)) {
		$message = sprintf($translationFunction('Command sent to %s: %s'), $source, htmlspecialchars($command));
		echo getToastNotification($message, 'success', [
			'command' => $command,
			'dtmf_path' => $dtmfPath,
			'source' => $source,
			'ajax_link' => $ajaxLink
		]);
	} else {
		$message = sprintf($translationFunction('Failed to send command to %s'), $source);
		echo getToastNotification($message, 'error', [
			'dtmf_path' => $dtmfPath,
			'command' => $command,
			'source' => $source,
			'reason' => 'write_failed'
		]);
	}
	exit;
}

echo getToastNotification($translationFunction('Invalid request method'), 'error', ['source' => 'system']);
