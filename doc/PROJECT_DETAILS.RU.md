# Документация по проекту SvxLink Dashboard by R2ADU

Дата обновления 24.01.2026

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

Вероятно, svxlink работает на headless устройстве, например Raspberry-подобном устройстве или виртуальной машине

Пользователи dashboard взаимодействуют с панелью посредством браузера

Возможно, клиентов подключенных к dashboard будет несколько - администратор и наблюдатели
Наличие наблюдателей потребует усложения архитектуры;
Текущая реализация поддерживает обновления для нескольких клиентов


## 2. Необходимые пакеты

- Apache2
- PHP
- Node.js


## 3. Порядок создания элементов

3.1. При первом запуске выполняется анализ параметров из settings.php и инициализкция констант в init.php
  
  3.1.1. Настройки в `settings.php`
  - константы определяющие содержимое заголовков
    + `DASHBOARD_VERSION`
    + `DASHBOARD_NAME`
    + `DASHBOARD_TITLE`
  - константы определяющие содержимое 
    + `SHOW_CON_DETAILS`
    + `SHOW_RADIO_ACTIVITY`
    + `SHOW_NET_ACTIVITY`, `NET_ACTIVITY_LIMIT`
    + `SHOW_REFLECTOR_ACTIVITY`, `REFLECTOR_ACTIVITY_LIMIT`
    + `SHOW_RF_ACTIVITY`, `RF_ACTIVITY_LIMIT`
  - константы определяющие состояние отладки
    + `DEBUG`, `DEBUG_VERBOSE`, `DEBUG_LOG_FILE`, `LOG_LEVEL`
  - константы определяющие настройки поведения
    + `UPDATE_INTERVAL`   Интервал обновления динамически обновляемых частей 
    + `WS_ENABLED`        Работать с websocket сервером состояний
    + настроки для websocket
      - `WS_PORT`
      - `WS_PATH`
    + `USE_CACHE`         Глобально, оспользовать кеш или нет
    + `LOG_CACHE_TTL_MS`  Настройки времени жизни кеша для различных функций
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
    


  3.1.2.1. Выполняется чтение файла конфигурации svxlink и построение структуры компонентов (init.php)

  3.1.2.2. Выполняется чтение последнего (текущего) сеанса сервиса и заполнение структуры компонентов состояниями активных компонентов. Актуальная структура сохраняется в сессии.

  3.1.3. Отрисовываются DOM компоненты
    3.1.3.1. блок состояния (левая парнель)
    - Состояние сервиса
    - Доступные логики, их модули, связанные рефлекторы и линки
    - Для активных модулей или рефлекторов - подключенные узлы/группы/конференции итд

    3.1.3.2. Отрисовывается блок радио
    - раздельно для каждой доступной логики 
      + устройства RX,TX, 
      + их состояние (STANDBY/TRANSMIT/RECEIVE)
      + Позывной от имени которого идет обмен
      + Длительность приема/передачи
    - логики с типом рефлектор отрисовываются с пустым содержимыи (только каркас)

  3.1.4. Вычисляются и отражаются состояния инфо-блоков
    - Подробности соединений активного модуля (для Echolink,Frn)
    - Активные рефлекторы
    - История вызовов связанных с интернет-сервисами
    - История эфирных вызовов

## 4. Концепция отображения данных

  Элементы страницы делятся на три части
  1. Статические - создаются единожды при открытии страницы
  2. Динамически обновляемые с интервалом 2-5 сек
  3. Обновляемые в реальном времени


### 4.1. Порядок обновления элементов 

#### Данные в реальном времени

Данные реального времени поставляет WebSocket сервер, а обрабатывает клиент. Таким образом обновляются часто меняющиеся состояния, например, активность передатчика/приемника:
  - Сервер отслеживает изменения журнала SvxLink
  - Выделяет значимые события
  - Создает команды DOM-обновлений
  - Клиент применяет полученные команды к структуре DOM

#### Данные обновляемые динамически

В эту группу входят исторические данные:
  - История активности - последние несколько событий для соответствующих режимов
  - Подробности текущего соединения - в зависимости от текщего режима (EchoLink,Frn или рефлектор) специфический набор данных   
  - Последняя локальная активность - вызовы принятые приемником (rf_activity)
  - Последняя сетевая активность - вызовы из интернета, от рефлекторов, модулей, повлиявшие на состояние передатчика  (net_activity)
  
Исторические сводки вычисляются соответствующими функциями отдельно для каждого блока. Данные рассчитываются путем анализа журнала svxlink в пределах последней активной сессии. Интервал обновления таких данных устанавливается константой `UPDATE_INTERVAL` .


## 5. Структура данных о состоянии ($_SESSION) 

Данные о состоянии сервиса хранятся в сессии (сессия с жестко заданным ID всегда одна для всех элементов, в т.ч. обновляемых AJAX)


### ['DTMF_CTRL_PTY'] : string
### ['TIMEZONE'] : string

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

#### Структура service

  - `start`: int
  - `name`: string
  - `is_active`: bool
  - `timestamp_format`: string
	- `aprs_sever` : array ['start','name']
	

#### Структура multiple_device[deviceName]

  - `device_name` : string (список передатчиков через запятую)

## 6. Организация файловой структуры

Фактическое абсолютное значение корня тестовой среды (для справки) - /var/www/html/
Тестовый сервер по адресу http://svxlink_development_dashboard.local

На время разработки вместо реальных файлов в каталогах размещены симлинки на текущие версии.
По окончании разработки симлинку будут заменены продакшн версиями.
Некоторые из файлов - атавизмы от предыдущих версий. 
Безусловно актуальными считать версии с текущим номером (см в начале файла)

```
/                                       # Корень проекта
├── index.php                           # Основная страница (продакшн)
├── index_debug.php                     # Основная страница для отладочного режима
├── backup.sh                           # Скрипт резервного копирования
├── favicon.ico                         # Иконка сайта
├── ws_state.php                        # Состояние WebSocket
├── include/                            # PHP включаемые файлы
│   ├── exct/                           # Версионированные источники
│   │   └── ...                         
│   ├── ajax_update.php                 # AJAX обновление
│   ├── auth_config.php                 # Конфигурация авторизации
│   ├── auth_handler.php                # Обработчик авторизации
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
│   ├── left_panel.php                  # Левая панель состояний
│   ├── logout.php                      # Выход из системы
│   ├── macros.php                      # Макросы
│   ├── monitor.php                     # Мониторинг аудио
│   ├── net_activity.php                # История сетевой активности
│   ├── radio_activity.php              # Состояние приемника/передатчика
│   ├── reflector_activity.php          # Данные рефлекторов
│   ├── reset_auth.php                  # Сброс авторизации
│   ├── rf_activity.php                 # История событий локальной активности
│   ├── session_header.php              # Легковесное открытие сессии
│   ├── settings.php                    # Настройки приложения
│   ├── top_menu.php                    # Основное меню команд
│   ├── websocket_client_config.php     # Конфигурация WebSocket клиента
│   ├── websocket_server.php            # WebSocket сервер (PHP)
│   └── fn/                             # Функции и пакеты функций
│       ├── exct/                       # Версионированные источники
│       │   └── ...
│       ├── dlog.php                    # Журналирование для отладки
│       ├── formatDuration.php          # Форматирование продолжительности
│       ├── getActualStatus.php         # Строит начальное состояние системы
│       ├── getLineTime.php             # Время из строки
│       ├── getTranslation.php          # Работа с переводами
│       ├── logTailer.php               # Работа с последними записями журнала
│       ├── parseXmlTags.php            # Парсит XML-теги строки журнала
│       └── removeTimestamp.php         # Удаляет временную метку из строки лога
├── scripts/                            # JS скрипты
│   ├── exct/                           # Версионированные источники
│   │   └── ...
│   ├── dashboard_ws_client.js          # WebSocket клиент состояний
│   ├── dashboard_ws_server.js          # WebSocket сервер состояний
│   ├── featherlight.js                 # Библиотека
│   ├── jquery.min.js                   # jQuery библиотека
│   └── svxlink-audio-proxy-server.js   # WebSocket Audio Monitor
├── css/                                # Стили
│   ├── css-mini.php                    # Стили
│   ├── css.php                         # Основной набор стилей
│   ├── menu.php                        # Дополнения к основному набору
│   ├── websocket_control.css           # Стили для кнопки управления WS
│   └── font-awesome.min.css            # Awesome Fonts
├── fonts/                              # Шрифты
│   ├── stylesheet.css
│   └── ...
├── install/                            # Установочные скрипты
│   ├── cli_setup.php                   # CLI Setup Script 0.1.1
│   └── setup_auth.php                  # Скрипт установки авторизации
└── config/                             # Конфигурационные файлы
```

## 7. WebSocket система v4.0

### Назначение
Обеспечение двусторонней коммуникации между сервером SvxLink и веб-интерфейсом Dashboard в реальном времени. 
Сервер мониторит журнал SvxLink, разбирает события и отправляет команды DOM-обновлений всем подключенным клиентам.

### Архитектура

#### Сервер WebSocket (Node.js, порт 8080)
**Основные компоненты:**
- **WebSocket сервер** - управление подключениями клиентов
- **Парсер начального состояния** - получает начальные данные при подключении клиента
- **Парсер логов SvxLink** - анализ событий в реальном времени
- **Генератор DOM-команд** - преобразование событий в команды обновления интерфейса

**Функционал:**
- Мониторинг лог-файла SvxLink с помощью `tail -F`
- Парсинг событий: transmitter, squelch, talkgroup, module activity
- Формирование команд DOM для обновления интерфейса
- Рассылка обновлений всем подключенным клиентам

Подробная документация в ws_server.md

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

## 8. Структура DOM 

### Корневая структура
```
html
├── head
│   ├── meta (charset)
│   ├── meta (viewport)
│   ├── title
│   ├── script (Внешний jquery.min.js)
│   ├── script (Внешний dashboard_ws_client.js - Клиентская часть WebSocket)
│   ├── script (встроенный - конфигурация - Инициализация глобальных параметров приложения.)
│   ├── link (font-awesome)
│   ├── link (stylesheet)
│   ├── link (stylesheet)
│   ├── link (stylesheet)
│   └── link (websocket_control.css)
├── body
│   └── div.container
│
└── script                             <!-- Встроенный - init и функции -->
```

### Основные секции body

#### 1. Header (Шапка)
```
div.header
├── div.SmallHeader (левая часть)
├── h1 (заголовок)
└── div.navbar (навигация)
    ├── div.headerClock
    ├── div (модальное окно авторизации)
    │   ├── div.auth-overlay
    │   └── div.auth-container
    │       └── div.auth-form
    ├── a.menuadmin (Login)
    ├── div (модальное окно DTMF)
    │   ├── script (работа с DTMF)
    │   ├── div#keypadOverlay
    │   └── div#keypadContainer
    ├── a.menukeypad (DTMF)
    ├── script (управление аудиомониторингом)
    ├── a.menureset (Reset Session)
    ├── a.menuconnection (Connect Details)
    ├── a.menureflector (Reflectors)
    ├── a.menuaudio (Monitor)
    ├── a.menudashboard (Dashboard)
    ├── a.menuwebsocket (WebSocket статус)
    ├── div (модальное окно авторизации/выхода)
    │   └── div#logoutModal
    ├── script (управление модальными окнами)
    ├── a.menuwsdisconnect (WS Disconnect)
    ├── a.menuwsconnect (WS Connect)
    └── a.menuwsreconnect (WS Reconnect)
```

#### 2. Основная структура контента

```
div.container
├── div.leftnav
│   └── div#leftPanel      
│               
├── div.content (основной контент)
│   ├── div#lcmsg (сообщения)
│   ├── div#radio_activity (статус радио)
│   │   ├── div#ra_header (шапка табл)
│   │   └── div#ra_table (тело таблицы - строки по количеству)
│   ├── div#connection_details (детали подключения (опционально))
│   │   ├── div#refl_header (данные о рефлекторе ?)
│   │   └── div#frn_server_table (подключенные к серверу Frn узлы)
│   │   └── div#el_msg_table (сообщения EchoLink)
│   ├── div#reflector_activity (инфо рефлекторов)
│   │   ├── div#refl_header
│   │   └── table
│   ├── div#net_activity (сетевая активность)
│   │   └── table
│   ├── div#local_activity (локальная активность)
│   │   └── table
│   └── div (отладка)
│       ├── div (консоль WebSocket)
│       └── div.debug (секция отладки)
│           ├── div (управление отладкой)
│           ├── div#debugContent
│           │   ├── div#performanceSection
│           │   └── div#variableSection
│           └── script (управление видимостью блока отладки)
├── div#footer (Подвал)
└── script <!-- Встроенный - часы и консоль отладки -->

```

##### Индентификаторы элементов левой панели (leftPanel)

```
leftPanel
        ├── service
        ├── logic_{имя_логики}
        │   ├── logic_{имя_логики}
        │   ├── logic_{имя_логики}_modules_header           Шапка блока модулей
        │   ├── logic_{имя_логики}_module_{имя_модуля}
        │   ├── ... (другие модули)
        │   └── logic_{имя_логики}_active                   Блок активного модуля
        │       ├── logic_{имя_логики}_active_header        Шапка
        │       └── logic_{имя_логики}_active_content       Тело блока
        │    
        ├─── Блок "Рефлектор - Линк"                                                 
        │   ├── logic_{имя_рефлектора}                      Рефлектор
        │   ├── link_{имя_линка}                            Линк      
        │   ├── logic_{имя_рефлектора}_groups               Разговорные группы рефлектора
        │   │   ├── logic_{имя_рефлектора}_{имя_группы}
        │   │   └── ...
        │   │
        │   └── logic_{имя_рефлектора}_nodes                Узлы рефлектора
        │       ├── logic_{имя_рефлектора}_nodes_header     Шапка блока узлов
        │       ├── logic_{имя_рефлектора}_node_{имя_узла}  Узел
        │       └── ...
        └── ...
```


### Элементы интерфейса

#### Модальные окна
1. **Авторизация** (`authContainer`) - форма входа
2. **DTMF клавиатура** (`keypadContainer`) - виртуальная клавиатура
3. **Выход/администрирование** (`logoutModal`) - меню администратора

#### Функциональные блоки
1. **Radio Status** - статус радиоустройств
2. **Connection Details** - детали сетевых подключений
3. **Reflectors Info** - информация о рефлекторах
4. **NET Activity** - сетевая активность
5. **RF Activity** - радиочастотная (локальная) активность
6. **Debug Console** - отладочная информация

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
