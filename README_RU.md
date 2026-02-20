# Svxlink Dashboard

Функциональная предрелизная версия.

## Вдохновение

Этот проект был вдохновлен функциональностью:

- [SVXLink-Dashboard-V2 от F5VMR](https://github.com/f5vmr/SVXLink-Dash-V2)

и дизайном

- [WPSD Project](https://w0chp.radio/wpsd/)

## Лицензия

Проект включает элементы дизайна, заимствованные из WPSD Project (стили и расположение элементов),
распространяемые под лицензией GNU FDL v1.3.

Оригинальное авторское право: Copyright © 2023 WPSD Project Development Team, et al.

## Описание

Полностью переписанная реализация панели управления SVXLink с современной архитектурой.

Детальная техническая информация доступна в **PROJECT_DETAILS.md**

## Возможности

Это протестированная и функциональная версия, хотя она все еще находится в активной разработке (исправляются мелкие проблемы, оптимизируются подходы и алгоритмы, добавляются функции). Пожалуйста, проявите терпение.

Я приложил все усилия, чтобы у вас не возникло трудностей; однако всегда есть вероятность, что инструкции могут содержать неточности или устаревшую информацию.

### Новый подход

Панель предназначена для владельцев/администраторов SVXLink, отображая состояние сервера в реальном времени[^note1] и позволяя управлять его состоянием[^note2].

### Современный интерфейс

- Интерфейс в стиле WPSD

![Main screen](./readme_images/main_screen_en.jpg)

- Отключаемый[^note3] мультиязычный интерфейс с возможностью добавления своих языков

### Двухрежимное обновление

- Доступны режимы обновления в реальном времени и периодический

![Menu](./readme_images/top_menu_en_realtime.jpg)
![Menu](./readme_images/top_menu_en_ajax.jpg)

### Состояния Сервиса, Логики, Рефлекторов, Модулей и Линков

#### Сервис
![Service](./readme_images/service_en.jpg)

#### Состояние логики и модулей

- Активен, но не подключен

![Modules](./readme_images/module_disconnected_en.jpg)

- Активен и подключен

![Modules](./readme_images/module_connected_en.jpg)

#### Переключаемые мышью модули и линки

![Modules](./readme_images/switchable_module_state_en.jpg)

#### Состояние и информация рефлектора

![Reflectors](./readme_images/reflector_en.jpg)

#### Расширенная всплывающая информация о линке

![Link](./readme_images/switchable_links_en.jpg)

#### Различные цвета для выбранной и временно отслеживаемой разговорной группы

![Talk Groups](./readme_images/tg_panel.jpg)

### Дополнительная информация

#### Статус подключения APRS

![APRS](./readme_images/aprs_en.jpg)

#### Статус каталога EchoLink и прокси

![EL Directory](./readme_images/directory_en.jpg)

#### Скрываемая панель макросов

Различные цвета кнопок для различных модулей

![Macros](./readme_images/macros_en.jpg)

### Статус приемника и передатчика

#### Основная строка приемника и передатчика

![Radio status](./readme_images/radio_status_single_en.jpg)
![Radio status](./readme_images/el_callsign_en.jpg)
![Radio status](./readme_images/radio_transmit_en.jpg)

#### Дополнительные строки рефлекторов

![Radio status](./readme_images/radio_status_en.jpg)

#### Обнаружение ошибок

Индикация перегрузок и ошибок тайм-аутов для устройств (включая составные)

![Peak](./readme_images/peak_meter_en.jpg)
![Peak](./readme_images/tx_error_en.jpg)
![Peak](./readme_images/tx_timeout_en.jpg)

### Удобная DTMF клавиатура, управляемая мышью или прямым вводом с клавиатуры для управления состоянием сервера, аналогично отправке сигналов управления через эфир

#### Компактный вид для одной логики

![DTMF Keypad](./readme_images/keypad_single_en.jpg)

#### Поддержка нескольких логик

![DTMF Keypad](./readme_images/dtmf_keypad.jpg)

### Аудио-мониторинг передаваемого сигнала

Слушайте активность через веб-браузер

![Monitor off](./readme_images/monitor_off_en.jpg)
![Monitor on](./readme_images/monitor_on_en.jpg)

### Скрываемая информация о текущем состоянии

#### Чат EchoLink, сообщения и поддержка malformed

![EchoLink details](./readme_images/el_msg_en.jpg)
![EchoLink details](./readme_images/el_info_en.jpg)
![EchoLink details](./readme_images/el_repeater_details.jpg)

#### Подключенные узлы Frn сервера

![Frn details](./readme_images/frn_details_en.jpg)

### Исторические данные с настраиваемыми лимитами керчангов

![kerchungs](./readme_images/kerchung_en.jpg)

#### Настраиваемая[^note3] сетевая активность

![network](./readme_images/network_activity_en.jpg)

#### Настраиваемая[^note3] локальная активность (входящее RF)

![rf](./readme_images/rf_activity_en.jpg)

## Технические примечания

Это независимая реализация, имеющая отношение к оригинальному проекту лишь по схожим принципам.

## Важное примечание о совместимости

**Этот проект НЕ является заменой или обновлением для оригинального SVXLink-Dashboard-V2**

### Что было сохранено из оригинала:

**Система аудио-мониторинга** была сохранена из [источника - f5vmr/SVXLink-Dash-V2](https://github.com/f5vmr/SVXLink-Dash-V2) как проверенное и рабочее решение.

## Для пользователей оригинального проекта

Если вы ищете:
- **Обновления для оригинального SVXLink-Dashboard-V2** → пожалуйста, обратитесь к [оригинальному репозиторию](https://github.com/f5vmr/SVXLink-Dash-V2)
- **Совместимые улучшения** → этот проект не для вас
- **Полностью переработанную альтернативу** → пожалуйста, читайте дальше

## Установка

Инструкции по развертыванию доступны в `doc/INSTALL.md`

[^note1]: Статус радио, активный модуль, рефлектор, подключенные узлы.
[^note2]: Параметр `DTMF_CTRL_PTY` должен быть настроен для управления.
[^note3]: Настраивается параметром в файле `settings.php`.
