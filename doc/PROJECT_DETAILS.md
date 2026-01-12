Понял, исправляю. Нужно оставить всю основную документацию, а исправить только раздел про WebSocket. Вот исправленная версия:

# Документация по проекту SvxLink Dashboard by R2ADU

Дата обновления 06.01.2026

Версия 0.4.x

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

### Выводы

@todo Продумать это после реализации основных механизмов в однопользовательском режиме


## 2. Необходимые пакеты

- Apache2
- PHP
- Node.js


## 3. Порядок создания элементов

3.1. При первом запуске выполняется анализ параметров из settings.php и инициализкция констант в init.php
  
  3.1.1. settings
  - константы определяющие форматы
    + DASHBOARD_TIME_FORMAT, DASHBOARD_DATE_FORMAT  
  - константы определяющие содержимое 
    + DASHBOARD_VERSION
    + DASHBOARD_NAME
    + DASHBOARD_TITLE
    + SHOW_CON_DETAILS
    + SHOW_RADIO_ACTIVITY
    + SHOW_NET_ACTIVITY
    + SHOW_REFLECTOR_ACTIVITY
    + SHOW_RF_ACTIVITY, RF_ACTIVITY_LIMIT
    + DEBUG, DEBUG_VERBOSE, DEBUG_LOG_FILE, LOG_LEVEL
  - константы определяющие настройки поведения
    + UPDATE_INTERVAL
    + DASHBOARD_FAST_INTERVAL
    + WS_ENABLED
    + настроки для websocket
      - WS_PORT
      - WS_PATH
      - LOCK_TIMEOUT.   Время блокировки
      - TMP_PATH        Путь для хранения вайла блокировки
      - LOCK_FILE       Файл для хранения состояния блокировки
      - CHANGES_HANDLER Функция получения обновленного? состояния ( @deprecated )
      - INIT_HANDLER    Функция для получения актуального состояния ( @deprecated )
      - BLOCK_RENDER    Функция рендеринга html для ответа ( @deprecated )
      - FALLBACK_URL.   Обработчик фолбеков ( @deprecated )
    + USE_CACHE         Глобально, оспользовать кеш или нет
    + LOG_CACHE_TTL_MS  Настройки времени жизни кеша для различных функций
  - константы определяющие настройки сессии
      + SESSION_NAME
      + SESSION_LIFETIME
      + SESSION_PATH
      + SESSION_ID
  - константы для конфигурации svxlink
    + SVXLOGPATH
    + SVXLOGPREFIX
    + SVXLINK_LOG_PATH
    + SVXCONFPATH
    + SVXCONFIG
    + TIMESTAMP_FORMAT


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
  - Последняя локальная активность
  - Последняя сетевая активность (вызовы из интернета, от рефлекторов)
  
Исторические сводки вычисляются соответствующими функциями отдельно для каждого блока. Данные рассчитываются путем анализа журнала svxlink в пределах последней активной сессии. Интервал обновления таких данных устанавливается 5 секунд.


## 5. Структура $_SESSION

Данные о состоянии сервиса хранятся в сессии (сессия с жестко заданным ID всегда одна для всех элементов, в т.ч. обновляемых AJAX)

@todo Подумать над целесообразностью хранения наиболее востребованных данных в независимых(отдельных) переменных - напр. имя активного модуля - это позволит избежать 
обхода логик для его поиска (1-5 мсек экономии)

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
  - `duration`: int (секунды)
  - `name`: string (logicName)
  - `is_active`: bool
  - `callsign`: string
  - `rx`: string (устройство RX)
  - `tx`: string (устройство TX)
  - `macros`: string
  - `type`: string
  - `dtmf_cmd`: string
  - `is_connected`: bool
  - `module`: array[moduleName] (опционально)
    - `start`: int
    - `duration`: int
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
  - `duration`: int
  - `name`: string
  - `is_active`: bool
  - `timestamp_format`: string

#### Структура multiple_device[deviceName]
  - string (список передатчиков через запятую)

## 6. Организация файловой структуры

Фактическое абсолютное значение корня тестовой среды (для справки) - /var/www/html/
Тестовый сервер по адресу http://svxlink_development_dashboard.local

На время разработки вмето реальных файлов в каталогах размещены симлинки на текущие версии.
По окончании разработки симлинку будут заменены продакшн версиями.
Некоторые из файлов - атавизмы от предыдущих версий. 
Безусловно актуальными считать версии с текущим номером (см в начале файла)
```
/                                       # Корень проекта
├── index_debug.php                     # Прокси -> /include/exct/index_debug.0.2.4.php
├── include/                            # здесь хранятся только симлинки на используемые файлы (сами файлы находятся в /include/exct)
│   ├── authorise.php                   # симлинк -> /include/exct/authorise.0.0.1.php
│   ├── browserdetect.php               # Подстройка под браузер
│   ├── connection_details.php          # симлинк -> /include/exct/connection_details.0.2.1.php
│   ├── debug_page.php          		    # симлинк -> /include/exct/debug_page.0.2.1.php
│   ├── echolink_data.php               # @deprecated v 0.3.x симлинк -> /include/exct/echolink_data.0.1.4.php
│   ├── footer.php                      # симлинк -> /include/exct/footer.0.1.5.php
│   ├── init.php                        # симлинк -> /include/exct/init.0.2.2.php
│   ├── keypad.php               		    # симлинк -> /include/exct/keypad.0.2.1.php
│   ├── left_panel.php               	  # симлинк -> /include/exct/left_panel.0.2.1.php
│   ├── log_parser.php               	  # @deprecated v 0.3.x симлинк -> /include/exct/log_parser.0.2.1.php
│   ├── monitor.php               		  # симлинк -> /include/exct/monitor.0.2.0.php
│   ├── net_activity.php               	# симлинк -> /include/exct/net_activity.0.2.1.php
│   ├── radio_activity.php				      # симлинк -> /include/exct/radio_activity.0.2.1.php
│   ├── reflector_activity.php          # симлинк -> /include/exct/reflector_activity.0.2.1.php
│   ├── rf_activity.php          		    # симлинк -> /include/exct/rf_activity.0.2.1.php
│   ├── session_header.php          	  # симлинк -> /include/exct/session_header.0.0.1.php
│   ├── settings.php                    # симлинк -> /include/exct/settings.0.2.2.php
│   ├── top_menu.php          			    # симлинк -> /include/exct/top_menu.0.2.2.php
│   ├── update_handler.php       		    # @deprecated v 0.3.x симлинк -> /include/exct/update_handler.0.2.1.php
│   ├── ws_log_parser.php       		    # @deprecated v 0.3.x симлинк -> /include/exct/ws_log_parser.0.2.2.php
│   ├── log_parser_dispatcher.php       # @deprecated ver 0.3 симлинк -> /include/exct/log_parser_dispatcher.0.2.4.php
│   ├── update_simlinks.sh   			      # скрипт для обновления симлинков на последнии версии источников
│   ├── exct/                                     # сдесь хранятся версионированные источники
│   │   ├── auth_config.0.0.1.php              		# Конфигурация авторизации
│   │   ├── auth_handler.0.0.1.php              	# Обработчик авторизации
│   │   ├── authorise.0.0.1.php              	    # Форма авторизации
│   │   ├── change_password.0.0.1.php             # Утилита для смены пароля и логина
│   │   ├── connection_details.0.2.1.php          # ПРОТОТИП Блок с подробностями для текущго соединения
│   │   ├── debug_page.2.2.php                    # Отладочные данные
│   │   ├── footer.0.2.0.php                      # Подвал
│   │   ├── index_debug.0.2.6.php       			    # Главная отладочная страница
│   │   ├── index.0.2.0.php       			          # Очистка данных сессии и перенаправление на отладочную страницу
│   │   ├── init.0.3.1.php              			    # Инициализация системы, настройка и запуск ws сервера
│   │   ├── keypad.0.2.1.php              			  # Модальная DTMF клавиатура
│   │   ├── left_panel.0.2.1.php     			        # Панель состояний сервиса,логики,модулей,линков
│   │   ├── log_parser_dispatcher.0.2.4.php     	# @deprecated v 0.3.x Роутер парсеров AJAX/WS - перенаправляет данные на нужный парсер
│   │   ├── ajax_log_parser.0.2.0.php             # @deprecated v 0.3.x Парсер новых строк журнала для AJAX fallback    
│   │   ├── ws_log_parser.0.2.2.php     			    # @deprecated v 0.3.x Парсер новых строк журнала для динамически обновляемых страниц (@todo Переписать)
│   │   ├── log_parser.php     				            # @deprecated Прокси -> отладочный simlink на парсер логов (диспетчер)
│   │   ├── logout.0.0.1.php     			            # Выход из системы
│   │   ├── monitor.0.2.0.php     					      # Скрипты аудиомониторинга
│   │   ├── net_activity.0.2.1.php     			      # Блок активности из сети
│   │   ├── radio_activity.0.2.1.php     			    # Блок радио
│   │   ├── reset_auth.0.0.1.php     				      # Сброс авторизации
│   │   ├── reflector_activity.0.2.1.php     		  # Блок рефлекторов
│   │   ├── session_header.0.0.1.php     			    # Служебный - инициализация сессии
│   │   ├── settings.0.2.2.php     			          # Константы (@todo Почистить от атавизмов)
│   │   ├── toggle_coninfo.0.1.0.php     			    # @deprecated v. 0.3
│   │   ├── top_menu.0.3.1.php     			          # Главное меню команд
│   │   ├── session_header.0.0.1.php     			    # Служебный - инициализация сессии
│   │   ├── rf_activity.0.2.1.php     		        # Блок локальных вызовов
│   │   ├── update_handler.0.2.1.php    			    # @deprecated v. 0.3
│   │   ├── websocket_starter.0.2.14.php    			# @deprecated see init.0.3.1.php __Запуск и управление WebSocket__ сервером
│   │   ├── websocket_control.0.2.0.php    			  # @deprecated Запуск и управление WebSocket сервером
│   ├── fn/                                       # Функции и наборы функций
│   │   ├── removeTimestamp.php                   # 
│   │   ├── getActualStatus.php                   # 
│   │   ├── logTailer.php                         # Работа с последнеми записями журнала (отслеживание и возврат)
│   │   ├── getTranslation.php                    #
│   │   ├── websocket_starter.php                 # @deprecated v. 0.3 Запускает ws сервер
│   │   ├── getCallsign.php                       # Выделяет позывной из строки журнала
│   │   ├── dlog.0.2.php                          # Журналирование для отладки
│   │   ├── parseXmlTags.0.1.5.php                # Парсит XML-теги строки журнала в ассоциативный массив
│   │   ├── getCallsign.0.1.14.php                # Функции getCallsign(), extractCallsignFromChat()
│   │   ├── removeTimestamp.0.1.14.php            # removeTimestamp()
│   │   ├── getActualStatus.0.4.0.php             # Строит начальное состояние системы
│   │   ├── getRadioStatus.0.2.0.php              # @deprecated v. 0.3 getRadioStatus()
│   │   ├── getNewState.php							          # Прокси -> /include/fn/getNewState.0.2.1.php
├── scripts/                                      здесь хранятся либо окончательные версии скриптов, либо симлинки на текущие (сами скрипты находятся в /scripts/exct)
│   ├── exct/                                     # сдесь хранятся версионированные источники
│   │   ├── dashboard_ws_server.4.0.js            # __WebSocket сервер__ версия 4.0
│   │   └── dashboard_ws_client.4.0.js            # __WebSocket клиент__ версия 4.0
│   ├── jquery.min.js 								            #
│   ├── server.js 									              # websocket Audio Monitor :8001
│   ├── featherlight.js         				          # 
│   ├── __Симлинки__
│   ├── dashboard_ws_server.js   			            # simlink dashboard_ws_server.js -> exct/dashboard_ws_server.4.0.js
│   ├── dashboard_ws_client.js   			            # simlink -> exct/dashboard_ws_client.4.0.js
│   └── update_simlinks.sh   			                # скрипт для обновления симлинков на последнии версии источников (для разработки)
├── css/
│   ├── css.php 								                  # Основной набор стилей
│   ├── menu.0.2.2.php 							              # Дополнения к основному набору
│   ├── websocket_control.css				              # стили для кнопки управления WS
│   ├─ font-awesome.min.css                       # Awesome
│   └─ font-awesome-4.7.0
│       ├── css
│       ├── fonts
│       ├── less
│       └── scss
├── fonts/
│   ├── stylesheet.css
│   ├── ...
│   ├── ...
│   └── ...
├── data/
│   └── ws_config                   # @deprecated v. 0.3 Каталог для хранения JSON конфигурации WebSocket сервера
├── favicon.ico                     # 
├── websocket.log                   # Лог WebSocket сервера
└── install/
    ├── cli_setup.php 							# CLI Setup Script 0.1.1
    └── setup_auth.php 							# Скрипт установки авторизации
```

## 7. WebSocket система v4.0

### Назначение
Обеспечение двусторонней коммуникации между сервером SvxLink и веб-интерфейсом Dashboard в реальном времени. Сервер мониторит журнал SvxLink, парсит события и отправляет команды DOM-обновлений всем подключенным клиентам.

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

#### Клиент WebSocket (JavaScript)
**Основные компоненты:**
- **DashboardWebSocketClientV4** - основной класс клиента
- **Обработчик DOM-команд** - выполнение команд обновления интерфейса
- **Менеджер соединения** - управление подключением и переподключениями
- **Интерфейс статуса** - визуальная индикация состояния

**Функционал:**
- Автоматическое подключение к WebSocket серверу
- Обработка и выполнение DOM-команд от сервера
- Поддержка всех типов операций: классы, контент, родительские операции
- Автоматические переподключения при разрыве связи
- Визуальная индикация статуса через кнопку в навигации

### Формат сообщений

#### От сервера к клиенту:
**Приветственное сообщение:**
```json
{
  "type": "welcome",
  "version": "4.0",
  "clientId": "client_123456789"
}
```

**Команды DOM:**
```json
{
  "type": "dom_commands",
  "commands": [
    {
      "action": "add_class",
      "id": "element_id",
      "class": "active-mode-cell"
    },
    {
      "action": "set_content",
      "id": "rx_status",
      "payload": "RECEIVE (15s)"
    }
  ],
  "chunk": 1,
  "chunks": 3
}
```

#### От клиента к серверу:
**Ping:**
```json
{
  "type": "ping",
  "timestamp": 1736000000000,
  "clientId": "client_123456789"
}
```

### Поддерживаемые действия DOM

#### Базовые операции:
- `add_class` - добавление класса(ов) к элементу
- `remove_class` - удаление класса(ов) из элемента
- `set_content` - полная замена содержимого элемента
- `add_content` - добавление контента в конец элемента
- `replace_content` - замена части контента по условиям
- `remove_content` - очистка содержимого элемента
- `remove_element` - удаление элемента из DOM

#### Родительские операции:
- `add_parent_class` - добавление класса родительскому элементу
- `remove_parent_class` - удаление класса у родительского элемента
- `replace_parent_content` - замена контента у родительского элемента

#### Дочерние операции:
- `add_child` - добавление/обновление дочернего элемента
- `remove_child` - удаление дочерних элементов (с фильтрацией по классам)
- `handle_child_classes` - массовые операции с классами для всех потомков

#### Специальные операции:
- `set_checkbox_state` - установка состояния чекбокса

### Инициализация и конфигурация

#### Конфигурация клиента:
```javascript
const config = {
  host: window.location.hostname,  // Хост сервера
  port: 8080,                      // Порт WebSocket
  autoConnect: true,               // Автоподключение при загрузке
  reconnectDelay: 3000,            // Задержка переподключения (мс)
  debugLevel: 2,                   // Уровень логирования: 1-4
  debugWebConsole: true,           // Логи в веб-консоль
  debugConsole: false,             // Логи в console браузера
  maxReconnectAttempts: 5,         // Макс. попыток переподключения
  pingInterval: 30000              // Интервал ping (мс)
};
```

#### Инициализация:
Конфигурация передается из PHP через глобальную переменную:
```javascript
window.DASHBOARD_CONFIG = {
  websocket: {
    host: "192.168.1.100",
    port: 8080,
    autoConnect: true
    // ... другие параметры
  }
};
```

Клиент автоматически инициализируется при загрузке страницы.

### Интерфейс управления

#### Кнопка статуса WebSocket:
Расположена в навигационной панели, показывает текущее состояние:

**Состояния и действия:**
- **Connected (OK)** - зеленый, клик отключает соединение
- **Disconnected (WS!)** - серый, клик перезагружает страницу для запуска сервера
- **Connecting/Reconnecting (TRY)** - желтый мигающий, процесс подключения
- **Error/Timeout (ERR/TO)** - красный, клик перезагружает страницу

### Особенности реализации v4.0

#### Поддержка родительских операций:
- Кэширование родительских элементов для производительности
- Единый интерфейс для операций с родительскими элементами
- Обработка множественных классов через запятую

#### Улучшенная обработка дочерних элементов:
- Интеллектуальное обновление существующих элементов
- Массовые операции с классами для всех потомков
- Фильтрация при удалении дочерних элементов

#### Надежность соединения:
- Автоматические переподключения с экспоненциальной задержкой
- Таймаут подключения 5 секунд
- Graceful shutdown при ручном отключении
- Пинг-понг для поддержания соединения

#### Производительность:
- Чанкинг больших наборов команд
- Кэширование DOM элементов
- Минимальное количество запросов к DOM

### Интеграция с Dashboard

#### Обновляемые элементы:
- Статусы радиоустройств (TX/RX)
- Позывные и длительность активности
- Состояния модулей и рефлекторов
- Списки подключенных узлов
- Исторические данные активности

#### CSS классы для состояний:
- `active-mode-cell` - активное состояние
- `inactive-mode-cell` - неактивное состояние  
- `paused-mode-cell` - приостановленное состояние
- `disabled-mode-cell` - отключенное состояние

#### Идентификаторы элементов:
Следуют паттерну: `{тип}_{имя}_{наименование-селектор}...`

Примеры: 

`device_Rx1_status`,
`device_Tx1_status`, 

`module_{EchoLink}`

`logic_{ReflectorLogicM.A.R.R.I}_node_{CN8EAA-DV}`

`link_{LinkKAVKAZ}`
`link_{LinkM.A.R.R.I}`

Родительский элемент для разговорных групп
`logic_{ReflectorLogic}_groups`
Дочерний элемент
`logic_{ReflectorLogic}_group_77`

`logic_{ReflectorLogic}_modules`
`logic_{ReflectorLogic}_modules_header`
`logic_{ReflectorLogic}_modules_content`

`logic_{ReflectorLogic}_module_{EchoLink}`

`logic_{ReflectorLogic}_nodes`
`logic_{ReflectorLogic}_node`

### Логирование и отладка

#### Уровни логирования:
- **ERROR (1)** - критические ошибки
- **WARNING (2)** - предупреждения (по умолчанию)
- **INFO (3)** - информационные сообщения
- **DEBUG (4)** - детальная отладочная информация

#### Направления логирования:
- Встроенная веб-консоль на странице
- Console браузера (опционально)
- Автоматическая ротация логов (100 последних сообщений)

### Восстановление после сбоев

#### Сценарии восстановления:
1. **Потеря соединения** - автоматическое переподключение
2. **Сервер не доступен** - перезагрузка страницы после исчерпания попыток
3. **Ошибка клиента** - перезагрузка страницы
4. **Ручное отключение** - graceful disconnect без переподключения

#### Механизмы восстановления:
- Экспоненциальная задержка переподключений
- Ограничение максимального количества попыток
- Перезагрузка страницы для полного перезапуска системы
- Сохранение состояния при кратковременных разрывах

### Производительность и масштабируемость

#### Оптимизации:
- Минимальный трафик: только команды изменений
- Группировка команд в пакеты
- Кэширование DOM-элементов
- Отложенное выполнение не критичных операций

#### Масштабируемость:
- Поддержка множественных клиентов
- Независимость сессий клиентов
- Эффективная рассылка обновлений
- Автоматическая очистка ресурсов

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
    ├── div (модальное окно выхода)
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
│       ├── div#rptInfoTable (сервис)
│       │   ├── div "Service" (шапка сервиса)
│       │   └── div
│       │       └── div#service[имя сервиса] (блок сервиса)
│       │
│       └── div.mode_flex (комплект блоков логики №1)
│           ├── div (логика)
│           │   └── div
│           │       └── div#logic[имя логики] (блок логики)
│           │           └── a (ссылка логики)
│           │
│           ├── div (модули)
│           │   ├── div "Modules" (шапка модулей)
│           │   └── div (тело модулей - ряды)
│           │       ├── div (ряд модулей)
│           │       │   ├── div
│           │       │   │   └── div#module[имя логики][имя модуля] (модуль 1)
│           │       │   │       └── a (ссылка модуля)
│           │       │   └── div
│           │       │       └── div#module[имя логики][имя модуля] (модуль 2)
│           │       └── div (ряд модулей)
│           │           ├── div
│           │           │   └── div#module[имя логики][имя модуля] (модуль 3)
│           │           └── div
│           │               └── div#module[имя логики][имя модуля] (модуль 4)
│           │
│           ├── div#[logic_name]modulesTable.divTable [.hidden опционально, когда нет подключенного модуля] (последний из подкл. узлов активного модуля)
│           │   ├── div#[logic_name]moduleNodesHeader.divTableHead  (шапка - имя активного модуля)
│           │   └── div.NodesTableBody (тело узлов модуля)
│           │       └── div.mode_flex 
│           │           └── div.mode_flex .row
│           │               └── div#[logic_name]moduleNodesContent .mode_flex .column .disabled-mode-cell
│           │                   └── a.tooltip 
│           │
│           ├── div.divTable (рефлектор 1.1)
│           │   ├── div.divTableBody
│           │   │   ├── div (инфо рефлектора)
│           │   │   │   ├── div "Reflector"
│           │   │   │   └── div#reflector[имя логики] (блок рефлектора)
│           │   │   └── div (линк)
│           │   │       ├── div "Link"
│           │   │       └── div#link[имя линка] (блок линка)
│           │   │           └── a (ссылка линка)
│           │   ├── div.divTable (talk groups)
│           │   │   ├── div "Talk Groups" (шапка talk groups)
│           │   │   └── div#reflector[имя рефлектора]TalkGroupsTableBody (тело talk groups)
│           │   │       └── div (ряд групп)
│           │   │           ├── div (talk group 1)
│           │   │           ├── div (talk group 2)
│           │   │           └── div (talk group 3)
│           │   └── div.divTable (узлы рефлектора)
│           │       ├── div "Nodes" (шапка узлов рефлектора)
│           │       └── div#reflector[имя рефлектора]NodesTableBody (тело узлов рефлектора)
│           │           ├── div (ряд узлов)
│           │           │   ├── div (узел рефлектора 1)
│           │           │   │   └── a (ссылка узла)
│           │           │   └── div (узел рефлектора 2)
│           │           │       └── a (ссылка узла)
│           │           └── div (ряд узлов)
│           │               └── div (узел рефлектора 3)
│           │                   └── a (ссылка узла)
│           │
│           └── div.divTable (рефлектор 1.2)
│               ├── div.divTableBody
│               │   ├── div (инфо рефлектора)
│               │   │   ├── div "Reflector"
│               │   │   └── div#reflector[имя логики] (блок рефлектора)
│               │   └── div (линк)
│               │       ├── div "Link"
│               │       └── div#link[имя линка] (блок линка)
│               │           └── a (ссылка линка)
│               ├── div.divTable (talk groups)
│               │   ├── div "Talk Groups" (шапка talk groups)
│               │   └── div#reflector[имя рефлектора]TalkGroupsTableBody (тело talk groups)
│               │       ├── div (ряд групп)
│               │       │   ├── div (talk group 1)
│               │       │   ├── div (talk group 2)
│               │       │   ├── div (talk group 3)
│               │       │   └── div (talk group 4)
│               │       └── div (ряд групп)
│               │           └── div (talk group 5)
│               └── div.divTable (узлы рефлектора)
│                   ├── div "Nodes" (шапка узлов рефлектора)
│                   └── div#reflector[имя рефлектора]NodesTableBody (тело узлов рефлектора)
│                       └── div (ряд узлов)
│                           ├── div (узел рефлектора 1)
│                           │   └── a (ссылка узла)
│                           └── div (узел рефлектора 2)
│                               └── a (ссылка узла)
│
│       └── ... (опционально: дополнительные комплекты блоков логики №2...№n)
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

### Ключевые элементы интерфейса

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

Выполнен в формате отдельного сервиса (```/etc/systemd/system/svxlink-audio-capture.service```)


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
ExecStart=/usr/bin/node /var/www/html/scripts/svxlink-audio-proxy-server.js
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

Система обеспечивает полный мониторинг состояния SvxLink с обновлением в реальном времени через WebSocket v4.0, историческими данными и аудио мониторингом.