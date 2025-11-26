<?php

/**
 * @date 2021-11-23
 * @version 0.1.3
 * @circool
 * @file debug_block_tg.php
 * @note Мониторится может несколько групп
 * @description Отображение блока разговорных групп рефлектора
 * @author vladimir@tsurkanenko.ru
 * 
 */

include_once __DIR__ . "/debug_config.php";

$sessionInfo['logic']['ReflectorLogic']['talkgroups']['temp_monitoring'] = debug_getTemporaryMonitor();
$sessionInfo['logic']['ReflectorLogic']['talkgroups']['selected'] = debug_getSelectedTG();
if ($sessionInfo['logic']['ReflectorLogic']['talkgroups']['selected'] == '0') {
    $sessionInfo['logic']['ReflectorLogic']['talkgroups']['selected'] = $sessionInfo['logic']['ReflectorLogic']['talkgroups']['default'];
}
?>
<div id="tgStatus" class="mode_flex">
    <div class="mode_flex row">
        <div class="mode_flex column">
            <div class="divTableHead"><?php echo getTranslation($lang, 'Monitored Talkgroups'); ?></div>
        </div>
        <div class="mode_flex row">
            <div class="mode_flex row">
                <?php
                // Мониторящиеся и активная группы
                if (isset($sessionInfo['logic']['ReflectorLogic']['talkgroups']['monitoring'])) {
                    foreach ($sessionInfo['logic']['ReflectorLogic']['talkgroups']['monitoring'] as $_tg) {
                        if ($_tg == $sessionInfo['logic']['ReflectorLogic']['talkgroups']['selected']) {
                            echo '<div class="mode_flex column active-mode-cell" title="' . $_tg . ' " style="border: .5px solid #3c3f47;">' . $_tg . '</div>';
                        } else {
                            echo '<div class="mode_flex column disabled-mode-cell" title="' . $_tg . ' " style="border: .5px solid #3c3f47;">' . $_tg . '</div>';
                        }
                    };
                };


                // Временно мониторящиеся группа
                if (isset($sessionInfo['logic']['ReflectorLogic']['talkgroups']['temp_monitoring'])) {
                    foreach ($sessionInfo['logic']['ReflectorLogic']['talkgroups']['temp_monitoring'] as $_mtg) {
                        $_format_cell = $_mtg == $sessionInfo['logic']['ReflectorLogic']['talkgroups']['selected'] ? "active-mode-cell" : "paused-mode-cell";
                        echo '<div class="mode_flex column ' . $_format_cell . ' " title="' . $_mtg . ' " style="border: .5px solid #3c3f47;">' . $_mtg . '</div>';
                    };
                };

                ?>
            </div>
        </div>
    </div>
</div>