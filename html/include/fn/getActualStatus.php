<?php

/** 
 * @author vladimir@tsurkanenko.ru
 * @date 2026.01.24
 * @filesource getActualStatus.php
 * @version 0.4.5.release
 */
function getActualStatus(bool $forceRebuild = false): array
{
	
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getLineTime.php';
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/parseXmlTags.php';
	
	if(isset($_SESSION['status']) === false ){
		$forceRebuild = true;
	}

	if ($forceRebuild) {
		$start = microtime(true);
		
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
				'start' => time(),
			],
			'callsign' => "LOG ERROR",
		];

		if (!defined('SVXCONFPATH') || !defined('SVXCONFIG')) {
			error_log("getActualStatus: SVXCONFPATH or SVXCONFIG not set");
			$has_error = 'CONSTANTS ERROR';
		}

		if ($has_error === '') {
			$config_file = SVXCONFPATH . SVXCONFIG;

			if (empty($config_file)) {
				error_log("getActualStatus: Config not found: $config_file");
				$has_error = 'CONFIG NOT FOUND';
			}
		}

		if ($has_error === '') {
			$lines = file($config_file, FILE_IGNORE_NEW_LINES);
			if ($lines === false) {
				error_log("getActualStatus: File read error: $config_file");
				$has_error = 'CONFIG READ ERROR';
			}

			if ($has_error === '') {
				$filteredLines = array_filter($lines, function ($line) {
					$trimmed = ltrim($line);
					return $trimmed !== '' && $trimmed[0] !== '#' && $trimmed[0] !== ';';
				});

				$svxconfig = parse_ini_string(implode("\n", $filteredLines), true, INI_SCANNER_RAW);
				
				if ($svxconfig === false) {
					$error_msg = error_get_last();
					$error = $error_msg['message'];
					error_log("getActualStatus: File read error: $error");
					$has_error = $error;
				}
			}
		}

		
		if ($has_error != '') {
			return $stub;
		}

		if(!isset($svxconfig['GLOBAL']['TIMESTAMP_FORMAT'])){		
			$stub['service']['name'] = 'PARAMS MISSING';
			return $stub;
		}


		$logics = isset($svxconfig['GLOBAL']['LOGICS']) ?
			array_filter(array_map('trim', explode(",", $svxconfig['GLOBAL']['LOGICS'])), 'strlen') : [];

		if (empty($logics)) {
			error_log("getActualStatus: $logics: logics empty");
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
			if (isset($svxconfig[$linkName]['TIMEOUT'])) {
				$linkTimeout = $svxconfig[$linkName]['TIMEOUT'];
			}
			$linkDefaultActive = false;
			if (!isset($svxconfig[$linkName]['DEFAULT_ACTIVE'])) {
				error_log("getActualStatus: PARSE CONFIG LOG: $linkName has no DEFAULT_ACTIVE");
			} else {
				$linkDefaultActive = $svxconfig[$linkName]['DEFAULT_ACTIVE'] ? true : false;
			}

			if (!isset($svxconfig[$linkName]['CONNECT_LOGICS'])) {
				error_log("getActualStatus: PARSE CONFIG LOG: $linkName has no CONNECT_LOGICS");
				continue;
			}

			$logicEntries = array_filter(array_map('trim', explode(",", $svxconfig[$linkName]['CONNECT_LOGICS'])), 'strlen');
			$_link_item = [
				'is_active' => false,
				'is_connected' => false,
				'start' => 0,
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

		$multipleDevice = [];
		$_logics = [];
		foreach ($logics as $_logic) {
			$_dtmf_cmd = '';
			if (isset($svxconfig[$_logic]['DTMF_CTRL_PTY'])) {
				$_dtmf_cmd = $svxconfig[$_logic]['DTMF_CTRL_PTY'];
				$_SESSION['DTMF_CTRL_PTY'] = $_dtmf_cmd;
			}

			$rxDeviceName = $svxconfig[$_logic]['RX'] ?? '';
			$txDeviceName = $svxconfig[$_logic]['TX'] ?? '';
			$rxProcessed = $rxDeviceName;
			if (
				isset($svxconfig[$rxDeviceName]) &&
				isset($svxconfig[$rxDeviceName]['TYPE']) &&
				strtoupper($svxconfig[$rxDeviceName]['TYPE']) === 'MULTI'
			) {

				if (isset($svxconfig[$rxDeviceName]['TRANSMITTERS'])) {
					$transmitters = array_filter(array_map('trim', explode(",", $svxconfig[$rxDeviceName]['TRANSMITTERS'])), 'strlen');
					$transmitters = array_map('trim', $transmitters);
					$rxProcessed = implode(",", $transmitters);
				}
			}


			$txProcessed = $txDeviceName;

			if (
				!empty($txDeviceName) &&
				isset($svxconfig[$txDeviceName]) &&
				isset($svxconfig[$txDeviceName]['TRANSMITTERS']) &&
				!isset($multipleDevice[$txDeviceName])
			) {

				$multipleDevice[$txDeviceName] = trim($svxconfig[$txDeviceName]['TRANSMITTERS']);

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
							'name' => $moduleName,
							'callsign' => '',
							'is_active' => false,
							'is_connected' => false,
							'connected_nodes' => []
						];
					}
				}
			}

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
				$item['connected_nodes'] = [];
			}
			if (isset($svxconfig[$_logic]['HOSTS'])) {
				$item['hosts'] = $svxconfig[$_logic]['HOSTS'] ? $svxconfig[$_logic]['HOSTS'] : '';
			}

			$_logics[$_logic] = $item;
			unset($rxDeviceName, $txDeviceName, $rxProcessed, $txProcessed);
		}

		// 
		$service = [
			'start' => 0,
			'name' => SERVICE_TITLE,
			'is_active' => false,
			'timestamp_format' => $svxconfig['GLOBAL']['TIMESTAMP_FORMAT'],
		];

		$callsign = isset($svxconfig['GLOBAL']['LOCATION_INFO'])
			? ($svxconfig[$svxconfig['GLOBAL']['LOCATION_INFO']]['CALLSIGN'] ?? 'NO CALLSIGN')
			: 'NO CALLSIGN';

		$status = [
			'link' => $_links,
			'logic' => $_logics,
			'service' => $service,
			'multiple_device' => $multipleDevice,
			'callsign' => $callsign,
		];

	} else {

		$status = [
			'link' => $_SESSION['status']['link'],
			'logic' => $_SESSION['status']['logic'],
			'service' => $_SESSION['status']['service'],
			'multiple_device' => $_SESSION['status']['multiple_device'],
			'callsign' => $_SESSION['status']['callsign']
		];
	}

	// @bookmark Заполнение конфигурации данными
	
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
		error_log("getActualStatus: Log not found or empty.");
		$status['service']['name'] = "LOG PARSE ERROR";
		return $status;
	}

	$logStatusLine = trim($logLines[0]);
	if (empty($logStatusLine)) {
		error_log("getActualStatus: Cant find svxlink start/stop actions");
		$status['service']['name'] = "LOG PARSE ERROR";
		return $status;
	}
	
	$line_timestamp = getLineTime($logStatusLine);
	if ($line_timestamp === false) {
		error_log("getActualStatus: Cant parse timestamp from $logStatusLine");
		$status['service']['name'] = "LOG TIMESTAMP ERROR";
		return $status;
	}

	if (strpos($logStatusLine, 'Tobias Blomberg', 0) !== false) {
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
		error_log("getActualStatus: Zero size log");
		$status['service']['name'] = "LOG SIZE ERROR";
	}

	$status['service']['start'] = $line_timestamp;
	$status['service']['log_line_count'] = $logLineCount;

	if ($status['service']['is_active'] === false) {
		error_log("getActualStatus: Svxlink session is not active, stopping");
		return $status;
	}

	$logCount = $status['service']['log_line_count'];
	$isSomeModuleActive = false;

	foreach ($status['logic'] as $logicName => &$logic) {
		if (!isset($logic['type'])) {
			error_log("getActualStatus: Cant parse logic type for $logicName");
			continue;
		}

		$logicType = $logic['type'];
		$serviceCommand = '';
		$serviceCommandTimestamp = 0;
		$logicTimestamp = $logic['start'];

		$required_condition = $logicName;
		if ($logicType === 'Reflector') {

			$or_conditions[] = "Authentication OK";
			$or_conditions[] = "Disconnected from";
			$serviceCommand = trim(getLogTailFiltered(1, $required_condition, $or_conditions, $logCount)[0]);
			unset($or_conditions);
			
		} else {
			
			$or_conditions[] = "Event handler script successfully loaded";
			$or_conditions[] = "ctivating module";
			$serviceCommand = trim(getLogTailFiltered(1, $required_condition, $or_conditions, $logCount)[0]);
			unset($or_conditions);
			
		}

		
		if (empty($serviceCommand)) {
			continue;
		}

		$serviceCommandTimestamp = getLineTime($serviceCommand);
		if (!is_int($serviceCommandTimestamp)) {
			error_log("getActualStatus: Wrong timestamp");
			continue;
		}
		
		
		if ($serviceCommandTimestamp < $logicTimestamp) {
			error_log("getActualStatus: Wrong timestamp (in future)");
			continue;
		}

		$logic['start'] = $serviceCommandTimestamp > $logic['start'] ? $serviceCommandTimestamp : $logic['start'];
		
		$logic['is_active'] = true; 

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
			if (isset($logic['module']) || is_array($logic['module'])) {
				if (strpos($serviceCommand, 'module') === false) {
					continue;
				} else {
					foreach ($logic['module'] as $moduleName => &$module) {

						if (
							strpos($serviceCommand, $moduleName) !== false &&
							strpos($serviceCommand, 'Activating') !== false
						) {

							$logic['is_connected'] = true;
							$module['is_active'] = true;
							$module['start'] = $serviceCommandTimestamp;
							$isSomeModuleActive = true;

							if ($moduleName === "EchoLink") {
								$logELcount = countLogLines("Activating module EchoLink", $logCount);
								$logEL = getLogTail($logELcount);

								if (!isset($module['connected_nodes']) || !is_array($module['connected_nodes'])) {
									$module['connected_nodes'] = [];
								}

								$foundNodes = [];

								foreach ($module['connected_nodes'] as $nodeName => $node) {
									if (is_array($node) && isset($node['name'])) {
										$foundNodes[$node['name']] = $node;
										//@todo Убрать эту проверку
									} elseif (is_array($node)) {
										$foundNodes[$nodeName] = $node;
									}
								}

								foreach ($logEL as $logELline) {
									$isConnected = strpos($logELline, "EchoLink QSO state changed to CONNECTED") !== false;
									$isDisconnected = strpos($logELline, "EchoLink QSO state changed to DISCONNECTED") !== false;

									if ($isConnected || $isDisconnected) {
										$firstColonPos = strpos($logELline, ": ");
										if ($firstColonPos !== false) {
											$cleanLine = substr($logELline, $firstColonPos + 2); // +2 чтобы пропустить ": "
											$parts = explode(":", $cleanLine, 2); // Разбиваем на 2 части: имя и остаток

											if (count($parts) >= 2) {
												$nodeName = trim($parts[0]); 
												$restOfLine = trim($parts[1]); 
												if (preg_match('/^\s*\*(.*)\*\s*$/', $nodeName, $matches)) {
													$rawName = $matches[1]; 
													if (preg_match('/^\s*\*+/', $nodeName)) {
														$type = "Conference";
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
												$startTime = getLineTime($logELline);
												if ($isConnected) {
													if (!isset($foundNodes[$nodeName])) {
														$foundNodes[$nodeName] = [
															'callsign' => $callsign,
															'start' => $startTime,
															'type' => $type,
															'name' => $nodeName
														];

													} else {
														$foundNodes[$nodeName]['start'] = $startTime;
													}
												}
												elseif ($isDisconnected) {
													if (isset($foundNodes[$nodeName])) {
														unset($foundNodes[$nodeName]);
														
													}
												}
											} 																						
										} 										
									}
								}

								// Помещаем подключенные узлы в connected_nodes
								$module['connected_nodes'] = $foundNodes;

								if (count($module['connected_nodes']) !== 0) {
									$module['is_connected'] = true;									
									$logic['is_connected'] = true;
								}
								continue;
							}
							// @bookmark Для модуля Frn
							if ($moduleName === "Frn") {
								$logFRNcount = countLogLines("Activating module Frn", $logCount);
								$logFRN = getLogTail($logFRNcount);
								$logFRN = array_reverse($logFRN);

								foreach ($logFRN as $logFRNline) {
									$isFrnServerDisconnected = strpos($logFRNline, "DR_REMOTE_DISCONNECTED") !== false;
									$isServerStateFound = strpos($logFRNline, "login stage 2 completed") !== false;

									if ($isFrnServerDisconnected) {
										$module['is_connected'] = false;
										unset($isFrnServerDisconnected, $isServerStateFound, $logFRN, $logFRNcount);
										break;
									}
									if ($isServerStateFound) {
										$frnServerIdArray = parseXmlTags($logFRNline);										
										$frnServerConnectTime = getLineTime($logFRNline);
										if ($frnServerConnectTime != false) {
											$module['is_connected'] = true;
											$module['start'] = $frnServerConnectTime;
											if (isset($frnServerIdArray) && isset($frnServerIdArray['BN'])) {
												$module['connected_nodes'][$frnServerIdArray['BN']] = [
													'name' => $frnServerIdArray['BN'],
													'start' => $frnServerConnectTime,
													'callsign' => $frnServerIdArray['BN'],
													'type' => "Server",
												];
												
											}											
											break;
										} 
									}
								}
							}
						} else {
							$module['is_active'] = false;
							$module['start'] = 0;
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
	}



	foreach ($status['link'] as $linkName => &$link) {
		$required_condition = $linkName;
		$or_conditions[] = "ctivating link";

		$logLinks = getLogTailFiltered(1, $required_condition, $or_conditions, $logCount);

		unset($required_condition, $or_conditions);

		if ($logLinks !== false) {

			$logLink = $logLinks[0];
			
			if (empty($logLink)) {
				$link['is_active'] = false;
				$link['start'] = 0;
				continue;
			}

			$logLinkTimestamp = getLineTime($logLink);

			if (!is_int($logLinkTimestamp)) {
				continue;
			}

			$link['is_active'] = true;
			if (strpos($logLink, 'Activating') !== false) {
				
				foreach ($status['logic'] as $logicName => &$logic) {
					if ($link['destination']['logic'] === $logicName) {
						$logic['is_active'] = true;						
					}
					if ($link['source']['logic'] === $logicName) {
						$logic['is_connected'] = true;
					}
				}

				$link['is_connected'] = true;
				$link['start'] = $logLinkTimestamp;
			} elseif (strpos($logLink, 'Deactivating') !== false || strpos($logLink, 'Removing') !== false) {
				foreach ($status['logic'] as $logicName => &$logic) {
					if ($link['destination']['logic'] === $logicName) {
						$logic['is_active'] = false;						
					}
				}
				$link['is_connected'] = false;
				$link['start'] = 0;
			}
		} else {
			
			$link['start'] = 0;
			$link['is_active'] = false;
			$link['is_connected'] = false;
			foreach ($status['logic'] as $logicName => &$logic) {
				if ($link['destination']['logic'] === $logicName) {
					if ($logic['is_active']) $logic['is_active'] = false;
				}
			}
		}
	}

	foreach ($status['logic'] as $logicName => &$logic) {
		if ($isSomeModuleActive == true) {
			if ($logic['type'] === "Reflector") $logic['is_active'] = false;
		}
	}

	return $status;
}
