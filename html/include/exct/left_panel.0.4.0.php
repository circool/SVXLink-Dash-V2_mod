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
?>

<!-- @bookmark Сервис -->
<div class="mode_flex" id="rptInfoTable">
	<div class="mode_flex row">
		<div class="mode_flex column">
			<div class="divTableHead"><?php echo getTranslation('Service') ?></div>
		</div>
	</div>
	<div class="mode_flex row">
		<div class="mode_flex column">
			<div class="divTableCell white-space:normal">
				<div id="logic_<?= $displayData['service']['name'] ?>" class="<?= $displayData['service']['style'] ?>">
					<?= $displayData['service']['tooltip_start'] . $displayData['service']['name'] . $displayData['service']['tooltip_end'] ?>
				</div>
			</div>
		</div>
	</div>
</div>
<br>

<?php if (!empty($displayData['logics'])):
	foreach ($displayData['logics'] as $logic): ?>

		<!-- Начало блока логики -->
		<div class="mode_flex">
			<!-- @bookmark Логика -->
			<div class="mode_flex column">
				<div class="divTableCell">
					<div id="logic_<?= $logic['name'] ?>" class="<?= $logic['style'] ?>" title="">
						<?= $logic['tooltip_start'] . $logic['name'] . $logic['tooltip_end'] ?>
					</div>
				</div>
			</div>

			<?php if (!empty($logic['modules'])): ?>
				<!-- @bookmark Модули шапка -->
				<div class="mode_flex row">
					<div class="mode_flex column">
						<div class="divTableHead">
							<?php echo getTranslation('Modules'); ?><?= $logic['module_count'] > 1 ? ' [' . $logic['module_count'] . ']' : '' ?>
						</div>
					</div>
				</div>

				<!-- @bookmark Модули тело -->
				<?php
				$moduleIndex = 0;
				$moduleCount = $logic['module_count'];
				foreach ($logic['modules'] as $module):
					// Начало новой строки каждые 2 модуля
					if ($moduleIndex % 2 == 0): ?>
						<div class="mode_flex row">
						<?php endif; ?>

						<div class="mode_flex column">
							<div class="divTableCell">
								<div id="module_<?php echo $logic['name'] . $module['name'] ?>" class="<?= $module['style'] ?>" title="">
									<?= $module['tooltip_start'] . $module['name'] . $module['tooltip_end'] ?>
								</div>
							</div>
						</div>

						<?php
						// Конец строки
						if ($moduleIndex % 2 == 1 || $moduleIndex == $moduleCount - 1): ?>
						</div>
			<?php endif;
						$moduleIndex++;
					endforeach;
				endif; ?>

			<!-- @bookmark Активный модуль и его узлы -->
			<?php
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
			?>
			<!-- @bookmark Всегда создаем пустой каркас для подключенных узлов но скрываем его если нет подключенных узлов -->
			<div id="module_<?= $logic['name'] ?>_Status" class="divTable <?= $nodesTableStyle ?>">
				<div id="module_<?= $logic['name'] ?>_Status_Header" class="divTableHead"><?= $nodesTableHeader ?></div>
				<div class="divTableBody">
					<div class="mode_flex">
						<div class="mode_flex row">
							<div id="module_<?= $logic['name'] ?>_Status_Content" class="mode_flex column disabled-mode-cell" style="border: .5px solid #3c3f47;">
								<?= $node['tooltip_start'] . $node['name'] . $node['tooltip_end'] ?>
							</div>
						</div>
					</div>
				</div>
			</div>


			<!-- @bookmark Рефлекторы связанные с этой логикой -->
			<?php if ($logic['has_reflectors']):
				foreach ($logic['reflectors'] as $reflector): ?>

					<!-- @bookmark Рефлектор блок -->
					<div class="divTable">
						<div class="divTableHead" style="background: none; border: none"></div>
						<div class="divTableBody">
							<div class="divTableRow center">
								<div class="divTableHeadCell"><?php echo getTranslation('Reflector') ?></div>
								<div id="logic_<?= $reflector['name'] ?>" class="divTableCell cell_content middle <?= $reflector['style'] ?>" style="border: .5px solid #3c3f47;">
									<?= $reflector['shortname'] ?>
								</div>
							</div>

							<!-- Линки рефлектора -->
							<?php if (!empty($reflector['links'])):
								foreach ($reflector['links'] as $link): ?>
									<div class="divTableRow center">
										<div class="divTableHeadCell"><?php echo getTranslation('Link') ?></div>
										<div id="link_<?= $link['name'] ?>" class="divTableCell cell_content middle <?= $link['style'] ?>" style="border: .5px solid #3c3f47;">
											<?= $link['tooltip_start'] . $link['shortname'] . $link['tooltip_end'] ?>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>

					<!-- @bookmark TalkGroups рефлектора -->
					<?php if ($reflector['has_talkgroups']): ?>
						<div class="divTable">
							<div class="divTableHead"><?php echo getTranslation('Talk Groups') ?></div>
							<div id="logic_<?= $reflector['name'] ?>_GroupsTableBody" class="divTableBody">
								<div class="mode_flex">
									<?php
									$tgIndex = 0;
									$tgCount = count($reflector['talkgroups']);
									foreach ($reflector['talkgroups'] as $group):
										$defaultCell = $group['name'] === $group['default'] ? "default":"";
										// Начало строки каждые 4 группы
										if ($tgIndex % 4 == 0): ?>
											<div class="mode_flex row">
											<?php endif; ?>

											<div id="logic_<?= $reflector['name'] ?>_Group_<?= $group['name'] ?>" class="<?php echo $defaultCell ?> mode_flex column <?= $group['style'] ?>" title="<?= $group['title'] ?>" style="border: .5px solid #3c3f47;">
												<?= $group['name'] ?>
											</div>

											<?php
											// Конец строки
											if ($tgIndex % 4 == 3 || $tgIndex == $tgCount - 1): ?>
											</div>
									<?php endif;

											$tgIndex++;
										endforeach;
									?>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<!-- @bookmark Узлы рефлектора -->
					<?php if (!empty($reflector['nodes'])): ?>
						<div class="divTable">
							<div class="divTableHead"><?php echo getTranslation('Nodes') ?> [<?= $reflector['node_count'] ?>]</div>
							<div id="logic_<?= $reflector['name'] ?>_Status" class="divTableBody">
								<div class="mode_flex">
									<?php
									$nodeIndex = 0;
									$nodeCount = $reflector['node_count'];
									foreach ($reflector['nodes'] as $node):
										// Начало строки каждые 2 узла
										if ($nodeIndex % 2 == 0): ?>
											<div class="mode_flex row">
											<?php endif; ?>

											<div id="logic_<?= $reflector['name'] ?>_Status_Node_<?= $node['name'] ?>" class="mode_flex column disabled-mode-cell" title="<?= $node['name'] ?>" style="border: .5px solid #3c3f47;">
												<?= $node['tooltip_start'] . $node['name'] . $node['tooltip_end'] ?>
											</div>

											<?php
											// Конец строки
											if ($nodeIndex % 2 == 1 || $nodeIndex == $nodeCount - 1): ?>
											</div>
									<?php endif;

											$nodeIndex++;
										endforeach;
									?>
								</div>
							</div>
						</div>
					<?php endif; ?>

				<?php endforeach; // Конец цикла по рефлекторам 
				?>
			<?php endif; // Конец if ($logic['has_reflectors']) 
			?>

			<br> <!-- Отступ после блока логики -->

		</div> <!-- Конец блока логики -->

	<?php endforeach; // Конец цикла по логикам 
	?>
<?php endif; // Конец if (!empty($displayData['logics'])) 
?>

<!-- @bookmark Несвязанные рефлекторы -->
<?php if (!empty($displayData['unconnected_reflectors'])): ?>
	<div class="mode_flex" id="unconnectedReflectorsTable">
		<div class="mode_flex row">
			<div class="mode_flex column">
				<div class="divTableHead"><?php echo getTranslation('Uninked Reflectors') ?></div>
			</div>
		</div>

		<?php foreach ($displayData['unconnected_reflectors'] as $reflector): ?>

			<!-- @bookmark Несвязанный рефлектор -->
			<div class="mode_flex" id="unconnected_reflector_<?= $reflector['name'] ?>_table">

				<!-- Имя рефлектора -->
				<div class="mode_flex row">
					<div class="mode_flex column">
						<div class="divTableHead <?= $reflector['style'] ?>">
							<?= $reflector['name'] ?>
						</div>
					</div>
				</div>

				<?php if (!empty($reflector['links'])): ?>
					<?php foreach ($reflector['links'] as $link): ?>
						<div class="divTable">
							<div class="divTableRow center">
								<div class="divTableHeadCell">
									<?php echo getTranslation('Link') ?>
								</div>
								<div class="divTableCell cell_content middle <?= $link['style'] ?>" style="border: .5px solid #3c3f47;">
									<?= $link['tooltip_start'] . $link['name'] . $link['tooltip_end'] ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<!-- @bookmark TalkGroups рефлектора -->
				<?php if ($reflector['has_talkgroups']): ?>
					<div class="divTable">
						<div class="divTableHead"><?php echo getTranslation('Talk Groups') ?></div>
						<div class="divTableBody">
							<div class="mode_flex">
								<?php
								$tgIndex = 0;
								$tgCount = count($reflector['talkgroups']);
								foreach ($reflector['talkgroups'] as $group):
									// Начало строки каждые 4 группы
									if ($tgIndex % 4 == 0): ?>
										<div class="mode_flex row">
										<?php endif; ?>

										<div class="mode_flex column <?= $group['style'] ?>" title="<?= $group['title'] ?>" style="border: .5px solid #3c3f47;">
											<?= $group['name'] ?>
										</div>

										<?php
										// Конец строки
										if ($tgIndex % 4 == 3 || $tgIndex == $tgCount - 1): ?>
										</div>
								<?php endif;

										$tgIndex++;
									endforeach;
								?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<!-- @bookmark Узлы рефлектора -->
				<?php if (!empty($reflector['nodes'])): ?>
					<div class="divTable">
						<div class="divTableHead"><?php echo getTranslation('Nodes') ?> [<?= $reflector['node_count'] ?>]</div>
						<div class="divTableBody">
							<div class="mode_flex">
								<?php
								$nodeIndex = 0;
								$nodeCount = $reflector['node_count'];
								foreach ($reflector['nodes'] as $node):
									// Начало строки каждые 2 узла
									if ($nodeIndex % 2 == 0): ?>
										<div class="mode_flex row">
										<?php endif; ?>

										<div class="mode_flex column disabled-mode-cell" title="<?= $node['name'] ?>" style="border: .5px solid #3c3f47;">
											<?= $node['tooltip_start'] . $node['name'] . $node['tooltip_end'] ?>
										</div>

										<?php
										// Конец строки
										if ($nodeIndex % 2 == 1 || $nodeIndex == $nodeCount - 1): ?>
										</div>
								<?php endif;

										$nodeIndex++;
									endforeach;
								?>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<br>
		<?php endforeach; ?>
	</div>
	<br>
<?php endif; ?>

<!-- @bookmark Линки без рефлекторов -->
<?php if (!empty($displayData['unconnected_links'])): ?>
	<div class="mode_flex" id="linksInfoTable">
		<div class="mode_flex row">
			<div class="mode_flex column">
				<div class="divTableHead"><?php echo getTranslation('Empty Links') ?></div>
			</div>
		</div>

		<?php
		$linkIndex = 0;
		$linkCount = count($displayData['unconnected_links']);
		foreach ($displayData['unconnected_links'] as $link):
			if ($linkIndex % 2 == 0): ?>
				<div class="mode_flex row">
				<?php endif; ?>

				<div class="mode_flex column">
					<div class="divTableCell">
						<div class="<?= $link['style'] ?>" title="">
							<?= $link['tooltip_start'] . $link['name'] . $link['tooltip_end'] ?>
						</div>
					</div>
				</div>

				<?php
				if ($linkIndex % 2 == 1 || $linkIndex == $linkCount - 1): ?>
				</div>
		<?php endif;

				$linkIndex++;
			endforeach;
		?>
	</div>
	<br>
<?php endif; ?>

<!-- Конец #repeaterInfo -->

<?php
// Очистка переменных
unset(
	$displayData,
	// $excl,
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
}
?>