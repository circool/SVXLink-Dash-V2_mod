<?php 
/**
 * @date 2021-11-23
 * @version 0.1.1
 * @author vladimir@tsurkanenko.ru
 * @file debug_tg_init.php
 * @description Инициализация базы разговорных групп рефлектора
 */
// include_once 'config.php';

if(count($svxconfig['ReflectorTG'])> 0){ 
	$tg_db = $svxconfig['ReflectorTG'];
} else {
	$tg_db = array();
}
?>