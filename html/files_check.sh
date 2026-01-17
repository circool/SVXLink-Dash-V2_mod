#!/bin/bash
# =============================================
# Проверка файловой структуры SvxLink Dashboard
# Упрощенная версия
# =============================================

# Корневая директория проекта
PROJECT_ROOT="${1:-/var/www/html}"

# Проверяем существование корневой директории
if [ ! -d "$PROJECT_ROOT" ]; then
    echo "Ошибка: Корневая директория проекта не найдена: $PROJECT_ROOT"
    exit 1
fi

cd "$PROJECT_ROOT"

echo "============================================="
echo "Проверка файловой структуры SvxLink Dashboard"
echo "Корневая директория: $PROJECT_ROOT"
echo "Время проверки: $(date)"
echo "============================================="

# Массив файлов для проверки (основные файлы из документации)
declare -A files_to_check=(
    # Корневые файлы
    ["index.php"]="Основная страница (продакшн)"
    ["index_debug.php"]="Основная страница для отладочного режима"
    ["backup.sh"]="Скрипт резервного копирования"
    ["favicon.ico"]="Иконка сайта"
    ["ws_state.php"]="Состояние WebSocket"

    
    # Директории
    ["include/"]="PHP включаемые файлы"
    ["scripts/"]="JS скрипты"
    ["css/"]="Стили"
    ["fonts/"]="Шрифты"
    ["install/"]="Установочные скрипты"
    ["config/"]="Конфигурационные файлы"
    
    # Основные файлы в include/
    ["include/auth_config.php"]="Конфигурация авторизации"
    ["include/auth_handler.php"]="Обработчик авторизации"
    ["include/authorise.php"]="Авторизация"
    ["include/browserdetect.php"]="Подстройка под браузер"
    ["include/change_password.php"]="Смена пароля"
    ["include/connection_details.php"]="Детальная информация о текущем соединении"
    ["include/footer.php"]="Подвал" 
    ["include/init.php"]="Основная инициализация"
    ["include/js_utils.php"]="JavaScript утилиты"
    ["include/keypad.php"]="DTMF клавиатура"
    ["include/left_panel.php"]="Левая панель состояний"
    ["include/logout.php"]="Выход из системы"
    ["include/macros.php"]="Макросы"
    ["include/monitor.php"]="Мониторинг аудио"
    ["include/macros.php"]="Макросы"
    ["include/net_activity.php"]="История сетевой активности"
    ["include/radio_activity.php"]="Состояние приемника/передатчика"
    ["include/reflector_activity.php"]="Данные рефлекторов"
    ["include/reset_auth.php"]="Сброс авторизации"
    ["include/rf_activity.php"]="История событий локальной активности"
    ["include/session_header.php"]="Легковесное открытие сессии"
		["include/settings.php"]="Настройки приложения"
		["include/top_menu.php"]="Основное меню команд"
		["include/websocket_client_config.php"]="Конфигурация WebSocket клиента"
		["include/websocket_server.php"]="Проверка и запуск WebSocket сервера"
		["include/settings.php"]="Настройки приложения"
    
    # Основные файлы в scripts/
    ["scripts/dashboard_ws_client.js"]="WebSocket клиент состояний"
    ["scripts/dashboard_ws_server.js"]="WebSocket сервер состояний"
    ["scripts/featherlight.js"]="Библиотека"
    ["scripts/svxlink-audio-proxy-server.js"]="WebSocket Audio Monitor"
    ["scripts/jquery.min.js"]="jQuery библиотека"
    
    # Основные файлы в css/
    ["css/css-mini.php"]="Минимальный набор стилей"
    ["css/css.php"]="Основной набор стилей"
    ["css/menu.php"]="Дополнения к основному набору"
    ["css/websocket_control.css"]="Стили для кнопки управления WS"
    ["css/font-awesome.min.css"]="Awesome Fonts"
)

# Счетчики
total=0
found=0
missing=0
symlinks=0
broken_symlinks=0

echo -e "\n=== Проверка основных файлов ===\n"

# Функция проверки
check_item() {
    local path="$1"
    local description="$2"
    
    ((total++))
    
    if [[ "$path" == */ ]]; then
        # Это директория
        if [ -d "$path" ]; then
            echo "✓ Директория: $description ($path)"
            ((found++))
        else
            echo "❌ Отсутствует директория: $description ($path)"
            ((missing++))
        fi
    else
        # Это файл
        if [ -L "$path" ]; then
            # Симлинк
            ((symlinks++))
            if [ -e "$path" ]; then
                target=$(readlink -f "$path" 2>/dev/null || echo "не удалось прочитать")
                echo "✓ Симлинк: $description"
                echo "  → Указывает на: $target"
                ((found++))
            else
                echo "❌ Битый симлинк: $description ($path)"
                ((broken_symlinks++))
                ((missing++))
            fi
        elif [ -f "$path" ]; then
            # Обычный файл
            echo "✓ Файл: $description ($path)"
            ((found++))
        else
            # Файл отсутствует
            echo "❌ Отсутствует: $description ($path)"
            ((missing++))
        fi
    fi
}

# Проверяем все файлы
for filepath in "${!files_to_check[@]}"; do
    check_item "$filepath" "${files_to_check[$filepath]}"
done

echo -e "\n=== Дополнительные проверки ===\n"

# Проверка лог файла svxlink
echo "Проверка лог файла SvxLink:"
if [ -f "/var/log/svxlink" ]; then
    echo "  ✓ /var/log/svxlink существует"
else
    echo "  ❌ /var/log/svxlink не существует"
fi

# Проверка конфигурации svxlink
echo -e "\nПроверка конфигурации SvxLink:"
if [ -d "/etc/svxlink" ]; then
    echo "  ✓ Директория /etc/svxlink существует"
    if [ -f "/etc/svxlink/svxlink.conf" ]; then
        echo "  ✓ Конфиг svxlink.conf существует"
    fi
else
    echo "  ❌ Директория /etc/svxlink не существует"
fi

# Проверка сервисов
echo -e "\nПроверка системных сервисов:"

check_service() {
    local service="$1"
    if systemctl is-active --quiet "$service" 2>/dev/null; then
        echo "  ✓ $service: активен"
    else
        echo "  ❌ $service: не активен"
    fi
}

check_service "apache2"
check_service "svxlink-audio-proxy.service"

echo -e "\n=== Статистика ===\n"

echo "Всего проверено: $total"
echo "Найдено: $found"
echo "Отсутствует: $missing"
echo "Симлинков: $symlinks"
echo "Битых симлинков: $broken_symlinks"

if [ $total -gt 0 ]; then
    percentage=$((found * 100 / total))
    echo "Процент наличия: $percentage%"
fi

echo -e "\n=== Краткий отчет ===\n"

if [ $missing -eq 0 ] && [ $broken_symlinks -eq 0 ]; then
    echo "✅ Все основные файлы присутствуют и симлинки целы!"
elif [ $missing -lt 5 ]; then
    echo "⚠️  Отсутствует несколько файлов ($missing)"
else
    echo "❌ Отсутствует много файлов ($missing)"
fi

if [ $broken_symlinks -gt 0 ]; then
    echo "❌ Обнаружены битые симлинки: $broken_symlinks"
fi

echo -e "\n============================================="