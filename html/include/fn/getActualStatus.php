<?php

/**
 * Calculating actual service status
  * @filesource /include/fn/getActualStatus.php 
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.11
 * @version 0.4.6
 */
function getActualStatus(array $config = null): array
{
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/logTailer.php';
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getLineTime.php';
	require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/parseXmlTags.php';
	
	if($config === null)	{
		$status = [
			'link' => $_SESSION['status']['link'],
			'logic' => $_SESSION['status']['logic'],
			'service' => $_SESSION['status']['service'],
			'multiple_device' => $_SESSION['status']['multiple_device'],
			'callsign' => $_SESSION['status']['callsign']
		];
	} else {
		$status = $config;
	}
		
	// }

	// @bookmark Заполнение конфигурации данными
	$search_limit = isset($status['service']['log_line_count'])
		? ($status['service']['log_line_count'] > 0 ? $status['service']['log_line_count'] : null)
		: null;
	
	$log_growth_rate = ( (defined("UPDATE_INTERVAL") && UPDATE_INTERVAL > 1000 ) ? UPDATE_INTERVAL : 10000 ) / 3 ;
	if(!is_null($search_limit)) $search_limit = $search_limit + $log_growth_rate; 

	$or_conditions = ["SIGTERM", "Tobias Blomberg"];
	$search_result = getLogTailFiltered(1, null, $or_conditions, $search_limit);
		
	if ($search_result === false) {
		
		$status['service']['name'] = "SRV STATUS UNKNOWN";
		return $status;

	} else {
		
		$action_line = $search_result[0];
		if (empty($action_line)) {
			error_log("getActualStatus: Cant find svxlink start/stop actions in empty line $action_line");
			$status['service']['name'] = "SRV STATUS EMPTY";
			return $status;
		}
	}
	unset($or_conditions);

	$action_line_timestamp = getLineTime($action_line);
	if ($action_line_timestamp === false || $action_line_timestamp === 0 ) {
		error_log("getActualStatus: Cant parse timestamp from $service_action_line");
		$status['service']['name'] = "LOG TIMESTAMP ERROR";
		return $status;
	}
	
	if (strpos($action_line, 'Tobias Blomberg', 0) !== false) {
		$status['service']['is_active'] = true;
		$status['service']['start'] = $action_line_timestamp;
	} else {
		$status['service']['is_active'] = false;
		return $status;
	}
	unset($or_conditions, $action_line_timestamp);

	// Service up, calculate log size

	
	$max_lines = is_null($search_limit) ? 0 : $search_limit;
	$log_size = countLogLines($action_line, $max_lines);
	if ($log_size === false) {
		
		error_log("getActualStatus: Zero size log for pattetn $action_line last $max_lines");
		$status['service']['name'] = "ZERO SERVICE SIZE";
		return $status;
	} else {
		$status['service']['log_line_count'] = $log_size;
		$session_log_size = $status['service']['log_line_count'] ?? null;
		if ($session_log_size !== $log_size) {
			$status['service']['log_line_count'] = $log_size;
		}
		unset($session_log_size, $max_lines);

	}
	


	if ($status['service']['is_active'] === false) {
		return $status;
	}

	$needMuteLogic = false;

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
		} else {
			$or_conditions[] = "Event handler script successfully loaded";
			$or_conditions[] = "ctivating module";
		}		
		$search_result = getLogTailFiltered(1, $required_condition, $or_conditions, $log_size + 50);
		
		if($search_result !== false){
			$serviceCommand = $search_result[0];
		} else {
			error_log("getActualStatus: Cant found state for $logicName");
		}
		unset($search_result, $or_conditions);

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
			$logic['is_connected'] = true;

			// Ищем 1 последнюю строку Connected nodes и обновляем массив connected_nodes
			$or_conditions[] = "Connected nodes:";
			$search_result = getLogTailFiltered(1, $required_condition, $or_conditions, $log_size);
			unset($or_conditions);
			
			if ($search_result !== false) {			
				$connected_nodes = $search_result[0];
				$nodes_connecting_time = getLineTime($connected_nodes);

				// Заполняем подключенные узлы для рефлектора
				$logic['connected_nodes'] = [];
				if (preg_match('/Connected nodes:\s*(.+)$/', $connected_nodes, $matches)) {
					$nodesStr = trim($matches[1]);
					$callsigns = array_filter(array_map('trim', explode(',', $nodesStr)));
					foreach ($callsigns as $fullCallsign) {
						if (!empty($fullCallsign)) {
							if (preg_match('/^([A-Za-z0-9]+)(?:[-\\/][A-Za-z0-9]+)?$/', $fullCallsign, $callMatch)) {
								$baseCallsign = $callMatch[1];
								$logic['connected_nodes'][$fullCallsign] = [
									'callsign' => $baseCallsign,
									'start' => $nodes_connecting_time,
									'type' => 'Node'
								];
							}
						}
					}
				}
			}
			

			// @todo Разговорные группы Временный монитор            
			$or_conditions[] = "emporary monitor";
			$logLineTM = getLogTailFiltered(50, $required_condition, $or_conditions, $log_size);
			unset($or_conditions);

			if (!empty($logLineTM) !== false) {
				foreach ($logLineTM as $index => $line) {
					if (empty($line)) {
						continue;
					}

					if (preg_match('/#(\d+)$/', $line, $matches)) {
						$tgNumber = $matches[1];
						if (strpos($line, 'timeout') !== false) {
							$groupStates[$tgNumber] = false;
						} else if (strpos($line, 'Add') !== false || strpos($line, 'Refresh') !== false) {
							$groupStates[$tgNumber] = true;
						}
					}
				}
			}

			// @bookmark Заполняю группы Временный монитор
			$activeTMGroups = [];
			if (isset($groupStates)) {
				foreach ($groupStates as $tgNumber => $isActive) {
					if ($isActive) {
						$activeTMGroups[] = $tgNumber;
					}
				}
			}

			// @bookmark Получаем текущие значения для выбранной группы
			$selectedTG = $logic['talkgroups']['selected'];
			$or_conditions[] = "Selecting TG";
			$logLinesSG = getLogTailFiltered(1, $required_condition, $or_conditions, $log_size);
			unset($or_conditions);
			if ($logLinesSG !== false) {
				$logLineSG = $logLinesSG[0];

				if (preg_match('/Selecting TG #(\d+)/', $logLineSG, $match)) {
					$selectedTG = ($match[1] == '0')
						? $logic['talkgroups']['default']
						: (int)$match[1];

					// УДАЛЯЕМ выбранную группу из временного мониторинга
					$key = array_search($selectedTG, $activeTMGroups);
					if ($key !== false) {
						unset($activeTMGroups[$key]);
					}
				}
			}

			$logic['talkgroups']['selected'] = $selectedTG;
			$logic['talkgroups']['temp_monitoring'] = $activeTMGroups;

			if ($logic['is_connected']) {
				// $required_condition = $logic['name'];
				$or_conditions = ["Talker start on TG", "Talker stop on TG"];
				$dev_last_action = getLogTailFiltered(1, $required_condition, $or_conditions, $log_size);
				if ($dev_last_action !== false) {
					if (strpos($dev_last_action[0], "Talker start") !== false) {
						preg_match('/:\s*([^:]+):\s*Talker start on TG #(\d+):\s*([^\s]+)/', $dev_last_action[0], $m);
						$logic['rx']['start'] = getLineTime($dev_last_action[0]);
						$logic['caller_callsign'] = $m[3];
						$logic['caller_tg'] = $m[2];

					} else {
						$logic['rx']['start'] = 0;
						$logic['caller_callsign'] = '';
						$logic['caller_tg'] = '';
					}
				} else {
					$logic['rx']['start'] = 0;
				}
				unset($or_conditions);
			}

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
							
							$needMuteLogic = $module['mute_logic'];
							// @bookmark Для модуля EchoLink
							if ($moduleName === "EchoLink") {
								$logELcount = countLogLines("Activating module EchoLink", $log_size);
								if($logELcount !== false) {
									$logEL = getLogTail($logELcount);
									if($logEL !== false) {
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
														} elseif ($isDisconnected) {
															if (isset($foundNodes[$nodeName])) {
																unset($foundNodes[$nodeName]);
															}
														}
													}
												}
											}
										}

										$module['connected_nodes'] = $foundNodes;
										if (count($module['connected_nodes']) !== 0) {
											$module['is_connected'] = true;
											$logic['is_connected'] = true;
										}
										continue;

									}
								} 
							}

							// @bookmark Для модуля Frn
							if ($moduleName === "Frn") {
								$logFRNcount = countLogLines("Activating module Frn", $log_size);
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
							$module['is_connected'] = false;
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
				$dev_last_action = getLogTailFiltered(1, $required_condition, $or_conditions, $log_size);

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
				$dev_last_action = getLogTailFiltered(1, $required_condition, $or_conditions, $log_size);

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

		$logLinks = getLogTailFiltered(1, $required_condition, $or_conditions, $log_size);

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
		if ($needMuteLogic == true) {
			if ($logic['type'] === "Reflector") $logic['is_active'] = false;
		}
	}

	// @bookmark APRS
	if (isset($status['service']['aprs_server'])) {
		$required_condition = "APRS server";
		$aprsLogState = getLogTailFiltered(1, $required_condition, [], $status['service']['log_line_count']);
		if ($aprsLogState !== false) {
			if (str_contains($aprsLogState[0], "Connected") !== false) {
				$pattern = '/Connected to APRS server (\S+) on port (\d+)/';
				if (preg_match($pattern, $aprsLogState[0], $matches)) {
					$status['service']['aprs_server']['start'] = getLineTime($aprsLogState[0]);
				}
			} elseif (str_contains($aprsLogState[0], "Disconnected") !== false) {
				$status['service']['aprs_server']['start'] = 0;
			}
		}
	}

	// @bookmark EchoLink directory server
	if (isset($status['service']['directory_server'])) {

		$required_condition = "EchoLink directory status";
		$directoryLogState = getLogTailFiltered(1, $required_condition, [], $status['service']['log_line_count']);
		if ($directoryLogState !== false) {
			$status['service']['directory_server']['name'] = "Unknown";
			if (str_contains($directoryLogState[0], "changed to ON") !== false) {
				$status['service']['directory_server']['name'] = "Connected";
				$status['service']['directory_server']['start'] = getLineTime($directoryLogState[0]);
			} elseif (str_contains($directoryLogState[0], "ERROR") !== false) {
				$status['service']['directory_server']['name'] = "Disconnected";
				$status['service']['directory_server']['start'] = 0;
			}
		}
	}

	// ...: Connected to EchoLink proxy 44.137.75.93:8100
	// ...: Disconnected from EchoLink proxy 44.137.75.93:8100
	$required_condition = "EchoLink proxy";
	$proxyLogState = getLogTailFiltered(1, $required_condition, [], $status['service']['log_line_count']);
	if ($proxyLogState !== false) {

		$status['service']['proxy_server'] = ['name' => '', 'start' => 0];
		if (str_contains($proxyLogState[0], "Connected") !== false) {
			$pattern = '/Connected to EchoLink proxy (\S+)/';
			if (preg_match($pattern, $proxyLogState[0], $matches)) {
				$proxyAddress = $matches[1];
				$proxyName = strstr($proxyAddress, ':', true);
				if ($proxyName === false) {
					$proxyName = $proxyAddress;
				}
				$proxyStartTime = getLineTime($proxyLogState[0]);
				$proxyServer = [
					'name' => $proxyName,
					'start' => $proxyStartTime,
				];
				$status['service']['proxy_server'] = $proxyServer;
			}
		} elseif (str_contains($proxyLogState[0], "Disconnected") !== false && isset($status['service']['proxy_server'])) {
			$status['service']['proxy_server']['start'] = 0;
		}
	}

	return $status;
}
