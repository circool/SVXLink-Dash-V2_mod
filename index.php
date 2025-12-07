<?php

/**
 * Debug Version Redirect Page
 * Redirects to debug_index.php after showing a warning message
 */

// Configuration - set redirect delay in seconds
$redirectDelay = 12;

// Set content type and disable caching
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Redirect after specified delay
header("Refresh: $redirectDelay; url=debug_index.php");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Version Notice</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            font-family: 'Arial', sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #ecf0f1;
        }

        .debug-notice {
            background: rgba(52, 73, 94, 0.9);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            text-align: center;
            max-width: 500px;
            width: 90%;
            border: 1px solid #4a6572;
            backdrop-filter: blur(10px);
        }

        .debug-title {
            color: #bdc3c7;
            font-size: 2.2em;
            margin-bottom: 20px;
            font-weight: 300;
            letter-spacing: 1px;
        }

        .debug-message {
            color: #95a5a6;
            font-size: 1.3em;
            line-height: 1.6;
            margin-bottom: 25px;
            font-weight: 300;
        }

        .debug-warning {
            color: #e74c3c;
            font-size: 1.1em;
            font-weight: 400;
            margin-top: 20px;
            padding: 12px;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 6px;
            border: 1px solid #c0392b;
        }

        .redirect-countdown {
            color: #3498db;
            font-size: 1em;
            margin-top: 15px;
            font-weight: 400;
        }

        .spinner {
            border: 3px solid rgba(52, 73, 94, 0.3);
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        a {
            color: #ffffff !important;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        a:link {
            color: #ffffff !important;
        }

        a:visited {
            color: #ffffff !important;
        }

        a:hover {
            color: #ffffff !important;
            text-decoration: underline;
            opacity: 0.9;
        }

        a:active {
            color: #ffffff !important;
            opacity: 0.8;
        }

        a:focus {
            color: #ffffff !important;
            outline: 2px solid #3498db;
            outline-offset: 2px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .version-info {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 20px;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="debug-notice">
        <div class="debug-title">DEBUG VERSION</div>

        <div class="debug-message">
            You are currently using the debug version of the application.<br>
            This version is intended for development and testing purposes only.
        </div>
        <div class="debug-message">
            <a href="debug_index.php">Click here to continue</a>
        </div>

        <div class="debug-warning">
            ⚠️ This version may contain unstable features and debugging information.
        </div>

        <div class="spinner"></div>

        <div class="redirect-countdown">
            Redirecting to debug interface in <span id="countdown"><?php echo $redirectDelay; ?></span> seconds...
        </div>

        <div class="version-info">
            For production use, please use the stable release version.
        </div>
    </div>

    <script>
        // Countdown timer
        let countdown = <?php echo $redirectDelay; ?>;
        const countdownElement = document.getElementById('countdown');

        const timer = setInterval(() => {
            countdown--;
            if (countdown > 0) {
                countdownElement.textContent = countdown;
            } else {
                clearInterval(timer);
                countdownElement.textContent = '0';
            }
        }, 1000);
    </script>
</body>

</html>