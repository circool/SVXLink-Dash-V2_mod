<?php

/**
 * @date 2026-01-08
 * @version 0.4.3
 * @filesource /include/exct/left_panel.0.4.3.php
 * @description Панель состояний сервиса,логики,модулей,линков
 * @since 0.2.1
 * Адаптировано под новый подход к версионированию и порядку включения зависимостей
 * @since 0.3.1
 * Установлены id блоков (кроме не связанных):
 * 	Для сервиса - service[имя сервиса]
 * 	Для логик (не рефлекторов) - logic[имя логики]
 * 	Для рефлекторов - reflector[имя логики]
 * 	Для линков - link[имя линка]
 * 	Для модулей логики - module[имя логики][имя модуля]
 * 	Для таблицы узлов активного модуля - module[имя модуля]NodesTableBody
 * 	Для таблицы узлов рефлектора - reflector[имя рефлектора]NodesTableBody
 * 	Для таблицы разговорных групп рефлектора - reflector[имя рефлектора]TalkGroupsTableBody
 * @since 0.3.2
 *  Отказ от показа нескольких последних подключенных узлов модуля - теперь отображается один - который подключен позже остальных
 *  Переработан принцип отображения всего блока - используется класс hidden
 * @since 0.3.5
 *  Версия изменена с 0.3.2 для визуальной связанности с сервером и клиентом WS 
 *  Используется buildLogicData.0.3.5.php с измененной логикой заполнения массива 'connected_nodes'
 * @since 0.4.0
 *  Переработан порядок именования элементов DOM
 * @since 0.4.3
 *  Изменен стиль не подключенных линков (на disabled-mode-cell)
 */

$func_start = microtime(true);

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
// require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/buildLogicData.0.3.5.php';
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

	$excl = ["Logic", "Reflector", "Link"];

	// Функция для получения стиля ячейки
	$getCellStyle = function ($active, $connected, $hasConnected=false) {
		if ($hasConnected) {
			return $active
				? ($connected ? "active-mode-cell" : "inactive-mode-cell")
				: ($connected ? "paused-mode-cell" : "disabled-mode-cell");
		}
		return $active
			? ($connected ? "active-mode-cell" : "paused-mode-cell")
			: "disabled-mode-cell";
	};

	// @bookmark 1. Сервис
	$durationHtml = $lp_service['duration'] > 0 ? formatDuration($lp_service['duration']) : '';
	$data['service'] = [
		'name' => $lp_service['name'],
		'style' => $lp_service['is_active'] ? 'active-mode-cell' : 'inactive-mode-cell',
		'has_duration' => $lp_service['duration'] > 0,
		'duration' => $lp_service['duration'],
		'tooltip_start' => '<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br></span>',
		'tooltip_end' => '</a>'
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

		$logicClass = $getCellStyle($logic['is_connected'], $logic['is_active'],  true); //@since 0.4
		
		// Модули логики
		$modules = [];
		$activeModule = null;

		if (!empty($logic['module'])) {
			foreach ($logic['module'] as $moduleName => $module) {
				$moduleClass = $getCellStyle($module['is_active'], $module['is_connected'], true);
				$durationHtml = $module['duration'] > 0 ? formatDuration($module['duration']) : '';
				$moduleData = [
					'name' => $module['name'],
					'style' => $moduleClass,
					'has_duration' => $module['duration'] > 0,
					'duration' => $module['duration'],
					'is_active' => $module['is_active'],
					'tooltip_start' => $module['duration'] > 0 ?
						'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br></span>' : '',
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
		// @todo Вернуть все узлы, а не только последний подключеннный
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
						$reflectorClass = $getCellStyle($reflector['is_active'], $reflector['is_connected'], true);

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

							if (isset($tg['default']) && $tg['default'] != '0' && $tg['default'] != '') {
								$default = $tg['default'];
							}

							if (!empty($tg['temp_monitoring'])) $allGroups = array_merge($allGroups, $tg['temp_monitoring']);

							foreach ($allGroups as $group) {
								$groupStyle = 'disabled-mode-cell';

								// Проверяем по приоритету:
								// 1. Выбранная группа (selected) - самая высокая приоритетность
								if (isset($tg['selected']) && $group == $tg['selected']) {
									$groupStyle = 'active-mode-cell';
								}
								// 2. Временно мониторируемая (temp_monitoring)
								elseif (!empty($tg['temp_monitoring']) && in_array($group, $tg['temp_monitoring'])) {
									$groupStyle = 'paused-mode-cell';
								}

								// 3. Постоянно мониторируемая (monitoring)
								if (isset($tg['monitoring']) && in_array($group, $tg['monitoring'])) {
									$groupStyle .= ' monitored';
								}
								// 4. Группа по умолчанию (default) - если не является selected/monitoring
								if (isset($tg['default']) && $group == $tg['default']) {
									$groupStyle .= ' default'; // или оставить disabled-mode-cell
								}


								$talkGroups[] = [
									'name' => $group,
									'style' => $groupStyle,
									'title' => $group,
									'default' => $default,
									'is_monitored' => isset($tg['monitoring']) && in_array($group, $tg['monitoring']), // Добавляем флаг
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
									'start' => $nodeData['start'] ?? 0,
									'has_uptime' => !empty($nodeData['start']),
									'uptime' => !empty($nodeData['start']) ? time() - $nodeData['start'] : 0,
									'tooltip_start' => !empty($nodeData['start']) ?
										'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . formatDuration(time() - $nodeData['start']) . '<br></span>' : '',
									'tooltip_end' => !empty($nodeData['start']) ? '</a>' : ''
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
							'links' => [] 
						];
					}

					// Добавляем линк к рефлектору
					$linkClass = $getCellStyle($link['is_connected'],$link['is_active'],  false);

					// Формируем содержимое tooltip для линка
					$tooltipParts = [];
					$durationHtml = $link['duration'] > 0 ? $link['duration'] : '';
					
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
						'tooltip_start' => '<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br>'.
							implode(' | ', $tooltipParts) . '</span>',
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
		$durationHtml = $logic['duration'] > 0 ? $logic['duration']:'';
		$data['logics'][$logicName] = [
			'shortname' => $shortname,
			'name' => $logic['name'],
			'style' => $logicClass,
			'has_duration' => $logic['duration'] > 0,
			'duration' => $logic['duration'],
			'tooltip_start' => '<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br></span>',
			'tooltip_end' => '</a>',
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
			$reflectorClass = $getCellStyle($reflector['is_active'], $reflector['is_connected'] ?? false, true);

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
						'start' => $nodeData['start'] ?? 0,
						'has_uptime' => !empty($nodeData['start']),
						'uptime' => !empty($nodeData['start']) ? time() - $nodeData['start'] : 0,
						'tooltip_start' => !empty($nodeData['start']) ?
							'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . formatDuration(time() - $nodeData['start']) . '<br></span>' : '',
						'tooltip_end' => !empty($nodeData['start']) ? '</a>' : ''
					];

					$reflectorNodes[$nodeName] = $nodeInfo;
				}
			}

			// Линки рефлектора
			$reflectorLinks = [];
			if (isset($reflectorLinksMap[$reflectorName])) {
				foreach ($reflectorLinksMap[$reflectorName] as $linkName => $link) {
					$linkClass = $getCellStyle($link['is_active'], $link['is_connected'] ?? false, true);

					// Формируем содержимое tooltip для линка
					$tooltipParts = [];
					if (!empty($link['timeout'])) $tooltipParts[] = 'Timeout: ' . $link['timeout'] . " s.";
					if (!empty($link['source']['announcement_name'])) $tooltipParts[] = 'Source: ' . $link['source']['announcement_name'];
					if (!empty($link['destination']['announcement_name'])) $tooltipParts[] = 'Destination: ' . $link['destination']['announcement_name'];
					if (!empty($link['source']['command']['activate_command'])) $tooltipParts[] = 'Activate: ' . $link['source']['command']['activate_command'];
					if (!empty($link['source']['command']['deactivate_command'])) $tooltipParts[] = 'Deactivate: ' . $link['source']['command']['deactivate_command'];

					// $hasTooltip = !empty($tooltipParts) || $link['duration'] > 0;

					$shortname = trim(str_replace($excl, '', $linkName));
					if ($shortname === '') {
						$shortname = $linkName;
					}
					
					$durationHtml = $link['duration']>0 ? $link['duration']: '';
					
					$reflectorLinks[$linkName] = [
						'shortname' => $shortname,
						'name' => $linkName,
						'style' => $linkClass,
						// 'has_duration' => $link['duration'] > 0,
						// 'duration' => $link['duration'],
						// 'has_tooltip' => $hasTooltip,
						// 'tooltip_parts' => $tooltipParts,
						'tooltip_start' => '<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br>' .
							implode(' | ', $tooltipParts) . '</span>',
						'tooltip_end' => '</a>'
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
				$linkClass = $getCellStyle($link['is_active'], $link['is_connected'] ?? false, true);

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
				$durationHtml = $link['duration'] > 0 ? formatDuration($link['duration']) : '';
				$data['unconnected_links'][$linkName] = [
					'shortname' => $shortname,
					'name' => $linkName,
					'style' => $linkClass,
					'has_duration' => $link['duration'] > 0,
					'duration' => $link['duration'],
					'has_tooltip' => $hasTooltip,
					'tooltip_parts' => $tooltipParts,
					'tooltip_start' => '<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br>' .
						implode(' | ', $tooltipParts) . '</span>',
					'tooltip_end' => '</a>'
				];
			}
		}
	}

	return $data;
}


$ver = "left_panel.php 0.4.0";
if (defined("DEBUG") && DEBUG && function_exists("dlog")) dlog("$ver: Начинаю работу", 3, "WARNING");


// Получение данных
$lp_status = $_SESSION['status'];

// Получаем структурированные данные
$displayData = buildLogicData($lp_status);


// @bookmark Сервис
echo '<div class="mode_flex">';
echo '<div class="mode_flex row">';
echo '<div class="mode_flex column"> ';
echo '<div class="divTableHead">' . getTranslation('Service') . '</div>';
echo '</div></div>';

echo '<div class="mode_flex row">';
echo '<div class="mode_flex column">';
echo '<div class="divTableCell white-space:normal">';
echo '<div id="service" class="' . $displayData['service']['style'] . '">';
echo $displayData['service']['tooltip_start'] . $displayData['service']['name'] . $displayData['service']['tooltip_end'];
echo '</div></div></div></div></div><br>';

if (!empty($displayData['logics'])){
	foreach ($displayData['logics'] as $logic){

		// Начало блока логики 
		echo '<div class="mode_flex">';
		// @bookmark Логика 
		echo '<div class="mode_flex column"><div class="divTableCell">';
		echo '<div id="logic_' . $logic['name'] . '" class="' . $logic['style'] . '" title="">'. $logic['tooltip_start'] . $logic['name'] . $logic['tooltip_end'] . '</div>';
		echo '</div></div>';			
				
		if (!empty($logic['modules'])) {
			// @bookmark Модули шапка
			echo '<div id= "logic_' . $logic['name'] . '_modules_header" class="mode_flex row"><div class="mode_flex column"><div class="divTableHead">';
			echo getTranslation('Modules');		
			echo $logic['module_count'] > 1 ? ' [' . $logic['module_count'] . ']' : '' ;
			echo '</div></div></div>';			

			// @bookmark Модули тело
			$moduleIndex = 0;
			$moduleCount = $logic['module_count'];
			foreach ($logic['modules'] as $module){
				// Начало новой строки каждые 2 модуля
				if ($moduleIndex % 2 == 0){
					echo '<div class="mode_flex row">';
				}

				echo '<div class="mode_flex column"><div class="divTableCell">';
				echo '<div id="logic_' . $logic['name'] . '_module_' . $module['name'] . '" class="' . $module['style'] . '" title="">';
				echo $module['tooltip_start'] . $module['name'] . $module['tooltip_end'];
				echo '</div></div></div>';

				// Конец строки
				if ($moduleIndex % 2 == 1 || $moduleIndex == $moduleCount - 1){
					echo '</div>';
				}
					
				$moduleIndex++;
			}
		}

			// @bookmark Активный модуль и его узлы -->

			if (!empty($logic['active_module_nodes'])) {
				$node = current($logic['active_module_nodes']);
				$nodesTableHeader = $node['parent'];
			} else {
				$nodesTableStyle = 'hidden';
				$node = [
					'name' => '',
					'tooltip_start' => '',
					'tooltip_end' => '',
				];
				$nodesTableHeader = '';
			}

			// @bookmark Всегда создаем пустой каркас для подключенных узлов но скрываем его если нет подключенных узлов
			echo '<div id="logic_' . $logic['name'] . '_active" class="divTable ' . $nodesTableStyle . '">';
			echo '<div id="logic_' . $logic['name'] . '_active_header" class="divTableHead">' . $nodesTableHeader . '</div>';
			echo '<div class="divTableBody"><div class="mode_flex"><div class="mode_flex row">';
			echo '<div id="logic_' . $logic['name'] . '_active_content" class="mode_flex column disabled-mode-cell" style="border: .5px solid #3c3f47;">';
			echo $node['tooltip_start'] . $node['name'] . $node['tooltip_end'];
			echo '</div></div></div></div></div>';

			// @bookmark Рефлекторы связанные с этой логикой
			if ($logic['has_reflectors']) {
				foreach ($logic['reflectors'] as $reflector) {
					
					
					// @bookmark Рефлектор блок
					echo '<div class="divTable">';
					echo '<div class="divTableHead" style="background: none; border: none"></div>';
					echo '<div class="divTableBody">';
					echo '<div class="divTableRow center">';
					echo '<div class="divTableHeadCell">' . getTranslation("Reflector") . '</div>';
					echo '<div id="logic_'  . $reflector['name']  . '" class="divTableCell cell_content middle '
								. $reflector['style'] .'" style="border: .5px solid #3c3f47;">';
					echo $reflector['tooltip_start'] . $reflector['shortname'] . $reflector['tooltip_end'];
					echo '</div></div>';
					
					// @bookmark Линки рефлектора
					if (!empty($reflector['links'])){
						foreach ($reflector['links'] as $link) {
							echo '<div id="logic_' . $reflector['name']  .'_links" class="divTableRow center">';
							echo '<div class="divTableHeadCell">' . getTranslation('Link') . '</div>';
							echo '<div id="link_' . $link['name'] . '" class="' . $reflector['name']  . ' divTableCell cell_content middle ' . $link['style'] . '" style="border: .5px solid #3c3f47;">';
							echo $link['tooltip_start'] . $link['shortname'] . $link['tooltip_end'];
							echo '</div></div>';
						}
					}
					echo '</div></div>';

					// @bookmark TalkGroups рефлектора 
					if ($reflector['has_talkgroups']){
						echo '<div class="divTable">';
						echo '<div class="divTableHead">' . getTranslation('Talk Groups') .'</div>';
						echo '<div class="divTableBody">';
						echo '<div id="logic_' . $reflector['name'] . '_groups" class="mode_flex row">';
							
						$tgIndex = 0;
						$tgCount = count($reflector['talkgroups']);
						foreach ($reflector['talkgroups'] as $group) {
							echo '<div id="logic_' . $reflector['name'] . '_group_' . $group['name'] .
							'" class="mode_flex column ' . $group['style'] .
								'" title="' . $group['title'] .
								'" style="border: .5px solid #3c3f47;">' .
								$group['name'] .
								'</div>';
						}
						echo '</div></div></div>';
					};

					// @bookmark Узлы рефлектора 
					if (!empty($reflector['nodes'])) {
						echo '<div class="divTable">';
						echo '<div id="logic_' . $reflector['name'] . '_nodes_header" class="divTableHead">' . getTranslation('Nodes') . ' [' . $reflector['node_count'] . ']</div>';
						
						echo '<div id = "logic_' . $reflector['name'] .'_nodes" class = "divTableBody mode-flex row" style="white-space: nowrap;">';
						foreach ($reflector['nodes'] as $node) {
							echo '<div id="logic_' . $reflector['name'] . '_node_' . $node['name'] . '" 
							class="mode_flex column disabled-mode-cell" 
							title="' . $node['name'] . '"' .
							'style="border: .5px solid #3c3f47;">' .
							$node['tooltip_start'] . $node['name'] . $node['tooltip_end'] .
							'</div>';
						} 
						echo '</div></div>';
					}
				}
				echo '<br>';

				echo '</div>';
			}
		
	}; 

}; 

unset(
	$displayData,
	$logic,
	$lp_status,
	$linkIndex,
	$linkCount,
	$link,
	$nodeCount,
	$nodeIndex,
	$node,
	$reflector,
	$tgIndex,
	$tgCount,
	$group,
	$moduleIndex,
	$moduleCount,
	$module
);

if (defined("DEBUG") && DEBUG && function_exists("dlog")) {
	$func_time = microtime(true) - $func_start;
	dlog("$ver: Закончил работу за $func_time msec", 3, "WARNING");
};
