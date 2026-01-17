<?php

/**
 * @filesource /include/exct/rf_activity.0.4.9.php
 * @version 0.4.9
 * @description RF Activity - поиск контекста по урезанным временным меткам с AJAX-обновлением
 * @note Добавлено динамическое обновление каждые SLOW_UPDATE_INTERVAL (3000 мс)
 */

// Если это AJAX-запрос, возвращаем только таблицу без JavaScript
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// Для AJAX-запросов используем минимальный набор зависимостей
if ($isAjax) {
	// Определяем корень документа
	$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';

	// Подключаем settings.php для констант
	$settingsFile = $docRoot . '/include/settings.php';
	if (file_exists($settingsFile)) {
		require_once $settingsFile;
	}

	// Подключаем только необходимые файлы
	$requiredFiles = [
		'/include/fn/getTranslation.php',
		'/include/fn/logTailer.0.2.1.php',
		'/include/fn/formatDuration.0.1.2.php',
		'/include/fn/getLineTime.0.1.2.php'
	];

	foreach ($requiredFiles as $file) {
		$fullPath = $docRoot . $file;
		if (file_exists($fullPath)) {
			require_once $fullPath;
		}
	}

	// Подключаем dlog только если DEBUG включен и файл существует
	if (defined("DEBUG") && DEBUG) {
		$dlogFile = $docRoot . '/include/fn/dlog.0.2.php';
		if (file_exists($dlogFile)) {
			require_once $dlogFile;
		}
	}

	// Инициализация сессии только для чтения
	if (session_status() === PHP_SESSION_NONE) {
		session_name(SESSION_NAME);
		session_start();
	}

	// Сразу освобождаем сессию
	session_write_close();
} else {
	// Обычный режим - подключаем как обычно
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.0.2.1.php';
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.0.1.2.php';


	if (defined("DEBUG") && DEBUG) {
		include_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.0.2.php';
	}
}

function buildLocalActivityTable(): string
{
	global $isAjax;

	if (!$isAjax && defined("DEBUG") && DEBUG && function_exists("dlog")) {
		$func_start = microtime(true);
		dlog("=== RF ACTIVITY 0.4.9 ===", 4, "DEBUG");
	}

	$rfResultLimit = RF_ACTIVITY_LIMIT . ' ' . getTranslation('Actions');
	$logLinesCount = $_SESSION['log_tracker']['total_lines'] ?? 1000;

	// 1. Получаем squelch события
	$squelch_lines = getLogTailFiltered(
		RF_ACTIVITY_LIMIT * 20,
		null,
		["The squelch is"],
		$logLinesCount
	);

	if (!$squelch_lines) {
		return generateEmptyTable($rfResultLimit);
	}

	if (!$isAjax && defined("DEBUG") && DEBUG && function_exists("dlog")) {
		dlog("Найдено squelch строк: " . count($squelch_lines), 4, "DEBUG");
	}

	// 2. Парсим squelch и находим пары
	$squelch_pairs = findSquelchPairs($squelch_lines);

	if (!$isAjax && defined("DEBUG") && DEBUG && function_exists("dlog")) {
		dlog("Найдено пар OPEN/CLOSED (≥2сек): " . count($squelch_pairs), 4, "DEBUG");
	}

	if (empty($squelch_pairs)) {
		return generateEmptyTable($rfResultLimit);
	}

	// 3. Получаем поисковые паттерны для всех пар
	$search_patterns = getSearchPatternsFromPairs($squelch_pairs);

	if (!$isAjax && defined("DEBUG") && DEBUG && function_exists("dlog")) {
		dlog("Уникальных поисковых паттернов: " . count($search_patterns), 4, "DEBUG");
	}


	$context_lines = getLogTailFiltered(
		500,
		null,
		$search_patterns,
		$logLinesCount
	);

	if (!$isAjax && defined("DEBUG") && DEBUG && function_exists("dlog")) {
		$context_count = is_array($context_lines) ? count($context_lines) : 0;
		dlog("Найдено строк контекста: " . $context_count, 4, "DEBUG");
	}

	// 5. Парсим контекстные строки
	$parsed_context = parseContextLines($context_lines);

	if (!$isAjax && defined("DEBUG") && DEBUG && function_exists("dlog")) {
		dlog("Распарсено контекстных событий: " . count($parsed_context), 4, "DEBUG");
	}

	// 6. Связываем контекст с парами
	$activity_rows = [];

	foreach ($squelch_pairs as $pair) {
		// Ищем события контекста между open и close
		$context_events = findContextForPair($parsed_context, $pair['open_time'], $pair['close_time']);

		// Определяем назначение
		$destination = determineDestinationFromContext($context_events);

		// Определяем логику и модуль
		$logic_module = determineLogicAndModule($pair['open_time'], $pair['close_time'], $pair['device']);

		// Корректируем модуль если нужно
		if (strpos($destination, 'Reflector') !== false && $logic_module['module'] === 'EchoLink') {
			$logic_module['module'] = '';
		}

		$activity_rows[] = [
			'date' => date('d M Y', $pair['open_time']),
			'time' => date('H:i:s', $pair['open_time']),
			'caller' => 'Local',
			'destination' => $destination,
			'logic' => $logic_module['logic'],
			'module' => $logic_module['module'],
			'duration' => formatDuration((int)$pair['duration'])
		];
	}

	// 7. Сортируем и ограничиваем
	usort($activity_rows, function ($a, $b) {
		return strtotime($b['date'] . ' ' . $b['time']) <=> strtotime($a['date'] . ' ' . $a['time']);
	});

	$activity_rows = array_slice($activity_rows, 0, RF_ACTIVITY_LIMIT);

	// 8. Генерируем HTML
	$html = generateActivityTable($activity_rows, $rfResultLimit);

	if (!$isAjax && defined("DEBUG") && DEBUG && function_exists("dlog")) {
		$func_time = microtime(true) - $func_start;
		dlog("=== ЗАВЕРШЕНО за {$func_time} мсек ===", 4, "DEBUG");
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
 * Создает поисковые паттерны из пар squelch
 * Отрезает 5 символов с конца (секунды и миллисекунды)
 */
function getSearchPatternsFromPairs(array $squelch_pairs): array
{
	$patterns = [];

	foreach ($squelch_pairs as $pair) {
		// Паттерн для OPEN
		$open_pattern = getTimePatternFromLine($pair['open_line']);
		if ($open_pattern) {
			$patterns[$open_pattern] = true;
		}

		// Паттерн для CLOSE
		$close_pattern = getTimePatternFromLine($pair['close_line']);
		if ($close_pattern) {
			$patterns[$close_pattern] = true;
		}
	}

	return array_keys($patterns);
}

/**
 * Извлекает временной паттерн из строки журнала
 * Отрезает 5 символов с конца (.xxx и последняя цифра секунд)
 */
function getTimePatternFromLine(string $log_line): string
{
	// Находим позицию первого ": " после временной метки
	$colon_pos = strpos($log_line, ': ');
	if ($colon_pos === false) {
		return '';
	}

	// Берем часть ДО первого ": "
	$timestamp_part = substr($log_line, 0, $colon_pos);

	// Отрезаем 5 символов с конца: .xxx (миллисекунды) + последняя цифра секунд
	if (strlen($timestamp_part) >= 5) {
		$pattern = substr($timestamp_part, 0, -5);
		return $pattern;
	}

	return $timestamp_part;
}

/**
 * Парсит контекстные строки в структурированные события
 */
function parseContextLines($context_lines): array
{
	if (!$context_lines || !is_array($context_lines)) {
		return [];
	}

	$parsed_events = [];

	foreach ($context_lines as $line) {
		$time = getLineTime($line);
		if (!$time) continue;

		$event = null;

		// Talker start события
		if (strpos($line, 'Talker start on TG #') !== false) {
			preg_match('/:\s*([^:]+):\s*Talker start on TG #(\d+):\s*([^\s]+)/', $line, $m);
			if (isset($m[1], $m[2], $m[3])) {
				$event = [
					'type' => 'talker_start',
					'reflector' => $m[1],
					'tg' => $m[2],
					'callsign' => $m[3],
					'line' => $line
				];
			}
		}
		// Talker stop события
		elseif (strpos($line, 'Talker stop on TG #') !== false) {
			preg_match('/:\s*([^:]+):\s*Talker stop on TG #(\d+):\s*([^\s]+)/', $line, $m);
			if (isset($m[1], $m[2], $m[3])) {
				$event = [
					'type' => 'talker_stop',
					'reflector' => $m[1],
					'tg' => $m[2],
					'callsign' => $m[3],
					'line' => $line
				];
			}
		}
		// EchoLink подключение
		elseif (strpos($line, 'EchoLink QSO state changed to CONNECTED') !== false) {
			preg_match('/:\s*([^:]+):\s*EchoLink QSO state changed to CONNECTED/', $line, $m);
			$event = [
				'type' => 'echolink_connect',
				'node' => $m[1] ?? '',
				'line' => $line
			];
		}
		// Frn подключение
		elseif (strpos($line, 'login stage 2 completed:') !== false) {
			$xml_part = substr($line, strpos($line, ': ') + 2);
			preg_match('/<BN>([^<]+)<\/BN>/', $xml_part, $m);
			$event = [
				'type' => 'frn_connect',
				'server' => $m[1] ?? 'Server',
				'line' => $line
			];
		}
		// Frn голосовая активность
		elseif (strpos($line, 'voice started:') !== false) {
			$event = [
				'type' => 'frn_voice',
				'line' => $line
			];
		}
		// Другие полезные события
		elseif (strpos($line, 'EchoLink chat message received from') !== false) {
			preg_match('/from ([^\s]+)/', $line, $m);
			$event = [
				'type' => 'echolink_chat',
				'node' => $m[1] ?? '',
				'line' => $line
			];
		}

		if ($event) {
			$event['time'] = $time;
			$parsed_events[] = $event;
		}
	}

	return $parsed_events;
}

/**
 * Находит события контекста для пары squelch
 */
function findContextForPair(array $parsed_context, int $open_time, int $close_time): array
{
	$context_for_pair = [];

	foreach ($parsed_context as $event) {
		if ($event['time'] >= $open_time && $event['time'] <= $close_time) {
			$context_for_pair[] = $event;
		}
	}

	return $context_for_pair;
}

/**
 * Определяет назначение из событий контекста
 */
function determineDestinationFromContext(array $context_events): string
{
	// Приоритет 1: Talker start (рефлектор)
	foreach ($context_events as $event) {
		if ($event['type'] === 'talker_start') {
			return "{$event['reflector']} (TG #{$event['tg']})";
		}
	}

	// Приоритет 2: EchoLink подключение
	foreach ($context_events as $event) {
		if ($event['type'] === 'echolink_connect') {
			return "EchoLink ({$event['node']})";
		}
	}

	// Приоритет 3: Frn события
	foreach ($context_events as $event) {
		if ($event['type'] === 'frn_connect') {
			return "Frn ({$event['server']})";
		}
		if ($event['type'] === 'frn_voice') {
			return "Frn Server";
		}
	}

	// Приоритет 4: EchoLink чат (менее надежно)
	foreach ($context_events as $event) {
		if ($event['type'] === 'echolink_chat') {
			return "EchoLink ({$event['node']})";
		}
	}

	return "RF Local";
}

/**
 * Определяет логику и модуль из сессии
 */
function determineLogicAndModule(int $open_time, int $close_time, string $device): array
{
	$result = ['logic' => '', 'module' => ''];

	if (!isset($_SESSION['status']['logic'])) {
		return $result;
	}

	foreach ($_SESSION['status']['logic'] as $logic_name => $logic) {
		if (isset($logic['rx']) && $logic['rx'] === $device) {
			$result['logic'] = $logic_name;

			if (isset($logic['module'])) {
				foreach ($logic['module'] as $module_name => $module) {
					$is_active = false;

					if (in_array($module_name, ['Frn', 'EchoLink'])) {
						$is_active = isset($module['is_connected']) && $module['is_connected'];
					} else {
						$is_active = isset($module['is_active']) && $module['is_active'];
					}

					if ($is_active && isset($module['start'])) {
						$module_end = $module['start'] + ($module['duration'] ?? 0);
						if ($module['start'] <= $open_time && $module_end >= $close_time) {
							$result['module'] = $module_name;
							break;
						}
					}
				}
			}
			break;
		}
	}

	return $result;
}

function generateActivityTable(array $activity_rows, string $rfResultLimit): string
{
	$html = '<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">';
	$html .= getTranslation('Last') . " " . $rfResultLimit . " " . getTranslation('RF Activity');
	$html .= '</div>';

	$html .= '<table class="divTable" style="word-wrap: break-word; white-space:normal;">';
	$html .= '<tbody class="divTableBody">';
	$html .= '<tr>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Date') . '<span><b>' . getTranslation('Date') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Time') . '<span><b>' . getTranslation('Local Time') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Caller') . '<span><b>' . getTranslation('Who is target of transmission') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Transmission destination') . '<span><b>' . getTranslation("Frn Server, Reflector's Talkgroup, Echolink Node, Conference etc.") . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Logic') . '<span><b>' . getTranslation('Active Logic') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Module') . '<span><b>' . getTranslation('Active module') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Duration') . '<span><b>' . getTranslation('Duration in Seconds') . '</b></span></a></th>';
	$html .= '</tr>';

	if (empty($activity_rows)) {
		for ($i = 0; $i < RF_ACTIVITY_LIMIT; $i++) {
			$html .= '<tr class="divTable divTableRow">';
			$html .= '<td class="divTableContent"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '</tr>';
		}
	} else {
		$filled_count = count($activity_rows);
		foreach ($activity_rows as $row) {
			$html .= '<tr class="divTable divTableRow">';
			$html .= '<td class="divTableContent">' . htmlspecialchars($row['date']) . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['time']) . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['caller']) . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['destination']) . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['logic']) . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['module']) . '</td>';
			$html .= '<td class="divTableCol">' . htmlspecialchars($row['duration']) . '</td>';
			$html .= '</tr>';
		}

		for ($i = $filled_count; $i < RF_ACTIVITY_LIMIT; $i++) {
			$html .= '<tr class="divTable divTableRow">';
			$html .= '<td class="divTableContent"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '<td class="divTableCol"></td>';
			$html .= '</tr>';
		}
	}

	$html .= '</tbody></table>';
	return $html;
}

function generateEmptyTable(string $rfResultLimit): string
{
	$html = '<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">';
	$html .= getTranslation('Last') . " " . $rfResultLimit . " " . getTranslation('RF Activity');
	$html .= '</div>';

	$html .= '<table class="divTable" style="word-wrap: break-word; white-space:normal;">';
	$html .= '<tbody class="divTableBody">';
	$html .= '<tr>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Date') . '<span><b>' . getTranslation('Date') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Time') . '<span><b>' . getTranslation('Local Time') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Caller') . '<span><b>' . getTranslation('Who is target of transmission') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">Transmission destination<span><b>' . getTranslation("Frn Server, Reflector's Talkgroup, Echolink Node, Conference etc.") . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Logic') . '<span><b>' . getTranslation('Active Logic') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Module') . '<span><b>' . getTranslation('Active module') . '</b></span></a></th>';
	$html .= '<th><a class="tooltip" href="#">' . getTranslation('Duration') . '<span><b>' . getTranslation('Duration in Seconds') . '</b></span></a></th>';
	$html .= '</tr>';

	for ($i = 0; $i < RF_ACTIVITY_LIMIT; $i++) {
		$html .= '<tr class="divTable divTableRow">';
		$html .= '<td class="divTableContent"></td>';
		$html .= '<td class="divTableCol"></td>';
		$html .= '<td class="divTableCol"></td>';
		$html .= '<td class="divTableCol"></td>';
		$html .= '<td class="divTableCol"></td>';
		$html .= '<td class="divTableCol"></td>';
		$html .= '<td class="divTableCol"></td>';
		$html .= '</tr>';
	}

	$html .= '</tbody></table>';
	return $html;
}



// ===================================================
// Основная логика выполнения
// ===================================================

// Сначала получаем HTML таблицы
$tableHtml = buildLocalActivityTable();

if ($isAjax) {
	// Для AJAX-запросов возвращаем только HTML таблицы
	header('Content-Type: text/html; charset=utf-8');
	echo $tableHtml;
	exit;
}

// Для обычных запросов выводим таблицу + JavaScript для автообновления
?>
<div id="rf_activity">
	<div id="rf_activity_table_container">
	<?php echo $tableHtml; ?>
</div>
</div>
<br>
<script>
	(function() {
		'use strict';

		const container = document.getElementById('rf_activity_table_container');
		if (!container) {
			console.warn('RF Activity container not found');
			return;
		}

		let isUpdating = false;
		const updateInterval = <?php echo defined('SLOW_UPDATE_INTERVAL') ? SLOW_UPDATE_INTERVAL : 3000; ?>;

		function updateRfActivityTable() {
			if (isUpdating) return;

			isUpdating = true;

			// Используем правильный путь к файлу через symlink
			const url = '/include/rf_activity.php?ajax=1&t=' + Date.now();

			fetch(url)
				.then(response => {
					if (!response.ok) {
						throw new Error('Network response was not ok: ' + response.status);
					}
					return response.text();
				})
				.then(html => {
					container.innerHTML = html;
					isUpdating = false;
				})
				.catch(error => {
					console.error('Error updating RF activity:', error);
					isUpdating = false;
				});
		}

		// Обновляем сразу при загрузке (небольшая задержка для инициализации)
		setTimeout(updateRfActivityTable, 1500);

		// Периодическое обновление
		let intervalId = setInterval(updateRfActivityTable, updateInterval);

		// Останавливаем обновление при скрытии страницы для экономии ресурсов
		document.addEventListener('visibilitychange', function() {
			if (document.hidden) {
				clearInterval(intervalId);
			} else {
				// При возвращении на страницу обновляем сразу и запускаем интервал заново
				setTimeout(updateRfActivityTable, 500);
				intervalId = setInterval(updateRfActivityTable, updateInterval);
			}
		});

		// Экспортируем функцию для ручного обновления (опционально)
		window.updateRfActivityTable = updateRfActivityTable;

	})();
</script>