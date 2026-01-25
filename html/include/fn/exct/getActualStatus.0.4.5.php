<?php

/** Обновление статуса сессии, логики, линков и модулей
 * @author vladimir@tsurkanenko.ru
 * @date 2026.01.24
 * @filesource /include/fn/exct/getActualStatus.0.4.5.php
 * @version 0.4.5

 * Среднее время выполнения 450-480 ms
 * @since 0.1.13
 * - Добавлена обработка [status][link][is_connected]
 * 	-- Если в журнале была активность - is_active = true, иначе false
 *  -- is_connected отражает состояние включения/выкобчения
 * @since 0.1.14
 * - Добавлено кеширование в сессии на MAIN_STATE_CACHE_TIME мсек
 * - Добавлен необязательный параметр $noCache для отключения кеширования
 * @since 0.1.15 (2025.12.19)
 * - Переименован параметр $noCache -> $forceRebuild
 * - Исправлена логика работы с параметром 
 *   -- При $forceRebuild = true функция пропускает чтение из кеша, но ВСЕГДА записывает результат в кеш после перерасчета
 *   -- Это гарантирует, что после принудительного обновления данные будут закешированы для последующих вызовов
 * @since 0.1.16
 * 	@todo Нужно всегда возвращать массив, даже если он пустой
 * 	@todo Если выполняется перерасчет, брать только новые строки журнала. Для этого нужно хранить номер последней строки журнала.
 * @since 0.2.1
 *  - Отказ от промежуточного получения конфигурации - интегрировано в тело функции
 * @since 0.3.1
 *  - Отказ от повторного парсинга конфигурации, когда в сессии уже он есть
 * @since 0.3.2
 *  - Ограничение глубини поиска если известен размер сессии (экономия от 150 мсек при выполнении countLogLines)
 *  - Исправление ошибки не очистки старых активных модулей в сессии при повторной перезагрузке страницы
 * @since 0.4.1
 * 	Реализован разбор макросов логики
 * @since 0.4.2
 * 	Добавлена фильтрация закоментированных строк при парсигне конфига
 * @since 0.4.4
 *  Добавлены состояния для устройств (приемники и передатчики для каждой логики) ('name','start')
 * @since 0.4.5
 *  Отказ от отдельной функции getServiceStatus
 *  Первое выполнение - 0.6 сек, последующие - 0.2 сек
 */
function getActualStatus(bool $forceRebuild = false): array
{
	if (defined("DEBUG") && DEBUG) {
		require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/dlog.php';
		$funct_start = microtime(true);
		$ver = "getActualStatus 0.4.5";
		dlog("$ver: Начинаю работу", 4, "INFO");
	}

	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getLineTime.php';
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/parseXmlTags.php';
	
	// Принудительно перечитываем конфигурацию если в сессии нет исходных данных
	if(!$forceRebuild){
		if(isset($_SESSION['status']) === false ){
			if (defined("DEBUG") && DEBUG) dlog("$ver: Принудительно включаю полный пересчет", 1, "WARNING");
			$forceRebuild = true;
		}
	}

	if ($forceRebuild) {
		$start = microtime(true);
		if (defined("DEBUG") && DEBUG) dlog("$ver: Выполняю полный расчет, включая конфигурацию", 3, "WARNING");
		// Заглушка для аварийных случаев
		$has_error = '';
		$stub = [
			'link' => [],
			'logic' => [],
			'service' => [
				'start' => 0,
				'name' => $has_error,
				'is_active' => false,
				'timestamp_format' => '%d %b %Y %H:%M:%S.%f',
			],
			'radio_status' => [
				'status' => "LOG ERROR",
				// 'duration' => 0,
				'start' => time(),
			],
			'callsign' => "LOG ERROR",
		];

		// @bookmark Конфигурация


		// Проверяем константы
		if (!defined('SVXCONFPATH') || !defined('SVXCONFIG')) {
			if (defined("DEBUG") && DEBUG) dlog("$ver: Не определено местонахождение конфигурации.", 1, "ERROR");
			$has_error = 'CONSTANTS ERROR';
		}

		if ($has_error === '') {
			$config_file = SVXCONFPATH . SVXCONFIG;

			if (empty($config_file)) {
				if (defined("DEBUG") && DEBUG) dlog("$ver: Не найден файл конфигурации $config_file", 1, "ERROR");
				$has_error = 'CONFIG NOT FOUND';
			}
		}

		// Читаем конфигурацию
		if ($has_error === '') {
			$lines = file($config_file, FILE_IGNORE_NEW_LINES);
			if ($lines === false) {
				if (defined("DEBUG") && DEBUG) dlog("$ver: Ошибка чтения файла: $config_file", 1, "ERROR");
				$has_error = 'CONFIG READ ERROR';
			}

			if ($has_error === '') {
				// Фильтруем комментарии
				$filteredLines = array_filter($lines, function ($line) {
					$trimmed = ltrim($line);
					return $trimmed !== '' && $trimmed[0] !== '#' && $trimmed[0] !== ';';
				});

				$svxconfig = parse_ini_string(implode("\n", $filteredLines), true, INI_SCANNER_RAW);
				if (defined("DEBUG") && DEBUG) dlog("$ver: Начинаю разбор конфигурации из $config_file", 1, "DEBUG");


				if ($svxconfig === false) {
					// Получение последней ошибки PHP
					$error_msg = error_get_last();
					$error = $error_msg['message'];
					if (defined("DEBUG") && DEBUG) dlog("$ver: Ошибка чтения файла: $error", 1, "ERROR");
					if (defined("DEBUG") && DEBUG) dlog("$ver: Будет создана пустая аварийная заглушка ", 2, "WARNING");
					$has_error = $error;
				}
			}
		}

		// Возвращаем заглушку если не удалось получить конфиг
		if ($has_error != '') {
			return $stub;
		}

		// Проверяем обязательные параметры конфигурации
		
		// Наличие формата для временных меток журнала
		if(!isset($svxconfig['GLOBAL']['TIMESTAMP_FORMAT'])){		
			$stub['service']['name'] = 'PARAMS MISSING';
			return $stub;
		}

		// @bookmark Создание каркаса конфигурации
		if (defined("DEBUG") && DEBUG) dlog("$ver: Создаю массив ", 4, "DEBUG");

		// Получаем логику
		$logics = isset($svxconfig['GLOBAL']['LOGICS']) ?
			array_filter(array_map('trim', explode(",", $svxconfig['GLOBAL']['LOGICS'])), 'strlen') : [];

		if (empty($logics)) {
			if (defined("DEBUG") && DEBUG) dlog("$ver: $logics: logics empty", 1, "ERROR");
			error_log(print_r($svxconfig, true));
			$stub['name'] == $logics . ': logics empty';
			return $stub;
		}

		// Получаем имена линков
		$linkNames = isset($svxconfig['GLOBAL']['LINKS']) ?
			array_filter(array_map('trim', explode(",", $svxconfig['GLOBAL']['LINKS'])), 'strlen') : [];
		$_links = [];
		foreach ($linkNames as $linkName) {
			$linkTimeout = 0;
			if (!isset($svxconfig[$linkName]['TIMEOUT'])) {
				if (defined("DEBUG") && DEBUG) dlog("$ver: PARSE CONFIG LOG: $linkName has no TIMEOUT", 2, "WARNING");
			} else {
				$linkTimeout = $svxconfig[$linkName]['TIMEOUT'];
			}
			$linkDefaultActive = false;
			if (!isset($svxconfig[$linkName]['DEFAULT_ACTIVE'])) {
				if (defined("DEBUG") && DEBUG) dlog("$ver: PARSE CONFIG LOG: $linkName has no DEFAULT_ACTIVE", 2, "WARNING");
			} else {
				$linkDefaultActive = $svxconfig[$linkName]['DEFAULT_ACTIVE'] ? true : false;
			}

			if (!isset($svxconfig[$linkName]['CONNECT_LOGICS'])) {
				if (defined("DEBUG") && DEBUG) dlog("$ver: PARSE CONFIG LOG: $linkName has no CONNECT_LOGICS", 2, "WARNING");
				continue;
			}

			$logicEntries = array_filter(array_map('trim', explode(",", $svxconfig[$linkName]['CONNECT_LOGICS'])), 'strlen');
			$_link_item = [
				'is_active' => false,
				'is_connected' => false,
				'start' => 0,
				// 'duration' => 0,
				'timeout' => $linkTimeout,
				'default_active' => $linkDefaultActive,
				'source' => [
					'logic' => '',
					'command' => [
						'activate_command' => '',
						'deactivate_command' => '',
					],
					'announcement_name' => '',
				],
				'destination' => [
					'logic' => '',
					'command' => [
						'activate_command' => '',
						'deactivate_command' => '',
					],
					'announcement_name' => '',
				]
			];


			foreach ($logicEntries as $index => $logicEntry) {
				$parts = explode(":", $logicEntry);

				$logicName = isset($parts[0]) ? trim($parts[0]) : '';
				$commandCode = isset($parts[1]) ? trim($parts[1]) : '';
				$announcementName = isset($parts[2]) ? trim($parts[2]) : '';

				if (!empty($logicName)) {
					if ($index == 0) { // source
						$_link_item['source']['logic'] = $logicName;
						$_link_item['source']['command']['activate_command'] = !empty($commandCode) ? $commandCode . '1' : '';
						$_link_item['source']['command']['deactivate_command'] = !empty($commandCode) ? $commandCode . '' : ''; // TODO В мануале сказано 0 но у меня не так
						$_link_item['source']['announcement_name'] = $announcementName;
					} elseif ($index == 1) { // destination
						$_link_item['destination']['logic'] = $logicName;
						$_link_item['destination']['command']['activate_command'] = !empty($commandCode) ? $commandCode . '1' : '';
						$_link_item['destination']['command']['deactivate_command'] = !empty($commandCode) ? $commandCode . '' : '';
						$_link_item['destination']['announcement_name'] = $announcementName;
					}
				}
			}

			$_links[$linkName] = $_link_item;
		};

		// Массив для хранения информации о мульти-устройствах
		$multipleDevice = [];

		// Заполняем массив логики
		$_logics = [];
		foreach ($logics as $_logic) {
			$_dtmf_cmd = '';
			if (isset($svxconfig[$_logic]['DTMF_CTRL_PTY'])) {
				$_dtmf_cmd = $svxconfig[$_logic]['DTMF_CTRL_PTY'];
				$_SESSION['DTMF_CTRL_PTY'] = $_dtmf_cmd;
			}

			// Получаем имя RX устройства из конфигурации логики
			$rxDeviceName = $svxconfig[$_logic]['RX'] ?? '';
			$txDeviceName = $svxconfig[$_logic]['TX'] ?? '';

			// Обрабатываем RX устройство: проверяем, является ли оно мульти-устройством
			$rxProcessed = $rxDeviceName;
			if (
				isset($svxconfig[$rxDeviceName]) &&
				isset($svxconfig[$rxDeviceName]['TYPE']) &&
				strtoupper($svxconfig[$rxDeviceName]['TYPE']) === 'MULTI'
			) {

				// Получаем список передатчиков из параметра TRANSMITTERS для мульти-устройства
				if (isset($svxconfig[$rxDeviceName]['TRANSMITTERS'])) {
					$transmitters = array_filter(array_map('trim', explode(",", $svxconfig[$rxDeviceName]['TRANSMITTERS'])), 'strlen');
					// Очищаем от пробелов и объединяем в строку
					$transmitters = array_map('trim', $transmitters);
					$rxProcessed = implode(",", $transmitters);
				}
			}

			// TX устройство не обрабатывается как мульти-устройство
			$txProcessed = $txDeviceName;

			if (
				!empty($txDeviceName) &&
				isset($svxconfig[$txDeviceName]) &&
				isset($svxconfig[$txDeviceName]['TRANSMITTERS']) &&
				!isset($multipleDevice[$txDeviceName])
			) {

				$multipleDevice[$txDeviceName] = trim($svxconfig[$txDeviceName]['TRANSMITTERS']);

				if (defined("DEBUG") && DEBUG) dlog("$ver: Добавлено составное устройство: $txDeviceName = " . $multipleDevice[$txDeviceName], 4, "DEBUG");
			}

			// @since 0.4.1. Разбираем макрокоманды
			$macroSection = $svxconfig[$_logic]['MACROS'] ?? '';
			$macroses = [];
			if (!empty($macroSection) && isset($svxconfig[$macroSection])) {
				foreach ($svxconfig[$macroSection] as $key => $value) {
					$macroses[$key] = $value;
				}
			}

			$item = [
				'start' => 0,
				// 'duration' => 0,
				'name' => $_logic,
				'is_active' => false,
				'callsign' => $svxconfig[$_logic]['CALLSIGN'] ?? 'N0CALL',
				'rx' => [
					'name' => $rxProcessed,
					'start' => 0
				],
				'tx' => [
					'name' => $txProcessed,
					'start' => 0
				],
				'macros' => $macroses,
				'type' => $svxconfig[$_logic]['TYPE'] ?? 'NOT SET',
				'dtmf_cmd' => $svxconfig[$_logic]['DTMF_CTRL_PTY'] ?? '',
				'is_connected' => false
			];

			// Добавляем модули для логики, если есть поле MODULES в конфиге
			if (isset($svxconfig[$_logic]['MODULES'])) {
				$item['module'] = [];
				$moduleNames = array_filter(
					array_map('trim', explode(",", str_replace('Module', '', $svxconfig[$_logic]['MODULES']))),
					'strlen'
				);
				foreach ($moduleNames as $moduleName) {
					$moduleName = trim($moduleName);
					if (!empty($moduleName)) {
						$item['module'][$moduleName] = [
							'start' => 0,
							// 'duration' => 0,
							'name' => $moduleName,
							'callsign' => '',
							'is_active' => false,
							'is_connected' => false,
							'connected_nodes' => []
						];
					}
				}
			}

			// Добавляем talkgroups, если есть DEFAULT_TG или MONITOR_TGS в конфиге
			$hasDefaultTg = isset($svxconfig[$_logic]['DEFAULT_TG']);
			$hasMonitorTgs = isset($svxconfig[$_logic]['MONITOR_TGS']);

			if ($hasDefaultTg || $hasMonitorTgs) {
				$item['talkgroups'] = [
					'default' => $svxconfig[$_logic]['DEFAULT_TG'] ?? '0',
					'selected' => $svxconfig[$_logic]['DEFAULT_TG'] ?? '0',
					'monitoring' => isset($svxconfig[$_logic]['MONITOR_TGS']) ?
						array_filter(array_map('trim', explode(",", $svxconfig[$_logic]['MONITOR_TGS'])), 'strlen') : [],
					'temp_monitoring' => '0',
				];
				// Если есть talkgroups, добавляем connected_nodes
				$item['connected_nodes'] = [];
			}

			// Добавляем host(s), если есть HOST или HOSTS в конфиге
			if (isset($svxconfig[$_logic]['HOSTS'])) {
				$item['hosts'] = $svxconfig[$_logic]['HOSTS'] ? $svxconfig[$_logic]['HOSTS'] : '';
			}

			$_logics[$_logic] = $item;

			// Очищаем временные переменные для текущей логики
			unset($rxDeviceName, $txDeviceName, $rxProcessed, $txProcessed);
		}

		// 
		$service = [
			'start' => 0,
			// 'duration' => 0,
			'name' => SERVICE_TITLE,
			'is_active' => false,
			'timestamp_format' => $svxconfig['GLOBAL']['TIMESTAMP_FORMAT'],
		];

		// Получаем глобальный позывной конфигурации
		$callsign = isset($svxconfig['GLOBAL']['LOCATION_INFO'])
			? ($svxconfig[$svxconfig['GLOBAL']['LOCATION_INFO']]['CALLSIGN'] ?? 'NO CALLSIGN')
			: 'NO CALLSIGN';

		// Формируем итоговый массив сессии
		$status = [

			'link' => $_links,
			'logic' => $_logics,
			'service' => $service,
			'multiple_device' => $multipleDevice,
			'callsign' => $callsign,
		];

		// Вычисляем время выполнения
		$config_time = microtime(true) - $start;
	} else {
		// Читаем существующий каркас из сессии
		if (defined("DEBUG") && DEBUG) dlog("$ver: Выполняю только обновление состояния", 3, "INFO");
		$status = [
			'link' => $_SESSION['status']['link'],
			'logic' => $_SESSION['status']['logic'],
			'service' => $_SESSION['status']['service'],
			'multiple_device' => $_SESSION['status']['multiple_device'],
			'callsign' => $_SESSION['status']['callsign']
		];
	}

	// @bookmark Заполнение конфигурации данными
	// $status['service'] = getServiceStatus();
	// Сначала определяемся с состоянием сервиса
	
	// Выясняем, есть ли в сессии старые данные о размере лога 
	if (isset($_SESSION['status']['service']['log_line_count'])) {
		$count = $_SESSION['status']['service']['log_line_count'] + 100;
	} else {
		$count = null;
	}

	$or_conditions[] = "Tobias Blomberg";
	$or_conditions[] = "SIGTERM";

	$logLines = getLogTailFiltered(1, null,	$or_conditions, $count);
	unset($or_conditions);

	// Строк не получено ни одной
	if ($logLines === false) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Ошибка получения журнала или в нем не найдено записей.", 1, "ERROR");
		$status['service']['name'] = "LOG PARSE ERROR";
		return $status;
	}

	// ??? Строка получена, но она пустая ???
	$logStatusLine = trim($logLines[0]);
	if (empty($logStatusLine)) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: В журнале не найдено сигналов о состоянии сервиса", 1, "ERROR");
		$status['service']['name'] = "LOG PARSE ERROR";
		return $status;
	}
	
	// Строка получена, она не пустая, но временная метка не парсится
	$line_timestamp = getLineTime($logStatusLine);
	if ($line_timestamp === false) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Не удалось получить время из строки $logStatusLine", 1, "ERROR");
		$status['service']['name'] = "LOG TIMESTAMP ERROR";
		return $status;
	}

	if (strpos($logStatusLine, 'Tobias Blomberg', 0) !== false) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Сервис запущен", 4, "DEBUG");
		$status['service']['is_active'] = true;
		$searchPattern = "Tobias Blomberg";
	} else {
		$status['service']['is_active'] = false;
		$searchPattern = "SIGTERM";
	}

	$log_count = is_null($count) ? 0 : $count;
	$logLineCount = countLogLines($searchPattern, $log_count);
	if ($logLineCount === false) {
		$logLineCount = 0;
		$status['service']['name'] = "LOG SIZE ERROR";
	}

	$status['service']['start'] = $line_timestamp;
	// $status['service']['duration'] = time() - $line_timestamp;
	$status['service']['log_line_count'] = $logLineCount;

	if (defined("DEBUG") && DEBUG) {
		$result_count = $logLines === false ? "false" : count($logLines);
		dlog("$ver: Получено строк: $result_count", 4, "DEBUG");
	}


	// Сервис отключен
	if ($status['service']['is_active'] === false) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Сервис выключен, возвращаю его статус и прекращаю обработку", 2, "WARNING");
		// @bookmark Возврат данных о неактивном сервисе
		return $status;
	}

	// На этом этапе уже должен быть получен размер журнала

	// Получаем количество строк журнала для текущей сессии сервиса
	$logCount = $status['service']['log_line_count'];
	if (defined("DEBUG") && DEBUG) dlog("$ver: Активных строк в журнале $logCount", 4, "DEBUG");


	// @bookmark Логики и состояние их модулей (с клиентами)
	$isSomeModuleActive = false;

	foreach ($status['logic'] as $logicName => &$logic) {
		if (defined("DEBUG") && DEBUG) dlog("$ver: Работаю c логикой $logicName", 4, "DEBUG");

		// Проверяем существование типа логики
		if (!isset($logic['type'])) {
			if (defined("DEBUG") && DEBUG) dlog("$ver: $logicName не имеет типа! Пропускаю проверку", 2, "WARNING");
			continue;
		}

		// с логикой порядок, начинаем обработку
		$logicType = $logic['type'];
		$serviceCommand = '';
		$serviceCommandTimestamp = 0;
		$logicTimestamp = $logic['start'];


		// @bookmark Разбор логики
		$required_condition = $logicName;
		if ($logicType === 'Reflector') {

			// Для рефлектора характерно Authentication OK или Disconnected...
			if (defined("DEBUG") && DEBUG) dlog("$ver: $logicName - рефлектор", 4, "DEBUG");

			$or_conditions[] = "Authentication OK";
			$or_conditions[] = "Disconnected from";
			$serviceCommand = trim(getLogTailFiltered(1, $required_condition, $or_conditions, $logCount)[0]);
			unset($or_conditions);
			if (defined("DEBUG") && DEBUG) dlog("$ver: Для $logicName найдена строка $serviceCommand", 4, "DEBUG");
		} else {
			// Для Симплекса и Дуплекса (Event handler script successfully loaded) говорит о включении логики 
			// а сообщения Activating/Deactivating говорит о включении/выключении модуля (игнорируем - логика не выключаемая 

			// Включенную логику отмечаем как на паузе
			if (defined("DEBUG") && DEBUG) dlog("$ver: $logicName - обычная логика", 4, "DEBUG");
			$or_conditions[] = "Event handler script successfully loaded";
			$or_conditions[] = "ctivating module";
			$serviceCommand = trim(getLogTailFiltered(1, $required_condition, $or_conditions, $logCount)[0]);
			unset($or_conditions);
			if (defined("DEBUG") && DEBUG) dlog("$ver: Для $logicName найдена строка $serviceCommand", 4, "DEBUG");
		}

		// если получена пустая строка, тоже вычислять нечего
		if (empty($serviceCommand)) {
			if (defined("DEBUG") && DEBUG) dlog("$ver: Пустая строка, пропускаю " .  $serviceCommandTimestamp, 4, "WARNING");
			continue;
		}

		// Проверяем валидность временной метки
		$serviceCommandTimestamp = getLineTime($serviceCommand);
		if (!is_int($serviceCommandTimestamp)) {
			if (defined("DEBUG") && DEBUG) dlog("$ver: Время не правильное", 1, "ERROR");
			continue;
		}

		// А царь-то ненастоящий!
		if ($serviceCommandTimestamp < $logicTimestamp) {
			if (defined("DEBUG") && DEBUG) dlog("$ver: Время метки $serviceCommandTimestamp меньше старта логики $logicTimestamp", 1, "ERROR");
			continue;
		}

		// Обрабатываем событие логики  
		// и оно говорит о отключении сервиса (для рефлектора) или модуля (для симплекса и дуплекса) сбрасываем все значения логики и ее модулей

		$logic['start'] = $serviceCommandTimestamp > $logic['start'] ? $serviceCommandTimestamp : $logic['start'];
		// $logic['duration'] = time() - $logic['start'];
		$logic['is_active'] = true; // @since 0.4
		// Рефлектор подключен если активен линк (еще не проверялся)
		// Обычная логика активна когда подключается модуль (еще не проверялся)
		// if (defined("DEBUG") && DEBUG) dlog("$ver: $logicName is active, continue ...", 4, "DEBUG");

		// @bookmark Рефлектор.
		if ($logicType === 'Reflector') {
			if (defined("DEBUG") && DEBUG) dlog("$ver: ********** Рефлектор $logicName ...", 4, "DEBUG");
			if (defined("DEBUG") && DEBUG) dlog("$ver: Включаем рефлектор $logicName", 4, "DEBUG");
			$logic['is_connected'] = true;

			// Ищем 1 последнюю строку Connected nodes и обновляем массив connected_nodes
			$or_conditions[] = "Connected nodes:";
			$logConnectedNode = getLogTailFiltered(1, $required_condition, $or_conditions, $logCount)[0];
			unset($or_conditions);

			// $logConnectedNode = trim(`tail -100000 "$logPath" | grep "$logicName: Connected nodes:" | tail -1`);
			if (defined("DEBUG") && DEBUG) dlog("$ver: Нашлась строка с данными о подключении узлов?: " . ($logConnectedNode ? "YES" : "NO"), 4, "DEBUG");
			$nodesConnectingTime = getLineTime($logConnectedNode);

			$nodesTimestamp = !empty($logConnectedNode) ? $nodesConnectingTime : 0; // @todo А зачем мне проверять время на 0?

			//Убедимся что время распарсилось
			if ($nodesTimestamp === false) {
				if (defined("DEBUG") && DEBUG) dlog("$ver: Время не правильное, пропускаю данные о подключении узлов", 2, "WARNING");
				continue;
			}


			// Заполняем подключенные узлы для рефлектора
			$logic['connected_nodes'] = [];
			if (preg_match('/Connected nodes:\s*(.+)$/', $logConnectedNode, $matches)) {
				$nodesStr = trim($matches[1]);
				$callsigns = array_filter(array_map('trim', explode(',', $nodesStr)));
				// Заполняем новыми значениями
				foreach ($callsigns as $fullCallsign) {
					if (!empty($fullCallsign)) {
						// Извлекаем базовый позывной (без цифр и символов)
						if (preg_match('/^([A-Za-z0-9]+)(?:[-\\/][A-Za-z0-9]+)?$/', $fullCallsign, $callMatch)) {
							$baseCallsign = $callMatch[1];
							$logic['connected_nodes'][$fullCallsign] = [
								'callsign' => $baseCallsign,
								'start' => $nodesTimestamp, // Сохраняем время получения данных
								'type' => 'Node'
							];
							if (defined("DEBUG") && DEBUG) dlog("$ver: Добавлен $baseCallsign ($fullCallsign) к узлам $logicName ", 4, "DEBUG");
						}
					}
				}
			}
			if (defined("DEBUG") && DEBUG) dlog("$ver: Обновлен список подключенных узлов у $logicName: " . count($logic['connected_nodes']) . " узла/узлов", 4, "DEBUG");


			// @todo Разговорные группы Временный монитор			
			$or_conditions[] = "emporary monitor";
			$logLineTM = getLogTailFiltered(50, $required_condition, $or_conditions, $logCount);
			unset($or_conditions);

			if (!empty($logLineTM) !== false) {

				if (defined("DEBUG") && DEBUG) dlog("$ver: для $logicName найдено " . count($logLineTM) . " строк временно мониторингуемых групп", 4, "DEBUG");

				foreach ($logLineTM as $index => $line) {
					if (empty($line)) {
						if (defined("DEBUG") && DEBUG) dlog("$ver: пропускаю запись  $index как пустую", 4, "DEBUG");
						continue;
					}
					if (defined("DEBUG") && DEBUG) dlog("$ver: работаю с  $index : $line", 4, "DEBUG");


					if (preg_match('/#(\d+)$/', $line, $matches)) {
						$tgNumber = $matches[1];
						if (strpos($line, 'timeout') !== false) {
							$groupStates[$tgNumber] = false;
							if (defined("DEBUG") && DEBUG) dlog("$ver: Временный мониторинг № $index$index: TG#$tgNumber отмечен INACTIVE", 4, "DEBUG");
						} else if (strpos($line, 'Add') !== false || strpos($line, 'Refresh') !== false) {
							$groupStates[$tgNumber] = true;
							if (defined("DEBUG") && DEBUG) dlog("$ver: Временный мониторинг № $index$index: TG#$tgNumber отмечен ACTIVE", 4, "DEBUG");
						} else {
							if (defined("DEBUG") && DEBUG) dlog("$ver: Временный мониторинг № $index$index: Неизвестное действие TG#$tgNumber: " . substr($line, 0, 100), 2, "WARNING");
						}
					} else {
						if (defined("DEBUG") && DEBUG) dlog("$ver: Временный мониторинг №: $index: Не смог разобрать строку: " . substr($line, 0, 100), 2, "WARNING");
					}
				}
				if (defined("DEBUG") && DEBUG) dlog("$ver: Обработал " . count($logLineTM) . " записей о временном мониторинге ", 4, "DEBUG");
			} else {
				if (defined("DEBUG") && DEBUG) dlog("$ver: Не нашлось записей о временном мониторинге для $logicName", 4, "DEBUG");
			}

			// @bookmark Заполняю группы Временный монитор
			$activeTMGroups = [];
			if (isset($groupStates)) {
				foreach ($groupStates as $tgNumber => $isActive) {
					if ($isActive) {
						if (defined("DEBUG") && DEBUG) dlog("$ver: добавляю группу $tgNumber ", 4, "DEBUG");
						$activeTMGroups[] = $tgNumber;
					} else {
						if (defined("DEBUG") && DEBUG) dlog("$ver: пропускаю группу $tgNumber как неактивную", 4, "DEBUG");
					}
				}
			}


			if (defined("DEBUG") && DEBUG) dlog("$ver: Получаю выбранную разговорную группу", 4, "DEBUG");

			// @bookmark Получаем текущие значения для выбранной группы
			$selectedTG = $logic['talkgroups']['selected'];
			$or_conditions[] = "Selecting TG";
			$logLinesSG = getLogTailFiltered(1, $required_condition, $or_conditions, $logCount);
			unset($or_conditions);
			if ($logLinesSG !== false) {
				$logLineSG = $logLinesSG[0];
				if (defined("DEBUG") && DEBUG) dlog("$ver: Разбираю строку $logLineSG", 4, "DEBUG");
				if (preg_match('/Selecting TG #(\d+)/', $logLineSG, $match)) {
					if (defined("DEBUG") && DEBUG) dlog("$ver: Отобрал номер - " . $match[1], 4, "DEBUG");
					$selectedTG = ($match[1] == '0')
						? $logic['talkgroups']['default']
						: (int)$match[1];

					if (defined("DEBUG") && DEBUG) dlog("$ver: Выбрана разговорная группа $selectedTG", 4, "DEBUG");
					// УДАЛЯЕМ выбранную группу из временного мониторинга
					$key = array_search($selectedTG, $activeTMGroups);
					if ($key !== false) {
						if (defined("DEBUG") && DEBUG) {
							dlog("$ver: Удаляю активную группу $selectedTG из временного мониторинга", 4, "DEBUG");
						}
						unset($activeTMGroups[$key]);
					}
				}
			}

			// Сохраняем обновленные данные
			$logic['talkgroups']['selected'] = $selectedTG;
			$logic['talkgroups']['temp_monitoring'] = $activeTMGroups;
		} else {
			// @bookmark Симплекс или дуплекс
			if (defined("DEBUG") && DEBUG) dlog("$ver: ********** Симплекс/дуплекс $logicName ...", 4, "DEBUG");

			if (isset($logic['module']) || is_array($logic['module'])) {

				if (defined("DEBUG") && DEBUG) dlog("$ver: Ищем активацию для $logicName в $serviceCommand", 4, "DEBUG");

				if (strpos($serviceCommand, 'module') === false) {

					if (defined("DEBUG") && DEBUG) dlog("$ver: В строке $serviceCommand нет информации о модулях", 3, "WARNING");
					continue;
				} else {

					if (defined("DEBUG") && DEBUG) dlog("$ver: Найдена информация о каком-то модуле", 4, "DEBUG");

					foreach ($logic['module'] as $moduleName => &$module) {

						if (
							strpos($serviceCommand, $moduleName) !== false &&
							strpos($serviceCommand, 'Activating') !== false
						) {

							if (defined("DEBUG") && DEBUG) {
								dlog("$ver: Для модуля $moduleName найдена команда АКТИВАЦИИ в $serviceCommand", 4, "DEBUG");
							}

							// Включаем логику
							$logic['is_connected'] = true;

							// Включаем этот конкретный модуль
							$module['is_active'] = true;
							$module['start'] = $serviceCommandTimestamp;
							// $module['duration'] = time() - $serviceCommandTimestamp;

							// Устанавливаем статус активности модуля
							$isSomeModuleActive = true;

							if (defined("DEBUG") && DEBUG) {
								$dur = time() - $module['start'];
								dlog("$ver: Модуль $moduleName активирован, работает уже $dur сек", 4, "DEBUG");
							}

							// @bookmark Для модуля EchoLink
							if ($moduleName === "EchoLink") {
								if (defined("DEBUG") && DEBUG) dlog("$ver: Ищу подключенные узлы модуля $moduleName", 4, "DEBUG");

								// @version 0.3.2
								// Оптимизируем количество вызовов countLogLines 
								$logELcount = countLogLines("Activating module EchoLink", $logCount);
								$logEL = getLogTail($logELcount);

								// Инициализируем массив подключенных узлов, если его еще нет
								if (!isset($module['connected_nodes']) || !is_array($module['connected_nodes'])) {
									$module['connected_nodes'] = [];
									if (defined("DEBUG") && DEBUG) dlog("$ver: Инициализирован пустой массив connected_nodes", 4, "DEBUG");
								}

								if (defined("DEBUG") && DEBUG) {
									dlog("$ver: Начальное количество узлов в массиве: " . count($module['connected_nodes']), 4, "DEBUG");
								}

								// Временный массив для найденных узлов
								$foundNodes = [];

								// Копируем существующие узлы (как отправную точку)
								foreach ($module['connected_nodes'] as $nodeName => $node) {
									// Проверяем структуру массива (может быть индексным или ассоциативным)
									if (is_array($node) && isset($node['name'])) {
										// Если массив индексный, используем имя узла как ключ
										$foundNodes[$node['name']] = $node;
										if (defined("DEBUG") && DEBUG) {
											dlog("$ver: Сохранен существующий узел: {$node['name']}", 4, "DEBUG");
										}
									} elseif (is_array($node)) {
										// Если это ассоциативный массив, где ключ - имя узла
										$foundNodes[$nodeName] = $node;
										if (defined("DEBUG") && DEBUG) {
											dlog("$ver: Сохранен существующий узел: {$nodeName}", 4, "DEBUG");
										}
									}
								}

								// Обрабатываем новые записи из журнала
								foreach ($logEL as $logELline) {
									// @bookmark Ищем строки с CONNECTED или DISCONNECTED
									$isConnected = strpos($logELline, "EchoLink QSO state changed to CONNECTED") !== false;
									$isDisconnected = strpos($logELline, "EchoLink QSO state changed to DISCONNECTED") !== false;

									if ($isConnected || $isDisconnected) {
										if (defined("DEBUG") && DEBUG) {
											dlog("$ver: Найдена подходящая строка: " . substr($logELline, 0, 100), 4, "DEBUG");
										}

										// Удаляем временную метку с начала строки (все до первого ": ")
										$firstColonPos = strpos($logELline, ": ");
										if ($firstColonPos !== false) {
											$cleanLine = substr($logELline, $firstColonPos + 2); // +2 чтобы пропустить ": "

											if (defined("DEBUG") && DEBUG) {
												dlog("$ver: Очищенная строка: {$cleanLine}", 4, "DEBUG");
											}

											// Теперь разбиваем очищенную строку на части
											$parts = explode(":", $cleanLine, 2); // Разбиваем на 2 части: имя и остаток

											if (count($parts) >= 2) {
												$nodeName = trim($parts[0]); // Первая часть - имя узла
												$restOfLine = trim($parts[1]); // Вторая часть - остаток строки

												if (defined("DEBUG") && DEBUG) {
													dlog("$ver: Извлеченное имя: '{$nodeName}'", 4, "DEBUG");
													dlog("$ver: Остаток строки: '{$restOfLine}'", 4, "DEBUG");
												}

												// Проверяем, что имя заключено в *
												if (preg_match('/^\s*\*(.*)\*\s*$/', $nodeName, $matches)) {
													$rawName = $matches[1]; // Имя без обрамляющих *
													if (defined("DEBUG") && DEBUG) dlog("$ver: Имя без *: '{$rawName}'", 4, "DEBUG");

													// Определяем тип узла по $nodeName (оригиналу), а не по $rawName
													if (preg_match('/^\s*\*+/', $nodeName)) {
														// Если имя начинается с одной или нескольких звездочек, считаем conference
														$type = "Conference";
														// Для callsign берем rawName (уже без обрамляющих звездочек)
														// Но если есть вложенные звездочки, их тоже нужно убрать
														$callsign = preg_replace('/^\*+/', '', $rawName);
													}
												} elseif (substr($nodeName, -2) === "-L") {
													$type = "Simplex";
													$callsign = substr($nodeName, 0, -2);
												} elseif (substr($nodeName, -2) === "-R") {
													$type = "Repeater";
													$callsign = substr($nodeName, 0, -2);
												} else {
													$type = "User";
													$callsign = $nodeName;
												}

												if (defined("DEBUG") && DEBUG) dlog("$ver: Определен тип: '{$type}', callsign: '{$callsign}'", 4, "DEBUG");


												// Получаем время из оригинальной строки
												$startTime = getLineTime($logELline);

												if (defined("DEBUG") && DEBUG) {
													$timeStr = $startTime ? date('Y-m-d H:i:s', $startTime) : 'false';
													dlog("$ver: Время из строки: {$timeStr}", 4, "DEBUG");
												}

												// Обрабатываем CONNECTED
												if ($isConnected) {
													if (!isset($foundNodes[$nodeName])) {
														$foundNodes[$nodeName] = [
															'callsign' => $callsign,
															'start' => $startTime,
															'type' => $type,
															'name' => $nodeName
														];

														if (defined("DEBUG") && DEBUG) {
															dlog("$ver: Добавлен новый узел: {$nodeName} ({$type})", 4, "DEBUG");
														}
													} else {
														if (defined("DEBUG") && DEBUG) {
															dlog("$ver: Узел {$nodeName} уже существует, обновляем время", 4, "DEBUG");
														}
														// Обновляем время, если узел уже есть
														$foundNodes[$nodeName]['start'] = $startTime;
													}
												}
												// Обрабатываем DISCONNECTED
												elseif ($isDisconnected) {
													if (isset($foundNodes[$nodeName])) {
														unset($foundNodes[$nodeName]);
														if (defined("DEBUG") && DEBUG) dlog("$ver: Удален узел: {$nodeName}", 4, "DEBUG");
													} else {
														if (defined("DEBUG") && DEBUG) dlog("$ver: Узел {$nodeName} не найден для отключения, игнорируем", 4, "DEBUG");
													}
												}
											} else {
												if (defined("DEBUG") && DEBUG) dlog("$ver: Имя '{$nodeName}' не соответствует шаблону *...*, пропускаем", 4, "DEBUG");

												if (defined("DEBUG") && DEBUG) {
													dlog("$ver: Не удалось разбить очищенную строку на части", 4, "DEBUG");
												}
											}

											// Очищаем временные переменные для этой итерации
											unset($cleanLine, $parts, $nodeName, $restOfLine, $rawName, $matches, $type, $callsign, $startTime);
										} else {
											if (defined("DEBUG") && DEBUG) {
												dlog("$ver: Не найдено ': ' в строке, пропускаем", 4, "DEBUG");
											}
										}

										unset($firstColonPos);
									}
								}

								// Помещаем подключенные узлы в connected_nodes
								$module['connected_nodes'] = $foundNodes;

								if (defined("DEBUG") && DEBUG) {
									if (defined("DEBUG") && DEBUG) dlog("$ver: Конечное количество узлов в массиве: " . count($module['connected_nodes']), 4, "DEBUG");

									// Логируем содержимое массива для отладки
									if (count($module['connected_nodes']) > 0) {
										dlog("$ver: Содержимое connected_nodes (ассоциативный массив):", 4, "DEBUG");
										foreach ($module['connected_nodes'] as $nodeName => $node) {
											$timeStr = $node['start'] ? date('Y-m-d H:i:s', $node['start']) : 'null';
											if (defined("DEBUG") && DEBUG) dlog("$ver:   [{$nodeName}] type: '{$node['type']}', callsign: '{$node['callsign']}', start: {$timeStr}", 4, "DEBUG");
										}
									} else {
										if (defined("DEBUG") && DEBUG) dlog("$ver: Массив connected_nodes пуст", 4, "DEBUG");
									}
								}
								// @todo Если есть подключенные узлы, отмечаем логику как подключенную
								if (count($module['connected_nodes']) !== 0) {
									$module['is_connected'] = true;
									if (defined("DEBUG") && DEBUG) dlog("$ver: Отмечаю логику {$logic['name']} как подключенную", 4, "DEBUG");
									$logic['is_connected'] = true;
								}
								continue;
							}
							// @bookmark Для модуля Frn
							if ($moduleName === "Frn") {

								if (defined("DEBUG") && DEBUG) dlog("$ver: Ищем сервер для $moduleName", 4, "DEBUG");

								$logFRNcount = countLogLines("Activating module Frn", $logCount);
								$logFRN = getLogTail($logFRNcount);
								// Ищем с конца
								$logFRN = array_reverse($logFRN);

								foreach ($logFRN as $logFRNline) {

									// Получаем данные сервера:
									// Ищем строки с login stage 2 completed
									// Попутно убеждаемся что не было отключения от сервера
									$isFrnServerDisconnected = strpos($logFRNline, "DR_REMOTE_DISCONNECTED") !== false;
									$isServerStateFound = strpos($logFRNline, "login stage 2 completed") !== false;

									if ($isFrnServerDisconnected) {
										// Сервер разорвал соединение
										// @todo Попытаться найти другие сообщения о ошибке

										if (defined("DEBUG") && DEBUG) dlog("$ver: Сервер Frn разорвал соединение", 3, "INFO");

										$module['is_connected'] = false;
										unset($isFrnServerDisconnected, $isServerStateFound, $logFRN, $logFRNcount);
										break;
									}
									if ($isServerStateFound) {
										if (defined("DEBUG") && DEBUG) dlog("$ver: Найдена строка с данными о сервере $logFRNline", 4, "DEBUG");

										$frnServerIdArray = parseXmlTags($logFRNline);
										if (!isset($frnServerIdArray)) {
											if (defined("DEBUG") && DEBUG) dlog("$ver: Не удалось распарсить данные о сервере", 2, "WARNING");
										}

										$frnServerConnectTime = getLineTime($logFRNline);
										if ($frnServerConnectTime != false) {
											$module['is_connected'] = true;
											$module['start'] = $frnServerConnectTime;
											// $module['duration'] = time() - $frnServerConnectTime;

											if (isset($frnServerIdArray) && isset($frnServerIdArray['BN'])) {
												$module['connected_nodes'][$frnServerIdArray['BN']] = [
													'name' => $frnServerIdArray['BN'],
													'start' => $frnServerConnectTime,
													'callsign' => $frnServerIdArray['BN'],
													'type' => "Server",
												];
												if (defined("DEBUG") && DEBUG) dlog("$ver: Добавлен сервер Frn {$frnServerIdArray['BN']}", 4, "DEBUG");
											}

											unset($isFrnServerDisconnected, $isServerStateFound, $logFRN, $logFRNcount, $frnServerIdArray, $frnServerConnectTime);
											break;
										} else {
											if (defined("DEBUG") && DEBUG) dlog("$ver: Не удалось разобрать время подключения $moduleName", 2, "WARNING");
										}
									}
								}
							}
						} else {
							// Для этого модуля нет команды активации - выключаем его
							$module['is_active'] = false;
							$module['start'] = 0;
							// $module['duration'] = 0;
							if (isset($module['connected_nodes'])) $module['connected_nodes'] = [];
						}
					}
				}
			}

			// @bookmark Состояние передатчика и приемника
			if (isset($logic['rx']) && !empty($logic['rx']['name'])) {
				$required_condition = $logic['rx']['name'];

				$or_conditions = ["The squelch is"];
				$dev_last_action = getLogTailFiltered(1, $required_condition, $or_conditions, $logCount);

				if ($dev_last_action !== false) {
					// ищем расширенную строку на случай если кому-то взбредет назвать устройство OPEN...
					if (strpos($dev_last_action[0], "s OPEN") !== false) {
						$logic['rx']['start'] = getLineTime($dev_last_action[0]);
					} else {
						$logic['rx']['start'] = 0;
					}
				}
			}
			if (isset($logic['tx']) && !empty($logic['tx']['name'])) {
				$required_condition = $logic['tx']['name'];

				$or_conditions = ["Turning the transmitter"];
				$dev_last_action = getLogTailFiltered(1, $required_condition, $or_conditions, $logCount);

				if ($dev_last_action !== false) {

					if (strpos($dev_last_action[0], "r ON") !== false) {
						$logic['tx']['start'] = getLineTime($dev_last_action[0]);
					} else {
						$logic['tx']['start'] = 0;
					}
				}
			}
		}

		if (defined("DEBUG") && DEBUG) dlog("$ver: Логика $logicName активна? ({$logic['is_active']}) подключена? ({$logic['is_connected']})", 4, "DEBUG");
	}


	// @bookmark Линки

	$start = microtime(true);
	if (defined("DEBUG") && DEBUG) dlog("Линки", 4, "DEBUG");
	foreach ($status['link'] as $linkName => &$link) {
		$required_condition = $linkName;
		$or_conditions[] = "ctivating link";

		$logLinks = getLogTailFiltered(1, $required_condition, $or_conditions, $logCount);

		unset($required_condition, $or_conditions);

		if ($logLinks !== false) {

			$logLink = $logLinks[0];
			if (defined("DEBUG") && DEBUG) dlog("$ver: Линк $linkName - получена строка: $logLink", 4, "DEBUG");

			if (empty($logLink)) {
				$link['is_active'] = false;
				// $link['duration'] = 0;
				$link['start'] = 0;
				if (defined("DEBUG") && DEBUG) dlog("$ver: Link $linkName - пустая строка: $logLink , продолжаю", 4, "DEBUG");
				continue;
			}

			$logLinkTimestamp = getLineTime($logLink);

			if (!is_int($logLinkTimestamp)) {
				if (defined("DEBUG") && DEBUG) dlog("$ver: Время не правильное", 1, "ERROR");
				continue;
			}

			if (defined("DEBUG") && DEBUG) {
				$dur = time() - $link['start'];
				dlog("$ver: Линк: Состояние: $linkName - Активен: $link[is_active], Начало: $link[start], Длительность: $dur сек.", 4, "DEBUG");
				unset($dur);
			}
			$link['is_active'] = true;
			if (strpos($logLink, 'Activating') !== false) {
				//@bookmark Линк активируется - начинаем отсчет и включаем рефлектор/логику
				foreach ($status['logic'] as $logicName => &$logic) {
					if ($link['destination']['logic'] === $logicName) {
						$logic['is_active'] = true;
						if (defined("DEBUG") && DEBUG) dlog("$ver: Link: $linkName - включает логику $logicName", 4, "DEBUG");
					}
					if ($link['source']['logic'] === $logicName) {
						$logic['is_connected'] = true;
						if (defined("DEBUG") && DEBUG) dlog("$ver: Link: $linkName - включает логику $logicName", 4, "DEBUG");
					}
				}

				if (defined("DEBUG") && DEBUG) dlog("$ver: Обнаружена команда включения линка $linkName", 4, "DEBUG");
				// $link['duration'] = time() - $logLinkTimestamp;
				$link['is_connected'] = true;
				$link['start'] = $logLinkTimestamp;
			} elseif (strpos($logLink, 'Deactivating') !== false || strpos($logLink, 'Removing') !== false) {
				//@bookmark Линк деактивируется - завершаем отсчет отключаем логику destination
				if (defined("DEBUG") && DEBUG) dlog("$ver: Линк $linkName выключен, его логики будут сброшены", 4, "DEBUG");
				foreach ($status['logic'] as $logicName => &$logic) {
					if ($link['destination']['logic'] === $logicName) {
						$logic['is_active'] = false;
						if (defined("DEBUG") && DEBUG) dlog("$ver: Link: $linkName - выключает логику $logicName", 4, "DEBUG");
					}
				}
				$link['is_connected'] = false;
				// $link['duration'] = 0;
				$link['start'] = 0;
			}
		} else {
			if (defined("DEBUG") && DEBUG) dlog("$ver: В журнале нет данных о линке", 4, "ERROR");
			// $link['duration'] = 0;
			$link['start'] = 0;
			$link['is_active'] = false;
			$link['is_connected'] = false;
			foreach ($status['logic'] as $logicName => &$logic) {
				if ($link['destination']['logic'] === $logicName) {
					if ($logic['is_active']) $logic['is_active'] = false;

					if (defined("DEBUG") && DEBUG) dlog("$ver: Link: $linkName - выключает логику $logicName", 4, "DEBUG");
				}
			}
		}
	}

	foreach ($status['logic'] as $logicName => &$logic) {
		if ($isSomeModuleActive == true) {
			if ($logic['type'] === "Reflector") $logic['is_active'] = false;
		}
	}

	if (defined("DEBUG") && DEBUG) {
		$funct_time = microtime(true) - $funct_start;
		dlog("$ver: Закончил работу за $funct_time мсек", 3, "INFO");
		unset($ver, $funct_start, $funct_time);
	}

	return $status;
}
