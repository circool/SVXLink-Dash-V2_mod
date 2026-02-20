<?php

/**
 * @filesource /include/dtmf_handler.php
 * @version 0.4.0.release
 * @date 2026.01.30
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
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

function getToastNotification(string $message, string $type = 'info', array $data = []): string
{
	$types = [
		'success' => ['duration' => 3000],
		'error'   => ['duration' => 5000],
		'warning' => ['duration' => 4000],
		'info'    => ['duration' => 3000]
	];

	$config = $types[$type] ?? $types['info'];
	$toastId = 'toast_' . md5($message . microtime());
	$dataJson = !empty($data) ? json_encode($data) : 'null';
	$jsMessage = addslashes($message);
	$jsType = addslashes($type);
	$jsToastId = addslashes($toastId);

	return <<<JS
(function() {
    let initToastContainer = function() {
        let container = document.getElementById('dtmfToast');
        if (!container) {
            container = document.createElement('div');
            container.id = 'dtmfToast';
            document.body.appendChild(container);
        }
        return container;
    };
    
    let showToast = function(message, type, duration, toastId, data) {
        const container = initToastContainer();     
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = 'dtmf-toast ' + type;
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
            toast.classList.add('show');
        }, 10);
        
        const autoClose = setTimeout(function() {
            removeToast(toast);
        }, duration);
        
        function removeToast(toastElement) {
            clearTimeout(autoClose);
            toastElement.classList.add('hide');
            toastElement.classList.remove('show');
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
