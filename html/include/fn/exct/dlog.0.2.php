<?php

/**
 * Функция логирования в журнал ошибок
 * @version 0.2
 * @filesource /include/fn/exct/dlog.0.2.php
 * */

function dlog($message, $verboseLevel = 1, $category = 'GENERAL')
{
	
	
	static $logInitialized = false;

	// Если DEBUG_VERBOSE не определен, считаем что все уровни разрешены
	if (defined('DEBUG_VERBOSE') && DEBUG_VERBOSE < $verboseLevel) {
		return;
	}

	// Формируем сообщение
	$timestamp = date('m-d H:i:s');
	$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
	$caller = '';

	if (isset($backtrace[1])) {
		$caller = basename($backtrace[1]['file']) . ':' . $backtrace[1]['line'];
	}

	// Определяем ANSI цвета для категорий
	$ansiStart = '';
	$ansiEnd = '';

	switch (strtoupper($category)) {
		case 'ERROR':
			$ansiStart = "\033[31m"; // Красный
			$ansiEnd = "\033[0m";
			break;
		case 'WARNING':
			$ansiStart = "\033[35m"; // Магента
			$ansiEnd = "\033[0m";
			break;
		case 'INFO':
			$ansiStart = "\033[32m"; // Зеленый
			$ansiEnd = "\033[0m";
			break;
		case "DEBUG":
			$ansiStart = "\033[36m"; // Голубой
			$ansiEnd = "\033[0m";
			break;
			// Остальные категории без цвета
	}

	$logMessage = sprintf(
		"%s[%s] [%s] [%s] %s - %s%s\n",
		$ansiStart,
		$timestamp,
		str_pad($category, 8),
		str_pad("LVL{$verboseLevel}", 5),
		str_pad($caller, 30),
		$message,
		$ansiEnd
	);

	// Записываем в системный лог (без ANSI кодов)
	if (defined('DEBUG_LOG_TO_APACHE') && DEBUG_LOG_TO_APACHE) {
		$cleanLogMessage = sprintf(
			"[%s] [%s] [%s] %s - %s\n",
			$timestamp,
			str_pad($category, 8),
			str_pad("LVL{$verboseLevel}", 5),
			str_pad($caller, 30),
			$message
		);
		error_log(trim($cleanLogMessage));
	}

	// Дополнительно пишем в файл, если определен (С ANSI кодами для tail -f)
	if (defined('DEBUG_LOG_FILE') && DEBUG_LOG_FILE) {
		// Создаем директорию, если нужно
		$logDir = dirname(DEBUG_LOG_FILE);
		if (!is_dir($logDir)) {
			mkdir($logDir, 0755, true);
		}

		file_put_contents(DEBUG_LOG_FILE, $logMessage, FILE_APPEND);

		// Ограничиваем размер файла (например, 10MB)
		if (filesize(DEBUG_LOG_FILE) > 10 * 1024 * 1024) {
			$lines = file(DEBUG_LOG_FILE, FILE_IGNORE_NEW_LINES);
			$lines = array_slice($lines, -5000); // Оставляем последние 5000 строк
			file_put_contents(DEBUG_LOG_FILE, implode("\n", $lines) . "\n");
		}
	}
}
?>