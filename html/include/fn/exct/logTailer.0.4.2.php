<?php

/**
 * Функции для работы с журналом svxlink
 * @filesource @filesource /include/fn/logTailer.0.4.0.php
 * @version 0.4.0
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.01.18
 */

if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
	include_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.php';
}


/** Возвращает последние N строк из лог-файла SVXLink
 * 
 * Использует системную команду tail для максимальной производительности
 * @filesource /include/fn/logTailer.0.4.0.php
 * @version 0.0.1
 * @param int $num_lines Количество строк для чтения с конца файла
 * @return array|false Массив строк (без символов конца строк) или false при ошибке
 */
function getLogTail($num_lines)
{
	$ver = "getLogTail 0.0.1";
	if (defined("DEBUG") && DEBUG) dlog("$ver: Ищу последние $num_lines строк журнала", 4, "DEBUG");
	// Проверка корректности параметра $num_lines
	if (!is_int($num_lines) || $num_lines <= 0) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Неверный параметр $num_lines", 1, "ERROR");
		return false;
	}

	// Определение пути к лог-файлу (из глобальных констант, предполагается что они определены)
	if (!defined('SVXLOGPATH') || !defined('SVXLOGPREFIX')) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Не определены константы _SVXLOGPATH или _SVXLOGPREFIX", 1, "ERROR");
		return false;
	}

	$logPath = SVXLOGPATH . SVXLOGPREFIX;

	// Проверка существования файла (быстрая проверка перед вызовом tail)
	if (!file_exists($logPath) || !is_readable($logPath)) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Не найден файл $logPath", 1, "ERROR");
		return false;
	}

	// Используем tail для максимальной скорости с большими файлами
	// -n $num_lines: последние N строк
	// 2>&1: перенаправляем stderr в stdout для обработки ошибок
	$command = sprintf('tail -n %d "%s" 2>&1', $num_lines, escapeshellarg($logPath));
	// $output = shell_exec($command);
	$output = `tail -n $num_lines $logPath 2>&1`;
	// Если команда вернула null, значит произошла ошибка
	if ($output === null) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Команда shell_exec вернула null", 1, "ERROR");
		return false;
	}

	// Разбиваем вывод на строки и убираем пустые строки в конце
	$lines = explode("\n", $output);

	// Убираем последний элемент если он пустой (последний \n в выводе tail)
	if (end($lines) === '') {
		array_pop($lines);
	}

	// Trim каждой строки (убираем пробелы и символы перевода строки)
	foreach ($lines as $line) {
		$line = trim($line);
	}
	// unset($line); // Разрываем ссылку для безопасности

	// Проверяем, получили ли мы хоть что-то
	if (empty($lines) || (count($lines) === 1 && $lines[0] === '')) {
		return false;
	}

	return $lines;
}

/** Возвращает количество строк после последнего вхождения паттерна в лог-файле
 * 
 * Использует fixed-string поиск (grep -F) для максимальной скорости
 * Запрещает wildcards и regex-символы в паттерне
 * @version 0.4.2 - упрощенное структурированное кеширование
 * @param string $pattern Подстрока для поиска (чувствительная к регистру)
 * @param int $analyze_lines Количество анализируемых строк:
 *   - 0 = анализировать весь файл
 *   - N > 0 = анализировать только последние N строк
 * @return int|false Количество строк после паттерна или false если не найден
*/
function countLogLines(string $pattern, int $analyze_lines = 0): int|false {
	$func_start = microtime(true);
	$ver = "countLogLines 0.4.2";

	$useCache = false;
	$cacheDuration = 1000;

	if (defined('USE_CACHE') && is_bool(USE_CACHE)) {
		$useCache = USE_CACHE;
	}

	if (defined('LOG_CACHE_TTL_MS') && is_int(LOG_CACHE_TTL_MS) && LOG_CACHE_TTL_MS > 0) {
		$cacheDuration = LOG_CACHE_TTL_MS;
	}

	// Отключаем кеширование если сессия не активна
	if ($useCache && session_status() !== PHP_SESSION_ACTIVE) {
		if (defined("DEBUG") && DEBUG) {
			dlog("$ver: Сессия не активна, отключаю кеширование", 2, "WARNING");
		}
		$useCache = false;
	}

	$cacheKey = null;
	$currentTimeMs = null;

	if ($useCache) {
		$currentTimeMs = microtime(true) * 1000;
		$cacheKey = md5($pattern . '|' . $analyze_lines);

		// Инициализируем массив кеша если нужно
		if (!isset($_SESSION['countLogLines_cache'])) {
			$_SESSION['countLogLines_cache'] = [];
		}

		$cache = &$_SESSION['countLogLines_cache'];

		// Очистка устаревших кешей
		foreach ($cache as $key => $data) {
			if (
				isset($data['timestamp_ms']) &&
				($currentTimeMs - $data['timestamp_ms']) > $cacheDuration
			) {
				unset($cache[$key]);
			}
		}

		// Проверяем свежий кеш
		if (isset($cache[$cacheKey])) {
			$cacheData = $cache[$cacheKey];
			if (defined("DEBUG") && DEBUG) {
				$age = $currentTimeMs - $cacheData['timestamp_ms'];
				dlog("$ver: Используется кеш (" . number_format($age, 1) . " мс)", 3, "INFO");
				$fuct_time = microtime(true) - $func_start;
				dlog("$ver: Закончил работу за $fuct_time мсек (из кеша)", 3, "INFO");
			}
			return $cacheData['result'];
		}
	}
	

	if (defined("DEBUG") && DEBUG) {
		dlog("$ver: Паттерн: '$pattern', Количество строк: $analyze_lines", 4, "DEBUG");
	}

		if (!is_string($pattern) || trim($pattern) === '') {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Неверный паттерн", 1, "ERROR");
		return false;
	}

	if (!is_int($analyze_lines) || $analyze_lines < 0) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Количество строк должно быть неотрицательным целым числом", 1, "ERROR");
		return false;
	}

	if (!defined('SVXLOGPATH') || !defined('SVXLOGPREFIX')) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Путь к лог-файлу не определён", 1, "ERROR");
		return false;
	}

	$logPath = SVXLOGPATH . SVXLOGPREFIX;

	if (defined("DEBUG") && DEBUG) dlog("$ver: Путь к файлу: $logPath", 4, "DEBUG");

	if (!file_exists($logPath) || !is_readable($logPath)) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Файл не найден или недоступен", 1, "ERROR");
		return false;
	}


	// Экранируем только кавычки для shell
	$shell_escaped_pattern = str_replace("'", "'\"'\"'", $pattern);

	if (defined("DEBUG") && DEBUG) {
		dlog("$ver:  Поиск паттерна: '$pattern'", 4, "DEBUG");
	}

	$result = false; // Инициализируем результат

	if ($analyze_lines === 0) {
		// Поиск во всем файле
		$command = sprintf(
			"grep -F -n '%s' %s 2>/dev/null | tail -1 | cut -d: -f1",
			$shell_escaped_pattern,
			escapeshellarg($logPath)
		);

		if (defined("DEBUG") && DEBUG) dlog("$ver: Команда (весь файл): $command", 4, "DEBUG");

		$last_line = shell_exec($command);

		if ($last_line !== null && trim($last_line) !== '') {
			$last_line = trim($last_line);

			$total_lines_command = sprintf("wc -l < %s 2>/dev/null", escapeshellarg($logPath));
			$total_lines = shell_exec($total_lines_command);

			if ($total_lines !== null && is_numeric(trim($total_lines))) {
				$total_lines = (int)trim($total_lines);
				$result = $total_lines - (int)$last_line;

				if (defined("DEBUG") && DEBUG) {
					dlog("$ver: Последняя строка с паттерном: $last_line", 4, "DEBUG");
					dlog("$ver: Всего строк: $total_lines", 4, "DEBUG");
					dlog("$ver: Строк после паттерна: $result", 4, "DEBUG");
				}

				if (defined("DEBUG") && DEBUG) {
					$fuct_time = microtime(true) - $func_start;
					dlog("$ver: Закончил работу за $fuct_time мсек, искал в последних $analyze_lines строках, вернул результат $result", 3, "INFO");
				}
			}
		}
	} else {
		// Поиск только в последних N строках
		$command = sprintf(
			"tail -n %d %s 2>/dev/null | grep -F -n '%s' 2>/dev/null | tail -1 | cut -d: -f1",
			$analyze_lines,
			escapeshellarg($logPath),
			$shell_escaped_pattern
		);

		if (defined("DEBUG") && DEBUG) dlog("$ver: Команда (tail): $command", 4, "DEBUG");

		$last_line = shell_exec($command);

		if ($last_line !== null && trim($last_line) !== '') {
			$last_line = trim($last_line);
			$result = $analyze_lines - (int)$last_line;

			if (defined("DEBUG") && DEBUG) {
				dlog("$ver: Относительная строка: $last_line", 4, "DEBUG");
				dlog("$ver: Строк после паттерна: $result", 4, "DEBUG");

				$fuct_time = microtime(true) - $func_start;
				dlog("$ver: Закончил работу за $fuct_time мсек, искал в последних $analyze_lines строках, вернул результат $result", 3, "INFO");
			}
		}
	}

	if ($result === false) {
		if (defined("DEBUG") && DEBUG) {
			dlog("$ver: Паттерн ($pattern) не найден в файле", 3, 'INFO');
		}
	}

	// Сохраняем результат в кеш
	if ($useCache && $cacheKey !== null && $currentTimeMs !== null) {
		$_SESSION['countLogLines_cache'][$cacheKey] = [
			'result' => $result,
			'timestamp_ms' => $currentTimeMs,
			'pattern' => $pattern,
			'analyze_lines' => $analyze_lines,
			'cached_at' => date('Y-m-d H:i:s')
		];

		if (defined("DEBUG") && DEBUG) {
			$cacheSize = count($_SESSION['countLogLines_cache']);
			dlog("$ver: Результат сохранен в кеш (всего записей: $cacheSize)", 3, "INFO");
		}
	}

	return $result;
}

/** Возвращает последние N строк из лог-файла SVXLink с фильтрацией по условиям
 * 
 * @note getLogTailFiltered
 * @version 0.1.12
 * @param int $num_lines Сколько строк вернуть в результате
 * @param string|null $required_condition Опциональное условие И
 * @param array $or_conditions Массив условий для ИЛИ
 * @param int|null $search_limit Сколько строк проверять (глубина разбора)
 * @return array|false При отсутствии результата, неправильных параметрах, ошибке получения журнала.
 */
function getLogTailFiltered($num_lines, $required_condition = null, $or_conditions = [], $search_limit = null) : array|false
{
	if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
		include_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.php';
	}
	$func_start = microtime(true);
	$ver = "getLogTailFiltered 1.2.0";
	
	if (defined("DEBUG") && DEBUG) dlog("$ver: Начинаю работу", 4, "DEBUG");
	
	
	// Проверка обязательных параметров
	if (!is_int($num_lines) || $num_lines <= 0) {
		if (defined("DEBUG") && DEBUG) {
			dlog("$ver: Неверное количество возвращаемых строк: $num_lines", 1, "ERROR");
		} else {
			error_log("Wrong quantity in 1 param in getLogTailFiltered - $num_lines");
		}
		return false;
	}

	if (!is_array($or_conditions)) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: or_conditions должен быть массивом", 1, "ERROR");
		return false;
	}

	// Обработка search_limit
	$actual_search_limit = 100000;

	if ($search_limit !== null) {
		if (!is_numeric($search_limit)) {
			if (defined("DEBUG") && DEBUG) dlog("$ver: search_limit должен быть числом", 1, "ERROR");
			return false;
		}

		$search_limit = (int)$search_limit;

		if ($search_limit == 0) {
			$actual_search_limit = 100000;
			if (defined("DEBUG") && DEBUG) dlog("$ver: search_limit=$search_limit, использую значение по умолчанию: $actual_search_limit строк", 4, "DEBUG");
		} else {
			$actual_search_limit = abs($search_limit);
			if (defined("DEBUG") && DEBUG) dlog("$ver: Будет искать в последних $actual_search_limit строках файла", 4, "DEBUG");
		}
	} else {
		if (defined("DEBUG") && DEBUG) dlog("$ver: search_limit не указан, использую значение по умолчанию: $actual_search_limit строк", 3, "INFO");
	}

	// Определение пути к файлу
	if (!defined('SVXLOGPATH') || !defined('SVXLOGPREFIX')) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Путь к лог-файлу не определен", 1, "ERROR");
		return false;
	}

	$logPath = SVXLOGPATH . SVXLOGPREFIX;
	// if (defined("DEBUG") && DEBUG) dlog("$ver: Путь к файлу: $logPath", 4, "DEBUG");

	if (!file_exists($logPath) || !is_readable($logPath)) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Файл не найден или недоступен", 1, "ERROR");
		return false;
	}

	// Обработка required_condition
	$has_required = false;

	if ($required_condition !== null && is_string($required_condition)) {
		$required_condition = trim($required_condition);
		if ($required_condition !== '') {
			$has_required = true;
			//if (defined("DEBUG") && DEBUG) dlog("$ver: Обязательное условие: $required_condition", 4, "DEBUG");
		}
	}

	// Обработка or_conditions
	$valid_or_conditions = [];
	foreach ($or_conditions as $condition) {
		if (is_string($condition) && trim($condition) !== '') {
			$valid_or_conditions[] = trim($condition);
		}
	}

	// Если нет условий фильтрации
	if (!$has_required && empty($valid_or_conditions)) {
		// if (defined("DEBUG") && DEBUG) dlog("$ver: Нет условий фильтрации, возвращаю последние $num_lines строк", 3, "INFO");

		$command = sprintf('tail -n %d %s 2>&1', $num_lines, escapeshellarg($logPath));
		// if (defined("DEBUG") && DEBUG) dlog("$ver: Команда: $command", 4, "DEBUG");

		$output = shell_exec($command);
		if ($output === null || $output === '') {
			if (defined("DEBUG") && DEBUG) dlog("$ver: Команда не вернула данных", 2, "WARNING");
			return false;
		}

		$lines = explode("\n", trim($output));
		$lines = array_map('trim', $lines);
		
		if (defined("DEBUG") && DEBUG) {
			$func_time = microtime(true) - $func_start;
			dlog("$ver: Поиск без условий нашел " . count($lines) . " строк за $func_time мсек", 3, "WARNING");
		}
		return $lines;
	}

	// Строим команду с исправленной логикой
	$command = '';

	// Важно: используем fgrep или grep -F для поиска фиксированных строк
	// escapeshellarg уже экранирует кавычки правильно

	if ($has_required && !empty($valid_or_conditions)) {
		// Оба условия: required И (or1 ИЛИ or2)
		// grep -F для fixed string поиска (без regex)
		$command = sprintf(
			'tail -n %d %s | grep -F %s | grep -F -e %s | tail -n %d 2>&1',
			$actual_search_limit,
			escapeshellarg($logPath),
			escapeshellarg($required_condition),
			implode(' -e ', array_map('escapeshellarg', $valid_or_conditions)),
			$num_lines
		);

		// if (defined("DEBUG") && DEBUG) dlog("$ver: Режим: required И (or_conditions) [FIXED STRING]", 4, "DEBUG");
	} elseif ($has_required) {
		// Только required
		$command = sprintf(
			'tail -n %d %s | grep -F %s | tail -n %d 2>&1',
			$actual_search_limit,
			escapeshellarg($logPath),
			escapeshellarg($required_condition),
			$num_lines
		);

		// if (defined("DEBUG") && DEBUG) dlog("$ver: Режим: только required [FIXED STRING]", 4, "DEBUG");
	} else {
		// Только or_conditions
		$command = sprintf(
			'tail -n %d %s | grep -F -e %s | tail -n %d 2>&1',
			$actual_search_limit,
			escapeshellarg($logPath),
			implode(' -e ', array_map('escapeshellarg', $valid_or_conditions)),
			$num_lines
		);

		// if (defined("DEBUG") && DEBUG) dlog("$ver: Режим: только or_conditions [FIXED STRING]", 4, "DEBUG");
	}

	if (defined("DEBUG") && DEBUG) dlog("$ver: Команда: $command", 4, "DEBUG");

	// Выполнение команды
	$output = shell_exec($command);

	if ($output === null || $output === '') {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Не нашлось строк с паттерном $required_condition + " . count($or_conditions), 3, "INFO");
		return false;
	}

	$lines = explode("\n", trim($output));
	$lines = array_map('trim', $lines);

	if (empty($lines) || (count($lines) === 1 && $lines[0] === '')) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Пустой результат после фильтрации", 2, "WARNING");
		return false;
	}

	// if (defined("DEBUG") && DEBUG) dlog("$ver: Успешно получено " . count($lines) . " строк, Первая строка результата: " . $lines[0], 4, "DEBUG");

	
	if (defined("DEBUG") && DEBUG) {
		$func_time = microtime(true) - $func_start;
		
		if ($has_required) {
			$hr = "обязательным условием " . $required_condition . " и ";
		} else {
			$hr = '';
		}
		
		// dlog("$ver: Поиск c " . $hr . count($valid_or_conditions) . " OR условиями нашел " . count($lines) . " строк за $func_time мсек", 3, "WARNING");
		
	}
	return $lines;
}



/** Возвращает номер последней строки в лог-файле SvxLink
 * Использует awk для максимальной производительности
 * 
 * @param string $logPath Полный путь к лог-файлу
 * @return int Номер последней строки (0 при ошибке)
 * @author vladimir@tsurkanenko.ru
 * @since 0.2.1
 */

function getLogLastLineNumber(): int {
	$logPath = SVXLOGPATH . SVXLOGPREFIX;
		// Проверяем существование файла
    if (!file_exists($logPath)) {
        error_log("SvxLink log file not found: " . $logPath);
        return 0;
    }
    
    // Проверяем доступность для чтения
    if (!is_readable($logPath)) {
        error_log("SvxLink log file is not readable: " . $logPath);
        return 0;
    }
    
    // Получаем номер последней строки
    exec("awk 'END {print NR}' " . escapeshellarg($logPath) . " 2>/dev/null", $output, $returnCode);
    
    if ($returnCode !== 0 || empty($output)) {
        // В случае ошибки возвращаем 0
        return 0;
    }
    
    $lineNumber = trim($output[0]);
    
    // Проверяем, что это число
    if (!is_numeric($lineNumber)) {
        return 0;
    }
    
    return (int)$lineNumber;
}


?>