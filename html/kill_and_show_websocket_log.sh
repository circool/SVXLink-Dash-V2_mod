#!/bin/bash

# Останавливаем старый сервер
echo "Останавливаем WebSocket сервер..."
sudo pkill -f "dashboard_ws_server" 2>/dev/null
sleep 1

# Очищаем старые файлы
PID_FILE="/var/www/html/dashboard_websocket.pid"
[ -f "$PID_FILE" ] && sudo rm "$PID_FILE" || echo "PID файл отсутствует"

# Очищаем лог (но оставляем файл)
LOG_FILE="/var/www/html/websocket_server.log"
sudo truncate -s0 "$LOG_FILE" 2>/dev/null || sudo > "$LOG_FILE"

echo "Лог файл: $LOG_FILE"
echo "Мониторим лог сервера..."
echo "=========================="

# Мониторим лог в реальном времени
sudo tail -f "$LOG_FILE"
