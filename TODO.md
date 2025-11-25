# Доделать

[ ] Функция getModuleSessionTime() неверно вычисляет дату окончания сессии
	проблема с часовым поясом - пока исправлена явным указанием.
[ ] После перезагрузки сервиса неверно отражается информация о активном модуле 
	Нет анализа на перезагрузку
	//18 Nov 2025 09:55:52.690: SIGTERM received. Shutting down application...

[ ] В рефлекторе сделать не только отображение действий, а их результат - если была передача - казать продолжительность. Если было действие - описать результат
[+] Не отражается подвал
[+] Frn не обновляется 
[ ] Нет времени сессии для логики





## Предварительное тестирование

## Порядок загрузки (index.php)
[+] include "include/settings.php";
[+] include "include/config.php";
	[+] include_once "parse_svxconf.php";
		[+] include "config.php";         
		[+] include_once "tools.php";        
		[+] include_once "functions.php";
[+] include_once "include/top_menu.php"
	[+] include '../include/functions.php';
	[!+] include_once('parse_svxconf.php');
[-] include "include/status.php";
	[-] include_once "config.php";         
	[-] include_once "tools.php";        
	[-] include_once "functions.php";
==================================================
Далее мои блоки

### Файлы к проверке

[!] debug_index.php
 - [ ] include/debug_settings.php";
 - [ ] include/debug_config.php";
 - [ ] include/debug_init.php";
 - [ ] css/featherlight.css
 - [ ] scripts/featherlight.js
 - [ ] css/font-awesome-4.7.0/css/font-awesome.min.css
  - [ ] css/fonts.css
  - [ ] css/css.php
  - [ ] include/debug_main.php
	- [ ] include/debug_config.php
	include/debug_tools.php
	include/debug_functions.php
	- [!] //include/init.php
	- [!] //include/funct_debug_modules_status.php
	- [-] include/funct_release.php
	- [-] funct_release.php
	- [+] /include/debug_config.php
	- [-] funct_debug.php
	- [-] funct_debug_active.php
	- [+] include 'debug_panel_active.php
	- [+] debug_keypad_module.php
	- [+] debug_block_radio_status.php
	- [+] debug_block_nearby_activity.php
	- [+] debug_block_local_activity.php
	- [+] debug_block_debug.php

  - [ ] include/debug_footer.php
  - [!] include/debug_status_panel.php