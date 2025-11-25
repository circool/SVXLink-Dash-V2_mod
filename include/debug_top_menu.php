<?php

/**
 * debug_top_menu.php
 * @file debug_top_menu.php
 * Copyright vladimir@tsurkanenko.ru
 * https://github.com/circool
 * https://vladimir.tsurkanenko.ru
 * 
 * Разметка верхнего меню
 * 
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
// $isAuthorised = isset($_SESSION['auth']) && $_SESSION['auth'] === 'AUTHORISED';
// $systemType = $_SESSION['system_type'] ?? 'Unknown';

// include '../include/debug_functions.php';


if ($_SESSION['auth'] === "AUTHORISED") {
	//<!-- Показываем для авторизованных -->
	//echo '<a href="debug_logout.php" class="menuadmin">' . getTranslation($lang, 'Logout') . '</a>';
	//echo '<a class="menuconfig" href="/edit">' . getTranslation($lang, 'Settings') . '</a>';
} else {
	//<!-- Показываем для неавторизованных -->
	//echo '<a class="menuadmin" href="/debug_authorise.php">' . getTranslation($lang, 'Login') . '</a>';
}; ?>

<a class="menulive" href="/debug_dtmf.php">DTMF</a>
<a class="menusysinfo" href="/debug_macro.php"><?php echo getTranslation($lang, 'Macro'); ?></a>
<a class="menuhwinfo" href="/debug_hw_info.php"><?php echo getTranslation($lang, 'System Info'); ?></a>
<a class="menusimple" href="/debug_tg_list.php"><?php echo getTranslation($lang, 'Talkgroups'); ?></a>
<a class="menudashboard" href="/debug_index.php"><?php echo getTranslation($lang, 'Dashboard'); ?> (Debug mode)</a>

<?php
//@deprecated
// include_once('debug_parse_svxconf.php');
?>