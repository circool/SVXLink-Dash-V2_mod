<?php

/** Отбирает события активности (начало/конец вызова, выбор группы, начало/конец открытия шумоподавителя, начало/конец передачи)
 *
 * @namespace Функции отображения активности с указанием длительности
 * @filesource /include/fn/exct/getLocalActivity.0.1.3.php
 * @date 2025.11.24
 * @version 0.1.3
 * @required filterLogLines
 */
function getLocalActivity(int $limit = 10, array $activityRules = null): array
{
	if (defined("DEBUG") && DEBUG) dlog("INIT getLocalActivity", 1, "ERROR");
	if ($activityRules === null) {


		// Правила для фильтрации событий активности
		$activityRules = [
			// Правило 1: Talker события (парные)
			[
				'sender' => 'ReflectorLogic',
				'action_start' => 'Talker start on TG #',
				'action_end' => 'Talker stop on TG #',
				'rule' => '/^([\d\w\s\.:]+):\s*(\w+):\s*Talker (start|stop) on TG #(\d+):\s*([^\s]+)/',
				'is_single' => false
			],
			// Правило 2: Selecting TG события (одиночные)
			[
				'sender' => 'ReflectorLogic',
				'action_start' => 'Selecting TG #',
				'action_end' => 'Selecting TG #',
				'rule' => '/^([\d\w\s\.:]+):\s*(\w+):\s*Selecting TG #(\d+)/',
				'is_single' => true
			],
			// Правило 3: Squelch события (парные)
			[
				'sender' => 'Rx1',
				'action_start' => 'The squelch is OPEN',
				'action_end' => 'The squelch is CLOSED',
				'rule' => '/^([\d\w\s\.:]+):\s*(\w+):\s*(The squelch is (OPEN|CLOSED))/',
				'is_single' => false
			],
			// Правило 4: Transmitter события (парные)
			[
				'sender' => 'MultiTx',
				'action_start' => 'Turning the transmitter ON',
				'action_end' => 'Turning the transmitter OFF',
				'rule' => '/^([\d\w\s\.:]+):\s*(\w+):\s*(Turning the transmitter (ON|OFF))/',
				'is_single' => false
			]
		];
	}

	// Получаем журнал
	$logLines = getLog("", 2000, false);

	// Фильтруем пустые строки
	$logLines = array_filter($logLines, function ($line) {
		return !empty(trim($line));
	});

	// Обрабатываем ВСЕ события из лога
	$allEvents = filterLogLines($logLines, $activityRules, null);

	// СОРТИРУЕМ события по времени (самые свежие сначала)
	usort($allEvents, function ($a, $b) {
		$timeA = $a['end_time']['unixtime'] ?? $a['start']['unixtime'] ?? 0;
		$timeB = $b['end_time']['unixtime'] ?? $b['start_time']['unixtime'] ?? 0;

		return $timeB <=> $timeA;
	});

	// Берем только нужное количество самых свежих событий
	$events = array_slice($allEvents, 0, $limit);

	// Форматируем для отображения
	$result = array_map(function ($event) {
		return [
			'timestamp' => [
				'date' => [
					'iso' => $event['start_time']['raw'] ?? $event['end_time']['raw'] ?? ''
				]
			],
			'sender' => $event['sender'],
			'BC' => 'RF',
			'ID' => $event['talkgroup'] ? 'TG #' . $event['talkgroup'] : '',
			'DS' => $event['callsign'] ?: 'Local',
			'payload' => $event['duration_formatted'],
			'status' => $event['status'],
			// Дополнительная информация
			'_raw_line' => $event['raw_start_line'] ?? $event['raw_line'] ?? '',
			'_event_type' => $event['status']
		];
	}, $events);

	return $result;
}

/** Применяет одно правило к строке журнала
 *
 * @namespace Функции отображения активности с указанием длительности
 * @filesource funct_active.php
 * @date 2025.11.24
 * @version 0.1.3
 */
function applyRule($line, $rule)
{
	if (defined("DEBUG") && DEBUG) dlog("INIT applyRule", 1, "ERROR");
	// Проверяем базовые фильтры
	if (!checkBasicFilters($line, $rule)) {
		return null;
	}

	// Применяем регулярное выражение
	$matches = [];
	if (isset($rule['rule']) && !empty($rule['rule'])) {
		if (!preg_match($rule['rule'], $line, $matches)) {
			return null;
		}
	} else {
		$matches = parseLineSimple($line);
	}

	// Определяем тип события
	$eventType = determineEventType($line, $rule);
	if (!$eventType) {
		return null;
	}

	// Извлекаем данные
	$extractedData = extractEventData($matches, $rule, $line);

	// Определяем является ли событие одиночным
	$isSingle = isset($rule['is_single']) ? $rule['is_single'] : (isset($rule['action_start']) && isset($rule['action_end']) &&
		$rule['action_start'] === $rule['action_end']);

	return [
		'type' => $eventType,
		'is_single' => $isSingle,
		'timestamp' => [
			'raw' => $extractedData['timestamp'] ?? '',
			'unixtime' => '' //convertLineToUnixTime($extractedData['timestamp'] ?? '')
		],
		'sender' => $extractedData['sender'] ?? '',
		'payload' => $extractedData['payload'] ?? '',
		'talkgroup' => $extractedData['talkgroup'] ?? '',
		'callsign' => $extractedData['callsign'] ?? '',
		'raw_line' => $line,
		'rule_id' => md5(serialize($rule))
	];
}

/**
 * Проверяет базовые фильтры
 * @namespace Функции отображения активности с указанием длительности
 * @filesource funct_active.php
 * @date 2025.11.24
 * @version 0.1.3
 */
function checkBasicFilters($line, $rule)
{
	if (defined("DEBUG") && DEBUG) dlog("INIT checkBasicFilters", 1, "ERROR");
	// Проверка sender
	if (isset($rule['sender']) && !empty($rule['sender'])) {
		if (strpos($line, $rule['sender']) === false) {
			return false;
		}
	}

	// Проверяем действия
	$hasStartAction = isset($rule['action_start']) && strpos($line, $rule['action_start']) !== false;
	$hasEndAction = isset($rule['action_end']) && strpos($line, $rule['action_end']) !== false;

	return $hasStartAction || $hasEndAction;
}

/** Определяет тип события
 *
 * @namespace Функции отображения активности с указанием длительности
 * @filesource funct_active.php
 * @date 2025.11.24
 * @version 0.1.3
 */
function determineEventType($line, $rule)
{
	if (defined("DEBUG") && DEBUG) dlog("INIT determineEventType", 1, "ERROR");
	// Для одиночных событий всегда возвращаем 'single'
	$isSingle = isset($rule['is_single']) ? $rule['is_single'] : (isset($rule['action_start']) && isset($rule['action_end']) &&
		$rule['action_start'] === $rule['action_end']);

	if ($isSingle) {
		return 'single';
	}

	if (isset($rule['action_start']) && strpos($line, $rule['action_start']) !== false) {
		return 'start';
	}

	if (isset($rule['action_end']) && strpos($line, $rule['action_end']) !== false) {
		return 'end';
	}

	return null;
}

/** Извлекает данные события
 *
 * @namespace Функции отображения активности с указанием длительности
 * @filesource funct_active.php
 * @date 2025.11.24
 * @version 0.1.3
 */
function extractEventData($matches, $rule, $line)
{
	if (defined("DEBUG") && DEBUG) dlog("INIT extractEventData", 1, "ERROR");
	$data = [];

	// Для Selecting TG событий
	if (strpos($line, 'Selecting TG #') !== false && count($matches) >= 4) {
		$data['timestamp'] = $matches[1] ?? '';
		$data['sender'] = $matches[2] ?? '';
		$data['payload'] = $matches[0] ?? $line;
		$data['talkgroup'] = $matches[3] ?? '';
		$data['callsign'] = '';
	}
	// Для Talker событий
	elseif (strpos($line, 'Talker') !== false && count($matches) >= 6) {
		$data['timestamp'] = $matches[1] ?? '';
		$data['sender'] = $matches[2] ?? '';
		$data['payload'] = $matches[0] ?? $line;
		$data['talkgroup'] = $matches[4] ?? '';
		$data['callsign'] = $matches[5] ?? '';
	}
	// Для Squelch событий
	elseif (strpos($line, 'The squelch is') !== false && count($matches) >= 4) {
		$data['timestamp'] = $matches[1] ?? '';
		$data['sender'] = $matches[2] ?? '';
		$data['payload'] = $matches[3] ?? $line;
		$data['talkgroup'] = '';
		$data['callsign'] = '';
	}
	// Для Transmitter событий
	elseif (strpos($line, 'Turning the transmitter') !== false && count($matches) >= 4) {
		$data['timestamp'] = $matches[1] ?? '';
		$data['sender'] = $matches[2] ?? '';
		$data['payload'] = $matches[3] ?? $line;
		$data['talkgroup'] = '';
		$data['callsign'] = '';
	}
	// Общий случай
	elseif (count($matches) >= 4) {
		$data['timestamp'] = $matches[1] ?? '';
		$data['sender'] = $matches[2] ?? '';
		$data['payload'] = $matches[3] ?? $line;
	} else {
		// Резервный метод
		$parts = explode(':', $line, 3);
		if (count($parts) >= 3) {
			$data['timestamp'] = trim($parts[0]);
			$data['sender'] = trim($parts[1]);
			$data['payload'] = trim($parts[2]);
		} else {
			$data['timestamp'] = '';
			$data['sender'] = '';
			$data['payload'] = $line;
		}
	}

	return $data;
}


/** Генерирует ключ для сопоставления
 *
 * @namespace Функции отображения активности с указанием длительности
 * @filesource funct_active.php
 * @date 2025.11.24
 * @version 0.1.3
 */
function generateEventKey($event)
{
	if (defined("DEBUG") && DEBUG) dlog("INIT generateEventKey", 1, "ERROR");
	return $event['sender'] . '|' . $event['talkgroup'] . '|' . $event['callsign'];
}

/** Создает одиночное событие
 *
 * @namespace Функции отображения активности с указанием длительности
 * @filesource funct_active.php
 * @date 2025.11.24
 * @version 0.1.3
 */
function createSingleEvent($event)
{
	if (defined("DEBUG") && DEBUG) dlog("INIT createSingleEvent", 1, "ERROR");
	return [
		'start_time' => $event['timestamp'],
		'end_time' => $event['timestamp'],
		'duration' => 0,
		'duration_formatted' => '0.000 сек.',
		'sender' => $event['sender'],
		'payload' => $event['payload'],
		'talkgroup' => $event['talkgroup'],
		'callsign' => $event['callsign'],
		'raw_start_line' => $event['raw_line'],
		'raw_end_line' => $event['raw_line'],
		'status' => 'single'
	];
}

/** Создает неполное событие
 *
 * @namespace Функции отображения активности с указанием длительности
 * @filesource funct_active.php
 * @date 2025.11.24
 * @version 0.1.3
 */
function createIncompleteEvent($endEvent)
{
	if (defined("DEBUG") && DEBUG) dlog("INIT createIncompleteEvent", 1, "ERROR");
	$currentTime = microtime(true);
	$duration = $currentTime - $endEvent['timestamp']['unixtime'];

	return [
		'start_time' => null,
		'end_time' => $endEvent['timestamp'],
		'duration' => $duration,
		'duration_formatted' => sprintf('%.3f сек. (активно)', $duration),
		'sender' => $endEvent['sender'],
		'payload' => $endEvent['payload'],
		'talkgroup' => $endEvent['talkgroup'],
		'callsign' => $endEvent['callsign'],
		'raw_start_line' => null,
		'raw_end_line' => $endEvent['raw_line'],
		'status' => 'incomplete_end_only'
	];
}

/** Создает полное событие
 *
 * @namespace Функции отображения активности с указанием длительности
 * @filesource funct_active.php
 * @date 2025.11.24
 * @version 0.1.3
 */
function createCompleteEvent($startEvent, $endEvent)
{
	if (defined("DEBUG") && DEBUG) dlog("INIT createCompleteEvent", 1, "ERROR");
	$duration = $endEvent['timestamp']['unixtime'] - $startEvent['timestamp']['unixtime'];

	return [
		'start_time' => $startEvent['timestamp'],
		'end_time' => $endEvent['timestamp'],
		'duration' => $duration,
		'duration_formatted' => sprintf('%.3f сек.', $duration),
		'sender' => $startEvent['sender'],
		'payload' => $startEvent['payload'],
		'talkgroup' => $startEvent['talkgroup'],
		'callsign' => $startEvent['callsign'],
		'raw_start_line' => $startEvent['raw_line'],
		'raw_end_line' => $endEvent['raw_line'],
		'status' => 'complete'
	];
}



?>