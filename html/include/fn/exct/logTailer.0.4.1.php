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
 * @version 0.1.18 - fixed-string поиск, запрет wildcards
 * @param string $pattern Подстрока для поиска (чувствительная к регистру)
 * @param int $analyze_lines Количество анализируемых строк:
 *   - 0 = анализировать весь файл
 *   - N > 0 = анализировать только последние N строк
 * @return int|false Количество строк после паттерна или false если не найден
 * @todo Понять и устранить причину частого вызова
 * @todo Попытаться сократить время выполнения (около 0.23 мсек)
 * @note Для сокращения времени поиска можно искать не по всему файлу а по последнии n строкам, если в сессии есть $_SESSION['status']['service']['log_line_count']
 */
function countLogLines(string $pattern, int $analyze_lines = 0): int|false
{
	$func_start = microtime(true);
	$ver = "countLogLines 0.1.18";

	// ===== КОНФИГУРИРУЕМОЕ КЕШИРОВАНИЕ =====
	$useCache = false;
	$cacheDuration = 1000;
	// ===== КОНЕЦ КЕШИРОВАНИЯ =====

	if (defined("DEBUG") && DEBUG) {
		dlog("$ver: Паттерн: '$pattern', Количество строк: $analyze_lines", 4, "DEBUG");
	}



	// Запрещаем wildcards и regex-символы
	// @since 0.4.0 - не проверяем на wildcard!
	// $forbidden_chars = ['*', '.', '?', '+', '[', ']', '(', ')', '{', '}', '^', '$', '|', '\\'];

	// foreach ($forbidden_chars as $char) {
	// 	if (str_contains($pattern, $char)) {
	// 		if (defined("DEBUG") && DEBUG) {
	// 			dlog("$ver: Запрещенный символ в паттерне $pattern: '$char'", 1, "ERROR");
	// 			dlog("$ver: Используйте только plain text для fixed-string поиска", 2, "WARNING");
	// 		}
	// 		return false;
	// 	}
	// }

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
				return $result;
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
			return $result;
		}
	}

	if (defined("DEBUG") && DEBUG) {
		dlog("$ver: Паттерн ($pattern) не найден в файле", 2, 'WARNING');
	}
	
	$result = false;
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
function getLogTailFiltered($num_lines, $required_condition = null, $or_conditions = [], $search_limit = null)
{
	if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
		include_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.php';
	}
	$func_start = microtime(true);
	$ver = "getLogTailFiltered 1.2.0";
	
	if (defined("DEBUG") && DEBUG) dlog("$ver: Начинаю работу", 4, "DEBUG");
	
	
	// Проверка обязательных параметров
	if (!is_int($num_lines) || $num_lines <= 0) {
		// if (defined("DEBUG") && DEBUG) dlog("$ver: Неверное количество возвращаемых строк: $num_lines", 1, "ERROR");
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

/** Отслеживает новые строки журнала с момента последнего вызова
 * 
 * Использует tail -n +N для чтения от определенной позиции
 * Сохраняет состояние в сессии, обнаруживает ротацию логов
 * Кеширует результаты на CACHE_SIZE миллисекунд
 * @filesource /include/fn/logTailer.0.4.0.php
 * @note trackNewLogLines
 * @version 0.1.16 - исправлена ошибка выхода за пределы файла
 * @param int $max_lines Максимальное количество строк для чтения (0 = без ограничений)
 * @return array|false Массив новых строк или false при ошибке
 */
function trackNewLogLines($max_lines = 1000)
{
	if (defined("DEBUG") && DEBUG) {
		$func_start = microtime(true);
		$ver = "trackNewLogLines 0.4.0";
		dlog("$ver: Начинаю работу", 4, "INFO"); 
		dlog("$ver: ID сессии: " . session_id(), 4, "DEBUG");
		dlog("$ver: Статус сессии: " . session_status(), 4, "DEBUG");
	}

	// ===== КОНФИГУРИРУЕМОЕ КЕШИРОВАНИЕ =====
	$useCache = false;
	$cacheDuration = 1000;

	if (defined('USE_CACHE') && is_bool(USE_CACHE)) {
		$useCache = USE_CACHE;
	}

	if (defined('LOG_CACHE_TTL_MS') && is_int(LOG_CACHE_TTL_MS) && LOG_CACHE_TTL_MS > 0) {
		$cacheDuration = LOG_CACHE_TTL_MS;
	}

	$cacheKey = 'tnll_' . md5((string)$max_lines);
	$currentTime = microtime(true);
	$currentTimeMs = $currentTime * 1000;

	// Проверка кеша
	if ($useCache && isset($_SESSION[$cacheKey])) {
		$cacheData = $_SESSION[$cacheKey];
		if (
			isset($cacheData['timestamp_ms']) &&
			($currentTimeMs - $cacheData['timestamp_ms']) < $cacheDuration
		) {
			if (defined("DEBUG") && DEBUG) {
				$age = $currentTimeMs - $cacheData['timestamp_ms'];
				dlog("$ver: Используется кеш (" . number_format($age, 1) . " мс)", 2, "INFO");
			}
			return $cacheData['result'];
		}
		if (defined("DEBUG") && DEBUG) dlog("$ver: Кеш устарел, читаю журнал ", 2, "INFO");
	}
	// ===== КОНЕЦ КЕШИРОВАНИЯ =====

	// Проверка обязательных констант
	if (!defined('SVXLOGPATH') || !defined('SVXLOGPREFIX')) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Не определены константы", 1, "ERROR");
		return false;
	}

	$logPath = SVXLOGPATH . SVXLOGPREFIX;

	if (!file_exists($logPath) || !is_readable($logPath)) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Файл недоступен", 1, "ERROR");
		return false;
	}

	// Получаем актуальное количество строк в файле
	$total_lines_cmd = 'wc -l < ' . escapeshellarg($logPath) . ' 2>/dev/null';
	$total_lines_output = shell_exec($total_lines_cmd);
	$current_total_lines = ($total_lines_output !== null && is_numeric(trim($total_lines_output)))
		? (int)trim($total_lines_output)
		: 0;

	if (defined("DEBUG") && DEBUG) {
		dlog("$ver: Текущее количество строк в файле: $current_total_lines", 4, "DEBUG");
	}

	// Работа с сессией
	$sessionWasActive = (session_status() === PHP_SESSION_ACTIVE);

	if (!$sessionWasActive) {
		session_start();
	}

	// Инициализация или чтение состояния
	if (!isset($_SESSION['log_tracker'])) {
		$_SESSION['log_tracker'] = [
			'last_line' => 0,
			'total_lines' => $current_total_lines,
			'initialized' => false
		];
	}

	$state = $_SESSION['log_tracker'];
	$last_line = $state['last_line'];

	if (defined("DEBUG") && DEBUG) {
		dlog("$ver: Состояние из сессии: last_line={$state['last_line']}, total_lines={$state['total_lines']}, initialized=" .
			($state['initialized'] ? 'true' : 'false'), 4, "DEBUG");
	}

	// Если сохраненная позиция больше, чем строк в файле - сбрасываем
	if ($state['initialized'] && $last_line > $current_total_lines) {
		if (defined("DEBUG") && DEBUG) {
			dlog("$ver: Выход за пределы файла! last_line=$last_line > current_total_lines=$current_total_lines", 2, "WARNING");
			dlog("$ver: Сбрасываю позицию (вероятно, ротация лога)", 3, "INFO");
		}
		$last_line = 0;
		$state['initialized'] = false;
	}

	// Определяем стартовую позицию
	$func_start_line = $last_line + 1;

	// Если первый запуск или после сброса - читаем хвост файла
	if (!$state['initialized'] && $max_lines > 0 && $current_total_lines > 0) {
		$func_start_line = max(1, $current_total_lines - $max_lines + 1);

		if (defined("DEBUG") && DEBUG) {
			dlog("$ver: Первый запуск или сброс. Начинаем с: $func_start_line строки журнала", 3, "INFO");
		}
	}

	// Проверяем, есть ли что читать
	if ($func_start_line > $current_total_lines) {
		if (defined("DEBUG") && DEBUG) {
			dlog("$ver: Нет новых строк (start_line=$func_start_line > current_total_lines=$current_total_lines)", 4, "DEBUG");
		}

		// Обновляем состояние (файл мог уменьшиться)
		$_SESSION['log_tracker'] = [
			'last_line' => $current_total_lines,
			'total_lines' => $current_total_lines,
			'initialized' => true,
			'last_update' => time()
		];

		$result = [];

		if ($useCache) {
			$_SESSION[$cacheKey] = [
				'result' => $result,
				'timestamp_ms' => $currentTimeMs
			];
		}

		return $result;
	}

	// Освобождаем сессию перед чтением файла
	if (!$sessionWasActive) {
		session_write_close();
	}

	// Читаем новые строки
	$escaped_path = escapeshellarg($logPath);
	$command = 'tail -n +' . $func_start_line . ' ' . $escaped_path;

	if ($max_lines > 0) {
		$command .= ' | head -n ' . $max_lines;
	}

	$command .= ' 2>&1';

	if (defined("DEBUG") && DEBUG) dlog("$ver: Команда: $command", 4, "DEBUG");

	$output = shell_exec($command);

	// Обработка результатов
	$result = false;

	if ($output === null) {
		if (defined("DEBUG") && DEBUG) {
			dlog("$ver: Ошибка выполнения команды tail. Возможно, файл изменился.", 1, "ERROR");
			// Проверяем, существует ли еще файл
			if (!file_exists($logPath)) {
				dlog("$ver: Файл удален!", 1, "ERROR");
			}
		}
		$result = false;
	} elseif (trim($output) === '') {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Нет новых строк", 3, "INFO");
		$result = [];
	} else {
		$lines = explode("\n", trim($output));
		$lines = array_map('trim', $lines);
		$lines = array_filter($lines, function ($line) {
			return $line !== '';
		});
		$lines = array_values($lines);

		$line_count = count($lines);

		if (defined("DEBUG") && DEBUG) {
			dlog("$ver: Получено $line_count новых строк", 4, "DEBUG");
		}

		$result = $lines;
	}

	// Обновляем состояние
	if ($result !== false) {
		if (!$sessionWasActive) {
			session_start();
		}

		if (is_array($result) && !empty($result)) {
			$new_last_line = $func_start_line + count($result) - 1;

			if (defined("DEBUG") && DEBUG) {
				dlog("$ver: Обновляю позицию с $last_line на $new_last_line", 4, "DEBUG");
			}

			$_SESSION['log_tracker'] = [
				'last_line' => $new_last_line,
				'total_lines' => $current_total_lines,
				'initialized' => true,
				'last_update' => time()
			];
		} elseif (!$state['initialized']) {
			// Нет новых строк, но нужно инициализировать
			$_SESSION['log_tracker'] = [
				'last_line' => $current_total_lines,
				'total_lines' => $current_total_lines,
				'initialized' => true,
				'last_update' => time()
			];
		}
	}

	// Сохраняем в кеш
	if ($useCache && $result !== false) {
		$_SESSION[$cacheKey] = [
			'result' => $result,
			'timestamp_ms' => $currentTimeMs
		];
	}

	// Очистка
	unset($output, $lines);

	if (!$sessionWasActive && session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
	if (defined("DEBUG") && DEBUG) {
		$func_time = microtime(true) - $func_start;
		dlog("$ver: Закончил работу за $func_time msec", 3, "INFO");
	}
	return $result;
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