<?php

/**
 * @author vladimir@tsurkanenko.ru 
 * @version 0.0.1
 * @filesource /include/session_header.php
 */


if (session_status() === PHP_SESSION_NONE) {
	require_once $_SERVER["DOCUMENT_ROOT"] . "/include/settings.php";
	session_set_cookie_params(SESSION_LIFETIME, SESSION_PATH);
	session_name(SESSION_NAME);
	session_id(SESSION_ID);
	session_start();
	
}

?>