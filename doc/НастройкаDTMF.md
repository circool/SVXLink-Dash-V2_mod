# Настройка

## Пользователь и пароль

При первом запуске система создаст файл /etc/svxlink/dashboard/auth.ini с хешированным паролем для учетной записи svxlink:svxlink


## Настройка разговорных групп рефлектора

Для того чтобы вместо номера разговорной группы рефлектора отображалось ее наименование,
в файл конфигурации svxlink.conf добавить раздел [ReflectorTG] и поместить в него соответствия номеров групп и их наименований

```svxlink.conf
[ReflectorTG]
1 = Название группы 1
2 = Название группы 2

```

## Управление DTMF

### В файле svxlink.conf

В блоке используемой логики ([SimplexLogic] или [RepeaterLogic]) указать устройство для передачи контроля DTMF

```svxlink.conf
[SimplexLogic]
...
DTMF_CTRL_PTY=/dev/shm/dtmf_ctrl
```

Перезапустить svxlink и убедится что файл создан

```bash
sudo systemctl restart svxlink
ls -l /dev/shm
```

Ответ должен быть что-то типа 
```
total 0
lrwxrwxrwx 1 svxlink svxlink 10 Nov 11 03:20 dtmf_ctrl -> /dev/pts/1
```
✅ Готово!




## Аудио мониторинг

### Убедится что существует Loopback:

```bash
cat /proc/asound/cards
# Ищем строку с "Loopback"
```

#### Если Loopback есть, проверить загружен ли модуль

```bash
# Проверить, есть ли модуль в системе
find /lib/modules/$(uname -r) -name "*aloop*"

# Проверить, загружен ли модуль
lsmod | grep snd_aloop
# Если пусто - модуль не загружен
```

```bash
# Tckb модуль не загружен - pагрузить модуль
sudo modprobe snd_aloop

# Проверить
lsmod | grep snd_aloop
# Теперь должно показать что-то вроде:
# snd_aloop 28672 0
```

#### Если Loopback отсутствует 

```bash
# Загрузить модуль без перезагрузки
sudo modprobe snd_aloop

# Проверить
cat /proc/asound/cards
# Теперь должен появиться Loopback
```


### Создать петлевое устройство

```bash
# Добавить модуль в автозагрузку
echo "snd_aloop" | sudo tee -a /etc/modules
# Задать индекс (опционально, чтобы Loopback был card 2)
echo "options snd_aloop index=2" | sudo tee /etc/modprobe.d/alsa-loopback.conf

sudo reboot now
```

#### Убедится что устройство появилось

```bash
cat /proc/asound/cards
```

```log
0 [Headphones     ]: bcm2835_headpho - bcm2835 Headphones
                      bcm2835 Headphones
 1 [Device         ]: USB-Audio - USB PnP Sound Device
                      C-Media Electronics Inc. USB PnP Sound Device at usb-0000:01:00.0-1.2, full spe
 2 [Loopback       ]: Loopback - Loopback
                      Loopback 1
 3 [vc4hdmi0       ]: vc4-hdmi - vc4-hdmi-0
                      vc4-hdmi-0
 4 [vc4hdmi1       ]: vc4-hdmi - vc4-hdmi-1
                      vc4-hdmi-1
```
#### Сконфигурировать его

```bash
sudo nano /etc/asound.conf
```
Поместить в файл строки

```
pcm.loophw {
    type hw
    card Loopback
    device 2
    subdevice 0
}

pcm.loopout {
    type plug
    slave.pcm "loophw"
}
```

#### Скрипт проверки и загрузки:

```bash
#!/bin/bash
echo "=== Проверка модуля snd-aloop ==="

# Проверяем, есть ли модуль в системе
if ! find /lib/modules/$(uname -r) -name "*aloop*" 2>/dev/null | grep -q .; then
    echo "❌ Модуль snd-aloop не найден в системе"
    echo "Попробуйте установить: sudo apt install linux-modules-extra-$(uname -r)"
    exit 1
fi

# Проверяем, загружен ли
if ! lsmod | grep -q snd_aloop; then
    echo "🔄 Модуль не загружен, загружаю..."
    if ! sudo modprobe snd_aloop; then
        echo "❌ Ошибка загрузки модуля"
        dmesg | tail -5
        exit 1
    fi
    echo "✅ Модуль загружен"
else
    echo "✅ Модуль уже загружен"
fi

# Проверяем устройства
echo -e "\n=== Проверка устройств ==="
if ! cat /proc/asound/cards | grep -q Loopback; then
    echo "❌ Устройство Loopback не найдено"
    echo "Попробуйте: sudo modprobe -r snd_aloop && sudo modprobe snd_aloop"
    exit 1
fi

echo "✅ Устройство Loopback найдено:"
cat /proc/asound/cards | grep Loopback

echo -e "\n=== Доступные устройства ==="
echo "Playback:"
aplay -l | grep Loopback || echo "Не найдены playback устройства"
echo -e "\nCapture:"
arecord -l | grep Loopback || echo "Не найдены capture устройства"
```

### Если модуля вообще нет в системе

```bash
# Для Raspberry Pi / Debian / Ubuntu
sudo apt update
sudo apt install linux-modules-extra-$(uname -r)

# Или пересобрать ядро с поддержкой
sudo modprobe configs
zcat /proc/config.gz | grep CONFIG_SND_ALOOP
# Должно быть: CONFIG_SND_ALOOP=m или =y
```

### Быстрая команда для загрузки если не загружен

```bash
# Одной командой
sudo modprobe snd_aloop 2>/dev/null || echo "Модуль snd-aloop не найден"
cat /proc/asound/cards | grep Loopback && echo "✅ Loopback загружен" || echo "❌ Loopback не загружен"
```

__Важно__: На Raspberry Pi модуль snd-aloop обычно есть в стандартной поставке, но может быть не загружен по умолчанию.




### Изменить настройки для svxlink.conf

В блоке используемой логики ([SimplexLogic] или [RepeaterLogic]) указать в качестве TX новое устройство [TxStream]

```svxlink.conf
[SimplexLogic]
#TX = Tx1
TX=MultiTx
```
Добавить само устройство (по аналогии в [TX1])

```svxlink.conf
[TxStream]
TYPE = Local
AUDIO_DEV = alsa:plughw:Loopback,0,0
AUDIO_CHANNEL = 0
PTT_TYPE = NONE
TIMEOUT = 7200
TX_DELAY = 0
PREEMPHASIS = 0
```

### Перезапустить сервис svxlink

```
sudo service svxlink restart
```

### Готово