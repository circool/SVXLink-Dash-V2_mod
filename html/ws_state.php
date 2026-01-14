<?php

/**
 * WebSocket State Provider - ВЕРСИЯ 0.4
 * Поставщик начального состояния для WebSocket-системы v4.0, 
 * обеспечивающий синхронизацию между PHP-сессией и Node.js WebSocket сервером
 */
// 3. Начинаем сессию
// require_once $_SERVER["DOCUMENT_ROOT"] . '/include/session_header.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/init.php';

// 1. Заголовки
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 2. Разрешаем только localhost
// $allowed_ips = ['127.0.0.1', '::1', 'localhost'];
// if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
// 	http_response_code(403);
// 	echo json_encode(['error' => 'Access denied. Only localhost allowed.']);
// 	exit;
// }


// 4. Основная логика
try {
	$status = $_SESSION['status'] ?? [];

	$response = [
		'timestamp' => time(),
		'data' => [
			'devices' => [],
			'modules' => [],
			'links' => [],
			'nodes' => [],
			'module_logic' => [], // Связи логика - модуль
			'service' => [],
			'logics' => [],
		]
	];

	// 5. Обработка сервиса
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

	// 6. Обработка логик и устройств
	if (isset($status['logic']) && is_array($status['logic'])) {
		foreach ($status['logic'] as $logicName => $logic) {
			if (!is_array($logic)) continue;

			$logicStart = isset($logic['start']) ? (int)$logic['start'] : 0;

			// 6.1 Добавляем логику в logics
			$logicKey = 'logic_' . $logicName;
			$response['data']['logics'][$logicKey] = [
				'start' => $logicStart,
				'is_active' => $logic['is_active'] ?? false,
				'name' => $logicName,
				'type' => $logic['type'] ?? 'Unknown'
			];

			// 6.2 Устройства RX/TX
			if (!empty($logic['rx'])) {
				$response['data']['devices'][$logic['rx']] = [
					'start' => 0,
					'type' => 'RX',
					'logic' => $logicName
				];
			}

			if (!empty($logic['tx'])) {
				$response['data']['devices'][$logic['tx']] = [
					'start' => 0,
					'type' => 'TX',
					'logic' => $logicName
				];
			}

			// 6.3 Модули
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

					// Связи модуль-логика
					if (!isset($response['data']['module_logic'][$moduleName])) {
						$response['data']['module_logic'][$moduleName] = [];
					}
					if (!in_array($logicName, $response['data']['module_logic'][$moduleName])) {
						$response['data']['module_logic'][$moduleName][] = $logicName;
					}

					// Узлы модуля
					if (isset($module['connected_nodes']) && is_array($module['connected_nodes'])) {
						foreach ($module['connected_nodes'] as $nodeName => $nodeData) {
							$nodeKey = 'logic_' . $logicName . '_node_' . $nodeName;

							// Формат: массив с ключами callsign и start
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

			// 6.4 Узлы рефлектора
			if (($logic['type'] ?? '') === 'Reflector') {
				if (isset($logic['connected_nodes']) && is_array($logic['connected_nodes'])) {
					foreach ($logic['connected_nodes'] as $nodeName => $nodeData) {
						$nodeKey = 'logic_' . $logicName . '_node_' . $nodeName;

						// Формат: массив с callsign и start
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

	// 7. Обработка линков
	if (isset($status['link']) && is_array($status['link'])) {
		foreach ($status['link'] as $linkName => $link) {
			if (!is_array($link)) continue;

			$isActive = $link['is_active'] ?? false;
			$isConnected = $link['is_connected'] ?? false;
			$link_destination = $link['destination']['logic'];
			$link_source = $link['source']['logic'];

			// Определяем время старта
			$linkStart = 0;
			if (($isActive || $isConnected) && isset($link['start']) && $link['start'] > 0) {
				$linkStart = (int)$link['start'];
			}

			// Связи линка с логиками (source и destination)
			// Источник (source)
			if (isset($link['source']['logic']) && !empty($link['source']['logic'])) {
				$sourceLogic = $link['source']['logic'];
				if (!isset($response['data']['link_logic'][$sourceLogic])) {
					$response['data']['link_logic'][$sourceLogic] = [];
				}
				if (!in_array($linkName, $response['data']['link_logic'][$sourceLogic])) {
					$response['data']['link_logic'][$sourceLogic][] = $linkName;
				}
			}

			// Назначение (destination)
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
				'duration' => $link['duration'] ?? 0,
				'timeout' => $link['timeout'] ?? 0,
				'default_active' => $link['default_active'] ?? false,
				'source' => $link['source'] ?? [],
				'destination' => $link['destination'] ?? []
			];
		}
	}

	// 8. Статистика
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

	// 9. Отправляем ответ
	echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode([
		'error' => 'Internal server error',
		'message' => $e->getMessage(),
		'timestamp' => time()
	]);
}
