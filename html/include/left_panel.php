<?php

/**
 * @author vladimir@tsurkanenko.ru
 * @date 2026-01-26
 * @version 0.4.4.release
 * @filesource /include/left_panel.php
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/formatDuration.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getActualStatus.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/include/session_header.php';

$activeCellClass = ' active-mode-cell';
$pausedCellClass = ' paused-mode-cell';
$inactiveCellClass = ' inactive-mode-cell';
$disabledCellClass = ' disabled-mode-cell';

function buildLogicData(array $lp_status): array
{
	$data = [
		'service' => null,
		'logics' => [],
		'unconnected_reflectors' => [],
		'unconnected_links' => []
	];


	$lp_service = $lp_status['service'];
	$lp_logics = $lp_status['logic'];
	$lp_links = $lp_status['link'];

	$excl = ["Logic", "Reflector", "Link"];

	$activeCellClass = $lp_service['is_active'] ? ' active-mode-cell' : ' disabled-mode-cell';
	$inactiveCellClass = $lp_service['is_active'] ? ' inactive-mode-cell' : ' disabled-mode-cell';
	$pausedCellClass = $lp_service['is_active'] ? ' paused-mode-cell' : ' disabled-mode-cell';
	$disabledCellClass = ' disabled-mode-cell';

	$getCellStyle = function ($active, $connected, $hasConnected = false) {
		if ($hasConnected) {

			if ($active) {
				return $connected ? "active-mode-cell" : "paused-mode-cell";
			} else {

				return "disabled-mode-cell";
			}
		} else {

			return $active ? "active-mode-cell" : "disabled-mode-cell";
		}
	};

	// @bookmark Service
	$durationHtml = formatDuration($lp_service['start'] > 0 && $lp_service['is_active'] ? time() - $lp_service['start'] : '0');
	$data['service'] = [
		'is_active' => $lp_service['is_active'],
		'name' => $lp_service['name'],
		'style' => $lp_service['is_active'] ? $activeCellClass : ' inactive-mode-cell',
		'tooltip_start' => '<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br></span>',
		'tooltip_end' => '</a>'
	];

	if (isset($lp_service['aprs_server'])) $data['aprs_server'] = $lp_service['aprs_server'];
	if (isset($lp_service['status_server'])) $data['status_server'] = $lp_service['status_server'];
	if (isset($lp_service['directory_server'])) $data['directory_server'] = $lp_service['directory_server'];
	if (isset($lp_service['proxy_server'])) $data['proxy_server'] = $lp_service['proxy_server'];

	$allReflectors = [];
	$reflectorLinksMap = [];

	foreach ($lp_logics as $logicName => $logic) {
		if ($logic['type'] === "Reflector") {
			$allReflectors[$logicName] = $logic;
		}
	}

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

	// @bookmark Logics (Simplex/Repeater)
	foreach ($lp_logics as $logicName => $logic) {
		if ($logic['type'] === "Reflector") {
			continue;
		}

		$logicClass = $getCellStyle($logic['is_connected'], $logic['is_active'],  true);

		$modules = [];
		$activeModule = null;

		if (!empty($logic['module'])) {
			foreach ($logic['module'] as $moduleName => $module) {
				$moduleCanConnected = $module['name'] == "EchoLink" || $module['name'] == "Frn";
				$moduleClass = $getCellStyle($module['is_active'], $module['is_connected'], $moduleCanConnected);
				$durationHtml = formatDuration($module['start'] > 0 ? time() - $module['start'] : 0);
				$command = '';
				if(!($module['is_connected'] || $module['is_active'])){
					$command = isset($module['id']) && $module['id'] !== '' ? $module['id'] : '';
				}
				$command = $command . '#';
				$moduleData = [
					'name' => $module['name'],
					'style' => $moduleClass,
					'tooltip_start' => '<a class="tooltip" href="javascript:void(0)"' .						
						' onclick="sendLinkCommand(\'' . htmlspecialchars($command, ENT_QUOTES) . '\', \'' .
						htmlspecialchars($logicName, ENT_QUOTES) . '\')">' .
						
							'<span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br>' .
							getTranslation('Click to toggle') . '</span>' ,
					'tooltip_end' => '</a>'
				];

				$modules[$moduleName] = $moduleData;

				if ($module['is_active'] && !empty($module['connected_nodes'])) {
					$activeModule = [
						'name' => $moduleName,
						'data' => $moduleData,
						'connected_nodes' => $module['connected_nodes']
					];
				}
			}
		}

		$activeModuleNodes = [];
		if ($activeModule && !empty($activeModule['connected_nodes'])) {
			foreach ($activeModule['connected_nodes'] as $nodeName => $nodeData) {
				$durationHtml = formatDuration(!empty($nodeData['start']) ? time() - $nodeData['start'] : 0);

				$nodeInfo = [
					'parent' => $activeModule['name'],
					'name' => $nodeName,
					'type' => $nodeData['type'] ?? '',
					'callsign' => $nodeData['callsign'] ?? '',
					'tooltip_start' => !empty($nodeData['start']) ?
						'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' .
						$durationHtml .
						(!empty($nodeData['type']) ? '<br>' . htmlspecialchars($nodeData['type']) : '') .
						(!empty($nodeData['callsign']) ? ' ' . htmlspecialchars($nodeData['callsign']) : '') .
						'</span>' : '',
					'tooltip_end' => !empty($nodeData['start']) ? '</a>' : '',
				];
				$activeModuleNodes[$nodeName] = $nodeInfo;
			}
		}

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

						$reflectorClass = $getCellStyle($reflector['is_connected'], $reflector['is_active'], true);

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
								$groupStyle = $disabledCellClass;

								if (isset($tg['selected']) && $group == $tg['selected']) {
									$groupStyle = $activeCellClass;
								} elseif (!empty($tg['temp_monitoring']) && in_array($group, $tg['temp_monitoring'])) {
									$groupStyle = $pausedCellClass;
								}



								if (isset($tg['monitoring']) && in_array($group, $tg['monitoring'])) {
									$groupStyle .= ' monitored';
								}

								if (isset($tg['default']) && $group == $tg['default']) {
									$groupStyle .= ' default';
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

						$reflectorNodes = [];
						if (!empty($reflector['connected_nodes'])) {
							foreach ($reflector['connected_nodes'] as $nodeName => $nodeData) {
								$durationHtml = formatDuration(empty($nodeData['start']) ? 0 : time() - $nodeData['start']);
								$nodeInfo = [
									'name' => $nodeName,
									'tooltip_start' => !empty($nodeData['start']) ?
										'<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br></span>' : '',
									'tooltip_end' => !empty($nodeData['start']) ? '</a>' : ''
								];

								$reflectorNodes[$nodeName] = $nodeInfo;
							}
						}
						$shortname = trim(str_replace($excl, "", $reflector['name']));
						if ($shortname === '') {
							$shortname = $reflector['name'];
						}
						$durationHtml = formatDuration($logic['start'] > 0 ? time() - $logic['start'] : 0);
						$relatedReflectors[$reflectorName] = [
							'shortname' => $shortname,
							'name' => $reflector['name'],
							'style' => $reflectorClass,
							'talkgroups' => $talkGroups,
							'has_talkgroups' => $hasTalkGroupsData,
							'nodes' => $reflectorNodes,
							'node_count' => count($reflectorNodes),
							'links' => [],
							'tooltip_start' => '<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br></span>',
							'tooltip_end' => '</a>'
						];
					}

					$linkClass = $getCellStyle($link['is_connected'], $link['is_active'],  false);
					$durationHtml = formatDuration($link['start'] > 0 ? time() - $link['start'] : 0);
					
					

					$tooltipParts = [];
					if (!empty($link['timeout'])) $tooltipParts[] = 'Timeout: ' . $link['timeout'] . " s.";
					if (!empty($link['source']['announcement_name'])) $tooltipParts[] = getTranslation('Source') . ': ' . $link['source']['announcement_name'];
					if (!empty($link['destination']['announcement_name'])) $tooltipParts[] = getTranslation('Destination') . ': ' . $link['destination']['announcement_name'];
					if (!empty($link['source']['command']['activate_command'])) $tooltipParts[] = getTranslation('Activate') . ': ' . $link['source']['command']['activate_command'];
					if (!empty($link['source']['command']['deactivate_command'])) $tooltipParts[] = getTranslation('Deactivate') . ': ' . $link['source']['command']['deactivate_command'];
					
					// Определяем команду для переключения состояния линка
					$toggleCommand = '';
					if (
						!empty($link['source']['command']['activate_command']) &&
						!empty($link['source']['command']['deactivate_command'])
					) {

						if ($link['is_connected']) {
							$toggleCommand = $link['source']['command']['deactivate_command'] . '#';
						} else {
							$toggleCommand = $link['source']['command']['activate_command'] . '#';
						}
					}

					$shortname = trim(str_replace($excl, '', $linkName));
					if ($shortname === '') {
						$shortname = $linkName;
					}

					$relatedReflectors[$reflectorName]['links'][$linkName] = [
						'shortname' => $shortname,
						'name' => $linkName,
						'style' => $linkClass,
						'tooltip_start' => '<a class="tooltip" href="javascript:void(0)" ' .
							($toggleCommand ? 'onclick="sendLinkCommand(\'' . htmlspecialchars($toggleCommand, ENT_QUOTES) .
								'\', \'' . htmlspecialchars($logicName, ENT_QUOTES) . '\')" ' : '') .
							'><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br>' .
							implode(' | ', $tooltipParts) . '<br>' .getTranslation("Click to toggle") . '</span>',
						'tooltip_end' => '</a>'
					];

				}
			}
		}


		$shortname = trim(str_replace($excl, "", $logic['name']));
		if ($shortname === '') {
			$shortname = $logic['name'];
		}
		$durationHtml = formatDuration($logic['start'] > 0 ? time() - $logic['start'] : 0);
		$data['logics'][$logicName] = [
			'shortname' => $shortname,
			'name' => $logic['name'],
			'style' => $logicClass,
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

	// return $data;

	// @bookmark Unlinked reflectors (for future releases)
	foreach ($allReflectors as $reflectorName => $reflector) {
		$hasLink = false;
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
					$groupStyle = $disabledCellClass;
					if (isset($tg['selected']) && $group == $tg['selected']) {
						$groupStyle = $activeCellClass;
					} elseif (!empty($tg['temp_monitoring']) && in_array($group, $tg['temp_monitoring'])) {
						$groupStyle = $pausedCellClass;
					}

					$talkGroups[] = [
						'name' => $group,
						'style' => $groupStyle,
						'title' => $group
					];
				}

				$hasTalkGroupsData = !empty($talkGroups);
			}

			$reflectorNodes = [];
			if (!empty($reflector['connected_nodes'])) {
				foreach ($reflector['connected_nodes'] as $nodeName => $nodeData) {
					$durationHtml = formatDuration(empty($nodeData['start']) ? '0' : time() - $nodeData['start']);
					$nodeInfo = [
						'name' => $nodeName,
						'tooltip_start' => '<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br></span>',
						'tooltip_end' => !empty($nodeData['start']) ? '</a>' : ''
					];

					$reflectorNodes[$nodeName] = $nodeInfo;
				}
			}

			$reflectorLinks = [];
			if (isset($reflectorLinksMap[$reflectorName])) {
				foreach ($reflectorLinksMap[$reflectorName] as $linkName => $link) {
					$linkClass = $getCellStyle($link['is_active'], $link['is_connected'] ?? false, true);

					$tooltipParts = [];
					if (!empty($link['timeout'])) $tooltipParts[] = 'Timeout: ' . $link['timeout'] . " s.";
					if (!empty($link['source']['announcement_name'])) $tooltipParts[] = 'Source: ' . $link['source']['announcement_name'];
					if (!empty($link['destination']['announcement_name'])) $tooltipParts[] = 'Destination: ' . $link['destination']['announcement_name'];
					if (!empty($link['source']['command']['activate_command'])) $tooltipParts[] = 'Activate: ' . $link['source']['command']['activate_command'];
					if (!empty($link['source']['command']['deactivate_command'])) $tooltipParts[] = 'Deactivate: ' . $link['source']['command']['deactivate_command'];
					if (!empty($link['source']['command']['deactivate_command'])) $tooltipParts[] = 'Click to toggle';
					$shortname = trim(str_replace($excl, '', $linkName));
					if ($shortname === '') {
						$shortname = $linkName;
					}

					$durationHtml = formatDuration($link['start'] > 0 ? time() - $link['start'] : '0');
					$reflectorLinks[$linkName] = [
						'shortname' => $shortname,
						'name' => $linkName,
						'style' => $linkClass,
						'tooltip_start' => '<a class="tooltip" href="#"><span><b>' . getTranslation('Uptime') . ':</b>' . $durationHtml . '<br>' .
							implode(' | ', $tooltipParts) . '</span>',
						'tooltip_end' => '</a>'
					];
				}
			}

			$shortname = trim(str_replace($excl, "", $reflector['name']));
			if ($shortname === '') {
				$shortname = $reflector['name'];
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

	// @bookmark Unlinked Links (for future releases)
	if (!empty($lp_links)) {
		foreach ($lp_links as $linkName => $link) {
			$isReflectorLink = false;
			if (isset($link['destination']['logic']) && isset($allReflectors[$link['destination']['logic']])) {
				$isReflectorLink = true;
			}

			if (!$isReflectorLink) {
				$linkClass = $getCellStyle($link['is_active'], $link['is_connected'] ?? false, true);

				$tooltipParts = [];
				if (!empty($link['source']['announcement_name'])) $tooltipParts[] = 'Source: ' . $link['source']['announcement_name'];
				if (!empty($link['destination']['announcement_name'])) $tooltipParts[] = 'Destination: ' . $link['destination']['announcement_name'];
				if (!empty($link['source']['command']['activate_command'])) $tooltipParts[] = 'Activate: ' . $link['source']['command']['activate_command'];
				if (!empty($link['source']['command']['deactivate_command'])) $tooltipParts[] = 'Deactivate: ' . $link['source']['command']['deactivate_command'];
				

				$hasTooltip = !empty($tooltipParts) || $link['start'] > 0;
				$shortname = trim(str_replace($excl, '', $linkName));
				if ($shortname === '') {
					$shortname = $linkName;
				}
				$durationHtml = formatDuration($link['start'] > 0 ? time() - $link['start'] : 0);
				$data['unconnected_links'][$linkName] = [
					'shortname' => $shortname,
					'name' => $linkName,
					'style' => $linkClass,
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

// $lp_status = $_SESSION['status'];
$lp_status = getActualStatus();
$displayData = buildLogicData($lp_status);

$cellStyleStr = ' style="border: .5px solid #3c3f47;"';




?>
<div class="mode_flex">
	<div class="mode_flex row">
		<div class="mode_flex column">
			<div class="divTableHead"> <?= getTranslation('Service'); ?></div>
		</div>
	</div>

	<div class="mode_flex row">
		<div class="mode_flex column">
			<div class="divTableCell white-space:normal">
				<div id="service" class="<?= $displayData['service']['style'] ?>">
					<?php echo $displayData['service']['tooltip_start'] . $displayData['service']['name'] . $displayData['service']['tooltip_end']; ?>
				</div>
			</div>
		</div>
	</div>
</div>
<br>

<?php
if (!empty($displayData['logics'])) {
	foreach ($displayData['logics'] as $logic) {

		// @bookmark Логика 
		// Начало блока логики 
?>
		<div class="mode_flex">
			<div class="mode_flex column">
				<div class="divTableCell">
					<div id="logic_<?= $logic['name'] ?>" class="<?= $logic['style'] ?>"><?php echo $logic['tooltip_start'] . $logic['name'] . $logic['tooltip_end'];  ?></div>
				</div>
			</div>


			<?php // @bookmark Header for Modules 
			?>
			<div id="logic_<?= $logic['name'] ?>_modules_header" class="mode_flex row">
				<div class="mode_flex column">
					<div class="divTableHead"><?= getTranslation('Modules') ?></div>
				</div>
			</div>

			<?php	// @bookmark Modules body	
			if (!empty($logic['modules'])) {
				$moduleIndex = 0;
				$moduleCount = $logic['module_count'];
				foreach ($logic['modules'] as $module) {
					// new row every 2 times
					if ($moduleIndex % 2 == 0) {
						echo '<div class="mode_flex row">';
					}
					echo 		'<div class="mode_flex column">';
					echo 			'<div class="divTableCell">';
					echo 				'<div id="logic_' . $logic['name'] . '_module_' . $module['name'] . '" class="' . $module['style'] . '" title="">';
					echo 					$module['tooltip_start'] . $module['name'] . $module['tooltip_end'];
					echo 				'</div>';
					echo 			'</div>';
					echo 		'</div>';
					// end string
					if ($moduleIndex % 2 == 1 || $moduleIndex == $moduleCount - 1) {
						echo '</div>';
					}

					$moduleIndex++;
				}
			}

			// @bookmark Amodule data
			if (!empty($logic['active_module_nodes'])) {
				$nodesTableHeader = $logic['active_module']['name'] ?? getTranslation('Connected Nodes');
				$nodesTableStyle = '';
				$nodeCount = $logic['active_module_node_count'];
			} else {
				$nodesTableStyle = 'hidden';
				$nodeCount = 0;
				$nodesTableHeader = '';
			}

			// @bookmark Amodule frame
			?>
			<div id="logic_<?= $logic['name'] ?>_active" class="divTable <?= $nodesTableStyle ?>">
				<div id="logic_<?= $logic['name'] ?>_active_header" class="divTableHead"><?= $nodesTableHeader ?> [<?= $nodeCount ?>]</div>
				<div class="divTableBody">
					<div id="logic_<?= $logic['name'] ?>_active_content" class="mode_flex row" style="white-space: nowrap;">
						<?php if (!empty($logic['active_module_nodes'])) {
							foreach ($logic['active_module_nodes'] as $nodeName => $node) : ?>
								<div id="logic_<?= $logic['name'] ?>_node_<?= $nodeName ?>"
									class="mode_flex column <?= $disabledCellClass ?>"
									title="<?= $nodeName ?>"
									<?= $cellStyleStr ?>>
									<?= $node['tooltip_start'] . $node['name'] . $node['tooltip_end'] ?>
								</div>
						<?php endforeach;
						} ?>
					</div>
				</div>
			</div>

			<?php // @bookmark Reflectors
			if ($logic['has_reflectors']) {
				foreach ($logic['reflectors'] as $reflector) : ?>
					<?php
					?>
					<div class="divTable">
						<div class="divTableHead" style="background: none; border: none"></div>
						<div class="divTableBody">
							<div class="divTableRow center">
								<div class="divTableHeadCell"><?= getTranslation("Reflector") ?></div>
								<div id="logic_<?= $reflector['name'] ?>" class="divTableCell cell_content middle <?= $reflector['style'] ?>" <?= $cellStyleStr ?>><?php echo $reflector['tooltip_start'] . $reflector['shortname'] . $reflector['tooltip_end'] ?></div>
							</div>

							<?php // @bookmark Link
							if (!empty($reflector['links'])) {
								foreach ($reflector['links'] as $link) {
									echo '<div id="logic_' . $reflector['name']  . '_links" class="divTableRow center">';
									echo 		'<div class="divTableHeadCell">' . getTranslation('Link') . '</div>';
									echo 		'<div id="link_' . $link['name'] . '" class="' . $reflector['name']  . ' divTableCell cell_content middle ' . $link['style'] . '" ' . $cellStyleStr . '>';
									echo 			$link['tooltip_start'] . $link['shortname'] . $link['tooltip_end'] . '</div>';
									echo 	'</div>';
								}
							} ?>
						</div>
					</div>

					<?php // @bookmark TalkGroups
					?>
					<div class=" divTable">
						<div class="divTableHead"><?= getTranslation('Talk Groups') ?></div>
						<div class="divTableBody">
							<div id="logic_<?= $reflector['name'] ?>_groups" class="mode_flex row">
								<?php
								if ($reflector['has_talkgroups']) {
									$tgIndex = 0;
									$tgCount = count($reflector['talkgroups']);
									foreach ($reflector['talkgroups'] as $group) {
										echo '<div id="logic_' . $reflector['name'] . '_group_' . $group['name'] . '" class="mode_flex column ' . $group['style'] . '" title="' . $group['title'] . '" ' . $cellStyleStr . '>' . $group['name'] . '</div>';
									}
								};
								?>

							</div>
						</div>
					</div>

					<?php // @bookmark Reflector's node 
					?>
					<div class="divTable">
						<div id="logic_<?= $reflector['name'] ?>_nodes_header" class="divTableHead"><?= getTranslation('Nodes') ?> [<?= $reflector['node_count'] ?>]</div>
						<div id="logic_<?= $reflector['name'] ?>_nodes" class="divTableBody mode-flex row" style="white-space: nowrap;">
							<?php if (!empty($reflector['nodes'])) {
								foreach ($reflector['nodes'] as $node) {
									echo '<div id="logic_' . $reflector['name'] . '_node_' . $node['name'] . '" class="mode_flex column ' . $disabledCellClass . '" title="' . $node['name'] . '"' . $cellStyleStr . '>' . $node['tooltip_start'] . $node['name'] . $node['tooltip_end'] . '</div>';
								}
							}
							?>
						</div>
					</div>

			<?php endforeach;
			}
			?>
		</div>
		<br>
	<?php
	}
}

// @bookmark APRS
if (isset($displayData['aprs_server'])) : ?>
	<div class="divTable">
		<div class="divTableHead"><?= getTranslation('APRS') ?></div>
	</div>
	<div class="divTable">
		<div class="divTableBody">
			<div class="divTableRow center">
				<div class="divTableHeadCell"><?= getTranslation('Main') ?></div>
				<div id="aprs_status" class="divTableCell cell_content center <?php echo $displayData['aprs_server']['start'] > 0 ? $activeCellClass : $inactiveCellClass ?>">
					<?= $displayData['aprs_server']['name'] ?>
				</div>
			</div>
			<?php if (isset($displayData['status_server'])) : ?>
				<div class="divTableRow center">
					<div class="divTableHeadCell"><?= getTranslation('EchoLink') ?></div>
					<div id="<?= $displayData['status_server']['name'] ?>" class="divTableCell cell_content center <?php echo $displayData['status_server']['has_error'] ? $inactiveCellClass : $disabledCellClass ?>">
						<?= $displayData['status_server']['name'] ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<br>

<?php endif;

// @bookmark EL Directory Server
if (!empty($displayData['directory_server'] || !empty($displayData['proxy_server']))) : ?>
	<div class="divTable">
		<div class="divTableHead"><?= getTranslation('Directory Server') ?></div>
	</div>
	<div class="divTable">
		<div class="divTableBody">
			<div class="divTableRow center">
				<div class="divTableHeadCell"><?= getTranslation('Server') ?></div>
				<div id="directory_server_status" class="divTableCell cell_content center <?php echo $displayData['directory_server']['start'] > 0 ? $activeCellClass : $inactiveCellClass ?>">
					<?= empty($displayData['directory_server']['name']) ? getTranslation('Disconnected') : getTranslation($displayData['directory_server']['name']) ?>
				</div>
			</div>


			<div id="proxy_server" class=" divTableRow center <?php echo isset($displayData['proxy_server']) ? '' : 'hidden'; ?>">
				<div class="divTableHeadCell"><?= getTranslation('Proxy') ?></div>
				<div id="proxy_server_status" class="divTableCell cell_content center <?php echo $displayData['proxy_server']['start'] > 0 ? $activeCellClass : $inactiveCellClass ?>">
					<?= $displayData['proxy_server']['name'] ?>
				</div>
			</div>


		</div>
	</div>
	<br>

<?php endif;

unset(
	$displayData,
	$logic,
	$lp_status,
	$link,
	$nodeCount,
	$node,
	$reflector,
	$tgIndex,
	$tgCount,
	$group,
	$moduleIndex,
	$moduleCount,
	$module
);
?>
<script>
	function sendLinkCommand(command, source) {
		fetch('/include/dtmf_handler.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded'
				},
				body: new URLSearchParams({
					command: command,
					source: source
				})
			})
			.then(r => r.text())
			.then(eval)
			.catch(console.error);
	}
</script>