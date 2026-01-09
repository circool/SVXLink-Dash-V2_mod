<?php

/**
 * Строит структурированный массив данных для отображения левой панели
 *
 * @version 0.3.5
 * @filesource /include/fn/buildLogicData.0.3.5.php
 * @since 0.1.21 Первая версия функции
 * @since 0.3.1 
 * 	Добавлено оригинальное (не усеченное) имя логики для правильного наименования блоков left_panel
 *  Добавлена проверка на пустые строки после формирования shortname
 * @since 0.3.5
 *  в массив 'active_module_nodes' добавляется только один узел - самый последний
 *  
 * Обрабатывает сырой статус системы и преобразует его в структурированный массив
 * для удобного отображения в пользовательском интерфейсе. Функция группирует
 * логики, модули, рефлекторы и линки, определяет их состояние и связи между ними.
 * Также обеспечивает корректное формирование коротких имен и проверяет,
 * что после замены исключаемых подстрок строка не становится пустой.
 *
 * @param array $lp_status Статус системы из функции getActualStatus().
 *                         Должен содержать ключи:
 *                         - 'service': информация о сервисе
 *                         - 'logic': массив логик (Simplex/Repeater/Reflector)
 *                         - 'link': массив линков между компонентами
 * 
 * @return array Структурированный массив данных для отображения левой панели со следующими ключами:
 *               - 'service': информация о сервисе (активность, время работы)
 *               - 'logics': массив логик Simplex/Repeater с их модулями, узлами и связанными рефлекторами
 *               - 'unconnected_reflectors': рефлекторы, не связанные с основными логиками
 *               - 'unconnected_links': линки, не связанные с рефлекторами
 * 
 *               Каждая логика содержит:
 *               - 'shortname': краткое имя (имя без суффиксов "Logic", "Reflector", "Link")
 *               - 'name': полное оригинальное имя
 *               - 'style': CSS-класс для стилизации ячейки
 *               - 'has_duration': флаг наличия времени работы
 *               - 'duration': время работы в секундах
 *               - 'tooltip_start/html': HTML для начала тултипа
 *               - 'tooltip_end/html': HTML для конца тултипа
 *               - 'modules': массив модулей логики
 *               - 'module_count': количество модулей
 *               - 'active_module': информация об активном модуле
 *               - 'active_module_nodes': самый поздний узел подключенный к модулю (актуально для EchoLink)
 *               - 'active_module_node_count': количество узлов активного модуля
 *               - 'reflectors': связанные рефлекторы
 *               - 'has_reflectors': флаг наличия связанных рефлекторов
 * 
 *               Рефлекторы содержат:
 *               - 'shortname': краткое имя (гарантированно не пустое)
 *               - 'name': полное имя
 *               - 'style': CSS-класс для стилизации
 *               - 'talkgroups': группы рефлектора
 *               - 'has_talkgroups': флаг наличия групп
 *               - 'nodes': подключенные узлы
 *               - 'node_count': количество узлов
 *               - 'links': связанные линки
 *
 * @throws InvalidArgumentException Если входной массив не содержит обязательных ключей
 * 
 * @note Особенности обработки:
 *       1. Для формирования 'shortname' удаляются подстроки: "Logic", "Reflector", "Link"
 *       2. Если после удаления строка становится пустой или состоит только из пробелов,
 *          используется оригинальное имя
 *       3. Рефлекторы типа "Reflector" не включаются в основной массив логик
 *       4. Линки без связи с рефлекторами попадают в 'unconnected_links'
 *       5. Рефлекторы без связи с логиками попадают в 'unconnected_reflectors'
 *       6. CSS-стили определяются на основе активности и подключения компонентов
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.0.1.2.php';


function buildLogicData(array $lp_status): array
{
	$data = [
		'service' => null,
		'logics' => [],
		'unconnected_reflectors' => [],
		'unconnected_links' => []
	];

	// Извлекаем компоненты статуса
	$lp_service = $lp_status['service'];
	$lp_logics = $lp_status['logic'];
	$lp_links = $lp_status['link'];

	$excl = ["Logic","Reflector","Link"];

	// Функция для получения стиля ячейки (локальная копия)
	$getCellStyle = function ($active, $connected, $type = "logic") {
		if ($type === "logic") {
			return $active
				? ($connected ? "active-mode-cell" : "inactive-mode-cell")
				: ($connected ? "paused-mode-cell" : "disabled-mode-cell");
		}
		return $active
			? ($connected ? "active-mode-cell" : "paused-mode-cell")
			: "disabled-mode-cell";
	};

	// @bookmark 1. Сервис
	$data['service'] = [
		'name' => $lp_service['name'],
		'style' => $lp_service['is_active'] ? 'active-mode-cell' : 'inactive-mode-cell',
		'has_duration' => $lp_service['duration'] > 0,
		'duration' => $lp_service['duration'],
		'tooltip_start' => $lp_service['duration'] > 0 ?
			'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . formatDuration($lp_service['duration']) . '</span>' : '',
		'tooltip_end' => $lp_service['duration'] > 0 ? '</a>' : ''
	];

	// Собираем все рефлекторы для быстрого поиска
	$allReflectors = [];
	$reflectorLinksMap = [];

	foreach ($lp_logics as $logicName => $logic) {
		if ($logic['type'] === "Reflector") {
			$allReflectors[$logicName] = $logic;
		}
	}

	// Собираем связи для рефлекторов
	if (!empty($lp_links)) {
		foreach ($lp_links as $linkName => $link) {
			if (isset($link['destination']['logic']) && isset($allReflectors[$link['destination']['logic']])) {
				$reflectorName = $link['destination']['logic'];
				if (!isset($reflectorLinksMap[$reflectorName])) {
					$reflectorLinksMap[$reflectorName] = [];
				}
				$reflectorLinksMap[$reflectorName][$linkName] = $link;
			}
		}
	}

	// @bookmark 2. Логики (Simplex/Repeater)
	foreach ($lp_logics as $logicName => $logic) {
		// Пропускаем рефлекторы в основном цикле
		if ($logic['type'] === "Reflector") {
			continue;
		}

		$logicClass = $getCellStyle($logic['is_active'], $logic['is_connected'] ?? false, "logic");

		// Модули логики
		$modules = [];
		$activeModule = null;

		if (!empty($logic['module'])) {
			foreach ($logic['module'] as $moduleName => $module) {
				$moduleClass = $getCellStyle($module['is_active'], $module['is_connected'] ?? false, "module");

				$moduleData = [
					'name' => $module['name'],
					'style' => $moduleClass,
					'has_duration' => $module['duration'] > 0,
					'duration' => $module['duration'],
					'is_active' => $module['is_active'],
					'tooltip_start' => $module['duration'] > 0 ?
						'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . formatDuration($module['duration']) . '</span>' : '',
					'tooltip_end' => $module['duration'] > 0 ? '</a>' : ''
				];

				$modules[$moduleName] = $moduleData;

				// Запоминаем активный модуль
				if ($module['is_active'] && !empty($module['connected_nodes'])) {
					$activeModule = [
						'name' => $moduleName,
						'data' => $moduleData,
						'connected_nodes' => $module['connected_nodes']
					];
				}
			}
		}

		// Узлы активного модуля
		$activeModuleNodes = [];
		if ($activeModule && !empty($activeModule['connected_nodes'])) {
			$maxStartTime = 0;
			$nodeWithMaxStart = null;
			$nodeNameWithMaxStart = null;

			// Ищем узел с максимальным временем старта
			foreach ($activeModule['connected_nodes'] as $nodeName => $nodeData) {
				$startTime = $nodeData['start'] ?? 0;

				// Выбираем узел с наибольшим start
				// Если startTime == 0 у всех, выберем последний обработанный узел
				if ($startTime > $maxStartTime || ($startTime == 0 && $maxStartTime == 0 && $nodeWithMaxStart === null)) {
					$maxStartTime = $startTime;
					$nodeNameWithMaxStart = $nodeName;
					$nodeWithMaxStart = $nodeData;
				}
			}

			// Создаем запись только для узла с максимальным start
			if ($nodeNameWithMaxStart !== null && $nodeWithMaxStart !== null) {
				$nodeInfo = [
					'parent' => $activeModule['name'],
					'name' => $nodeNameWithMaxStart,
					'start_time' => $nodeWithMaxStart['start'] ?? 0,
					'type' => $nodeWithMaxStart['type'] ?? '',
					'callsign' => $nodeWithMaxStart['callsign'] ?? '',
					'has_uptime' => !empty($nodeWithMaxStart['start']),
					'uptime' => !empty($nodeWithMaxStart['start']) ? time() - $nodeWithMaxStart['start'] : 0,
					'tooltip_start' => !empty($nodeWithMaxStart['start']) ?
						'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' .
						formatDuration(time() - $nodeWithMaxStart['start']) .
						(!empty($nodeWithMaxStart['type']) ? '<br>' . htmlspecialchars($nodeWithMaxStart['type']) : '') .
						(!empty($nodeWithMaxStart['callsign']) ? ' ' . htmlspecialchars($nodeWithMaxStart['callsign']) : '') .
						'</span>' : '',
					'tooltip_end' => !empty($nodeWithMaxStart['start']) ? '</a>' : '',
					'is_latest_node' => true  // Флаг, что это узел с максимальным start
				];

				$activeModuleNodes[$nodeNameWithMaxStart] = $nodeInfo;
			}
		}

		// Рефлекторы, связанные с этой логикой
		$relatedReflectors = [];

		if (!empty($lp_links)) {
			foreach ($lp_links as $linkName => $link) {
				if (
					isset($link['source']['logic']) &&
					$link['source']['logic'] === $logicName &&
					isset($link['destination']['logic']) &&
					isset($allReflectors[$link['destination']['logic']])
				) {

					$reflectorName = $link['destination']['logic'];

					if (!isset($relatedReflectors[$reflectorName])) {
						$reflector = $allReflectors[$reflectorName];
						$reflectorClass = $getCellStyle($reflector['is_active'], $reflector['is_connected'] ?? false, "logic");

						// TalkGroups рефлектора
						$talkGroups = [];
						$hasTalkGroupsData = false;

						if (isset($reflector['talkgroups']) && is_array($reflector['talkgroups'])) {
							$tg = $reflector['talkgroups'];

							$allGroups = [];
							if (isset($tg['monitoring'])) $allGroups = array_merge($allGroups, $tg['monitoring']);
							
							if (
								isset($tg['selected']) && $tg['selected'] != '0' && $tg['selected'] != '' &&
								(!isset($tg['monitoring']) || !in_array($tg['selected'], $tg['monitoring']))
							) {
								$allGroups[] = $tg['selected'];
							}

							if ( isset($tg['default']) && $tg['default'] != '0' && $tg['default'] != '' ) 
							{
								$default = $tg['default'];
							}

							if (!empty($tg['temp_monitoring'])) $allGroups = array_merge($allGroups, $tg['temp_monitoring']);

							foreach ($allGroups as $group) {
								$groupStyle = 'disabled-mode-cell';
								if (isset($tg['selected']) && $group == $tg['selected']) {
									$groupStyle = 'active-mode-cell';
								} elseif (!empty($tg['temp_monitoring']) && in_array($group, $tg['temp_monitoring'])) {
									$groupStyle = 'paused-mode-cell';
								}

								$talkGroups[] = [
									'name' => $group,
									'style' => $groupStyle,
									'title' => $group,
									'default' =>$default,
								];
							}

							$hasTalkGroupsData = !empty($talkGroups);
						}

						// Узлы рефлектора
						$reflectorNodes = [];
						if (!empty($reflector['connected_nodes'])) {
							foreach ($reflector['connected_nodes'] as $nodeName => $nodeData) {
								$nodeInfo = [
									'name' => $nodeName,
									'timestamp' => $nodeData['timestamp'] ?? 0,
									'has_uptime' => !empty($nodeData['timestamp']),
									'uptime' => !empty($nodeData['timestamp']) ? time() - $nodeData['timestamp'] : 0,
									'tooltip_start' => !empty($nodeData['timestamp']) ?
										'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . formatDuration(time() - $nodeData['timestamp']) . '</span>' : '',
									'tooltip_end' => !empty($nodeData['timestamp']) ? '</a>' : ''
								];

								$reflectorNodes[$nodeName] = $nodeInfo;
							}
						}
						$shortname = trim(str_replace($excl, "", $reflector['name']));
						if ($shortname === '') {
							$shortname = $reflector['name']; 
						}
						$relatedReflectors[$reflectorName] = [
							'shortname' => $shortname,
							'name' => $reflector['name'],
							'style' => $reflectorClass,
							'talkgroups' => $talkGroups,
							'has_talkgroups' => $hasTalkGroupsData,
							'nodes' => $reflectorNodes,
							'node_count' => count($reflectorNodes),
							'links' => [] // Заполним ниже
						];
					}

					// Добавляем линк к рефлектору
					$linkClass = $getCellStyle($link['is_active'], $link['is_connected'] ?? false, "logic");

					// Формируем содержимое tooltip для линка
					$tooltipParts = [];
					if (!empty($link['timeout'])) $tooltipParts[] = 'Timeout: ' . $link['timeout'] . " s.";
					if (!empty($link['source']['announcement_name'])) $tooltipParts[] = 'Source: ' . $link['source']['announcement_name'];
					if (!empty($link['destination']['announcement_name'])) $tooltipParts[] = 'Destination: ' . $link['destination']['announcement_name'];
					if (!empty($link['source']['command']['activate_command'])) $tooltipParts[] = 'Activate: ' . $link['source']['command']['activate_command'];
					if (!empty($link['source']['command']['deactivate_command'])) $tooltipParts[] = 'Deactivate: ' . $link['source']['command']['deactivate_command'];

					$hasTooltip = !empty($tooltipParts) || $link['duration'] > 0;
					$shortname = trim(str_replace($excl, '', $linkName));
					if ($shortname === '') {
						$shortname = $linkName;
					}
					$relatedReflectors[$reflectorName]['links'][$linkName] = [
						'shortname' => $shortname,
						'name' => $linkName,
						'style' => $linkClass,
						'has_duration' => $link['duration'] > 0,
						'duration' => $link['duration'],
						'has_tooltip' => $hasTooltip,
						'tooltip_parts' => $tooltipParts,
						'tooltip_start' => $hasTooltip ?
							'<a class="tooltip" href="#"><span>' .
							($link['duration'] > 0 ? '<b>' . getTranslation('Uptime') . ':</b>' . formatDuration($link['duration']) . '<br>' : '') .
							implode(' | ', $tooltipParts) . '</span>' : '',
						'tooltip_end' => $hasTooltip ? '</a>' : ''
					];
				}
			}
		}

		// Формируем данные логики
		$shortname = trim(str_replace($excl, "", $logic['name']));
		if ($shortname === '') {
			$shortname = $logic['name'];
		}
		$data['logics'][$logicName] = [
			'shortname' => $shortname,
			'name' => $logic['name'],
			'style' => $logicClass,
			'has_duration' => $logic['duration'] > 0,
			'duration' => $logic['duration'],
			'tooltip_start' => $logic['duration'] > 0 ?
				'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . formatDuration($logic['duration']) . '</span>' : '',
			'tooltip_end' => $logic['duration'] > 0 ? '</a>' : '',
			'modules' => $modules,
			'module_count' => count($modules),
			'active_module' => $activeModule,
			'active_module_nodes' => $activeModuleNodes,
			'active_module_node_count' => count($activeModuleNodes),
			'reflectors' => $relatedReflectors,
			'has_reflectors' => !empty($relatedReflectors)
		];
	}

	// @bookmark 3. Несвязанные рефлекторы (без привязки к логикам)
	foreach ($allReflectors as $reflectorName => $reflector) {
		$hasLink = false;

		// Проверяем, есть ли линки к этому рефлектору
		if (isset($reflectorLinksMap[$reflectorName])) {
			foreach ($reflectorLinksMap[$reflectorName] as $link) {
				if (isset($link['source']['logic']) && isset($lp_logics[$link['source']['logic']])) {
					$hasLink = true;
					break;
				}
			}
		}

		if (!$hasLink) {
			$reflectorClass = $getCellStyle($reflector['is_active'], $reflector['is_connected'] ?? false, "logic");

			// TalkGroups рефлектора
			$talkGroups = [];
			$hasTalkGroupsData = false;

			if (isset($reflector['talkgroups']) && is_array($reflector['talkgroups'])) {
				$tg = $reflector['talkgroups'];

				$allGroups = [];
				if (isset($tg['monitoring'])) $allGroups = array_merge($allGroups, $tg['monitoring']);
				if (
					isset($tg['selected']) && $tg['selected'] != '0' && $tg['selected'] != '' &&
					(!isset($tg['monitoring']) || !in_array($tg['selected'], $tg['monitoring']))
				) {
					$allGroups[] = $tg['selected'];
				}
				if (!empty($tg['temp_monitoring'])) $allGroups = array_merge($allGroups, $tg['temp_monitoring']);

				foreach ($allGroups as $group) {
					$groupStyle = 'disabled-mode-cell';
					if (isset($tg['selected']) && $group == $tg['selected']) {
						$groupStyle = 'active-mode-cell';
					} elseif (!empty($tg['temp_monitoring']) && in_array($group, $tg['temp_monitoring'])) {
						$groupStyle = 'paused-mode-cell';
					}

					$talkGroups[] = [
						'name' => $group,
						'style' => $groupStyle,
						'title' => $group
					];
				}

				$hasTalkGroupsData = !empty($talkGroups);
			}

			// Узлы рефлектора
			$reflectorNodes = [];
			if (!empty($reflector['connected_nodes'])) {
				foreach ($reflector['connected_nodes'] as $nodeName => $nodeData) {
					$nodeInfo = [
						'name' => $nodeName,
						'timestamp' => $nodeData['timestamp'] ?? 0,
						'has_uptime' => !empty($nodeData['timestamp']),
						'uptime' => !empty($nodeData['timestamp']) ? time() - $nodeData['timestamp'] : 0,
						'tooltip_start' => !empty($nodeData['timestamp']) ?
							'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . formatDuration(time() - $nodeData['timestamp']) . '</span>' : '',
						'tooltip_end' => !empty($nodeData['timestamp']) ? '</a>' : ''
					];

					$reflectorNodes[$nodeName] = $nodeInfo;
				}
			}

			// Линки рефлектора
			$reflectorLinks = [];
			if (isset($reflectorLinksMap[$reflectorName])) {
				foreach ($reflectorLinksMap[$reflectorName] as $linkName => $link) {
					$linkClass = $getCellStyle($link['is_active'], $link['is_connected'] ?? false, "logic");

					// Формируем содержимое tooltip для линка
					$tooltipParts = [];
					if (!empty($link['timeout'])) $tooltipParts[] = 'Timeout: ' . $link['timeout'] . " s.";
					if (!empty($link['source']['announcement_name'])) $tooltipParts[] = 'Source: ' . $link['source']['announcement_name'];
					if (!empty($link['destination']['announcement_name'])) $tooltipParts[] = 'Destination: ' . $link['destination']['announcement_name'];
					if (!empty($link['source']['command']['activate_command'])) $tooltipParts[] = 'Activate: ' . $link['source']['command']['activate_command'];
					if (!empty($link['source']['command']['deactivate_command'])) $tooltipParts[] = 'Deactivate: ' . $link['source']['command']['deactivate_command'];

					$hasTooltip = !empty($tooltipParts) || $link['duration'] > 0;
					
					$shortname = trim(str_replace($excl, '', $linkName));
					if ($shortname === '') {
						$shortname = $linkName;
					}

					$reflectorLinks[$linkName] = [
						'shortname' => $shortname,
						'name' => $linkName,
						'style' => $linkClass,
						'has_duration' => $link['duration'] > 0,
						'duration' => $link['duration'],
						'has_tooltip' => $hasTooltip,
						'tooltip_parts' => $tooltipParts,
						'tooltip_start' => $hasTooltip ?
							'<a class="tooltip" href="#"><span>' .
							($link['duration'] > 0 ? '<b>' . getTranslation('Uptime') . ':</b>' . formatDuration($link['duration']) . '<br>' : '') .
							implode(' | ', $tooltipParts) . '</span>' : '',
						'tooltip_end' => $hasTooltip ? '</a>' : ''
					];
				}
			}

			$shortname = trim(str_replace($excl, "", $reflector['name']));
			if ($shortname === '') {
				$shortname = $reflector['name']; // или значение по умолчанию
			}

			$data['unconnected_reflectors'][$reflectorName] = [
				'shortname' => $shortname,
				'name' => $reflector['name'],
				'style' => $reflectorClass,
				'talkgroups' => $talkGroups,
				'has_talkgroups' => $hasTalkGroupsData,
				'nodes' => $reflectorNodes,
				'node_count' => count($reflectorNodes),
				'links' => $reflectorLinks
			];
		}
	}

	// 4. Линки без рефлекторов
	if (!empty($lp_links)) {
		foreach ($lp_links as $linkName => $link) {
			// Проверяем, связан ли линк с рефлектором
			$isReflectorLink = false;
			if (isset($link['destination']['logic']) && isset($allReflectors[$link['destination']['logic']])) {
				$isReflectorLink = true;
			}

			if (!$isReflectorLink) {
				$linkClass = $getCellStyle($link['is_active'], $link['is_connected'] ?? false, "module");

				// Формируем содержимое tooltip для линка
				$tooltipParts = [];
				if (!empty($link['source']['announcement_name'])) $tooltipParts[] = 'Source: ' . $link['source']['announcement_name'];
				if (!empty($link['destination']['announcement_name'])) $tooltipParts[] = 'Destination: ' . $link['destination']['announcement_name'];
				if (!empty($link['source']['command']['activate_command'])) $tooltipParts[] = 'Activate: ' . $link['source']['command']['activate_command'];
				if (!empty($link['source']['command']['deactivate_command'])) $tooltipParts[] = 'Deactivate: ' . $link['source']['command']['deactivate_command'];

				$hasTooltip = !empty($tooltipParts) || $link['duration'] > 0;

				$shortname = trim(str_replace($excl, '', $linkName));
				if ($shortname === '') {
					$shortname = $linkName; 
				}
				$data['unconnected_links'][$linkName] = [
					'shortname' => $shortname,
					'name' => $linkName,
					'style' => $linkClass,
					'has_duration' => $link['duration'] > 0,
					'duration' => $link['duration'],
					'has_tooltip' => $hasTooltip,
					'tooltip_parts' => $tooltipParts,
					'tooltip_start' => $hasTooltip ?
						'<a class="tooltip" href="#"><span>' .
						($link['duration'] > 0 ? '<b>' . getTranslation('Uptime') . ':</b>' . formatDuration($link['duration']) . '<br>' : '') .
						implode(' | ', $tooltipParts) . '</span>' : '',
					'tooltip_end' => $hasTooltip ? '</a>' : ''
				];
			}
		}
	}

	return $data;
}
?>