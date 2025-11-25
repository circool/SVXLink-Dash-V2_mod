<?php

/**
 * @date 2021-11-23
 * @version 0.1.2
 * @description Формирование блока состояния модулей или логик
 * Блок состояния активной логики или модуля
 * Если задан $sessionInfo['active_module'], то отображается состояние модуля
 * иначе, если задан $sessionInfo['active_logic'], то отображается состояние логики
 */



?>
<div class="mode_flex">
    <div class="mode_flex row">
        <div class="mode_flex column">
            <div class="divTableHead">
                <!-- Шапка блока с временем работы -->
                <?php
                if (!empty($sessionInfo['active_module'])) {
                    // echo $sessionInfo['active_module'];
                    if (!empty($sessionInfo['module'][$sessionInfo['active_module']]['duration'])) {
                        echo '<br>' . getTranslation($lang, 'uptime') . ': ' . date("H:i:s", $sessionInfo['module'][$sessionInfo['active_module']]['duration']);
                    }
                } else if (!empty($sessionInfo['active_logic'])) {
                    echo $sessionInfo['active_logic'];
                    $logic = $sessionInfo['logic'][$sessionInfo['active_logic']];
                    if (!empty($logic['duration'])) {
                        echo '<br>' . getTranslation($lang, 'uptime') . ': ' . date("H:i:s", $logic['duration']);
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <!-- блок с параметрами логики или модуля -->
    <?php if (empty($sessionInfo['active_module'])) { ?>
    <div class="divTable">
        <div class="divTableBody">

            <div class="divTableRow center">
                <div class="divTableHeadCell"><?php echo getTranslation($lang, 'Server'); ?></div>
                <div class="divTableCell cell_content center" title="Connected to Pool: rotate.aprs2.ru"><?php echo $sessionInfo['logic'][$sessionInfo['active_logic']]['hosts']; ?></div>
            </div>

        </div>
    </div>
    <?php };
    
    if (empty($sessionInfo['active_module'])) {
        include 'debug_block_tg.php';
    } else {
        include 'debug_block_module.php';
    }
    ?>
 
</div>