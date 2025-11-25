<?php
/**
 * Copyright SVXLink-Dashboard-V2 by F5VMR 
 * @note debug_settings.php
 * @date 2021-11-24
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$progname = basename($_SERVER['SCRIPT_FILENAME'],".php");
include_once "debug_config.php";
// include_once "debug_tools.php";

$svxConfigFile = '/etc/svxlink/svxlink.conf';
if (fopen($svxConfigFile, 'r')) {
    $svxconfig = parse_ini_file($svxConfigFile, true, INI_SCANNER_RAW);
    $callsign = $svxconfig['ReflectorLogic']['CALLSIGN'];
    // $fmnetwork = $svxconfig['ReflectorLogic']['HOSTS'];
    // $node_password = $svxconfig['ReflectorLogic']['AUTH_KEY'];
    // $node_user = $callsign;
    }
else { 
    $callsign="NOCALL"; 
    //    $fmnetwork="not registered";
}

?>