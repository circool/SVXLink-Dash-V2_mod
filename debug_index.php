<?php
// Отладочный режим
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// обязательно
if (isset($_POST['auth'])) {
    $_SESSION['auth'] = 'AUTHORISED';
} else {
    $_SESSION['auth'] = 'UNAUTHORISED';
}
define('DEBUG', false);
include_once "include/debug_settings.php";
include_once "include/debug_config.php";
include_once "include/debug_init.php";
if (DEBUG) {
    error_log("=== INIT COMPLETTED ===");
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index" />
    <meta name="robots" content="follow" />
    <meta name="language" content="English" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="generator" content="SVXLink" />
    <meta name="Author" content="G4NAB, SP2ONG, SP0DZ, R2ADU" />
    <meta name="Description" content="Dashboard for SVXLink by G4NAB, SP2ONG, SP0DZ, R2ADU" />
    <meta name="KeyWords" content="SVXLink,G4NAB, SP2ONG, SP0DZ, R2ADU" />
    <meta http-equiv="cache-control" content="max-age=0" />
    <meta http-equiv="cache-control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="expires" content="0" />
    <meta http-equiv="pragma" content="no-cache" />
    <link rel="shortcut icon" href="images/favicon.ico" sizes="16x16 32x32" type="image/png">
    <?php echo "<title>SvxLink Dashboard by R2ADU (" . $callsign . ") Ver 0.1</title>"; ?>
    <?php include_once "include/browserdetect.php"; ?>
    <script type="text/javascript" src="scripts/jquery.min.js"></script>
    <script type="text/javascript" src="scripts/functions.js"></script>
    <!-- <script type="text/javascript" src="scripts/pcm-player.min.js"></script> -->
    <script type="text/javascript">
        $.ajaxSetup({
            cache: false
        });
        window.time_format = '<?php echo constant("TIME_FORMAT"); ?>';
    </script>

    <link href="css/debug_featherlight.css" type="text/css" rel="stylesheet" />
    <script src="scripts/debug_featherlight.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" type="text/css" href="/css/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="/css/debug_fonts.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="SmallHeader shLeft noMob"><a style="border-bottom: 1px dotted;" class="tooltip" href="#">Имя узла: <span><strong>Системный IP адрес<br></strong><?php echo str_replace(',', ',<br />', exec("hostname -I | awk '{print $1}'")); ?></span><?php echo exec('cat /etc/hostname'); ?></a></div>
            <div class="SmallHeader shRight noMob">
                <div id="CheckUpdate">
                    <span title="SvxLink Dashboard Ver 2.3">SvxLink Dashboard by R2ADU debug</span>
                </div><br />
            </div>

            <h1>DEBUG SvxLink Dashboard <code style="font-weight:550;">(<?php echo $callsign; ?>) </code></h1>
            <div id="CheckMessage"></div>

            <div class="navbar">
                <div class="headerClock">
                    <span id="DateTime"><?php echo date('d-m-Y H:i:s'); ?></span>
                </div>
                <?php include_once "include/debug_top_menu.php"; ?>
            </div>
        </div>

        <div class="contentwide">
            <!-- панель статуса вверху страницы -->
            <div id="statusInfo"><?php //include "include/debug_sw_menu.php";
                                    ?></div>
        </div>
        <br class="noMob">
        <!-- далее блоки nav и content -->
        <div id="mainPage">
            <?php include "include/debug_main.php"; ?>
        </div>

        <div>
            <!-- footer -->
            <?php include "include/debug_footer.php"; ?>

        </div>
        <div id="session_debug">
            <?php
            if (DEBUG) {
                dlog("Try load debug_block.php");
                include "include/debug_block.php";
            }
            ?>
        </div>
        <script type="text/javascript">
            function updateClock() {
                const now = new Date();
                const dateTimeString = now.toLocaleString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }).replace(/,/g, '').replace(/(\d+)\.(\d+)\.(\d+)/, '$1-$2-$3');

                document.getElementById('DateTime').textContent = dateTimeString;
            }
            updateClock();
            setInterval(updateClock, 1000);
        </script>


        <script type="text/javascript">
            // function reloadStatusInfo() {
            //     $("#statusInfo").load("include/debug_status_panel_emty.php", function() {
            //         setTimeout(reloadStatusInfo, 1500)
            //     });
            // }
            // setTimeout(reloadStatusInfo, 1500);

            // function reloadMainPage() {
            //     $("#mainPage").load("include/debug_main.php", function() {
            //         setTimeout(reloadMainPage, 9000);
            //     });
            // }
            // setTimeout(reloadMainPage, 9000);
        </script>
</body>

</html>