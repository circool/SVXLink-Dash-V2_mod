<?php

/**
 * @date 2026-01-08
 * @version 0.4.0
 * @filesource /include/exct/left_panel.0.4.0.php
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
 */

$func_start = microtime(true);

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/buildLogicData.0.3.5.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.0.1.2.php';

$ver = "left_panel.php 0.3.5";
if (defined("DEBUG") && DEBUG && function_exists("dlog")) dlog("$ver: Начинаю работу", 3, "WARNING");


// Получение данных
$lp_status = $_SESSION['status'];

// Получаем структурированные данные
$displayData = buildLogicData($lp_status);


// @bookmark Сервис
echo '<div class="mode_flex" id="rptInfoTable">';
echo '<div class="mode_flex row">';
echo '<div class="mode_flex column"> ';
echo '<div class="divTableHead">' . getTranslation('Service ') . '</div>';
echo '</div></div>';

echo '<div class="mode_flex row">';
echo '<div class="mode_flex column">';
echo '<div class="divTableCell white-space:normal">';
echo '<div id="service_' . $displayData['service']['name'] .'" class="' . $displayData['service']['style'] . '">';
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
								. $reflector['style'] .'" style="border: .5px solid #3c3f47;">' 
								. $reflector['shortname'];
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
						echo '<div class="divTableHead">' . getTranslation('Nodes') . ' [' . $reflector['node_count'] . ']</div>';
						
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

				echo '</div> <!-- Конец блока логики -->';
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
