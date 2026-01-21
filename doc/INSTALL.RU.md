```markdown
# Руководство по установке SVXLink Dashboard

## Обязательные требования

1. Установите часовой пояс системы в файле `/etc/timezone`.
2. Убедитесь, что в разделе `[GLOBAL]` конфигурации SVXLink установлен параметр `TIMESTAMP_FORMAT` с миллисекундами. Например:  
   `TIMESTAMP_FORMAT = "%d %b %Y %H:%M:%S.%f"` (`.%f` — миллисекунды).

## Необязательные настройки

3. Для управления DTMF убедитесь, что в логике SVXLink установлен параметр `DTMF_CTRL_PTY` в значение `/dev/shm/dtmf_ctrl` или другой путь.

4.1. Для мониторинга аудио настройте в логике устройство `TX` с поддержкой нескольких устройств.

```svxlink.conf
[SimplexLogic]
#TX = Tx1
TX=MultiTx
```
4.2. Добавить само устройство (по аналогии в [TX1])

```svxlink.conf
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
## Быстрая установка

1. **Загрузите файлы**: скопируйте все файлы на ваш веб-сервер.
2. **Скопируйте и запустите скрипт установки** из `doc/install.sh`.
3. @TODO **Автоматическая настройка**: откройте браузер и перейдите на адрес вашего сайта.
4. @TODO **Установка в один клик**: вы будете автоматически перенаправлены на страницу настройки.
5. @TODO **Нажмите "Run Setup"**: система создаст все необходимые файлы.
6. **Войдите в систему**: используйте учетные данные по умолчанию: **svxlink** / **svxlink**.
7. **Обеспечьте безопасность**: смените пароль после первого входа.

## Учетные данные по умолчанию
- **Логин**: `svxlink`
- **Пароль**: `svxlink`

## Ручная установка (при необходимости)

```bash
# Создайте каталог для панели управления
sudo mkdir -p /etc/svxlink/dashboard

# Установите корректного владельца (при необходимости замените www-data на пользователя вашего веб-сервера)
sudo chown svxlink:svxlink /etc/svxlink/dashboard

# Установите соответствующие права доступа
sudo chmod 755 /etc/svxlink/dashboard

# Скопируйте пример конфигурации
sudo cp config/sample.auth.ini /etc/svxlink/dashboard/auth.ini

# Установите права доступа (при необходимости измените для пользователя вашего веб-сервера)
sudo chown www-data:www-data /etc/svxlink/dashboard/auth.ini
sudo chmod 644 /etc/svxlink/dashboard/auth.ini
```

## Поддержка

Если настройка завершилась ошибкой, проверьте:

- Наличие прав на запись у веб-сервера.
- Возможность создания каталогов PHP.
- Отсутствие блокировок со стороны фаервола.

После настройки удалите каталог установки в целях безопасности.

## Настройка SVXLink

### Дополнительная логика

При добавлении нестандартной логики (например, для `ReflectorLogicMyName`) не забудьте создать соответствующий TCL-файл, просто скопировав существующий стандартный и изменив его имя рабочей области.

```bash
cp ReflectorLogic.tcl ReflectorLogicMyName.tcl
nano ReflectorLogicMyName.tcl
```

```tcl
###############################################################################
#
# Обработчики событий ReflectorLogic
#
###############################################################################

#
# Это пространство имён, в котором будут находиться все функции ниже. Имя
# должно соответствовать соответствующему разделу "[ReflectorLogic]" в файле
# конфигурации. Имя можно изменить, но изменение должно быть выполнено в обоих местах.
#
namespace eval ReflectorLogic {
...

```

Замените `ReflectorLogic` на `ReflectorLogicMyName` и сохраните файл.

## Примечания по бета-тестированию

1. Убедитесь, что необходимые файлы присутствуют в каталоге сайта:

```bash
sudo chmod +x files_check.sh
./files_check.sh
```

2. Обновите символические ссылки для каталогов `/include`, `/include/fn/` и `/scripts` и в корне :

```bash
cd /var/www/html/include
sudo chmod +x update_symlink.sh
sudo ./update_symlink.sh exct

cd /var/www/html/include/fn
sudo chmod +x update_symlink.sh
sudo ./update_symlink.sh exct

cd /var/www/html/scripts
sudo chmod +x update_symlink.sh
sudo ./update_symlink.sh exct

cd /var/www/html
sudo chmod +x update_symlink.sh
sudo ./update_symlink.sh include/exct

sudo ./set_rights.sh
```
