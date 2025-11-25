<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}


// Temporary debug output to check Node process
// $nodeRunning = isProcessRunning('node');



?>
<div class="divTable" id="softwareInfoTable">
	<div class="divTableBody">
		<div class="divTableRow">
			<div class="divTableHeadCell">Статус радио</div>
			<div class="divTableHeadCell">Рефлектор</div>
			<div class="divTableHeadCell">Radio Mode</div>
			<div class="divTableHeadCell">Мониторинг</div>
			<div class="divTableHeadCell">PEAK_METER</div>
		</div>
		<div class="divTableRow">
			<div class="divTableCell cell_content middle">Rario status<?php //echo $radioStatus; 
																		?></div>
			<div class="divTableCell cell_content middle">Рефлектор<?php //echo getSVXRstatus(); 
																	?></div>
			<div class="divTableCell cell_content middle">Мониторинг<?php //echo $logic_mode; 
																	?></div>
			<div class="divTableCell cell_content middle">Мониторинг</div>
			<div class="divTableCell cell_content middle">Мониторинг<?php //echo getRXPeak(); 
																	?></div>
		</div>
	</div>
</div>