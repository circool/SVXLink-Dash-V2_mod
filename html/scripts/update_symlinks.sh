#!/bin/bash

# Скрипт для создания симлинков на последние версии файлов
# Ищет файлы с версиями в указанной папке-параметре
# Создает симлинки в директории, где лежит сам скрипт

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Функция для вывода сообщений
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Функция для проверки симлинка
check_symlink() {
    local link="$1"
    local target="$2"
    
    if [ -L "$link" ]; then
        # Проверяем, существует ли целевой файл
        if [ ! -e "$link" ]; then
            log_warn "Битый симлинк: $link"
            return 2  # битый симлинк
        fi
        
        # Проверяем, ведет ли на правильный файл
        current_target=$(readlink -f "$link")
        if [ "$current_target" = "$target" ]; then
            return 0  # правильный симлинк
        else
            return 1  # симлинк ведет не туда
        fi
    fi
    
    return 3  # не симлинк
}

# Функция для вывода справки
show_help() {
    echo "Использование: $(basename "$0") ПАПКА_С_ВЕРСИЯМИ"
    echo ""
    echo "Ищет файлы с версиями в указанной папке и создает симлинки без версий"
    echo "в директории, где находится этот скрипт."
    echo ""
    echo "Аргументы:"
    echo "  ПАПКА_С_ВЕРСИЯМИ    Обязательный параметр. Папка с файлами в формате name.X.Y.Z.ext"
    echo ""
    echo "Пример:"
    echo "  # Скрипт лежит в /var/www/html/scripts/"
    echo "  # Файлы с версиями лежат в /var/www/html/scripts/exct/"
    echo "  /var/www/html/scripts/update_simlinks.sh exct"
    echo ""
    echo "Формат файлов: name.X.Y.Z.ext (например: config.1.2.3.yaml, data.0.5.1.json)"
    echo ""
    echo "Результат:"
    echo "  В /var/www/html/scripts/ создадутся симлинки на последние версии из exct/"
    exit 0
}

# Проверяем флаг помощи
if [[ "$1" == "-h" || "$1" == "--help" ]]; then
    show_help
fi

# Проверяем, передан ли параметр
if [ $# -eq 0 ]; then
    log_error "Не указана папка с версионированными файлами"
    echo ""
    show_help
fi

# Определяем директорию с версионированными файлами
SOURCE_DIR="$1"

# Проверяем, существует ли указанная директория
if [ ! -d "$SOURCE_DIR" ]; then
    log_error "Указанная папка не существует: $SOURCE_DIR"
    exit 1
fi

# Получаем абсолютный путь к директории скрипта
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT_NAME="$(basename "${BASH_SOURCE[0]}")"

# Преобразуем SOURCE_DIR в абсолютный путь относительно директории скрипта
if [[ ! "$SOURCE_DIR" = /* ]]; then
    # Если путь относительный, делаем его абсолютным относительно директории скрипта
    SOURCE_DIR="$(cd "$SCRIPT_DIR" && cd "$SOURCE_DIR" && pwd)"
else
    SOURCE_DIR="$(cd "$SOURCE_DIR" && pwd)"
fi

log_info "Папка с версиями: $SOURCE_DIR"
log_info "Папка для симлинков: $SCRIPT_DIR"

# Проверяем, не пытаемся ли создать симлинк на саму папку с версиями
if [ "$SCRIPT_DIR" = "$SOURCE_DIR" ]; then
    log_error "Директория для симлинков не может быть той же, что и директория с версиями"
    exit 1
fi

# Проверяем, есть ли в директории файлы
FILES_COUNT=$(find "$SOURCE_DIR" -maxdepth 1 -type f 2>/dev/null | wc -l)

if [ "$FILES_COUNT" -eq 0 ]; then
    log_error "В указанной папке не найдено файлов: $SOURCE_DIR"
    exit 1
fi

log_info "Поиск файлов с версиями в $SOURCE_DIR..."

# Функция для извлечения версии из имени файла
# Ожидаемый формат: name.X.Y.Z.ext
extract_version() {
    local filename="$1"
    # Ищем паттерн .X.Y.Z. перед расширением
    if [[ "$filename" =~ \.([0-9]+)\.([0-9]+)\.([0-9]+)(\.[^.]+)?$ ]]; then
        major="${BASH_REMATCH[1]}"
        minor="${BASH_REMATCH[2]}"
        patch="${BASH_REMATCH[3]}"
        echo "$((major * 10000 + minor * 100 + patch))"
    else
        echo "0"
    fi
}

# Функция для получения базового имени файла (без версии)
get_base_name() {
    local filename="$1"
    # Убираем .X.Y.Z с конца имени файла (перед расширением)
    echo "$filename" | sed -E 's/\.[0-9]+\.[0-9]+\.[0-9]+(\.[^.]+)?$//'
}

# Функция для получения расширения файла (после версии)
get_extension() {
    local filename="$1"
    # Извлекаем расширение после версии
    if [[ "$filename" =~ \.[0-9]+\.[0-9]+\.[0-9]+(\.[^.]+)$ ]]; then
        echo "${BASH_REMATCH[1]}"
    else
        echo ""
    fi
}

# Находим все файлы с версионированием
declare -A latest_versions  # Ассоциативный массив: базовое_имя -> последняя_версия
declare -A latest_files     # Ассоциативный массив: базовое_имя -> полное_имя_файла
declare -A file_extensions  # Ассоциативный массив: базовое_имя -> расширение

# Сканируем файлы в исходной директории
while IFS= read -r file; do
    filename=$(basename "$file")
    
    # Пропускаем пустые строки
    [ -z "$filename" ] && continue
    
    # Пропускаем сам скрипт, если он оказался в SOURCE_DIR
    if [[ "$filename" == "$SCRIPT_NAME" ]]; then
        continue
    fi
    
    # Проверяем, имеет ли файл версию в имени
    version=$(extract_version "$filename")
    
    if [ "$version" -gt 0 ]; then
        base_name=$(get_base_name "$filename")
        extension=$(get_extension "$filename")
        
        # Сохраняем расширение
        file_extensions["$base_name"]="$extension"
        
        # Проверяем, есть ли уже более новая версия
        if [ -z "${latest_versions[$base_name]}" ] || [ "$version" -gt "${latest_versions[$base_name]}" ]; then
            latest_versions["$base_name"]="$version"
            latest_files["$base_name"]="$filename"
            log_info "Найдена версия: $filename (базовое имя: $base_name, версия: $version)"
        fi
    fi
done < <(find "$SOURCE_DIR" -maxdepth 1 -type f 2>/dev/null)

# Проверяем, нашли ли мы файлы с версиями
if [ ${#latest_files[@]} -eq 0 ]; then
    log_error "Не найдено файлов с версиями в формате name.X.Y.Z.ext"
    log_info "Пример ожидаемого формата: settings.0.2.2.php, config.1.0.0.yaml, data.2.1.5.json"
    
    # Показываем какие файлы есть в директории
    log_info "\nФайлы в директории $SOURCE_DIR:"
    find "$SOURCE_DIR" -maxdepth 1 -type f ! -name "$SCRIPT_NAME" -exec basename {} \; 2>/dev/null | sort
    
    exit 1
fi

log_info "Найдено ${#latest_files[@]} файлов с версиями"

# Создаем симлинки в директории скрипта
created_count=0
fixed_count=0
for base_name in "${!latest_files[@]}"; do
    source_file="$SOURCE_DIR/${latest_files[$base_name]}"
    extension="${file_extensions[$base_name]}"
    
    # Формируем имя симлинка (в директории скрипта)
    if [ -n "$extension" ]; then
        link_name="$SCRIPT_DIR/${base_name}${extension}"
    else
        link_name="$SCRIPT_DIR/${base_name}"
    fi
    
    # Формируем относительный путь для симлинка (от SCRIPT_DIR к SOURCE_DIR)
    relative_source_path=$(realpath --relative-to="$SCRIPT_DIR" "$source_file")
    
    # Проверяем, существует ли исходный файл
    if [ ! -f "$source_file" ]; then
        log_error "Исходный файл не найден: $source_file"
        continue
    fi
    
    # Проверяем существующий симлинк
    check_result=0
    check_symlink "$link_name" "$source_file"
    check_result=$?
    
    case $check_result in
        0)  # Правильный симлинк
            log_info "Симлинк уже существует и ведет на правильный файл: $(basename "$link_name") → ${latest_files[$base_name]}"
            continue
            ;;
        1)  # Симлинк ведет не туда
            log_warn "Симлинк существует, но ведет на другой файл: $(basename "$(readlink -f "$link_name")")"
            log_info "Исправляю симлинк..."
            rm "$link_name"
            ;;
        2)  # Битый симлинк
            log_warn "Битый симлинк: $(basename "$link_name") → $(readlink "$link_name")"
            log_info "Исправляю битый симлинк..."
            rm "$link_name"
            ((fixed_count++))
            ;;
        3)  # Не симлинк (обычный файл)
            if [ -e "$link_name" ]; then
                log_warn "Файл $(basename "$link_name") уже существует и не является симлинком"
                backup_name="${link_name}.backup.$(date +%Y%m%d_%H%M%S)"
                mv "$link_name" "$backup_name"
                log_info "Создана резервная копия: $(basename "$backup_name")"
            fi
            ;;
    esac
    
    # Создаем симлинк с относительным путем
    ln -s "$relative_source_path" "$link_name"
    
    if [ $? -eq 0 ]; then
        if [ $check_result -eq 1 ] || [ $check_result -eq 2 ]; then
            log_info "Исправлен симлинк: $(basename "$link_name") → $relative_source_path"
        else
            log_info "Создан симлинк: $(basename "$link_name") → $relative_source_path"
        fi
        ((created_count++))
    else
        log_error "Ошибка при создании симлинка для $(basename "$link_name")"
    fi
done

# Проверяем все симлинки в директории скрипта на битость
log_info "\nПроверка существующих симлинков в $SCRIPT_DIR:"
symlink_count=0
broken_count=0

for item in "$SCRIPT_DIR"/*; do
    if [ -L "$item" ]; then
        filename=$(basename "$item")
        target_path=$(readlink "$item")
        target_full=$(readlink -f "$item")
        
        if [ ! -e "$item" ]; then
            log_error "Битый симлинк: $filename → $target_path"
            ((broken_count++))
        else
            # Получаем имя целевого файла (только имя, без пути)
            target_name=$(basename "$target_full")
            log_info "Симлинк: $filename → $target_name"
        fi
        ((symlink_count++))
    fi
done

# Показываем статистику
log_info "\n=== Статистика ==="
log_info "Всего найдено версионированных файлов: ${#latest_files[@]}"
log_info "Создано/обновлено симлинков: $created_count"
if [ $fixed_count -gt 0 ]; then
    log_info "Исправлено битых симлинков: $fixed_count"
fi
if [ $broken_count -gt 0 ]; then
    log_error "Найдено битых симлинков: $broken_count"
fi
log_info "Всего симлинков в папке: $symlink_count"

if [ $broken_count -gt 0 ]; then
    log_warn "\nВНИМАНИЕ: В папке есть битые симлинки!"
    log_info "Вы можете удалить их командой: find \"$SCRIPT_DIR\" -type l ! -exec test -e {} \; -delete"
fi

log_info "\nГотово!"