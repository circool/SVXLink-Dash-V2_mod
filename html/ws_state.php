<?php

/**
 * WebSocket State Provider - ВЕРСИЯ 1.4
 * С учетом реальной структуры connected_nodes
 */

// 1. Заголовки
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 2. Разрешаем только localhost
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
	http_response_code(403);
	echo json_encode(['error' => 'Access denied. Only localhost allowed.']);
	exit;
}

// 3. Начинаем сессию
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/session_header.php';

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
			'module_logic' => []
		]
	];

	// 5. Обработка сервиса
	if (isset($status['service']) && is_array($status['service'])) {
		if (($status['service']['is_active'] ?? false) && isset($status['service']['start'])) {
			$response['data']['service'] = [
				'start' => (int)$status['service']['start'],
				'name' => $status['service']['name'] ?? 'SvxLink'
			];
		}
	}

	// 6. Обработка логик и устройств
	if (isset($status['logic']) && is_array($status['logic'])) {
		foreach ($status['logic'] as $logicName => $logic) {
			if (!is_array($logic)) continue;

			$logicStart = isset($logic['start']) ? (int)$logic['start'] : 0;

			// Устройства RX/TX
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

			// Модули
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

					// Узлы модуля (могут быть в другом формате)
					if (isset($module['connected_nodes']) && is_array($module['connected_nodes'])) {
						foreach ($module['connected_nodes'] as $nodeName => $nodeData) {
							$nodeKey = 'logic_' . $logicName . '_node_' . $nodeName;

							// Формат 1: массив с ключами callsign и timestamp
							if (is_array($nodeData) && isset($nodeData['timestamp'])) {
								$response['data']['nodes'][$nodeKey] = [
									'start' => (int)$nodeData['timestamp'],
									'logic' => $logicName,
									'module' => $moduleName,
									'node' => $nodeName,
									'callsign' => $nodeData['callsign'] ?? $nodeName,
									'type' => 'module_node'
								];
							}
							// Формат 2: просто timestamp
							else if (is_numeric($nodeData)) {
								$response['data']['nodes'][$nodeKey] = [
									'start' => (int)$nodeData,
									'logic' => $logicName,
									'module' => $moduleName,
									'node' => $nodeName,
									'type' => 'module_node'
								];
							}
							// Формат 3: массив с start
							else if (is_array($nodeData) && isset($nodeData['start'])) {
								$response['data']['nodes'][$nodeKey] = [
									'start' => (int)$nodeData['start'],
									'logic' => $logicName,
									'module' => $moduleName,
									'node' => $nodeName,
									'type' => 'module_node'
								];
							}
						}
					}
				}
			}

			// Узлы рефлектора (теперь с правильной структурой!)
			if (($logic['type'] ?? '') === 'Reflector') {
				if (isset($logic['connected_nodes']) && is_array($logic['connected_nodes'])) {
					foreach ($logic['connected_nodes'] as $nodeName => $nodeData) {
						$nodeKey = 'logic_' . $logicName . '_node_' . $nodeName;

						// РЕАЛЬНАЯ СТРУКТУРА: массив с callsign и timestamp
						if (is_array($nodeData) && isset($nodeData['timestamp'])) {
							$response['data']['nodes'][$nodeKey] = [
								'start' => (int)$nodeData['timestamp'],
								'logic' => $logicName,
								'node' => $nodeName,
								'callsign' => $nodeData['callsign'] ?? $nodeName,
								'type' => 'reflector_node'
							];
						}
						// Резервный вариант
						else {
							$response['data']['nodes'][$nodeKey] = [
								'start' => $logicStart,
								'logic' => $logicName,
								'node' => $nodeName,
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

			$response['data']['links'][$linkName] = [
				'start' => isset($link['start']) ? (int)$link['start'] : 0,
				'is_active' => $link['is_active'] ?? false
			];
		}
	}

	// 8. Статистика (для отладки WebSocket сервера)
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
