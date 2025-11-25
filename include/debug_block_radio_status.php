<?php

/**
 * Активность радио
 * @file debug_block_radio_status.php
 * @author vladimir@tsurkanenko
 * @version 0.1.1
 * @date 2021-11-23
 */

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}


// Описание разговорных групп рефлектора 
if(empty($SessionInfo['active_module'])) {
	include_once "debug_tg_init.php";
};


$_status = debug_getRadioStatus();

$_src = '';
$_dest = '';
$_act_src = '';
$_freq = $svxconfig['LocationInfo']['FREQUENCY'] . ' MHz'; 
if(isset($svxconfig['LocationInfo']['TONE'])) {
	$_freq .= ' #' . $svxconfig['LocationInfo']['TONE'];	
}
// cattsign, source
if(!empty($SessionInfo['active_module'])) {
	$_nodes = $SessionInfo['module'][$sessionInfo['active_module']]['connected_nodes'] ?? [];
	$_callsigns = !empty($_nodes) ? implode(", ", array_column($_nodes, 'callsign')) : "Control";
	$_act_src = $sessionInfo['active_module'] . ': ' . $_callsigns;
	$_callsign = $sessionInfo['module'][$sessionInfo['active_module']]['callsign'];
} else {
	$_act_src = $sessionInfo['active_logic'] . ': TG:' . $sessionInfo['logic'][$sessionInfo['active_logic']]['talkgroups']['selected'];
	$_callsign = $sessionInfo['logic'][$sessionInfo['active_logic']]['callsign'];
}	

// source/destination
if($_status === "TRANSMIT") {
	$_src = $_act_src;
	$_dest = "RF: " . $_freq;
} else {
	$_dest = $_act_src;
	$_src = "RF: " . $_freq; ;
}


$_dur = 'TODO';

?>
<div id="RadioStatus">
	<!-- TODO починить кнопку и назначить ей действие -->
	<input type="hidden" name="filter-activity" value="OFF">
	<div style="float: right; vertical-align: bottom; padding-top: 0px;" id="monCtrl">
		<div class="grid-container"
			style="display: inline-grid; grid-template-columns: auto 40px; padding: 1px;; grid-column-gap: 5px;">
			<div class="grid-item filter-activity" style="padding: 10px 0 0 20px;" title="Monitor">Monitor:
			</div>
			<div class="grid-item">
				<div style="padding-top:6px;">
					<input id="toggle-filter-activity" class="toggle toggle-round-flat" type="checkbox"
						name="activate-monitor" value="ON" aria-checked="true"
						aria-label="Toggle audio monitor" onchange="setFilterActivity(this)"><label
						for="toggle-filter-activity"></label>
				</div>
			</div>
		</div>
	</div>
	<!-- Статус радио -->
	<div class="larger" style="vertical-align: bottom; font-weight: bold; padding-top:14px;text-align:left;"><?php echo getTranslation($lang, 'Radio Status'); ?></div>
	<table style="word-wrap: break-word; white-space:normal;">
		<tbody>
			<tr>
				<th width=250px>
					<a class="tooltip" href="#"><?php echo getTranslation($lang, 'Status'); ?>
						<span><b><?php echo getTranslation($lang, 'Status of radio'); ?></b>Iddle, Receive, Transmit</span>
					</a>
				</th>
				<th>
					<a class="tooltip" href="#"><?php echo getTranslation($lang, 'Callsign'); ?>
						<span><b><?php echo getTranslation($lang, 'Callsign'); ?></b></span>
					</a>
				</th>
				<th>
					<a class="tooltip" href="#"><?php echo getTranslation($lang, 'Destination'); ?>
						<span><b><?php echo getTranslation($lang, 'Destination'); ?></b></span>
					</a>
				</th>
				<th>
					<a class="tooltip" href="#"><?php echo getTranslation($lang, 'Signal Source'); ?><span><b><?php echo getTranslation($lang, 'Signal Source'); ?></b></span></a>
				</th>
				<th width="100px">
					<a class="tooltip" href="#"><?php echo getTranslation($lang, 'Duration'); ?><span><b><?php echo getTranslation($lang, 'Duration'); ?></b></span></a>
				</th>
			</tr>
			<tr>
				<?php 
				echo '<td style="font-size:1.3em;" class="';
				echo $_status == "STANDBY" ? ' disabled-mode-cell' : ' paused-mode-cell';
				echo '">' . getTranslation($lang, $_status) . '</td>';
				
				echo '<td style="font-size:1.3em;" class="divTableCellMono"><strong><a href="https://www.qrz.com/db/';
				echo clearCallsign($_callsign) . '" target="_blank">' . $_callsign . '</a></strong></td>';
				?>
				<td style="font-size:1.3em;">
					<?php echo $_dest; ?>
				</td>
				<td style="font-size:1.3em;">
					<?php echo $_src; ?>
				</td>
				<td style="font-size:1.3em;">
					<?php echo $_dur; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<br>
</div>