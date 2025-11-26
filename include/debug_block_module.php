<?php

include_once __DIR__ . "/debug_config.php";

// Вычисляем количество подключенных узлов
$connectionCount = 0;
$_server_count = 0;
$_conference_count = 0;
$_link_count = 0;
$_repeater_count = 0;
foreach ($sessionInfo['module'] as $module) {
	if ($module['name'] === $sessionInfo['active_module']) {
		$connectionCount = count($module['connected_nodes']);
		foreach ($module['connected_nodes'] as $node) {
			if (preg_match('/\*([^*]+)\*/', $node['callsign'])) {
				$_conference_count++;
			} elseif (substr(trim($node['callsign']), -2) === '-L') {
				$_link_count++;
			} elseif (substr(trim($node['callsign']), -2) === '-R') {
				$_repeater_count++;
			} elseif ($node['type'] === 'server') {
				$_server_count++;
			}
		}
		break;
	}
};
if ($connectionCount == 0) return;
?>

<div id = "moduleStatus">
<br>
<!-- Список подключенных узлов -->
<div class="divTable">
	<div class="divTableHead"><?php echo getTranslation($lang, 'Connections') . ': [' . $connectionCount . ']'; ?></div>
	<div class="divTableBody">
		<div class="divTableRow center">
			<div class="divTableCell">
				<?php
				// Список подключенных узлов
				foreach ($sessionInfo['module'] as $module) {
					if ($module['name'] == $sessionInfo['active_module']) {
						foreach ($module['connected_nodes'] as $node) {
							echo '<div style="background: #949494;" title="' . $node['callsign'] . '">' . $node['callsign'] . '</div>';
						}
					};
				}; ?>
			</div>
		</div>
	</div>
</div>
<br>

<!-- Детализация по типу узлов -->
<?php if($_server_count+$_link_count+$_repeater_count+$_conference_count == 0) return;?>

<div class="divTable">
	<div class="divTableHead"><?php echo getTranslation($lang, 'Details'); ?></div>
	<div class="divTableBody">
		<?php
		if ($_conference_count !== 0) {
			echo '<div class="divTableRow"><div class="divTableHeadCell">' .getTranslation($lang, 'Conferences') .'</div>';
			echo '<div class="divTableCell cell_header '. ($_conference_count > 0 ? "paused-mode-cell" : "disabled-mode-cell") . '" title="" style="border: .5px solid #3c3f47;">' .$_conference_count . '</div></div>';

		};
		if ($_repeater_count !== 0) {
			echo '<div class="divTableRow"><div class="divTableHeadCell">' . getTranslation($lang, 'Repeaters') . '</div>';
			echo '<div class="divTableCell cell_header ' . ($_repeater_count > 0 ? "paused-mode-cell" : "disabled-mode-cell") . '" title="" style="border: .5px solid #3c3f47;">' . $_repeater_count . '</div></div>';
		};

		if ($_link_count !== 0) {
			echo '<div class="divTableRow"><div class="divTableHeadCell">' . getTranslation($lang, 'Links') . '</div>';
			echo '<div class="divTableCell cell_header ' . ($_link_count > 0 ? "paused-mode-cell" : "disabled-mode-cell") . '" title="" style="border: .5px solid #3c3f47;">' . $_link_count . '</div></div>';
		};

		if ($_server_count !== 0) {
			echo '<div class="divTableRow"><div class="divTableHeadCell">' . getTranslation($lang, 'Servers') . '</div>';
			echo '<div class="divTableCell cell_header ' . ($_server_count > 0 ? "paused-mode-cell" : " disabled-mode-cell") . '" title="" style="border: .5px solid #3c3f47;">' . $_server_count . '</div></div>';
		};
		?>

	</div>
</div>
</div>
