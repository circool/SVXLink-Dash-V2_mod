#!/bin/bash
# =============================================
# Установка SvxLink Dashboard by R2ADU
# =============================================

set -e  # Прерывать выполнение при ошибках

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Пути и константы
DASHBOARD_ROOT="/var/www/html"
SVXLINK_CONFIG_DIR="/etc/svxlink"
APACHE_CONFIG_FILE="/etc/apache2/sites-available/svxlink-dashboard.conf"
SERVICE_FILE="/etc/systemd/system/svxlink-dashboard.service"
AUTH_FILE="/etc/svxlink/dashboard.auth.ini"
SUDOERS_FILE="/etc/sudoers.d/svxlink-dashboard"

# Функция логирования
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Функция проверки прав суперпользователя
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "Этот скрипт должен запускаться с правами root"
        exit 1
    fi
}

# Функция подтверждения действия
confirm() {
    read -p "$1 (y/N): " -n 1 -r
    echo
    [[ $REPLY =~ ^[Yy]$ ]]
}

# Функция установки пакетов
install_packages() {
    log_info "Обновление списка пакетов..."
    apt-get update

    log_info "Установка необходимых пакетов..."
    apt-get install -y \
        apache2 \
        php \
        php-cli \
        php-common \
        php-curl \
        php-json \
        php-mbstring \
        php-xml \
        nodejs \
        npm \
        git \
        curl \
        whiptail

    log_info "Проверка установки PHP..."
    php --version
    log_info "Проверка установки Node.js..."
    node --version
}

# Настройка Apache
setup_apache() {
    log_info "Настройка Apache..."
    
    # Включаем необходимые модули
    a2enmod rewrite
    a2enmod headers
    
    # Создаем конфигурацию виртуального хоста
    cat > "$APACHE_CONFIG_FILE" << EOF
<VirtualHost *:80>
    ServerName svxlink-dashboard.local
    DocumentRoot $DASHBOARD_ROOT
    
    <Directory $DASHBOARD_ROOT>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/svxlink-dashboard-error.log
    CustomLog \${APACHE_LOG_DIR}/svxlink-dashboard-access.log combined
</VirtualHost>
EOF

    # Активируем сайт и отключаем стандартный
    a2ensite svxlink-dashboard.conf
    a2dissite 000-default.conf
    
    # Перезапускаем Apache
    systemctl restart apache2
}

# Создание пользователя и групп
setup_users() {
    log_info "Настройка пользователей и групп..."
    
    # Создаем группу svxlink если ее нет
    if ! getent group svxlink > /dev/null; then
        groupadd svxlink
    fi
    
    # Создаем пользователя svxlink если его нет
    if ! id svxlink > /dev/null; then
        useradd -r -s /bin/bash -g svxlink svxlink
    fi
    
    # Добавляем www-data в группу svxlink
    usermod -a -G svxlink www-data
}

# Клонирование или копирование проекта
setup_project() {
    log_info "Настройка проекта..."
    
    # Если директория уже существует, создаем backup
    if [ -d "$DASHBOARD_ROOT" ]; then
        timestamp=$(date +%Y%m%d_%H%M%S)
        backup_dir="/var/backups/svxlink-dashboard_$timestamp"
        log_info "Создание бэкапа существующей установки в $backup_dir"
        mkdir -p "$backup_dir"
        cp -r "$DASHBOARD_ROOT"/* "$backup_dir/" 2>/dev/null || true
    fi
    
    # Создаем структуру каталогов
    mkdir -p "$DASHBOARD_ROOT"
    mkdir -p "$DASHBOARD_ROOT/logs"
    mkdir -p "$SVXLINK_CONFIG_DIR"
    
    # Здесь должна быть логика копирования файлов проекта
    # Например, если проект в текущей директории:
    if [ -f "PROJECT_DETAILS.md" ]; then
        log_info "Копирование файлов проекта..."
        cp -r ./* "$DASHBOARD_ROOT/" 2>/dev/null || true
    else
        log_warn "Файлы проекта не найдены в текущей директории"
        log_info "Пожалуйста, скопируйте файлы проекта в $DASHBOARD_ROOT вручную"
    fi
    
    # Устанавливаем права
    chown -R svxlink:svxlink "$DASHBOARD_ROOT"
    chmod -R 755 "$DASHBOARD_ROOT"
    chmod 777 "$DASHBOARD_ROOT/logs"
}

# Настройка аутентификации
setup_auth() {
    log_info "Настройка аутентификации..."
    
    if [ ! -f "$AUTH_FILE" ]; then
        # Запрашиваем учетные данные
        DASHBOARD_USER=$(whiptail --title "Аутентификация Dashboard" --inputbox "Введите имя пользователя для dashboard:" 8 78 svxlink 3>&1 1>&2 2>&3)
        
        DASHBOARD_PASSWORD=$(whiptail --title "Аутентификация Dashboard" --passwordbox "Введите пароль для dashboard:" 8 78 3>&1 1>&2 2>&3)
        
        # Создаем файл аутентификации
        cat > "$AUTH_FILE" << EOF
[dashboard]
auth_user = '$DASHBOARD_USER'
auth_pass = '$DASHBOARD_PASSWORD'
EOF
        
        chown svxlink:svxlink "$AUTH_FILE"
        chmod 640 "$AUTH_FILE"
        log_info "Файл аутентификации создан"
    else
        log_info "Файл аутентификации уже существует"
    fi
}

# Настройка WebSocket сервиса
setup_websocket() {
    log_info "Настройка WebSocket сервера..."
    
    # Устанавливаем зависимости Node.js
    cd "$DASHBOARD_ROOT/scripts"
    
    if [ -f "package.json" ]; then
        sudo -u svxlink npm install
    else
        # Устанавливаем ws модуль
        sudo -u svxlink npm install ws
    fi
    
    # Создаем systemd сервис для WebSocket
    cat > "$SERVICE_FILE" << EOF
[Unit]
Description=SvxLink Dashboard WebSocket Server
After=network.target apache2.service
Wants=apache2.service

[Service]
Type=simple
User=svxlink
Group=svxlink
WorkingDirectory=$DASHBOARD_ROOT/scripts
ExecStart=/usr/bin/node dashboard_ws_server.js
Restart=always
RestartSec=5
Environment=NODE_ENV=production

StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF
    
    systemctl daemon-reload
    systemctl enable svxlink-dashboard.service
    systemctl start svxlink-dashboard.service
}

# Настройка sudoers
setup_sudoers() {
    log_info "Настройка прав sudo..."
    
    cat > "$SUDOERS_FILE" << EOF
# Права для SvxLink Dashboard
www-data ALL=(svxlink) NOPASSWD: /bin/systemctl restart svxlink-dashboard.service
www-data ALL=(svxlink) NOPASSWD: /bin/journalctl -u svxlink-dashboard.service -f
www-data ALL=(svxlink) NOPASSWD: /usr/bin/tail -F /var/log/svxlink/*
svxlink ALL=(ALL) NOPASSWD: /usr/bin/arecord
EOF
    
    chmod 440 "$SUDOERS_FILE"
    visudo -c -f "$SUDOERS_FILE"
}

# Настройка сервиса аудио мониторинга
setup_audio_monitor() {
    log_info "Настройка аудио мониторинга..."
    
    AUDIO_SERVICE_FILE="/etc/systemd/system/svxlink-audio-proxy.service"
    
    cat > "$AUDIO_SERVICE_FILE" << EOF
[Unit]
Description=SVXLink Audio Proxy Server
After=network.target

[Service]
Type=simple
User=svxlink
Group=svxlink
WorkingDirectory=$DASHBOARD_ROOT/scripts
ExecStart=/usr/bin/node svxlink-audio-proxy-server.js
Restart=always
RestartSec=5
Environment=NODE_ENV=production

StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF
    
    systemctl daemon-reload
    systemctl enable svxlink-audio-proxy.service
    
    log_info "Сервис аудио мониторинга настроен"
    log_info "Для запуска выполните: sudo systemctl start svxlink-audio-proxy.service"
}

# Настройка планировщика задач
setup_cron() {
    log_info "Настройка cron задач..."
    
    # Создаем скрипт очистки логов
    CLEANUP_SCRIPT="/usr/local/bin/cleanup-dashboard-logs.sh"
    
    cat > "$CLEANUP_SCRIPT" << 'EOF'
#!/bin/bash
# Очистка старых логов Dashboard

# Директории для очистки
LOG_DIRS=(
    "/var/www/html/logs"
    "/var/log/apache2"
)

DAYS_TO_KEEP=7

for dir in "${LOG_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        find "$dir" -type f -name "*.log" -mtime +$DAYS_TO_KEEP -delete
        echo "Очищены логи в $dir старше $DAYS_TO_KEEP дней"
    fi
done
EOF
    
    chmod +x "$CLEANUP_SCRIPT"
    
    # Добавляем в cron
    (crontab -l 2>/dev/null | grep -v "cleanup-dashboard-logs.sh"; echo "0 2 * * * $CLEANUP_SCRIPT") | crontab -
    
    log_info "Cron задача для очистки логов настроена"
}

# Настройка окружения
setup_environment() {
    log_info "Настройка окружения..."
    
    # Создаем директорию для временных файлов
    mkdir -p /var/run/svxlink
    chown svxlink:svxlink /var/run/svxlink
    chmod 775 /var/run/svxlink
    
    # Настраиваем timezone
    if [ -f /etc/timezone ]; then
        TIMEZONE=$(cat /etc/timezone)
        echo "date.timezone = $TIMEZONE" > /etc/php/$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')/apache2/conf.d/timezone.ini
    fi
}

# Функция проверки установки
verify_installation() {
    log_info "Проверка установки..."
    
    echo "================================"
    echo "Проверка статусов сервисов:"
    echo "================================"
    
    # Проверка Apache
    if systemctl is-active --quiet apache2; then
        log_info "Apache2: работает ✓"
    else
        log_error "Apache2: не работает ✗"
    fi
    
    # Проверка WebSocket сервиса
    if systemctl is-active --quiet svxlink-dashboard.service; then
        log_info "WebSocket сервис: работает ✓"
    else
        log_warn "WebSocket сервис: не работает (запустите вручную)"
    fi
    
    # Проверка доступности веб-интерфейса
    if curl -s http://localhost > /dev/null; then
        log_info "Веб-интерфейс: доступен ✓"
    else
        log_error "Веб-интерфейс: недоступен ✗"
    fi
    
    echo "================================"
    echo "Информация для доступа:"
    echo "================================"
    echo "Dashboard URL: http://$(hostname -I | awk '{print $1}')"
    echo "Dashboard URL: http://svxlink-dashboard.local"
    echo ""
    echo "Логи Apache: /var/log/apache2/"
    echo "Логи Dashboard: /var/www/html/logs/"
    echo ""
    echo "Управление сервисами:"
    echo "  sudo systemctl status svxlink-dashboard.service"
    echo "  sudo journalctl -u svxlink-dashboard.service -f"
    echo "  sudo systemctl restart apache2"
}

# Основная функция
main() {
    clear
    echo "============================================="
    echo "Установка SvxLink Dashboard by R2ADU"
    echo "Версия 0.4.x"
    echo "============================================="
    
    # Проверка прав
    check_root
    
    # Подтверждение установки
    if ! confirm "Начать установку SvxLink Dashboard?"; then
        log_info "Установка отменена"
        exit 0
    fi
    
    # Выполнение этапов установки
    install_packages
    setup_users
    setup_project
    setup_apache
    setup_auth
    setup_websocket
    setup_sudoers
    setup_audio_monitor
    setup_cron
    setup_environment
    
    # Финальная проверка
    verify_installation
    
    log_info "Установка завершена успешно!"
    log_info "Перейдите по адресу http://$(hostname -I | awk '{print $1}') для доступа к Dashboard"
}

# Обработка аргументов командной строки
case "$1" in
    --help|-h)
        echo "Использование: $0 [опции]"
        echo "Опции:"
        echo "  --help, -h    Показать эту справку"
        echo "  --silent      Тихая установка (без подтверждений)"
        echo "  --verify      Только проверка установки"
        exit 0
        ;;
    --silent)
        # Режим тихой установки
        ;;
    --verify)
        verify_installation
        exit 0
        ;;
    *)
        main
        ;;
esac

exit 0