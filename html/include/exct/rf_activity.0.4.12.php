<?php

/**
 * @filesource /include/exct/rf_activity.0.4.12.php
 * @version 0.4.12
 * @description RF Activity - локальная активность
 * Распознаются принятые в рефлектор,dtmf-посылки, принятые в конференции echolink
 */


// AJAX режим - минимальная инициализация
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
	header('Content-Type: text/html; charset=utf-8');
	$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
	require_once $docRoot . '/include/settings.php';

	$requiredFiles = [
		'/include/fn/getTranslation.php',
		'/include/fn/logTailer.php',
		'/include/fn/formatDuration.php',
		'/include/fn/getLineTime.php'
	];

	foreach ($requiredFiles as $file) {
		$fullPath = $docRoot . $file;
		if (file_exists($fullPath)) {
			require_once $fullPath;
		}
	}

	if (defined("DEBUG") && DEBUG) {
		$dlogFile = $docRoot . '/include/fn/dlog.php';
		if (file_exists($dlogFile)) {
			require_once $dlogFile;
		}
	}

	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
	echo generatePageHead();
	echo buildLocalActivityTable();
	echo generatePageTail();
	exit;
}

// Обычный режим - полная инициализация
if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
	$ver = "rf_activity 0.4.12";
	dlog("$ver: Начинаю работу", 4, "INFO");
	$func_start = microtime(true);
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.php';

if (defined("DEBUG") && DEBUG) {
	include_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.php';
}

function buildLocalActivityTable(): string
{
	if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
		$func_start = microtime(true);
		dlog("buildLocalActivityTable: Начинаю работу", 4, "DEBUG");
	}

	if (isset($_SESSION['TIMEZONE'])) {
		date_default_timezone_set($_SESSION['TIMEZONE']);
	}

	$logLinesCount = $_SESSION['service']['log_line_count'] ?? 10000;

	// 1. Получаем squelch события
	$squelch_lines = getLogTailFiltered( RF_ACTIVITY_LIMIT * 20, null, ["The squelch is"], $logLinesCount);

	if (!$squelch_lines) {
		return generateEmptyTableBody();
	}

	if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
		dlog("Найдено squelch строк: " . count($squelch_lines), 4, "DEBUG");
	}

	// 2. Парсим squelch и находим пары
	$squelch_pairs = findSquelchPairs($squelch_lines);

	if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
		dlog("Найдено пар OPEN/CLOSED (≥2сек): " . count($squelch_pairs), 4, "DEBUG");
	}

	if (empty($squelch_pairs)) {
		return generateEmptyTableBody();
	}

	// Для каждой пары ищем первый паттерн между OPEN и CLOSE
	$activity_rows = [];
	$defaultDestination = getTranslation('Unknown');
	foreach ($squelch_pairs as $pair) {
		$destination = $defaultDestination; 

		// Извлекаем временные метки из строк OPEN и CLOSE
		$open_colon_pos = strpos($pair['open_line'], ': ');
		$close_colon_pos = strpos($pair['close_line'], ': ');

		if ($open_colon_pos === false || $close_colon_pos === false) {
			continue;
		}

		$open_timestamp = substr($pair['open_line'], 0, $open_colon_pos);
		$close_timestamp = substr($pair['close_line'], 0, $close_colon_pos);

		// Находим общий префикс двух временных меток
		$time_prefix = '';
		$min_length = min(strlen($open_timestamp), strlen($close_timestamp));

		for ($i = 0; $i < $min_length; $i++) {
			if ($open_timestamp[$i] !== $close_timestamp[$i]) {
				break;
			}
			$time_prefix .= $open_timestamp[$i];
		}

		if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
			dlog("Общий префикс времени: '{$time_prefix}' для пары OPEN=" . $pair['open_line'], 4, "DEBUG");
		}

		// Получаем строки с этим временным префиксом
		$time_lines = getLogTailFiltered(100, $time_prefix, [], $logLinesCount);

		if ($time_lines && is_array($time_lines)) {
			// Ищем строки между OPEN и CLOSE в полученных строках
			$context_lines = [];
			$open_found = false;

			foreach ($time_lines as $line) {
				// Если это строка OPEN - начинаем сбор
				if (trim($line) === trim($pair['open_line'])) {
					$open_found = true;
					continue;
				}

				// Если это строка CLOSE - заканчиваем сбор
				if (trim($line) === trim($pair['close_line'])) {
					break;
				}

				// Собираем строки между OPEN и CLOSE
				if ($open_found) {
					$context_lines[] = $line;
				}
			}

			if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
				dlog("Найдено строк между OPEN/CLOSE: " . count($context_lines), 4, "DEBUG");
			}

			// Ищем паттерны и DTMF цифры
			if ($context_lines) {
				$primary_destination = null;
				$dtmf_digits = [];
				$dtmf_prefix = '';

				foreach ($context_lines as $line) {
					if (defined("DEBUG") && DEBUG) dlog("Check $line", 4, "DEBUG");

					// Проверяем на основной паттерн (Talker start, Selecting TG, EchoLink)
					$pattern_match = findPatternInLine($line);
					if ($pattern_match !== null) {
						$primary_destination = $pattern_match;
						// При нахождении основного паттерна сбрасываем ранее собранные DTMF
						// чтобы не учитывать DTMF, которые были до паттерна
						$dtmf_digits = [];
						$dtmf_prefix = '';
					}

					// Проверяем на DTMF
					if (preg_match('/:\s*([^:]+):\s*digit=(.+)/', $line, $dtmf_matches)) {
						// Сохраняем префикс из первой DTMF строки (если ещё нет основного паттерна)
						if ($primary_destination === null && empty($dtmf_digits)) {
							$dtmf_prefix = $dtmf_matches[1];
						}
						$dtmf_digits[] = $dtmf_matches[2];
					}
				}

				// Формируем финальный результат
				if ($primary_destination !== null && !empty($dtmf_digits)) {
					// Основной паттерн + DTMF
					$destination = $primary_destination . ': <b>DTMF ' . implode('', $dtmf_digits) . '</b>';
				} elseif ($primary_destination !== null) {
					// Только основной паттерн
					$destination = $primary_destination;
				} elseif (!empty($dtmf_digits)) {
					// Только DTMF 
					$destination = $dtmf_prefix . ': <b>DTMF ' . implode('', $dtmf_digits) . '</b>';
				}
				
			}
		}

		
		$open_time = strtotime($open_timestamp);

		$activity_rows[] = [
			'date' => date('d M Y', $open_time),
			'time' => date('H:i:s', $open_time),
			'destination' => $destination,
			'duration' => formatDuration((int)$pair['duration'])
		];
	}

	// Сортируем и ограничиваем
	usort($activity_rows, function ($a, $b) {
		return strtotime($b['date'] . ' ' . $b['time']) <=> strtotime($a['date'] . ' ' . $a['time']);
	});

	$activity_rows = array_slice($activity_rows, 0, RF_ACTIVITY_LIMIT);

	if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
		dlog("Сформировано " . count($activity_rows) . " строк", 4, "DEBUG");
	}

	$html = generateActivityTableBody($activity_rows);

	if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
		$func_time = microtime(true) - $func_start;
		dlog("buildLocalActivityTable: Закончил за {$func_time} мсек", 3, "INFO");
	}

	return $html;
}

/**
 * Находит пары OPEN/CLOSED из squelch строк
 */
function findSquelchPairs(array $squelch_lines): array
{
	$squelch_events = [];

	foreach ($squelch_lines as $line) {
		$time = getLineTime($line);
		if (!$time) continue;

		if (strpos($line, 'The squelch is') !== false) {
			preg_match('/:\s*(\w+):\s*The squelch is (OPEN|CLOSED)/', $line, $m);
			if (isset($m[1], $m[2])) {
				$squelch_events[] = [
					'time' => $time,
					'device' => $m[1],
					'state' => $m[2],
					'line' => $line
				];
			}
		}
	}

	usort($squelch_events, function ($a, $b) {
		return $a['time'] <=> $b['time'];
	});

	$pairs = [];
	$open_event = null;

	foreach ($squelch_events as $event) {
		if ($event['state'] === 'OPEN') {
			$open_event = $event;
		} elseif ($event['state'] === 'CLOSED' && $open_event && $open_event['device'] === $event['device']) {
			$duration = $event['time'] - $open_event['time'];

			if ($duration >= 2) {
				$pairs[] = [
					'open_time' => $open_event['time'],
					'close_time' => $event['time'],
					'duration' => $duration,
					'device' => $event['device'],
					'open_line' => $open_event['line'],
					'close_line' => $event['line']
				];
			}

			$open_event = null;
		}
	}

	return $pairs;
}

/**
 * Ищет известные паттерны в строке
 */
function findPatternInLine(string $line): ?string
{
	// 1. Talker start (рефлектор) - проверяем совпадение позывного
	if (strpos($line, 'Talker start on TG #') !== false) {
		preg_match('/:\s*([^:]+):\s*Talker start on TG #(\d+):\s*([^\s]+)/', $line, $m);
		if (isset($m[1], $m[2], $m[3])) {
			$logic_name = $m[1];
			$tg_number = $m[2];
			$callsign_from_log = $m[3];

			if (isset($_SESSION['status']['logic'][$logic_name]['callsign'])) {
				$callsign_from_session = $_SESSION['status']['logic'][$logic_name]['callsign'];
				if ($callsign_from_log === $callsign_from_session) {
					if (defined("DEBUG") && DEBUG) dlog("Позывной $callsign_from_log совпал с $callsign_from_session", 4, "DEBUG");
					return "{$logic_name} (TG #{$tg_number})";
				} else {
					if (defined("DEBUG") && DEBUG) dlog("Позывной $callsign_from_log не совпал с $callsign_from_session - пропускаем строку", 4, "DEBUG");
					return null;
				}
			} else {
				if (defined("DEBUG") && DEBUG) dlog("Позывной $callsign_from_log не найден в сессии - пропускаем строку", 4, "DEBUG");
				return null;
			}
		}
	}

	// Selecting TG (выбор группы в рефлекторе)
	if (strpos($line, 'Selecting TG #') !== false) {
		preg_match('/:\s*([^:]+):\s*Selecting TG #(\d+)/', $line, $m);
		if (isset($m[1], $m[2])) {
			if (defined("DEBUG") && DEBUG) dlog("Нашел условие Selecting TG", 4, "DEBUG");
			return "{$m[1]} (TG #{$m[2]})";
		}
	}

	// EchoLink chat message received
	if (strpos($line, 'message received from') !== false) {
		preg_match('/from ([^\s]+)/', $line, $m);
		if (isset($m[1])) {
			if (defined("DEBUG") && DEBUG) dlog("Нашел условие EchoLink", 4, "DEBUG");
			return "EchoLink ({$m[1]})";
		}
	}
	return null;
}

function generateActivityTableBody(array $activity_rows): string
{
	$html = '';
	if (!empty($activity_rows)) {
		foreach ($activity_rows as $row) {
			$html .= '<tr class="divTable divTableRow">';
			$html .= '<td class="divTableContent">' . htmlspecialchars($row['date']) . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['time']) . '</td>';
			$html .= '<td class="divTableCol">' . $row['destination'] . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['duration']) . '</td>';
			$html .= '</tr>';
		}
		return $html;
	} else {
		return generateEmptyTableBody();
	}
}

function generateEmptyTableBody(): string
{
	return '<tr class="divTable divTableRow"><td colspan="4" class="divTableContent">' . getTranslation('No activity history found') . '</td></tr>';
}

function generatePageHead(): string
{

	$result = '</div><div id="rf_activity_content"><table class="divTable" style="word-wrap: break-word; white-space:normal;">';
	$result .= '<tbody class="divTableBody"><tr>';
	$result .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Date') . '<span><b>' . getTranslation('Date') . '</b></span></a></th>';
	$result .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Time') . '<span><b>' . getTranslation('Local Time') . '</b></span></a></th>';
	$result .= '<th><a class="tooltip" href="#">' . getTranslation('Transmission destination') . '<span><b>' . getTranslation("Frn Server, Reflector's Talkgroup, Echolink Node, Conference etc.") . '</b></span></a></th>';
	$result .= '<th width="150px"><a class="tooltip" href="#">' . getTranslation('Duration') . '<span><b>' . getTranslation('Duration in Seconds') . '</b></span></a></th></tr>';
	return $result;
}

function generatePageTail(): string
{
	return '</tbody></table></div></div>';
}

// Сначала получаем HTML таблицы
$tableHtml = buildLocalActivityTable();
$rfResultLimit = RF_ACTIVITY_LIMIT . ' ' . getTranslation('Actions');
echo '<div id="rf_activity"><div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">';
echo getTranslation('Last') . ' ' . RF_ACTIVITY_LIMIT . ' ' . getTranslation('Actions') . " " . getTranslation('RF Activity');

echo generatePageHead();
echo $tableHtml;
echo generatePageTail();

if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
	$func_time = microtime(true) - $func_start;
	dlog("$ver: Закончил работу за $func_time msec", 3, "INFO");
}

unset($tableHtml, $rfResultLimit);
?>

			