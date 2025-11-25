<?php

/**
 * @file debug_main.php
 * @brief Главная страница используемая для отладки
 * @details Используются файлы с префиксом debug_
 * @author vladimir@tsurkanenko.ru
 * @version 0.1.3
 * @date 2021-11-23
 */



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once dirname(__DIR__) . "/include/debug_config.php";
// include_once dirname(__DIR__) . "/include/debug_tools.php";
include_once dirname(__DIR__) . "/include/debug_min_function.php";
// include_once dirname(__DIR__) . "/include/init.php";
// include_once dirname(__DIR__) . "/include/funct_debug_modules_status.php";
// include_once dirname(__DIR__) .  "/include/funct_release.php";
// include_once dirname(__DIR__) .  "funct_release.php";

$lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';

// ИНИЦИАЛИЗАЦИЯ ПЕРЕМЕННЫХ ДЛЯ ОТЛАДКИ


if (!isset($svxrstatus)) {
    $svxrstatus = "Unknown";
}


// ПРОВЕРКА ФАЙЛОВ
// $config_file = dirname(__DIR__) . "/include/debug_config.php";
// if (!file_exists($config_file)) {
//     die("Config file not found: " . $config_file);
// }

// ОТЛАДКА
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================================================
// Основной источник данных
// $sessionInfo = [debug]_getSessionStatus();
// ==================================================

?>


<!-- Статусная панель  -->
<div class="nav">
    <div id="statusPanelBlock">
        <!-- Сервис -->
        <div class="mode_flex" id="serviceInfoTable">
            <div class="mode_flex row">
                <div class="mode_flex column">
                    <div class="divTableHead"><?php echo getTranslation($lang, 'Service') ?></div>
                </div>
            </div>
            <div class="mode_flex row">
                <div class="mode_flex column">
                    <div class="divTableCell">
                        <div class="<?php echo $sessionInfo['service'][0]['is_active'] ? "active-mode-cell" : "inactive-mode-cell"; ?>" title="<?php echo getTranslation($lang, 'Status: Green - enabled, Red - disabled') ?>">SvxLink</div>
                    </div>
                </div>
            </div>
        </div>
        <br>
        <!-- Логика -->
        <div class="mode_flex" id="logicsInfoTable">
            <div class="mode_flex row">
                <div class="mode_flex column">
                    <div class="divTableHead"><?php echo getTranslation($lang, 'Logics') ?></div>
                </div>
            </div>
            <div class="mode_flex row">
                <?php
                // Состояние логик 
                foreach ($sessionInfo['logic'] as $_tmp_logicArray) {
                    echo '<div class="mode_flex column"><div class="divTableCell"><div class="';
                    if ($_tmp_logicArray['is_active']) {
                        echo 'active-mode-cell';
                    } else {
                        echo 'disabled-mode-cell';
                    };
                    echo '" title="">' . $_tmp_logicArray['name'] . '</div></div></div>';
                }
                $_tmp_logicArray = '';
                ?>
            </div>
        </div>
        <br>

        <!-- Модули -->
        <div class="mode_flex" id="modulesInfoTable">
            <div class="mode_flex row">
                <div class="mode_flex column">
                    <div class="divTableHead"><?php echo getTranslation($lang, 'Modules') ?></div>
                </div>
            </div>
            <?php

            // debug_getsessionInfo ver 0.1.2
            if (!empty($sessionInfo['module'])) {
                $moduleIndex = 0; // Добавляем счетчик для определения четности
                $moduleCount = count($sessionInfo['module']); // Общее количество модулей

                foreach ($sessionInfo['module'] as $moduleName => $_tmp_moduleArray) {
                    // Модули разбиваем две колонки    
                    if ($moduleIndex % 2 == 0) echo '<div class="mode_flex row">';

                    echo '<div class="mode_flex column"><div class="divTableCell"><div class="';
                    // Для обычных модулей устанавливаем класс disabled/active;
                    // Для активных модулей Frn и EchoLink устанавливаем класс active/paused в зависимости от состояния подключения к серверу (isModuleConnected) 
                    if (($moduleName == "Frn" || $moduleName == "EchoLink") && $moduleName == $sessionInfo['active_module']) {
                        echo $_tmp_moduleArray['is_connected'] ? 'active-mode-cell' : 'paused-mode-cell';
                    } else {
                        echo $_tmp_moduleArray['is_active']  ? 'active-mode-cell' : 'disabled-mode-cell';
                    };
                    echo '" title="">' . $moduleName . '</div></div></div>';

                    // Закрываем строку если это второй элемент в строке или последний элемент
                    if ($moduleIndex % 2 == 1 || $moduleIndex == $moduleCount - 1) echo '</div>';

                    $moduleIndex++; // Увеличиваем счетчик
                }
            }
            // /debug_getsessionInfo ver 0.1.2
            ?>
        </div>
        <br>


        <!-- Блок состояния подключения логики или модулей -->
        <?php
        include 'debug_panel_active.php';
        ?>



        <!-- Блок состояния APRS () -->
        <!-- TODO: заголовок - ссылка на адрес сервера APRS карты -->
        <br>
        <div class="divTable">
            <div class="divTableHead"><a class="tooltip" href="https://www.aprs.mx/views/search.php?q=<?php echo $callsign;?>" target="_new"><?php echo getTranslation($lang, 'APRS Status'); ?></a></div>
            <div class="divTableBody">

                <div class="divTableRow center">
                    <div class="divTableHeadCell"><?php echo getTranslation($lang, 'Server'); ?></div>
                    <!-- TODO: титул ссылки -->
                    <div class="divTableCell cell_content center" title="Connected to Pool:"><?php echo substr($svxconfig['LocationInfo']['APRS_SERVER_LIST'], 0, 15); ?></div>
                </div>
                <div class="divTableRow center">
                    <div class="divTableHeadCell"><?php echo getTranslation($lang, 'EchoLink'); ?></div>
                    <div class="divTableCell cell_content center">
                        <div style="background: #949494;"><a href="#" target="_new"><?php echo substr($svxconfig['LocationInfo']['STATUS_SERVER_LIST'], 0, 15); ?></a></div>
                    </div>
                </div>
            </div>
        </div>
        <br>

        <!-- Блок DTMF -->
        <?php include 'debug_keypad_module.php'; ?>
    </div>
</div>

<!-- Основное содержимое -->
<div class="content">

    <?php

    include 'debug_block_radio_status.php';



    include 'debug_block_nearby_activity.php';



    include 'debug_block_local_activity.php';

    ?>
</div>

<!-- Добавляем блок отладки -->
<!-- <div id="session_debug"> -->
    <?php //include "debug_block_debug.php"; ?>
<!-- </div> -->