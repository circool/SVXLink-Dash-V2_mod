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

// Загружаем переводы если доступны
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
$translationFunction = null;
if (function_exists('getTranslation')) {
	$translationFunction = 'getTranslation';
} else {
	// Запасная функция если переводы не загружены
	function getTranslationFallback($key)
	{
		return $key;
	}
	$translationFunction = 'getTranslationFallback';
}

/**
 * Формирует чистый JavaScript код для toast-уведомления в ЦЕНТРЕ экрана БЕЗ иконок
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

	// Экранируем сообщение для JavaScript
	$jsMessage = addslashes($message);
	$jsType = addslashes($type);
	$jsColor = addslashes($config['color']);
	$jsToastId = addslashes($toastId);

	// Возвращаем ЧИСТЫЙ JavaScript код БЕЗ <script> тегов
	return <<<JS
(function() {
    // Инициализация контейнера в ЦЕНТРЕ экрана
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
    
    // Функция показа toast в ЦЕНТРЕ
    let showToast = function(message, type, color, duration, toastId, data) {
        const container = initToastContainer();
        
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = 'dtmf-global-toast ' + type;
        
        // Стили для центрального расположения
        toast.style.cssText = 'padding: 20px 40px; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 6px 20px rgba(0,0,0,0.4); color: white; background: ' + color + '; opacity: 0; transform: translateY(-20px) scale(0.9); transition: opacity 0.3s ease, transform 0.3s ease; min-width: 300px; max-width: 500px; text-align: center; word-wrap: break-word; pointer-events: auto; cursor: pointer; border: 1px solid rgba(255,255,255,0.1);';
        
        // БЕЗ иконок - только чистый текст
        toast.innerHTML = message;
        
        // Добавляем data-атрибуты если есть
        if (data) {
            try {
                const dataStr = JSON.stringify(data);
                toast.setAttribute('data-context', dataStr);
            } catch(e) {
                console.warn('Could not serialize toast data:', e);
            }
        }
        
        // Клик для закрытия
        toast.addEventListener('click', function() {
            removeToast(toast);
        });
        
        // Удаляем предыдущие toast'ы перед показом нового
        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }
        
        container.appendChild(toast);
        
        // Анимация появления с эффектом "выплывания"
        setTimeout(function() {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0) scale(1)';
        }, 10);
        
        // Автоматическое закрытие
        const autoClose = setTimeout(function() {
            removeToast(toast);
        }, duration);
        
        // Функция удаления с анимацией
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
        
        // Сохраняем функцию удаления для возможного внешнего доступа
        toast.removeToast = removeToast;
        
        return toast;
    };
    
    // Показываем toast в центре экрана
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
 * Отправляет DTMF команду
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

// Основная обработка
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$command = $_POST['command'] ?? '';
	$source = $_POST['source'] ?? 'unknown';
	$ajaxLink = $_POST['ajax_link'] ?? false;

	if (empty($command)) {
		echo getToastNotification($translationFunction('Empty command'), 'error', ['source' => $source]);
		exit;
	}

	// Ищем DTMF путь в сессии по имени логики
	$dtmfPath = null;
	if (isset($_SESSION['status']['logic'][$source]['dtmf_cmd'])) {
		$dtmfPath = $_SESSION['status']['logic'][$source]['dtmf_cmd'];
	} elseif (isset($_SESSION['DTMF_CTRL_PTY'])) {
		$dtmfPath = $_SESSION['DTMF_CTRL_PTY'];
	}

	if (empty($dtmfPath)) {
		$message = sprintf($translationFunction('DTMF not configured for %s'), $source);
		echo getToastNotification($message, 'error', [
			'source' => $source,
			'reason' => 'not_configured'
		]);
		exit;
	}

	// Отправляем команду
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

// Обработка GET запроса (для тестирования)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test_toast'])) {
	$type = $_GET['type'] ?? 'info';
	$message = $_GET['message'] ?? 'Test toast message';
	$source = $_GET['source'] ?? 'test';

	echo getToastNotification($message, $type, ['source' => $source, 'test' => true]);
	exit;
}

echo getToastNotification($translationFunction('Invalid request method'), 'error', ['source' => 'system']);
