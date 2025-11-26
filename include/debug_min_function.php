<?php 
/**
 * Minimum Function
 */

/** Функция для получения перевода
 * 
 * @param string $lang - язык
 * @param string $key - ключ перевода
 * @param string $default - значение по умолчанию
 */

error_log("debug_min_functions.php loaded"); // Отладочное сообщение

// $lang_db = [
// 	'ru' => include __DIR__ . '/languages/ru.php',
// ];




// function getTranslation($lang, $key, $default = '')
// {
// 	global $lang_db;

// 	// Проверяем существование перевода
// 	if (isset($lang_db[$lang][$key])) {
// 		return $lang_db[$lang][$key];
// 	}

// 	// Если перевод не найден, возвращаем значение по умолчанию
// 	return $default ?: $key;
// }

// Обновляем функцию перевода с fallback
function getTranslation($key, $default = '')
{
	global $lang_db, $lang;

	// Сначала ищем на текущем языке
	if (isset($lang_db[$lang][$key])) {
		return $lang_db[$lang][$key];
	}

	// Fallback на английский
	if ($lang !== 'en' && isset($lang_db['en'][$key])) {
		return $lang_db['en'][$key];
	}

	return $default ?: $key;
}
// Отладка в журнал ошибок
function dlog($msg)
{
	$bt = debug_backtrace()[0];
	error_log(basename($bt['file']) . ":" . $bt['line'] . " - " . $msg);
}
//============================================================
/**
 * Возвращает информацию о текущем сеансе
 * TODO: Поместить позывной из активной логики в поле callsign
 * @author vladimir@tsurkanenko.ru
 * @version 0.1.3
 * @date 2021-11-25
 */
function debug_getSessionInfo($config_file = null)
{
	// ПРОВЕРКА ФАЙЛОВ
	if (empty($config_file)) {
		die("Config file not defined");
	}
	if (!file_exists($config_file)) {
		die("Config file not found: " . $config_file);
	}

	// Читаем конфигурацию
	if (file_exists($config_file)) {
		$svxconfig = parse_ini_file($config_file, true, INI_SCANNER_RAW);
	} else {
		$svxconfig = [];
	}

	if(empty($svxconfig)){
		die("Config not found");
	}

	// DEGUG
	error_reporting(E_ALL);
	ini_set('display_errors', 1);

	$logics = isset($svxconfig['GLOBAL']['LOGICS']) ? explode(",", $svxconfig['GLOBAL']['LOGICS']) : [];
	
	if (empty($logics)) {
		error_log("logics empty");
		return null;
	}
	// Получаем модули для разных логик
	if (in_array("RepeaterLogic", $logics) && isset($svxconfig['RepeaterLogic']['MODULES'])) {
		$modules = explode(",", str_replace('Module', '', $svxconfig['RepeaterLogic']['MODULES']));
	} elseif (in_array("SimplexLogic", $logics) && isset($svxconfig['SimplexLogic']['MODULES'])) {
		$modules = explode(",", str_replace('Module', '', $svxconfig['SimplexLogic']['MODULES']));
	}

	// Получаем активную логику
	// $activeLogic
	// Проверяем наличие ReflectorLogic в массиве
	if (in_array("ReflectorLogic", $logics)) {
		if (empty($activeModule)) {
			// Если activeModule пустое - берем ReflectorLogic
			$activeLogic = "ReflectorLogic";
		} else {
			// Если activeModule не пустое - ищем первое значение не ReflectorLogic
			foreach ($logics as $logic) {
				if ($logic !== "ReflectorLogic") {
					$activeLogic = $logic;
					break;
				}
			}
		}
	} else {
		// Если ReflectorLogic нет в массиве - берем первый элемент
		$activeLogic = $logics[0] ?? "";
	}

	// Заполняем массив логики

	$_logics = []; // Инициализируем как ассоциативный массив
	foreach ($logics as $_logic) {

		$item = [
			'start' => 0,
			'duration' => 0,
			'name' => $_logic,
			'is_active' => $_logic == $activeLogic ? true : false,
			'callsign' => $svxconfig[$_logic]['CALLSIGN'] ?? 'N0CALL',
			'type' => $svxconfig[$_logic]['TYPE'] ?? 'NOT SET',
		];

		if ($_logic == "ReflectorLogic") {
			$item['talkgroups'] = [
				'default' => $svxconfig[$_logic]['DEFAULT_TG'] ?? '0',
				'selected' => $svxconfig[$_logic]['DEFAULT_TG'] ?? '0',
				'monitoring' => explode(",", $svxconfig[$_logic]['MONITOR_TGS']),
				'temp_monitoring' => '0',
			];
			$item['hosts'] = $svxconfig[$_logic]['HOSTS'] ?? 'N0 HOST';
		};
		// Используем имя логики как ключ массива
		$_logics[$_logic] = $item;
	}

	// Заполняем массив модулей

	// Получаем активный
	if (!empty($modules)) {
		$activeModule = debug_getActiveModule();
	} else {
		$activeModule = '';
	}



	// ver 0.1.2
	// Заполняем массив модулей. Для эхолинка и frn добавляем подключенных пользователей
	if (count($modules) > 0) {
		$_modules = []; // Инициализируем как ассоциативный массив

		foreach ($modules as $module) {
			$connected_nodes = [];

			if ($activeModule == $module) {
				$module_log = debug_getModuleSessionLog($module);
				// Изменяем статус модуля is_connected на 1
				$first_line_parse = convertLineToUnixTime($module_log[0]);

				$connected_node_array = debug_getConnected($module_log);
				// список имен подключенных станций включая детали
				foreach ($connected_node_array as $node_name) {
					//TODO разобраться с полями
					$connected_nodes[] = [
						'callsign' => $node_name['name'],
						'details' => $node_name['details'],
						'start' => $node_name['datestamp'],
						'type' => $node_name['type'],
						'duration' => 0,
						'is_active' => $node_name['state'] == 'CONNECTED' ? true : false
					];
				}
				//TODO: разобраться с часовым поясом
				date_default_timezone_set('Europe/Moscow');
				$module_item = [
					'start' => $first_line_parse,
					'duration' => time() - $first_line_parse,
					'name' => $module,
					'callsign' => 'TODO',
					'is_active' => $module == $activeModule ? true : false,
					'is_connected' => count($connected_nodes) > 0 ? true : false,
					'connected_nodes' => $connected_nodes
				];
			} else {
				$module_item = [
					'start' => 0,
					'duration' => 0,
					'name' => $module,
					'is_active' => false,
					'is_connected' => false,
					'connected_nodes' => []
				];
			}

			// Используем имя модуля как ключ массива (аналогично логикам)
			$_modules[$module] = $module_item;
		}
	} else {
		$_modules = []; // Если модулей нет, инициализируем пустым массивом
	}

	// ========================================
	$service[] = [
		'start' => 0,
		'duration' => 0,
		'name' => 'SvxLink',
		'is_active' => isProcessRunning('svxlink')
	];
	$SessionInfo = [
		'start' => 0,
		'duration' => 0,
		'callsign' => $_logics[$activeLogic]['callsign'],
		'active_logic' => $activeLogic,
		'active_module' => $activeModule,
		'logic' => $_logics,
		'module' => $_modules,
		'service' => $service
	];
	return $SessionInfo;
}

/**
 * Original function from include/tools.php getActiveModules()
 * @ver 0.1.2
 * @note Удалены некоторые ненужные переменные
 * @note Изменен результат на строку
 */
function debug_getActiveModule()
{
	$modules = array();
	$logPath = SVXLOGPATH . SVXLOGPREFIX;
	$logLines = explode("\n", `tail -10000 $logPath | egrep -a -h "Activating module|Deactivating module" `);
	$logLines = array_slice($logLines, -250);

	foreach ($logLines as $logLine) {
		if (strpos($logLine, "Activating module") !== false) {
			// Ищем строку типа "SimplexLogic: Activating module Parrot..."
			$parts = explode("Activating module", $logLine);
			if (count($parts) >= 2) {
				$moduleName = trim($parts[1]);
				// Убираем троеточие в конце
				$moduleName = rtrim($moduleName, ". \t\n\r\0\x0B");
				$modules[$moduleName] = 'On';
			}
		}
		if (strpos($logLine, "Deactivating module") !== false) {
			// Ищем строку типа "SimplexLogic: Deactivating module EchoLink..."
			$parts = explode("Deactivating module", $logLine);
			if (count($parts) >= 2) {
				$moduleName = trim($parts[1]);
				// Убираем троеточие в конце
				$moduleName = rtrim($moduleName, ". \t\n\r\0\x0B");
				$modules[$moduleName] = 'Off';
			}
		}
	}

	// Находим последний активный модуль (последний модуль со статусом 'On')
	$activeModule = '';
	foreach ($modules as $moduleName => $status) {
		if ($status === 'On') {
			$activeModule = $moduleName;
		}
	}

	return $activeModule;
}
/**
 * Original function from include/tools.php
 */
function isProcessRunning($processName, $full = false, $refresh = false)
{
	if ($full) {
		static $processes_full = array();
		if ($refresh) $processes_full = array();
		if (empty($processes_full))
			exec('ps -eo args', $processes_full);
	} else {
		static $processes = array();
		if ($refresh) $processes = array();
		if (empty($processes))
			exec('ps -eo comm', $processes);
	}
	foreach (($full ? $processes_full : $processes) as $processString) {
		if (strpos($processString, $processName) !== false)
			return true;
	}
	return false;
}
function debug_getModuleSessionLog(string $moduleName): array
{
	$logPath = SVXLOGPATH . SVXLOGPREFIX;
	$logLines = explode("\n", `tail -10000 $logPath | egrep -a -h "."`);

	$sessionLog = [];
	$activationFound = false;
	$activationIndex = -1;

	// Ищем активацию с конца
	for ($i = count($logLines) - 1; $i >= 0; $i--) {
		$line = trim($logLines[$i]);
		if (empty($line)) continue;

		// Нашли активацию
		if (preg_match('/Activating module ' . preg_quote($moduleName) . '\.\.\.$/', $line)) {
			$activationFound = true;
			$activationIndex = $i;
			break;
		}
	}

	if (!$activationFound) {
		return []; // Сессия не найдена
	}

	// Собираем записи от активации до деактивации или до конца
	for ($i = $activationIndex; $i < count($logLines); $i++) {
		$line = trim($logLines[$i]);
		if (empty($line)) continue;

		$sessionLog[] = $line;

		// Проверяем деактивацию (кроме самой строки активации)
		if (
			$i > $activationIndex &&
			preg_match('/Deactivating module ' . preg_quote($moduleName) . '\.\.\.$/', $line)
		) {
			break; // Нашли деактивацию - заканчиваем
		}
	}

	return array_filter($sessionLog);
}
/**
 * Конвертирует строку журнала в Unix-время 
 * 
 * @param string $logString
 * @return int
 */
function convertLineToUnixTime($logString)
{

	if (empty($logString)) {
		return 0;
	}

	// Извлекаем только временную метку до первого двоеточия после времени
	if (preg_match('/^(\d{1,2} [A-Za-z]{3} \d{4} \d{1,2}:\d{2}:\d{2}(?:\.\d+)?)/', $logString, $matches)) {
		$timestampPart = $matches[1]; // "21 Nov 2025 14:20:39.806"
		$cleanTimestamp = preg_replace('/\.\d+$/', '', $timestampPart); // "21 Nov 2025 14:20:39"
		return strtotime($cleanTimestamp) ?: 0;
	}

	return 0;
}
/**
 * Получает информацию о текущих соединениях
 * @param $logLines - массив строк лога
 * @return array - 'name','type','state','datestamp'
 */
function debug_getConnected($logLines)
{
	$_result = [];

	foreach ($logLines as $index => $_line) {
		// Пропускаем пустые строки
		if (empty(trim($_line))) {
			continue;
		}

		// Обработка QSO state changed
		$_pattern = '/^(\d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2}\.\d{3}): ([^:]+): (\w+) QSO state changed to (\w+)$/';
		if (preg_match($_pattern, $_line, $_matches)) {
			$_datestamp = $_matches[1];
			$_name = $_matches[2];
			$_protocol = $_matches[3];
			$_state = $_matches[4];

			// Определяем тип узла по имени
			$_node_type = "station";
			if (substr($_name, 0, 1) === '*' && substr($_name, -1) === '*') {
				$_node_type = "conference";
			} elseif (substr($_name, -2) === '-R') {
				$_node_type = "repeater";
			} elseif (substr($_name, -2) === '-L') {
				$_node_type = "link";
			}

			// Обновляем или удаляем запись
			$_found_index = -1;
			foreach ($_result as $_result_index => $_item) {
				if ($_item['name'] === $_name) {
					$_found_index = $_result_index;
					break;
				}
			}
			// Если соединение разорвано
			if ($_state === 'DISCONNECTED') {
				if ($_found_index >= 0) {
					unset($_result[$_found_index]);
					$_result = array_values($_result);
				}
				// Если соединение установлено
			} else {
				if ($_found_index >= 0) {
					$_result[$_found_index]['state'] = $_state;
					$_result[$_found_index]['datestamp'] = $_datestamp;
					$_result[$_found_index]['type'] = $_node_type;
				} else {
					$_result[] = [
						'name' => $_name,
						'type' => $_node_type,
						'state' => $_state,
						'datestamp' => $_datestamp,
						'details' => [] // Для QSO событий details пустой
					];
				}
			}
		}

		// Обработка login stage 2 completed
		if (strpos($_line, ': login stage 2 completed:') !== false) {
			$_pattern_login = '/^(\d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2}\.\d{3}): login stage 2 completed: (.*)$/';
			if (preg_match($_pattern_login, $_line, $_matches)) {
				$_datestamp = $_matches[1];
				$_xml_data = $_matches[2];

				// Парсим XML данные
				$_details = parseXmlTags($_xml_data);

				// Извлекаем NT из распарсенных данных или используем значение по умолчанию
				$_id = $_details['NT'] ?? 'unknown';

				// Используем NT как уникальный идентификатор (аналогично "*ECHOTEST*" для эхолинка)
				$_name = $_id;
				$_node_type = "server"; // По умолчанию для login
				$_state = "";

				// Проверяем, нет ли уже такой записи
				$_found_index = -1;
				foreach ($_result as $_result_index => $_item) {
					if ($_item['name'] === $_name) {
						$_found_index = $_result_index;
						break;
					}
				}

				// Добавляем только если записи еще нет (избегаем дубликатов)
				if ($_found_index === -1) {
					$_result[] = [
						'name' => $_name,
						'type' => $_node_type,
						'state' => $_state,
						'datestamp' => $_datestamp,
						'details' => $_details // Добавляем распарсенные данные
					];
				} else {
					// Если запись уже существует, обновляем details
					$_result[$_found_index]['details'] = $_details;
				}
			}
		}
	}

	return $_result;
}
/**
 * Как выяснилось, мониторится может несколько групп
 * 
 * @return array
 * [
 *  '0' => 'TG1',
 *  '1' => 'TG2',
 * ]
 */
function debug_getTemporaryMonitor()
{
	$logPath = SVXLOGPATH . SVXLOGPREFIX;
	$groupStates = []; // Храним последнее состояние для каждой группы

	// Читаем последние 10000 строк журнала и фильтруем строки с Temporary monitor
	$logLines = `tail -10000 $logPath | egrep -a -h "emporary monitor"`;
	$lines = explode("\n", trim($logLines));

	error_log("Found " . count($lines) . " lines with 'Temporary monitor' in log");

	// Обрабатываем каждую строку в хронологическом порядке
	foreach ($lines as $index => $line) {
		if (empty($line)) continue;

		// Универсальный regexp для всех форматов
		if (preg_match('/#(\d+)$/', $line, $matches)) {
			$tgNumber = $matches[1];

			// Определяем тип действия по содержимому строки
			if (strpos($line, 'timeout') !== false) {
				// Помечаем группу как неактивную
				$groupStates[$tgNumber] = false;
				error_log("Line $index: TG#$tgNumber marked INACTIVE (timeout)");
			} else if (strpos($line, 'Add') !== false || strpos($line, 'Refresh') !== false) {
				// Помечаем группу как активную (Add или Refresh)
				$groupStates[$tgNumber] = true;
				error_log("Line $index: TG#$tgNumber marked ACTIVE");
			} else {
				error_log("Line $index: Unknown action for TG#$tgNumber: " . substr($line, 0, 100));
			}
		} else {
			error_log("Line $index: Could not parse line: " . substr($line, 0, 100));
		}
	}

	// Формируем массив активных групп
	$activeGroups = [];
	foreach ($groupStates as $tgNumber => $isActive) {
		if ($isActive) {
			$activeGroups[] = $tgNumber;
		}
	}

	$finalCount = count($activeGroups);
	error_log("Final result: $finalCount active groups: [" . implode(', ', $activeGroups) . "]");

	// Возвращаем массив активных групп
	return $activeGroups;
}

/**
 * @original getSVXTGSelect()
 * @since version 0.1.3
 * @return string
 * Возвращает номер активной (выбранной разговорной группы рефлектора)
 */
function debug_getSelectedTG()
{
	$logPath = SVXLOGPATH . SVXLOGPREFIX;
	$tgselect = "0";
	$logLine = `tail -10000 $logPath | egrep -a -h "Selecting" | tail -1`;
	if (strpos($logLine, "TG #")) {
		$tgselect = substr($logLine, strpos($logLine, "#") + 1, 12);
	}
	return $tgselect;
}
/**
 * Определяет общее состояние радиостанции
 * 
 * Анализирует последние события передатчика и сквилча
 * 
 * @return string "TRANSMIT"|"RECEIVE"|"STANDBY"
 */
function debug_getRadioStatus(): string
{
	$logPath = SVXLOGPATH . SVXLOGPREFIX;
	if (!file_exists($logPath)) {
		return "STANDBY";
	}

	// Получаем последние события передатчика и сквилча
	$command = "tail -10000 " . escapeshellarg($logPath) . " | grep -a -h 'Turning the transmitter\\|The squelch is' | tail -2";
	$lastEvents = `$command`;

	if (empty($lastEvents)) {
		return "STANDBY";
	}

	$events = explode("\n", trim($lastEvents));
	$lastTxEvent = "";
	$lastSquelchEvent = "";

	// Разделяем события на передатчик и сквилч
	foreach ($events as $event) {
		if (strpos($event, "Turning the transmitter") !== false) {
			$lastTxEvent = $event;
		} elseif (strpos($event, "The squelch is") !== false) {
			$lastSquelchEvent = $event;
		}
	}

	// Получаем временные метки для сравнения
	$txTime = $lastTxEvent ? extractTimestamp($lastTxEvent) : 0;
	$squelchTime = $lastSquelchEvent ? extractTimestamp($lastSquelchEvent) : 0;

	// Определяем какое событие было последним
	if ($txTime >= $squelchTime) {
		// Последнее событие - передатчик
		if (strpos($lastTxEvent, "Turning the transmitter ON") !== false) {
			return "TRANSMIT";
		} else {
			return "STANDBY";
		}
	} else {
		// Последнее событие - сквилч
		if (strpos($lastSquelchEvent, "The squelch is OPEN") !== false) {
			return "RECEIVE";
		} else {
			return "STANDBY";
		}
	}
}

/**
 * Извлекает временную метку из строки лога
 */
function extractTimestamp(string $logLine): int
{
	if (preg_match('/^(\d+ \w+ \d+ \d+:\d+:\d+\.\d+):/', $logLine, $matches)) {
		return strtotime($matches[1]);
	}
	return 0;
}

/** Clear a callsign by removing any leading characters that are not letters, numbers, or underscores.
 * If the callsign is empty, return it as is.
 *
 * @param string $callsign The callsign to clear.
 * @return string The cleared callsign.
 */
function clearCallsign($callsign)
{
	if (empty($callsign)) {
		return $callsign;
	}
	$pattern = '/^([^-\/]*)/';
	preg_match($pattern, $callsign, $matches);

	return $matches[1] ?? $callsign;
}

/**
 * Получает события активности для отображения
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_getLocalActivity($limit = 10)
{
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

	// Получаем журнал
	$logLines = getLog("", 2000, false);

	// Фильтруем пустые строки
	$logLines = array_filter($logLines, function ($line) {
		return !empty(trim($line));
	});

	// Обрабатываем ВСЕ события из лога
	$allEvents = debug_filterLogLines($logLines, $activityRules, null);

	// СОРТИРУЕМ события по времени (самые свежие сначала)
	usort($allEvents, function ($a, $b) {
		$timeA = $a['end_time']['unixtime'] ?? $a['start_time']['unixtime'] ?? 0;
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
			'_debug_raw_line' => $event['raw_start_line'] ?? $event['raw_line'] ?? '',
			'_debug_event_type' => $event['status']
		];
	}, $events);

	return $result;
}
/** Получение журнала
 * @noted Исправлена ошибка фильтрации результата поиска
 */
function getLog($search_value = "", $limit = 250, $reverse = false)
{
	$_log_content = [];
	$_log_file = SVXLOGPATH . SVXLOGPREFIX;

	if (!file_exists($_log_file) || !is_readable($_log_file)) {
		return $_log_content;
	}

	// Экранируем все параметры для безопасного использования в shell
	$_escaped_log_file = escapeshellarg($_log_file);
	$_escaped_search = escapeshellarg($search_value);
	$_escaped_limit = escapeshellarg((string)$limit);

	// Базовые проверки параметров
	$limit = max(1, min(100000, (int)$limit)); // Ограничиваем лимит разумными значениями
	$_command = '';
	// Формируем команду в зависимости от условий
	if (empty($search_value)) {
		// Если поиск не задан, просто читаем файл
		if ($reverse) {
			$_command = "head -100000  {$_escaped_log_file} | head -n {$_escaped_limit}";
		} else {
			$_command = "tail -100000  {$_escaped_log_file} | tail -n {$_escaped_limit}";
		}
	} else {
		// Если задан поиск, используем grep
		$buffer_size = min(10000, $limit * 10); // Буфер для поиска
		$_escaped_buffer = escapeshellarg((string)$buffer_size);

		$_command = "tail -100000  {$_escaped_log_file} | tail -n {$_escaped_limit}" . " | grep -a -h {$_escaped_search}";
	}

	// Выполняем команду и обрабатываем результат
	$_output = `$_command`;

	if (!empty($_output)) {
		$_log_content = explode("\n", trim($_output));
		// Удаляем пустые строки
		$_log_content = array_filter($_log_content, function ($line) {
			return !empty(trim($line));
		});
		// Переиндексируем массив
		$_log_content = array_values($_log_content);
	}

	return $_log_content;
}

/**
 * Фильтрует строки журнала по заданным правилам
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_filterLogLines($logLines, $rules, $limit = null)
{
	$events = [];
	$pendingStarts = [];

	foreach ($logLines as $line) {
		$matchedEvent = debug_matchLine($line, $rules);

		if (!$matchedEvent) {
			continue;
		}

		// Для одиночных событий создаем событие сразу
		if ($matchedEvent['is_single']) {
			$singleEvent = debug_createSingleEvent($matchedEvent);
			$events[] = $singleEvent;
		} else {
			// Для парных событий
			$key = debug_generateEventKey($matchedEvent);

			if ($matchedEvent['type'] === 'start') {
				$pendingStarts[$key] = $matchedEvent;
			} elseif ($matchedEvent['type'] === 'end') {
				if (isset($pendingStarts[$key])) {
					$startEvent = $pendingStarts[$key];
					$completeEvent = debug_createCompleteEvent($startEvent, $matchedEvent);
					$events[] = $completeEvent;
					unset($pendingStarts[$key]);
				} else {
					$incompleteEvent = debug_createIncompleteEvent($matchedEvent);
					$events[] = $incompleteEvent;
				}
			}
		}

		if ($limit && count($events) >= $limit) {
			break;
		}
	}

	return $events;
}

/**
 * Проверяет соответствие строки журнала правилам
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_matchLine($line, $rules)
{
	foreach ($rules as $ruleIndex => $rule) {
		$event = debug_applyRule($line, $rule);
		if ($event) {
			$event['rule_index'] = $ruleIndex;
			return $event;
		}
	}
	return null;
}

/**
 * Применяет одно правило к строке журнала
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_applyRule($line, $rule)
{
	// Проверяем базовые фильтры
	if (!debug_checkBasicFilters($line, $rule)) {
		return null;
	}

	// Применяем регулярное выражение
	$matches = [];
	if (isset($rule['rule']) && !empty($rule['rule'])) {
		if (!preg_match($rule['rule'], $line, $matches)) {
			return null;
		}
	} else {
		$matches = debug_parseLineSimple($line);
	}

	// Определяем тип события
	$eventType = debug_determineEventType($line, $rule);
	if (!$eventType) {
		return null;
	}

	// Извлекаем данные
	$extractedData = debug_extractEventData($matches, $rule, $line);

	// Определяем является ли событие одиночным
	$isSingle = isset($rule['is_single']) ? $rule['is_single'] : (isset($rule['action_start']) && isset($rule['action_end']) &&
		$rule['action_start'] === $rule['action_end']);

	return [
		'type' => $eventType,
		'is_single' => $isSingle,
		'timestamp' => [
			'raw' => $extractedData['timestamp'] ?? '',
			'unixtime' => debug_parseTimestamp($extractedData['timestamp'] ?? '')
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
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_checkBasicFilters($line, $rule)
{
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

/**
 * Определяет тип события
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_determineEventType($line, $rule)
{
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

/**
 * Извлекает данные события
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_extractEventData($matches, $rule, $line)
{
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

/**
 * Парсит timestamp в unixtime
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_parseTimestamp($timestampStr)
{
	if (empty($timestampStr)) {
		return 0;
	}

	try {
		$date = DateTime::createFromFormat('d M Y H:i:s.v', $timestampStr);

		if ($date === false) {
			$date = DateTime::createFromFormat('d M Y H:i:s', $timestampStr);
		}

		if ($date !== false) {
			return (float) $date->format('U.u');
		}

		$timestamp = strtotime($timestampStr);
		if ($timestamp !== false) {
			return (float) $timestamp;
		}
	} catch (Exception $e) {
		// В случае ошибки возвращаем 0
	}

	return 0;
}

/**
 * Генерирует ключ для сопоставления
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_generateEventKey($event)
{
	return $event['sender'] . '|' . $event['talkgroup'] . '|' . $event['callsign'];
}

/**
 * Создает одиночное событие
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_createSingleEvent($event)
{
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

/**
 * Создает неполное событие
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_createIncompleteEvent($endEvent)
{
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

/**
 * Создает полное событие
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-24
 * @version 0.1.3
 */
function debug_createCompleteEvent($startEvent, $endEvent)
{
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

/**
 * Парсит XML-теги
 * Функции отображения активности с указанием длительности
 * @file funct_debug_active.php
 * @date 2025-11-26
 * @version 0.1.4
 */
function parseXmlTags($_xml_data) {
    $result = [];
    
    // Регулярное выражение для поиска XML-тегов
    $pattern = '/<([A-Za-z0-9_]+)>(.*?)<\/\\1>/s';
    
    if (preg_match_all($pattern, $_xml_data, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tag_name = $match[1];
            $payload_content = $match[2];
            $result[$tag_name] = $payload_content;
        }
    }
    
    return $result;
}

?>