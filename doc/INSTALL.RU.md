# Руководство по установке SVXLink Dashboard

**ВАЖНОЕ ЗАМЕЧАНИЕ**
Имейте ввиду что не принималось никаких специальных мер по обеспечению безопасного использования панели. 
Учитывайте это если предоставляете доступ к панели из интернета.

## Обязательные требования

1. **Часовой пояс**: Установите часовой пояс системы

```bash
sudo timedatectl set-timezone Europe/Moscow
# или вручную:
echo "Europe/Moscow" | sudo tee /etc/timezone
```

2. **Настройка SVXLink**: 

	2.1. Убедитесь, что в разделе `[GLOBAL]` конфигурации SVXLink установлен параметр `TIMESTAMP_FORMAT`, используйте формат `%d %b %Y %H:%M:%S.%f` для гарантированной работы.

```bash
sudo nano /etc/svxlink/svxlink.conf
```
Добавьте или раскомментируйте:

```
[GLOBAL]
TIMESTAMP_FORMAT = "%d %b %Y %H:%M:%S.%f"
```

2.2. Убедитесь что в разделе `[GLOBAL]` конфигурации SVXLink установлен параметр `LOCATION_INFO` а в указанном разделе установлен параметр `CALLSIGN`.
		
```
[GLOBAL]
...
LOCATION_INFO=LocationInfo
...
[LocationInfo]
CALLSIGN=...

```

3. **Установка веб-сервера (если не установлен)**:
```bash
sudo apt update
sudo apt install apache2 -y
```

4. **Установка PHP (если не установлен)**:
```bash
sudo apt install php php-json php-mbstring -y
```

5. **Установка Node.js (если не установлен)**:
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs -y
```

## Необязательные настройки

### 1. Управление DTMF

Для возможности управления через веб-интерфейс убедитесь, что в конфиге `svxlink.conf` для каждой из логик установлен уникальное значение параметра `DTMF_CTRL_PTY`:

Если используется несколько логик, установите разные значения для каждой.

В нужной логике (например, в разделе `[SimplexLogic]`) добавьте:

```
DTMF_CTRL_PTY = /dev/shm/simplex_ctrl
```

### 2. Аудио мониторинг
Для прослушивания эфира через браузер:

**2.1.** В конфиге SVXLink настройте логику на использование составного устройства:

Замените аудиоустройство в нужной логике:

```
[SimplexLogic]
#TX = Tx1
TX = MultiTx
```

**2.2.** Добавьте составное устройство и устройство для стриминга:
```
[TxStream]
TYPE = Local
AUDIO_DEV = alsa:plughw:Loopback,0,0
AUDIO_CHANNEL = 0
PTT_TYPE = NONE
TIMEOUT = 7200
TX_DELAY = 0
PREEMPHASIS = 0

[MultiTx]
TYPE = Multi
TRANSMITTERS = Tx1,TxStream
```

## Установка Dashboard

### Копирование файлов проекта

1. **Перейдите в директорию с проектом** 

2. **Скопируйте все содержимое в корень веб-сервера**:
   
```bash
sudo cp -r * /var/www/html/
```

3. **Установите права доступа**:
   
```bash
# Владелец файлов - (сервис svxlink)
sudo chown -R svxlink:svxlink /var/www/html/

# Права на директории - 755 (rwxr-xr-x)
sudo find /var/www/html/ -type d -exec chmod 755 {} \;

# Права на файлы - 644 (rw-r--r--)
sudo find /var/www/html/ -type f -exec chmod 644 {} \;

# JavaScript и PHP файлы должны быть исполняемыми для владельца
sudo chmod 755 /var/www/html/scripts/*.js
sudo chmod 755 /var/www/html/include/*.php
```

### Первый запуск и настройка авторизации

При первом обращении к сайту будет выполнено создание учетных данных администратора.

Вы будете перенаправлены на страницу /install/setup_auth.php

Нажмите кнопку **"Run Setup"**

Установщик создаст необходимые директории и файл с учетными данными

На экране отобразятся созданные учетные данные

Перейдите на главную страницу и авторизуйтесь

Смените пароль через меню входа

#### Ручная установка

Если по каким-то причинам автоматическая установка не сработала, выполните установку вручную.

```bash
# Создайте каталог для конфигурации
sudo mkdir -p /etc/svxlink/dashboard

# Установите владельца каталога (пользователь svxlink)
sudo chown svxlink:svxlink /etc/svxlink/dashboard
sudo chmod 755 /etc/svxlink/dashboard

# Скопируйте пример конфигурации
sudo cp /var/www/html/config/sample.auth.ini /etc/svxlink/dashboard/auth.ini

# Установите владельца файла (пользователь svxlink)
sudo chown svxlink:svxlink /etc/svxlink/dashboard/auth.ini
sudo chmod 644 /etc/svxlink/dashboard/auth.ini
```

### Учетные данные по умолчанию
- **Логин**: `svxlink`
- **Пароль**: `svxlink`


## Настройка аудио мониторинга

### Создание systemd сервиса

Создайте файл сервиса:

```bash
sudo nano /etc/systemd/system/svxlink-audio-proxy.service
```

Вставьте содержимое:

```ini
[Unit]
Description=SVXLink Node.js Server
After=network.target

[Service]
StandardOutput=journal
StandardError=journal
Restart=always
RestartSec=5
ExecReload=/bin/kill -HUP
TimeoutStopSec=10
Type=simple
User=svxlink
Group=svxlink
ExecStart=/usr/bin/node /var/www/html/scripts/svxlink-audio-proxy-server.js
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

### Управление сервисом аудио мониторинга

Для снижения нагрузки сервис автоматически отключается при отсутствии соединения и запускается при включении мониторинга в меню дашборда.

Для ручного управления сервисом используйте следующие команды

```bash
# Запустить сервер
sudo systemctl start svxlink-audio-proxy.service

# Остановить сервер
sudo systemctl stop svxlink-audio-proxy.service

# Перезапустить сервер
sudo systemctl restart svxlink-audio-proxy.service

# Добавить сервис в автозагрузку
sudo systemctl enable svxlink-audio-proxy.service

# Отключить автозагрузку
sudo systemctl disable svxlink-audio-proxy.service

# Проверить статус
sudo systemctl status svxlink-audio-proxy.service

# Просмотр логов в реальном времени
sudo journalctl -u svxlink-audio-proxy.service -f

# Логи за последний час
sudo journalctl -u svxlink-audio-proxy.service --since="1 hour ago"
```

