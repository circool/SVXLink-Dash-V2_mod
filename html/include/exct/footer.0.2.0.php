<?php

/**
 * @date 2021-12-01
 * @version 0.2.0
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/exct/footer.0.2.0.php
 * @description Подвал - Предварительая версия
 * @todo Заполнить содержимое
 */
if(defined("DEBUG") && DEBUG) {
	$ver = "footer.php " . DASHBOARD_VERSION;
	dlog("$ver: Loaded successfully", 4, "DEBUG");}
echo DASHBOARD_TITLE . ' v' . DASHBOARD_VERSION . '. Created by R2ADU ' . date('Y') . '. © Concept idea G4NAB, SP2ONG, SP0DZ. Design idea by Chip Cuccio, <code>W0CHP</code>';
?>