#!/bin/bash
# =============================================
# Проверка файловой структуры SvxLink Dashboard
# Упрощенная версия
# =============================================

# Корневая директория проекта
PROJECT_ROOT="${1:-/var/www/html}"

# Проверяем существование корневой директории
if [ ! -d "$PROJECT_ROOT" ]; then
    echo "Error: Root dir not found: $PROJECT_ROOT"
    exit 1
fi

cd "$PROJECT_ROOT"

echo "============================================="
echo "Checking SvxLink Dashboard file structure"
echo "Root directory: $PROJECT_ROOT"
echo "Check time: $(date)"
echo "============================================="

# Массив файлов для проверки (основные файлы из документации)
declare -A files_to_check=(
    # Корневые файлы
    ["index.php"]="Main page (prod)"
    ["index_debug.php"]="Main page for beta-testing"
    ["backup.sh"]="Backup script"
    ["favicon.ico"]="Icon"
    ["ws_state.php"]="WebSocket state"

    
    # Директории
    ["include/"]="PHP files"
    ["scripts/"]="JS scripts"
    ["css/"]="CSS Styles"
    ["fonts/"]="Fonts"
    ["install/"]="Installation scripts (todo)"
    ["config/"]="Configuration files"
    
    # Основные файлы в include/
    ["include/ajax_update.php"]="AJAX update"
    ["include/auth_config.php"]="Authentification configuration"
    ["include/auth_handler.php"]="Authentification handler"
    ["include/authorise.php"]="Authentification"
    ["include/browserdetect.php"]="Browser detect"
    ["include/change_password.php"]="Password changer"
    ["include/connection_details.php"]="Connection details"
    ["include/dtmf_handler.php"]="DTMF command handler" 
    ["include/footer.php"]="Footer" 
    ["include/init.php"]="Main init"
    ["include/js_utils.php"]="JavaScript utils"
    ["include/keypad.php"]="DTMF keypad"
    ["include/left_panel.php"]="Left panel (status panel)"
    ["include/logout.php"]="Logout handler"
    ["include/macros.php"]="Macros"
    ["include/monitor.php"]="Audio monitor"
    ["include/net_activity.php"]="Network activity history"
    ["include/radio_activity.php"]="Radio activity state"
    ["include/reflector_activity.php"]="Reflectors activity state & history"
    ["include/reset_auth.php"]="Authorisation killer"
    ["include/rf_activity.php"]="Local activity history"
    ["include/session_header.php"]="Light session open"
	["include/settings.php"]="Main settings constants"
	["include/top_menu.php"]="Top menu"
	["include/websocket_client_config.php"]="WebSocket client configuration"
	["include/websocket_server.php"]="WebSocket server handler"
	["include/fn/dlog.php"]="Debug logger"
	["include/fn/formatDuration.php"]="Time string formatting"
	["include/fn/getActualStatus.php"]="Actualizer state"
	["include/fn/getLineTime.php"]="Time parser"
	["include/fn/getServiceStatus.php"]="Service state checker"
	["include/fn/getTranslation.php"]="Translate tool"
	["include/fn/logTailer.php"]="Log handler"
	["include/fn/parseXmlTags.php"]="XML-parser"
	["include/fn/removeTimestamp.php"]="Timestamp remover"
	
    
    # Основные файлы в scripts/
    ["scripts/dashboard_ws_client.js"]="WebSocket client"
    ["scripts/dashboard_ws_server.js"]="WebSocket server"
    ["scripts/featherlight.js"]="Library"
    ["scripts/svxlink-audio-proxy-server.js"]="WebSocket Audio Monitor"
    ["scripts/jquery.min.js"]="jQuery lib"
    
    # Основные файлы в css/
    ["css/css-mini.php"]="Styles"
    ["css/css.php"]="Main style set"
    ["css/menu.php"]="Addition styles"
    ["css/websocket_control.css"]="Styles for WS button WS"
    ["css/font-awesome.min.css"]="Awesome Fonts"
)

# Счетчики
total=0
found=0
missing=0
symlinks=0
broken_symlinks=0

echo -e "\n=== Main files checking ===\n"

# Функция проверки
check_item() {
    local path="$1"
    local description="$2"
    
    ((total++))
    
    if [[ "$path" == */ ]]; then
        # Это директория
        if [ -d "$path" ]; then
            echo "Dir: $description ($path)"
            ((found++))
        else
            echo "❌ Missing dir: $description ($path)"
            ((missing++))
        fi
    else
        # Это файл
        if [ -L "$path" ]; then
            # Симлинк
            ((symlinks++))
            if [ -e "$path" ]; then
                target=$(readlink -f "$path" 2>/dev/null || echo "read error")
                echo "Symlink: $description"
                echo "  → Point to: $target"
                ((found++))
            else
                echo "❌ Broken symlink: $description ($path)"
                ((broken_symlinks++))
                ((missing++))
            fi
        elif [ -f "$path" ]; then
            # Обычный файл
            echo "File: $description ($path)"
            ((found++))
        else
            # Файл отсутствует
            echo "❌ Missing: $description ($path)"
            ((missing++))
        fi
    fi
}

# Проверяем все файлы
for filepath in "${!files_to_check[@]}"; do
    check_item "$filepath" "${files_to_check[$filepath]}"
done

echo -e "\n=== Additional checks ===\n"

# Проверка лог файла svxlink
echo "SvxLink log file:"
if [ -f "/var/log/svxlink" ]; then
    echo "  /var/log/svxlink found"
else
    echo "  ❌ /var/log/svxlink not found"
fi

# Проверка конфигурации svxlink
echo -e "\nSvxLink configuration file:"
if [ -d "/etc/svxlink" ]; then
    echo "  Dir /etc/svxlink exist"
    if [ -f "/etc/svxlink/svxlink.conf" ]; then
        echo "  Config svxlink.conf exist"
    fi
else
    echo "  ❌ Dir /etc/svxlink not found"
fi

# Проверка сервисов
echo -e "\nSystem service check:"

check_service() {
    local service="$1"
    if systemctl is-active --quiet "$service" 2>/dev/null; then
        echo "  $service: is active"
    else
        echo "  ❌ $service: not active"
    fi
}

check_service "apache2"
check_service "svxlink-audio-proxy.service"

echo -e "\n=== Summary ===\n"

echo "Total checked: $total"
echo "Found: $found"
echo "Missing: $missing"
echo "Symlinks: $symlinks"
echo "Broken symlinks: $broken_symlinks"

if [ $total -gt 0 ]; then
    percentage=$((found * 100 / total))
    echo "Ready percent: $percentage%"
fi

echo -e "\n=== Short ===\n"

if [ $missing -eq 0 ] && [ $broken_symlinks -eq 0 ]; then
    echo "✅ All essential files are in place, and the symbolic links are valid!"
else
    echo "❌ Several files are missing ($missing)"
fi

if [ $broken_symlinks -gt 0 ]; then
    echo "❌ Found broken symlinks: $broken_symlinks"
fi

echo -e "\n============================================="