<?php

/**
 * @version 0.2.1
 * @since 0.1.14
 * @date 2025.12.22
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/exct/net_activity.0.2.1.php 
 * @simlink /include/net_activity.php 
 * @todo Прототип
 * @copyright vladimir@tsurkanenko.ru
 * @description Блок информации о активности из сети
 * @note Адаптирован под 0.2
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/include/fn/getTranslation.php';
$netResultLimit = NET_ACTIVITY_LIMIT . ' ' . getTranslation('Actions');
?>
<div id="net_activity">
	<div class="larger" style="vertical-align: bottom; font-weight:bold;text-align:left;margin-top:12px;">
		<?php echo getTranslation('Last') . " " . $netResultLimit . " " . getTranslation('NET Activity') ?>
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
