# Документация по проекту SvxLink Dashboard by R2ADU

Дата обновления 17.02.2026

Версия 0.4.x

**ПРИМЕЧАНИЕ** Английская версия документации обновляется на вторичной основе, когда появляется свободное время. Для получения актуальной информации обращайтесь к русскоязычному источнику

## 1. Назначение проекта

Проект представляет собой веб-интерфейс (dashboard) для мониторинга и управления сервисом SvxLink — программным обеспечением для организации любительской радиосвязи через интернет. Дашборд отображает в реальном времени состояние:

- Логик (logic)
- Связей (links)
- Сервиса (service)
- Устройств (multiple_device)
- WebSocket-соединений

Все данные берутся из лог-файла SvxLink (/var/log/svxlink), парсятся и динамически обновляются в браузере.

### 1.1. Предпосылки

Предполагается что svxlink server работает на headless устройстве, например Raspberry-подобном устройстве или виртуальной машине. Работа на других ОС вероятнее всего также поддерживается, но не тестировалась.

### 1.2. Безопасность

Следует понимать, что никаких мер относительно безопасного использования не принималось, любой пользователь получивший доступ к сайту сможет управлять состоянием сервера svxlink (если установлены соответствующие настройки `DTMF_CTRL_PTY`) - включать или выключать модули, управлять линками итд. Впрочем, такие-же права получает и любой эфирный корреспондент, способный передавать DTMF команды.


## 2. Необходимые пакеты

- Apache2
- PHP
- Node.js


## 3. Описание работы
При первом запуске выполняется парсинг параметров из настроечных файлов svxlinkserver,  и инициализация их в виде массива, который записывается в сессию.
Следующим шагом полученный массив заполняется актуальными данными, которые рассчитываются путем выполнения соответствующих выборок из журнала сервиса svxlink.
Блоки состояния радио (radio_activity) и общего состояния сервиса (включенный режим, его переменные и время активности - находится в left_panel)
Данные для формирования этих блоков находятся в сессии.


### 3.1. Настройки
Описание настроечных констант 

Настройки находятся в файле  `/include/settings.php`. 
Настройки определяющие содержимое переводов располагаются в соответствующих файлах в каталоге `/include/languages`
Настройки определяющие цветовую схему 
	- Основная (базовая) - `/css/css.php` 
	- Дополнительная (приоритетная) - `/css/menu.css` 
	- Оформление кнопки сервера обновлений - `/css/websocket_control.css` 

#### 3.1.1. Пользовательские настройки
  
	- константы определяющие содержимое заголовков страницы
    + `DASHBOARD_VERSION`
    + `DASHBOARD_NAME`
    + `DASHBOARD_TITLE`
  
	- константы определяющие содержимое страниц
	При желании можно отображать или скрывать различные части страницы сайта. 
    + `SHOW_CON_DETAILS` - Подробности соединения
    + `SHOW_RADIO_ACTIVITY` - Состояние радиоприемника/передатчика
    + `SHOW_NET_ACTIVITY`, `NET_ACTIVITY_LIMIT` - История сетевой активности (вызовы из модулей или рефлекторов) и количество последних событий
    + `SHOW_RF_ACTIVITY`, `RF_ACTIVITY_LIMIT` - История эфирной активности
	
	- константы определяющие механизм обновления страницы
	При желании можно отключить службу обновления в реальном времени или настроить желаемую частоту обновлений. 
    + `UPDATE_INTERVAL`   Интервал обновления динамически обновляемых частей. Не рекомендуется устанавливать интервал менее 1000 мсек (1 сек) во избежании перегрузки (кроме того, это не принесет никаких преимуществ - все отображаемые таймеры имеют секундную дискретность). При включенном realtime сервере рекомендуется устанавливать интервал в 5000 (5 сек) - влияет только на частоту обновлений исторических сводок
    + `WS_ENABLED`        Использовать взаимодействие с realtime сервером состояний или нет.


#### 3.1.1. Технические настройки
  
	- константы определяющие состояние отладки. Установите `DEBUG = false` для jnrлючения отладки
    + `DEBUG`, `DEBUG_VERBOSE`, `DEBUG_LOG_FILE`, `LOG_LEVEL`
  
	- различные технические константы
	Изменяйте их только если хорошо понимаете, что делаете.
    + настроки для realtime сервера
      - `WS_PORT`
      - `WS_PATH`
    + `USE_CACHE`         Глобально, использовать кеш при разборе 
    + `LOG_CACHE_TTL_MS`  Настройки времени жизни кеша для функций разбора лога
  
	- константы определяющие настройки сессии
      + `SESSION_NAME`
      + `SESSION_LIFETIME`
      + `SESSION_PATH`
      + `SESSION_ID`
  
	- константы для конфигурации svxlink
    + `SVXLOGPATH`
    + `SVXLOGPREFIX`
    + `SVXLINK_LOG_PATH`
    + `SVXCONFPATH`
    + `SVXCONFIG`
    


## 4. Концепция отображения данных

  Элементы страницы делятся на три части
  1. Статические - создаются единожды при открытии страницы
  2. Динамически обновляемые с заданным интервалом 2-5 сек
  3. Обновляемые в реальном времени


### 4.1. Порядок обновления элементов 

Система использует гибридный подход: периодические AJAX-обновления с возможностью real-time через WebSocket. При отключенном WebSocket все обновления происходят при помощи AJAX.


#### Данные в реальном времени
Предусмотрены два способа обновления данных страницы - в реальном времени и с периодичностью определенной в `UPDATE_INTERVAL`
Данные реального времени поставляет Node.js сервер, а обрабатывает JS клиент. Таким образом обновляются часто меняющиеся состояния, например, активность передатчика/приемника:
  - Сервер отслеживает изменения журнала SvxLink
  - Выделяет значимые события
  - Создает команды DOM-обновлений
  - Клиент применяет полученные команды к структуре DOM

#### Данные обновляемые динамически

В эту группу входят исторические данные:
  - История активности - последние несколько событий для соответствующих режимов
  - Подробности текущего соединения - в зависимости от текщего режима (EchoLink, Frn или рефлектор) специфический набор данных   
  - Последняя локальная активность - вызовы принятые приемником (rf_activity)
  - Последняя сетевая активность - вызовы из интернета, от рефлекторов, модулей, повлиявшие на состояние передатчика  (net_activity)
  
## 5. Структура данных о состоянии ($_SESSION) 

Данные о конфигурации и состоянии сервиса хранятся в сессии (сессия с жестко заданным ID всегда одна для всех элементов, в т.ч. обновляемых AJAX)


### ['TIMEZONE'] : string

### RF Activity kerchunks filter:
	- ['rf_filter'] : string ON | OFF (default: ON)
	- ['rf_filter_max'] : float (default: 1)

### NET Activity kerchunks filter:
	- ['net_filter'] : string ON | OFF (default: ON)
	- ['net_filter_max'] : float (default: 1)

### ['status'] : array

  - `link`: array[linkName] (массив связей)
  - `logic`: array[logicName] (массив логик)
  - `service`: array (информация о сервисе)
  - `multiple_device`: array[deviceName] (составные устройства)
  - `callsign`: string (глобальный позывной)

#### Структура link[linkName]

  - `is_active`: bool
  - `is_connected`: bool
  - `start`: int (timestamp)
  - `duration`: int (секунды)
  - `timeout`: int
  - `default_active`: bool
  - `source`: array
    - `logic`: string
    - `command`: array
      - `activate_command`: string
      - `deactivate_command`: string
    - `announcement_name`: string
  - `destination`: array
    - `logic`: string
    - `command`: array
      - `activate_command`: string
      - `deactivate_command`: string
    - `announcement_name`: string

#### Структура logic[logicName]

  - `start`: int (timestamp)
  - `name`: string (logicName)
  - `is_active`: bool
  - `callsign`: string
  - `rx`: array [
      'name' : string 
      'start' : int
    ]
  - `tx`: array [
      'name' : string 
      'start' : int
    ]
  - `macros`: array [key,value]
  - `type`: string
  - `dtmf_cmd`: string
  - `is_connected`: bool
  - `module`: array[moduleName] (опционально)
    - `start`: int
    - `name`: string
    - `callsign`: string
    - `is_active`: bool
    - `is_connected`: bool
		- `id` : string
		- `mute_logic` : bool
    - `connected_nodes`: array[nodeName] (опционально)
      - `callsign` : string (nodeName без суффиксов и обрамляющих '*' ...-R,...-L, *...*)
      - `start` : int
      - `type` : string <'Repeater','Link','Conference'>
      - `name` : string @todo Дублирует имя ключа - убрать
  - `talkgroups`: array (опционально)
    - `default`: string
    - `selected`: string
    - `monitoring`: array
    - `temp_monitoring`: string
  - `connected_nodes`: array[nodeName] (опционально)
    - `callsign` : string
    - `start` : int
    - `type` : string ("Node")
  - `hosts`: string (опционально) - разделенные запятыми значения.
	- `caller_callsign` : string (для рефлекторов)
	- `caller_tg` : string (для рефлекторов)

#### Структура service

  - `start`: int
  - `name`: string
  - `is_active`: bool
  - `timestamp_format` : string
	- `aprs_sever` : array 
		- `start` : int
		-	`name` : srtring		
	- `status_server` : array
		- `has_error` : bool
		- `name`. :string
	- `directory_server` : array 
		- `start` : int
		-	`name` : srtring
	- `proxy_server` : array
		- `start` : int
		- `name` : srtring
	

#### Структура multiple_device[deviceName]

  - `device_name` : string (список передатчиков через запятую)

## 6. Организация файловой структуры


```
/                                       # Корень проекта
├── index.php                           # Основная страница
├── favicon.ico                         # Иконка сайта
├── ws_state.php                        # Данные о состоянии для realtime сервера
├── include/                            # PHP включаемые файлы
│   ├── ajax_update.php                 # AJAX обновление
│   ├── auth_config.php                 # Конфигурация авторизации
│   ├── auth_handler.php                # Обработчик событий авторизации
│   ├── authorise.php                   # Авторизация
│   ├── browserdetect.php               # Подстройка под браузер
│   ├── change_password.php             # Смена пароля
│   ├── connection_details.php          # Детальная информация о текущем соединении
│   ├── dtmf_handler.php                # Обработчик DTMF команд
│   ├── footer.php                      # Подвал
│   ├── init.php                        # Основная инициализация
│   ├── js_utils.php                    # JavaScript утилиты
│   ├── keypad.php                      # DTMF клавиатура
│   ├── languages/                      # Локализации
│   │   └── ru.php
│   ├── left_panel.php                  # Левая панель (панель статуса/состояний)
│   ├── logout.php                      # Выход из системы
│   ├── macros.php                      # Панель макросов
│   ├── monitor.php                     # Мониторинг аудио
│   ├── net_activity.php                # История сетевой активности
│   ├── radio_activity.php              # Состояние приемника/передатчика
│   ├── reset_auth.php                  # Сброс авторизации
│   ├── rf_activity.php                 # История событий локальной активности
│   ├── session_header.php              # Легковесное открытие сессии
│   ├── settings.php                    # Настройки приложения
│   ├── top_menu.php                    # Основное меню команд
│   ├── websocket_client_config.php     # Конфигурация клиента обновления
│   ├── websocket_server.php            # Конфигурация сервера обновлений (PHP)
│   └── fn/                             # Функции и пакеты функций
│       ├── dlog.php                    # Журналирование для отладки
│       ├── formatDuration.php          # Форматирование продолжительности
│       ├── getConfig.php         			# Создает конфигурацию системы
│       ├── getActualStatus.php         # Строит начальное состояние системы
│       ├── getLineTime.php             # Время из строки
│       ├── getTranslation.php          # Работа с переводами
│       ├── logTailer.php               # Работа с записями журнала
│       ├── parseXmlTags.php            # Парсит XML-теги строки журнала
│       └── removeTimestamp.php         # Удаляет временную метку из строки лога
├── scripts/                            # JS скрипты
│   ├── dashboard_ws_client.js          # Клиент обновления состояний
│   ├── dashboard_ws_server.js          # Сервер состояний реального времени
│   ├── featherlight.js                 # Библиотека
│   ├── jquery.min.js                   # jQuery библиотека
│   └── svxlink-audio-proxy-server.js   # WebSocket Audio Monitor
├── css/                                # Стили
│   ├── css-mini.php                    # Стили
│   ├── css.php                         # Основной набор стилей
│   ├── menu.css                        # Дополнения к основному набору
│   ├── websocket_control.css           # Стили для кнопки управления WS
│   └── font-awesome.min.css            # Awesome Fonts
├── fonts/                              # Шрифты
│   ├── stylesheet.css
│   └── ...
└── install/                            # Установочные скрипты
    ├── cli_setup.php                   # CLI Setup Script 0.1.1
    └── index.php                  			# Страница для первоначальной настройки
```

## 7. WebSocket система v4.0

### Назначение

Обеспечение двусторонней коммуникации между сервером SvxLink и веб-интерфейсом Dashboard в реальном времени. 
Сервер мониторит журнал SvxLink, разбирает события и отправляет команды DOM-обновлений всем подключенным клиентам.

### Архитектура

#### Сервер WebSocket (Node.js, порт 8080)

**Основные компоненты:**
- **WebSocket сервер** - управление подключениями клиентов
- **Парсер начального состояния** - получает начальные данные при первом запуске (подключении клиента)
- **Парсер логов SvxLink** - анализ событий в реальном времени
- **Генератор DOM-команд** - преобразование событий в команды обновления интерфейса

**Функционал:**
- Мониторинг лог-файла SvxLink с помощью `tail -F`
- Парсинг событий: transmitter, squelch, talkgroup, module activity
- Формирование команд DOM для обновления интерфейса
- Рассылка обновлений всем подключенным клиентам

Подробная документация в `ws_server.md`

#### Клиент WebSocket (JavaScript)

Клиент автоматически инициализируется при загрузке страницы.

##### Формат команд

Команды представляют собой объекты JSON с обязательным полем `action`:

```javascript
{
  "action": "имя_действия",
  // дополнительные параметры
}
```

Подробная документация в ws_client.md



### Элементы интерфейса

#### Модальные окна
1. **Авторизация** (`authContainer`) - форма входа
2. **DTMF клавиатура** (`keypadContainer`) - виртуальная клавиатура
3. **Выход/администрирование** (`logoutModal`) - меню администратора

#### Функциональные блоки
1. **Radio Status** - статус радиоустройств
2. **Connection Details** - детали сетевых подключений
3. **NET Activity** - сетевая активность
4. **RF Activity** - радиочастотная (локальная) активность
5. **Debug Console** - отладочная информация

#### Панели управления
1. **Навигационная панель** - кнопки управления
2. **Левая панель** (`#leftPanel`) - структура логик и модулей
3. **Панель отладки** - метрики производительности и переменные

### Особенности структуры
- Используется адаптивная flex-верстка
- Поддержка отзывчивого дизайна

## 9. Сервис аудио мониторинга

### Сервер

Автоматически перезапускается через 5 секунд после падения.

Начинает захват только после подключения клиентов, освобождает захват после отключения всех клиентов

Выполнен в формате отдельного сервиса (```/etc/systemd/system/svxlink-audio-proxy.service```)


```bash
[Unit]
Description=SVXLink Node.js Server
After=network.target

[Service]
# Send logs directly to journald instead of syslog or files
StandardOutput=journal
StandardError=journal

# Ensure service restarts even after journal restarts or SIGHUPs
Restart=always
RestartSec=5

# Allow clean reloads (optional, useful if you add reload scripts later)
ExecReload=/bin/kill -HUP

# Give the process a few seconds to shut down gracefully
TimeoutStopSec=10
Type=simple
User=svxlink
Group=svxlink
ExecStart=/usr/bin/node /etc/systemd/system/svxlink-audio-proxy.service.js
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

#### Управление:

| Команда | Действие |
|---------|----------|
| `sudo systemctl stop svxlink-audio-proxy.service` | Остановить сервер (не будет перезапущен) |
| `sudo systemctl start svxlink-audio-proxy.service` | Запустить сервер |
| `sudo systemctl restart svxlink-audio-proxy.service` | Перезапустить сервер |
| `sudo systemctl disable svxlink-audio-proxy.service` | Отключить автозапуск |
| `sudo systemctl enable svxlink-audio-proxy.service` | Включить автозапуск |
| `sudo journalctl -u svxlink-audio-proxy.service -f` | Просмотр логов в реальном времени |
| `sudo journalctl -u svxlink-audio-proxy.service --since="1 hour ago"` | Логи за последний час |

---

#### Настройка

Сервер настраиваем на 'plughw:Loopback,1,0'

```javascript
function startRecording() {
  console.log('Starting audio capture...');
  const record = spawn('arecord', [
    '-D', 'plughw:Loopback,1,0',
    '-f', 'S16_LE',
    '-r', '48000',
    '-c', '1'
  ], {
    stdio: ['ignore', 'pipe', 'ignore'] // stdout only
  });
  ...
```

В конфиге svxlink  настраиваем на 'alsa:plughw:Loopback,0,0'.

```
[TxStream]
TYPE = Local
AUDIO_DEV = alsa:plughw:Loopback,0,0
AUDIO_CHANNEL = 0
PTT_TYPE = NONE
TIMEOUT = 7200
TX_DELAY = 0
PREEMPHASIS = 0
```
