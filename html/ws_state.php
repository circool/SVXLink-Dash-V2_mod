<?php

/**
 * WebSocket State Provider
 * @author Vladimir Tsurkanenko <vladimir@tsurkanenko.ru>
 * @version 1.0.0.release
 * @filesource /ws_state.php
 * @note Preliminary version.
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/init.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');


try {
	$status = $_SESSION['status'] ?? [];

	$response = [
		'timestamp' => time(),
		'data' => [
			'devices' => [],
			'modules' => [],
			'links' => [],
			'nodes' => [],
			'module_logic' => [], 
			'service' => [],
			'logics' => [],
		]
	];

	if (isset($status['service']) && is_array($status['service'])) {
		$serviceName = $status['service']['name'] ?? 'Unnamed';
		$isActive = $status['service']['is_active'] ?? false;
		$startTime = $status['service']['start'] ?? 0;

		$response['data']['service'] = [
			'name' => $serviceName,
			'is_active' => $isActive,
			'start' => $startTime
		];
	}

	if (isset($status['logic']) && is_array($status['logic'])) {
		foreach ($status['logic'] as $logicName => $logic) {
			if (!is_array($logic)) continue;

			$logicStart = isset($logic['start']) ? (int)$logic['start'] : 0;
			$logicKey = 'logic_' . $logicName;
			$response['data']['logics'][$logicKey] = [
				'start' => $logicStart,
				'is_active' => $logic['is_active'] ?? false,
				'name' => $logicName,
				'type' => $logic['type'] ?? 'Unknown'
			];

			if (!empty($logic['rx'])) {
				$response['data']['devices'][$logic['rx']['name']] = [
					'start' => $logic['rx']['start'],
					'type' => 'RX',
					'logic' => $logicName
				];
			}

			if (!empty($logic['tx'])) {
				$response['data']['devices'][$logic['tx']['name']] = [
					'start' => $logic['tx']['start'],
					'type' => 'TX',
					'logic' => $logicName
				];
			}


			if (isset($logic['module']) && is_array($logic['module'])) {
				foreach ($logic['module'] as $moduleName => $module) {
					if (!is_array($module)) continue;

					$moduleKey = 'logic_' . $logicName . '_module_' . $moduleName;
					$moduleStart = isset($module['start']) ? (int)$module['start'] : $logicStart;

					$response['data']['modules'][$moduleKey] = [
						'start' => $moduleStart,
						'logic' => $logicName,
						'module' => $moduleName
					];

					if (!isset($response['data']['module_logic'][$moduleName])) {
						$response['data']['module_logic'][$moduleName] = [];
					}
					if (!in_array($logicName, $response['data']['module_logic'][$moduleName])) {
						$response['data']['module_logic'][$moduleName][] = $logicName;
					}

					if (isset($module['connected_nodes']) && is_array($module['connected_nodes'])) {
						foreach ($module['connected_nodes'] as $nodeName => $nodeData) {
							$nodeKey = 'logic_' . $logicName . '_node_' . $nodeName;

							if (is_array($nodeData) && isset($nodeData['start'])) {
								$response['data']['nodes'][$nodeKey] = [
									'start' => (int)$nodeData['start'],
									'logic' => $logicName,
									'module' => $moduleName,
									'node' => $nodeName,
									'callsign' => $nodeData['callsign'] ?? $nodeName,
									'type' => 'module_node'
								];
							}
						}
					}
				}
			}


			if (($logic['type'] ?? '') === 'Reflector') {
				if (isset($logic['connected_nodes']) && is_array($logic['connected_nodes'])) {
					foreach ($logic['connected_nodes'] as $nodeName => $nodeData) {
						$nodeKey = 'logic_' . $logicName . '_node_' . $nodeName;
						if (is_array($nodeData) && isset($nodeData['start'])) {
							$response['data']['nodes'][$nodeKey] = [
								'start' => (int)$nodeData['start'],
								'logic' => $logicName,
								'node' => $nodeName,
								'callsign' => $nodeData['callsign'] ?? $nodeName,
								'type' => 'reflector_node'
							];
						}
					}
				}
			}
		}
	}

	if (isset($status['link']) && is_array($status['link'])) {
		foreach ($status['link'] as $linkName => $link) {
			if (!is_array($link)) continue;

			$isActive = $link['is_active'] ?? false;
			$isConnected = $link['is_connected'] ?? false;
			$link_destination = $link['destination']['logic'];
			$link_source = $link['source']['logic'];
			$linkStart = 0;
			if (($isActive || $isConnected) && isset($link['start']) && $link['start'] > 0) {
				$linkStart = (int)$link['start'];
			}

			if (isset($link['source']['logic']) && !empty($link['source']['logic'])) {
				$sourceLogic = $link['source']['logic'];
				if (!isset($response['data']['link_logic'][$sourceLogic])) {
					$response['data']['link_logic'][$sourceLogic] = [];
				}
				if (!in_array($linkName, $response['data']['link_logic'][$sourceLogic])) {
					$response['data']['link_logic'][$sourceLogic][] = $linkName;
				}
			}

			if (isset($link['destination']['logic']) && !empty($link['destination']['logic'])) {
				$destLogic = $link['destination']['logic'];
				if (!isset($response['data']['link_logic'][$destLogic])) {
					$response['data']['link_logic'][$destLogic] = [];
				}
				if (!in_array($linkName, $response['data']['link_logic'][$destLogic])) {
					$response['data']['link_logic'][$destLogic][] = $linkName;
				}
			}

			$response['data']['links'][$linkName] = [
				'is_active' => $isActive,
				'is_connected' => $isConnected,
				'start' => $linkStart,
				'timeout' => $link['timeout'] ?? 0,
				'default_active' => $link['default_active'] ?? false,
				'source' => $link['source'] ?? [],
				'destination' => $link['destination'] ?? []
			];
		}
	}


	$response['meta'] = [
		'counts' => [
			'devices' => count($response['data']['devices']),
			'modules' => count($response['data']['modules']),
			'links' => count($response['data']['links']),
			'nodes' => count($response['data']['nodes'])
		],
		'generated_at' => date('Y-m-d H:i:s'),
		'session_id' => session_id()
	];


	echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode([
		'error' => 'Internal server error',
		'message' => $e->getMessage(),
		'timestamp' => time()
	]);
}
