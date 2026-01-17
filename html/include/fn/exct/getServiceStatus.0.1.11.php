<?php
/** 
 * Возвращаем состояние элемента servise 
 * @version 0.1.11
 * @since 0.1.11
 * @date 2025.12.21
 * @filesource /include/fn/exct/getServiceStatus.0.1.11.php
 * @author vladimir@tsurkanenko.ru
 * @return array [start, duration, name, is_active, timestamp_format]
 */
function getServiceStatus() : array
{
	
	if (defined("DEBUG") && DEBUG) {
		$funct_start = microtime(true);
		$ver = "getServiceStatus 0.1.11";
		dlog("$ver: Начинаю выполнение", 3, "WARNING");
	}

	if (defined('TIMESTAMP_FORMAT')) {
		$timestamp_format = TIMESTAMP_FORMAT;
	} else {
		$timestamp_format = '';
	}

	$result = [
		'start' => 0,
		'duration' => 0,
		'name' => defined("SERVICE_TITLE") ? SERVICE_TITLE : "NOT DEFINED",
		'is_active' => false,
		'timestamp_format' => $timestamp_format,
		'log_line_count' => 0,
	];
	
	// Если уже известно количество строк в журнале, ограничиваем глубину поиска в журнале
	// @todo Проблема - с времени предыдущего подсчета прошло какое-то время, 
	// поэтому старый счетчик строк журнала нужно увеличить
	if (isset($_SESSION['status']['service']['log_line_count'])) {
		$count = $_SESSION['status']['service']['log_line_count'] + 100;
	} else {
		$count = null;
	}

	$or_conditions[] = "Tobias Blomberg";
	$or_conditions[] = "SIGTERM";

	$logLines = getLogTailFiltered(1, null,	$or_conditions, $count);
	unset($or_conditions);


	if ($logLines === false) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Ошибка получения журнала или в нем не найдено записей.", 1, "ERROR");
		$result['name'] = "LOG ERROR";
		return $result;
	}

	if (defined("DEBUG") && DEBUG) {
		$result_count = $logLines === false ? "false" : count($logLines);
		dlog("$ver: Получено строк: $result_count", 4, "DEBUG");
		unset($result_count);
	}

	if (!isset($logLines) || !is_array($logLines) || empty($logLines)) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Журнал пустой", 1, "ERROR");
		$result['name'] = "LOG PARSE ERROR";
		return $result;
	}


	$logStatusLine = trim($logLines[0]);
	if (empty($logStatusLine)) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: В журнале не найдено сигналов о состоянии сервиса", 1, "ERROR");
		$result['name'] = "NO STATUS RECORDS";
		return $result;
	}

	$line_timestamp = getLineTime($logStatusLine);
	if ($line_timestamp === false) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Не удалось получить время из строки $logStatusLine", 1, "ERROR");
		$result['name'] = "LOG TIMESTAMP ERROR";
		return $result;
	}



	if (strpos($logLines[0], 'Tobias Blomberg', 0) !== false) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Сервис запущен", 4, "DEBUG");
		$result['is_active'] = true;
		$searchPattern = "Tobias Blomberg";
	} else {
		$result['is_active'] = false;
		$searchPattern = "SIGTERM";
	}
	$log_count = is_null($count) ? 0:$count;
	$logLineCount = countLogLines($searchPattern,$log_count);
	if ($logLineCount === false) {
		$logLineCount = 0;
		$result['name'] = "LOG SIZE ERROR";
	}

	$result['start'] = $line_timestamp;
	$result['duration'] = time() - $line_timestamp;
	$result['log_line_count'] = $logLineCount;

	if (defined("DEBUG") && DEBUG) {
		$funct_time = microtime(true) - $funct_start;
		dlog("$ver: Закончил работу за $funct_time мсек", 3, "WARNING");
		unset($ver, $funct_start, $funct_time);
	}
	return $result;
}



?>