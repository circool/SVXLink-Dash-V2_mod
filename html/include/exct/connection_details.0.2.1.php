<?php

/**
 * @version 0.2.2
 * @since 0.2.0
 * @date 2025.12.22
 * @author vladimir@tsurkanenko.ru
 * @note Новое в 0.2.2: Добавлена обертка для плавного скрытия/показа
 */

// require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
?>

<div id="connection_details">
	<div id="refl_header" class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">
		<?= getTranslation('Connection Details') ?>
	</div>
	<table style="word-wrap: break-word; white-space:normal;">
		<tbody>
			<tr>
				<th width="150px"><a class="tooltip" href="#"><?php echo getTranslation('Date'); ?><span><b><?php echo getTranslation('Date'); ?></b></span></a></th>
				<th width="150px"><a class="tooltip" href="#"><?php echo getTranslation('Time'); ?><span><b><?php echo getTranslation('Time'); ?></b></span></a></th>
				<th><a class="tooltip" href="#"><?php echo getTranslation('Source'); ?><span><b><?php echo getTranslation('Source'); ?></b></span></a></th>
				<th><a class="tooltip" href="#"><?php echo getTranslation('Destination'); ?><span><b><?php echo getTranslation('Destination of transmission'); ?></b></span></a></th>
				<th><a class="tooltip" href="#"><?php echo getTranslation('Duration'); ?><span><b><?php echo getTranslation('Duration'); ?></b></span></a></th>
			</tr>
			<tr>
				<td>TODO</td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>
		</tbody>
	</table>
	<br>
</div>