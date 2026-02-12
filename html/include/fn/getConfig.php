<?php

/**
 * Build svxlink confiruration
 * @filesource /include/fn/getConfig.php
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @date 2026.02.11
 * @version 0.4.6
 */

function getConfig() : array
{

	$has_error = '';
	$stub = [
		'link' => [],
		'logic' => [],
		'service' => [
			'start' => 0,
			'name' => $has_error,
			'is_active' => false,
			'timestamp_format' => '%d %b %Y %H:%M:%S.%f',
			'aprs_server' => [
				'name' => '',
				'start' => 0
			],
		],
		'callsign' => "LOG ERROR",
	];

	if (!defined('SVXCONFPATH') || !defined('SVXCONFIG')) {
		error_log("getConfig: SVXCONFPATH or SVXCONFIG not set");
		$has_error = 'CONSTANTS ERROR';
	}

	if ($has_error === '') {
		$config_file = SVXCONFPATH . SVXCONFIG;

		if (empty($config_file)) {
			error_log("getConfig: Config not found: $config_file");
			$has_error = 'CONFIG NOT FOUND';
		}
	}

	if ($has_error === '') {
		$lines = file($config_file, FILE_IGNORE_NEW_LINES);
		if ($lines === false) {
			error_log("getConfig: File read error: $config_file");
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
				error_log("getConfig: File read error: $error");
				$has_error = $error;
			}
		}
	}

	if ($has_error != '') {
		return $stub;
	}

	if (!isset($svxconfig['GLOBAL']['TIMESTAMP_FORMAT'])) {
		$stub['service']['name'] = 'PARAMS MISSING';
		return $stub;
	}

	$logics = isset($svxconfig['GLOBAL']['LOGICS']) ?
		array_filter(array_map('trim', explode(",", $svxconfig['GLOBAL']['LOGICS'])), 'strlen') : [];

	if (empty($logics)) {
		error_log("getConfig: $logics: logics empty");
		$stub['service']['name'] = 'EMPTY LOGICS';
		return $stub;
	}

	$service = [
		'start' => 0,
		'name' => SERVICE_TITLE,
		'is_active' => false,
		'timestamp_format' => $svxconfig['GLOBAL']['TIMESTAMP_FORMAT'],
	];

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
			error_log("getConfig: PARSE CONFIG LOG: $linkName has no DEFAULT_ACTIVE");
		} else {
			$linkDefaultActive = $svxconfig[$linkName]['DEFAULT_ACTIVE'] ? true : false;
		}

		if (!isset($svxconfig[$linkName]['CONNECT_LOGICS'])) {
			error_log("getConfig: PARSE CONFIG LOG: $linkName has no CONNECT_LOGICS");
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
	// @bookmark Logic
	$_logics = [];
	foreach ($logics as $_logic) {

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
		// @bookmark Modules
		if (isset($svxconfig[$_logic]['MODULES'])) {
			$cfgdir = $svxconfig['GLOBAL']['CFG_DIR'] ?? '';
			if (!empty($cfgdir)) {
				$module_id = $module_id ?? [];
			}
			$item['module'] = [];
			$moduleNames = array_filter(
				array_map('trim', explode(",", str_replace('Module', '', $svxconfig[$_logic]['MODULES']))),
				'strlen'
			);
			foreach ($moduleNames as $moduleName) {
				$moduleName = trim($moduleName);
				// @bookmark Module ID for DTMF controlling
				if (!empty($moduleName)) {
					$module_id_value = '';
					$module_mute_logic_value = true;

					if (isset($module_id[$moduleName])) {
						$module_id_value = $module_id[$moduleName];
					} elseif (!empty($cfgdir)) {
						$module_config_file = SVXCONFPATH . $cfgdir . '/Module' . $moduleName . '.conf';
						if (file_exists($module_config_file)) {
							$module_content = file_get_contents($module_config_file);
							if ($module_content !== false) {
								$lines = explode("\n", $module_content);
								$in_module_section = false;
								foreach ($lines as $line) {
									$line = trim($line);
									if (strpos($line, '#') === 0 || strpos($line, ';') === 0) {
										continue;
									}

									if (preg_match('/^\[Module' . preg_quote($moduleName, '/') . '\]$/i', $line)) {
										$in_module_section = true;
										continue;
									}

									if ($in_module_section && preg_match('/^ID\s*=\s*(.+)$/i', $line, $matches)) {
										$module_id_value = trim($matches[1]);
										$module_id[$moduleName] = $module_id_value;
									}
									if ($in_module_section && preg_match('/^MUTE_LOGIC_LINKING\s*=\s*(.+)$/i', $line, $matches)) {
										$module_mute_logic_value = trim($matches[1]);
										$module_mute_logic[$moduleName] = $module_mute_logic_value;
									}

									if ($in_module_section && preg_match('/^\[/', $line)) {
										break;
									}
								}
							}
						}
					}

					$item['module'][$moduleName] = [
						'start' => 0,
						'name' => $moduleName,
						'callsign' => '',
						'is_active' => false,
						'is_connected' => false,
						'connected_nodes' => [],
						'id' => $module_id_value,
						'mute_logic' => $module_mute_logic_value ? true : false,
					];

					if ($moduleName === 'EchoLink') {
						if (!isset($service['directory_server'])) {
							$service['directory_server'] = [
								'name' => '',
								'start' => 0,
							];
						}
					}
				}
			}
		}
		
		// @bookmark Talkgroups
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
			$item['caller_callsign'] = '';
			$item['caller_tg'] = '';
		}

		$_logics[$_logic] = $item;
	}

	$locationInfoSection = $svxconfig['GLOBAL']['LOCATION_INFO'] ?? '';

	if (!empty($locationInfoSection) && isset($svxconfig[$locationInfoSection])) {
		// @bookmark APRS
		$aprsServerList = $svxconfig[$locationInfoSection]['APRS_SERVER_LIST'] ?? '';
		if (!empty($aprsServerList)) {
			$serverName = strstr($aprsServerList, ':', true) ?: $aprsServerList;
			$aprsServer = [
				'name' => $serverName,
				'start' => 0
			];
			$service['aprs_server'] = $aprsServer;
		}
		// @bookmark STATUS SERVER
		$statusServerList = $svxconfig[$locationInfoSection]['STATUS_SERVER_LIST'] ?? '';
		if (!empty($statusServerList)) {
			$serverName = strstr($statusServerList, ':', true) ?: $statusServerList;
			$statusServer = [
				'name' => $serverName,
				'has_error' => false,
			];
			$service['status_server'] = $statusServer;
		}
	}

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
	return $status;
}
