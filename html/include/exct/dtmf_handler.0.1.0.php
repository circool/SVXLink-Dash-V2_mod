<?php
/**
 * @filesource /include/exct/dtmf_handler.0.1.0.php
 * @version 0.1.0
 * @date 2026.01.16
 * @author vladimir@tsurkanenko.ru
 * @description Обработчик DTMF команд для всех модулей
 */

// Инициализация сессии с теми же параметрами, что и в основной системе
if (session_status() === PHP_SESSION_NONE) {
    require_once $_SERVER["DOCUMENT_ROOT"] . '/include/settings.php';
    session_set_cookie_params(SESSION_LIFETIME, SESSION_PATH);
    session_name(SESSION_NAME);
    session_id(SESSION_ID);
    session_start();
}

// header('Content-Type: application/json');

/**
 * Получение пути к DTMF устройству
 * @return string|null Путь к устройству или null если не найден
 */
function getDtmfPath() {
    // 1. Пробуем из сессии (самый быстрый способ)
    if (isset($_SESSION['DTMF_CTRL_PTY']) && !empty($_SESSION['DTMF_CTRL_PTY'])) {
        return $_SESSION['DTMF_CTRL_PTY'];
    }
    
    // 2. Пробуем из данных статуса в сессии
    if (isset($_SESSION['status']['logic'])) {
        foreach ($_SESSION['status']['logic'] as $logic) {
            if (!empty($logic['dtmf_cmd'])) {
                $_SESSION['DTMF_CTRL_PTY'] = $logic['dtmf_cmd'];
                return $logic['dtmf_cmd'];
            }
        }
    }
    
    // 3. По умолчанию
    $defaultPath = '/dev/shm/dtmf_ctrl';
    if (file_exists($defaultPath)) {
        $_SESSION['DTMF_CTRL_PTY'] = $defaultPath;
        return $defaultPath;
    }
    
    return null;
}

/**
 * Отправка команды в DTMF устройство
 * @param string $command Команда для отправки
 * @param string $dtmfPath Путь к устройству
 * @return bool Успешно ли отправлено
 */
function sendDtmfCommand($command, $dtmfPath) {
    if (empty($command) || empty($dtmfPath)) {
        return false;
    }
    
    // Проверяем существование устройства
    if (!file_exists($dtmfPath)) {
        return false;
    }
    
    // Проверяем доступность для записи
    if (!is_writable($dtmfPath)) {
        return false;
    }
    
    // Отправляем команду
    $result = file_put_contents($dtmfPath, $command . PHP_EOL);
    return $result !== false;
}

// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => 'Unknown error'];
    
    try {
        // Получаем параметры
        $command = $_POST['command'] ?? '';
        $source = $_POST['source'] ?? 'unknown';
        
        if (empty($command)) {
            $response = ['status' => 'error', 'message' => 'Empty command'];
            echo json_encode($response);
            exit;
        }
        
        // Получаем путь к DTMF устройству
        $dtmfPath = getDtmfPath();
        
        if (empty($dtmfPath)) {
            $response = ['status' => 'error', 'message' => 'DTMF path not configured'];
            echo json_encode($response);
            exit;
        }
        
        // Отправляем команду
        if (sendDtmfCommand($command, $dtmfPath)) {
            $response = [
                'status' => 'success', 
                'message' => 'Command sent: ' . htmlspecialchars($command),
                'source' => $source
            ];
        } else {
            $response = [
                'status' => 'error', 
                'message' => 'Failed to send command to: ' . htmlspecialchars($dtmfPath),
                'source' => $source
            ];
        }
    } catch (Exception $e) {
        $response = [
            'status' => 'error', 
            'message' => 'Server error: ' . $e->getMessage(),
            'source' => $source ?? 'unknown'
        ];
    }
    
    echo json_encode($response);
    exit;
}

// Если запрос не POST
echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>