# Документация по проекту SvxLink Dashboard by R2ADU

Дата обновления 06.01.2026

Версия 0.3.x


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

Данные реального времени поставляет websocket сервер а обрабатывает клиент. Таким образом обновляются часто меняющиеся состояния, например, активность передатчика/приемника;
  - Сервер отслеживает изменения журнала (tail ...)
  - Выделяет значимые события
  - Создает структуру обновлений (какой эл. DOM следует обновить и как)
  - Клиент применяет полученные обновления к структуре DOM

#### Данные обновляемые динамически

Различаем две группы данных:

1. Данные о состоянии сервиса (активный модуль у логики (включая его подключенных узлов/клиентов), состояние линка рефлектора, его узлов)
Состояние сервиса вычисляется периодически, желательно не реже 1-5 сек, путем анализа новых событий журнала. 
Для этого необходимо иметь механизм обновления, который хранит сведения о своем последнем запуске, анализирует
тольно новые данные, выделяя события влияющие на состояние сервиса (изменение состояния линков, модулей логик итд)
Реализация такого механизма должна быть реализована в функциях получения последних данных журнала;
На остовании таких данных можем реализовать отображение состояние так:
 - концепция А
  Данные обновляются путем обновления всей страницы - делаем страницу перезагружаемой раз в сек, при загрузке страницы анализируем наличие истории и принимаем решение
  о обьеме необходимых данных (количестве последних строк журнала, которые следует проанализировать)
  
 - концепция Б
  Данные обновляются адресно - только изменившееся состояние, перерисовываются отдельные элементы DOM

 - концепция В
  Данные перерисовываются адресно, под управлением websocket сервера реального времени - парсер анализирует события в реальном времени и отправляет клиентам уведомления о необходимости установить новое состояние;

  Клиент выполняет перерисовку элементов DOM или вызывает легкий выхов перезагрузки всей страницы, с последующим анализом последний n строк журнала, 
  обеспечивая быстрый анализ без необходимости перерасчета всех изменений с момента запуска сервися.

2. История - данные о активности - последние несколько событий для соответствующих режимов, напр. последняя локальная активность, последняя сетевая активность (вызовы пришедшие из интернета, от рефлекторов напр. ) 

Исторические сводки вычисляются соотв. функциями отдельно для каждого блока. 
Данные получаются путем агализа журнала svxlink, 
в переделах последней активной сессии (перезапуска сервиса). Интервал обновления таких данных устанавливаем таким, чтобы не нагружать систему - напр. 5 сек.





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
    - `connected_nodes`: array
  - `talkgroups`: array (опционально)
    - `default`: string
    - `selected`: string
    - `monitoring`: array
    - `temp_monitoring`: string
  - `connected_nodes`: array (опционально)
  - `hosts`: string (опционально)

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
│   │   ├── getActualStatus.0.2.1.php             # Строит начальное состояние системы
│   │   ├── getRadioStatus.0.2.0.php              # @deprecated v. 0.3 getRadioStatus()
│   │   ├── getNewState.php							          # Прокси -> /include/fn/getNewState.0.2.1.php
├── scripts/                                      здесь хранятся либо окончательные версии скриптов, либо симлинки на текущие (сами скрипты находятся в /scripts/exct)
│   ├── exct/                                     # сдесь хранятся версионированные источники
│   │   ├── dashboard_ws_server.0.3.2.js          # __WebSocket сервер__ 
│   │   └── dashboard_ws_client.0.3.3.js          # __WebSocket клиент__
│   ├── jquery.min.js 								            #
│   ├── server.js 									              # websocket Audio Monitor :8001
│   ├── featherlight.js         				          # 
│   ├── __Симлинки__
│   ├── dashboard_ws_server.js   			            # simlink dashboard_ws_server.js -> exct/dashboard_ws_server.0.3.2.js
│   ├── dashboard_ws_client.js   			            # simlink -> exct/dashboard_ws_client.0.3.3.js
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
│           ├── div.divTable (узлы активного модуля)
│           │   ├── div "Nodes" (шапка узлов)
│           │   └── div#module[имя модуля]NodesTableBody (тело узлов модуля)
│           │       └── div (ряд узлов)
│           │           └── div (узел)
│           │               └── a (ссылка узла)
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
│       │   └── div#debugLog
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


## 7. Сервис аудио мониторинга

### Сервер

Автоматически перезапускается через 5 секунд после падения.

@todo Периодически теряет захват аудио, исправляется перезагрузкой сервиса ```svxlink-node.service```

Выполнен в формате отдельного сервиса (```/etc/systemd/system/svxlink-node.service```)


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
ExecStart=/usr/bin/node /var/www/html/scripts/server.js
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```



#### Управление:

| Команда | Действие |
|---------|----------|
| `sudo systemctl stop svxlink-node.service` | Остановить сервер (не будет перезапущен) |
| `sudo systemctl start svxlink-node.service` | Запустить сервер |
| `sudo systemctl restart svxlink-node.service` | Перезапустить сервер |
| `sudo systemctl disable svxlink-node.service` | Отключить автозапуск |
| `sudo systemctl enable svxlink-node.service` | Включить автозапуск |
| `sudo journalctl -u svxlink-node.service -f` | Просмотр логов в реальном времени |
| `sudo journalctl -u svxlink-node.service --since="1 hour ago"` | Логи за последний час |

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