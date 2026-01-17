#!/bin/bash

# Скрипт для создания симлинков на последние версии файлов
# Ищет файлы с версиями в указанной папке-параметре
# Создает симлинки только для тех файлов, которые уже существуют в директории скрипта

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
    echo "в директории, где находится этот скрипт, ТОЛЬКО для файлов, которые уже"
    echo "существуют в директории скрипта (как обычные файлы или симлинки)."
    echo ""
    echo "Аргументы:"
    echo "  ПАПКА_С_ВЕРСИЯМИ    Обязательный параметр. Папка с файлами в формате name.X.Y.Z.ext"
    echo ""
    echo "Пример:"
    echo "  # Скрипт лежит в /var/www/html/scripts/"
    echo "  # Файлы с версиями лежат в /var/www/html/scripts/exct/"
    echo "  # В /var/www/html/scripts/ уже есть файлы: config.yaml, data.json"
    echo "  /var/www/html/scripts/update_simlinks.sh exct"
    echo ""
    echo "Формат файлов: name.X.Y.Z.ext (например: config.1.2.3.yaml, data.0.5.1.json)"
    echo ""
    echo "Результат:"
    echo "  В /var/www/html/scripts/ обновятся симлинки на последние версии из exct/"
    echo "  только для тех файлов, которые уже есть в /var/www/html/scripts/"
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

# Собираем список существующих файлов в директории скрипта (кроме самого скрипта)
log_info "Поиск файлов в директории скрипта..."
declare -A existing_files  # Ассоциативный массив: имя_файла -> тип (file/link)

while IFS= read -r item; do
    filename=$(basename "$item")
    [ -z "$filename" ] && continue
    [ "$filename" = "$SCRIPT_NAME" ] && continue
    
    if [ -L "$item" ]; then
        existing_files["$filename"]="link"
    elif [ -f "$item" ]; then
        existing_files["$filename"]="file"
    fi
done < <(find "$SCRIPT_DIR" -maxdepth 1 \( -type f -o -type l \) 2>/dev/null)

if [ ${#existing_files[@]} -eq 0 ]; then
    log_info "В директории скрипта не найдено файлов или симлинков (кроме самого скрипта)"
    log_info "Создание симлинков не требуется"
    exit 0
fi

log_info "Найдено ${#existing_files[@]} файлов/симлинков в директории скрипта"

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

# Функция для генерации всех возможных имен кандидатов (без версии)
get_candidate_names() {
    local versioned_filename="$1"
    
    # Если файл не содержит версию, возвращаем его имя
    version_check=$(extract_version "$versioned_filename")
    if [ "$version_check" -eq 0 ]; then
        echo "$versioned_filename"
        return
    fi
    
    base_name=$(get_base_name "$versioned_filename")
    extension=$(get_extension "$versioned_filename")
    
    if [ -n "$extension" ]; then
        echo "${base_name}${extension}"
    else
        echo "$base_name"
    fi
}

# Находим файлы с версиями в SOURCE_DIR
log_info "Поиск файлов с версиями в $SOURCE_DIR..."
declare -A versioned_candidates  # Ассоциативный массив: кандидат -> [версия=файл,...]
declare -A candidates_to_process  # Ассоциативный массив: кандидат -> 1 (если существует в SCRIPT_DIR)

# Сначала сканируем SOURCE_DIR для создания списка кандидатов
while IFS= read -r source_file; do
    filename=$(basename "$source_file")
    [ -z "$filename" ] && continue
    
    version=$(extract_version "$filename")
    if [ "$version" -gt 0 ]; then
        candidate=$(get_candidate_names "$filename")
        
        # Добавляем версию в список кандидатов
        if [ -z "${versioned_candidates[$candidate]}" ]; then
            versioned_candidates["$candidate"]="${version}=$filename"
        else
            versioned_candidates["$candidate"]="${versioned_candidates[$candidate]}:${version}=$filename"
        fi
    fi
done < <(find "$SOURCE_DIR" -maxdepth 1 -type f 2>/dev/null)

# Теперь проверяем, какие кандидаты существуют в директории скрипта
for candidate in "${!versioned_candidates[@]}"; do
    if [ -n "${existing_files[$candidate]}" ]; then
        candidates_to_process["$candidate"]=1
        log_info "Найдено соответствие: $candidate (${existing_files[$candidate]}) имеет версии в $SOURCE_DIR"
    fi
done

# Проверяем, нашли ли мы кандидатов для обработки
if [ ${#candidates_to_process[@]} -eq 0 ]; then
    log_info "Не найдено файлов в директории скрипта, для которых есть версии в $SOURCE_DIR"
    
    # Показываем какие файлы есть в директориях для справки
    log_info "\nФайлы в директории скрипта:"
    for filename in "${!existing_files[@]}"; do
        echo "  - $filename (${existing_files[$filename]})"
    done | sort
    
    log_info "\nФайлы с версиями в $SOURCE_DIR:"
    for candidate in "${!versioned_candidates[@]}"; do
        versions_str="${versioned_candidates[$candidate]}"
        # Берем первую версию для примера
        first_pair="${versions_str%%:*}"
        version="${first_pair%%=*}"
        file="${first_pair#*=}"
        echo "  - $file → $candidate (версия: $version)"
    done | sort
    
    log_info "\nСоздание симлинков не требуется"
    exit 0
fi

log_info "Найдено ${#candidates_to_process[@]} файлов для обработки"

# Для каждого кандидата находим последнюю версию
declare -A latest_files  # Ассоциативный массив: кандидат -> полное_имя_файла_с_версией

for candidate in "${!candidates_to_process[@]}"; do
    versions_str="${versioned_candidates[$candidate]}"
    latest_version=0
    latest_file=""
    
    # Разбираем все версии для этого кандидата
    IFS=':' read -ra VERSION_PAIRS <<< "$versions_str"
    for pair in "${VERSION_PAIRS[@]}"; do
        version="${pair%%=*}"
        file="${pair#*=}"
        
        if [ "$version" -gt "$latest_version" ]; then
            latest_version="$version"
            latest_file="$file"
        fi
    done
    
    if [ -n "$latest_file" ]; then
        latest_files["$candidate"]="$latest_file"
        log_info "Последняя версия для $candidate: $latest_file (версия: $latest_version)"
    fi
done

# Создаем/обновляем симлинки ТОЛЬКО для существующих файлов
created_count=0
fixed_count=0
for candidate in "${!latest_files[@]}"; do
    source_file="$SOURCE_DIR/${latest_files[$candidate]}"
    link_name="$SCRIPT_DIR/$candidate"
    
    # Формируем относительный путь для симлинка (от SCRIPT_DIR к SOURCE_DIR)
    relative_source_path=$(realpath --relative-to="$SCRIPT_DIR" "$source_file")
    
    # Проверяем, существует ли исходный файл
    if [ ! -f "$source_file" ]; then
        log_error "Исходный файл не найден: $source_file"
        continue
    fi
    
    # Проверяем существующий симлинк или файл
    check_result=0
    check_symlink "$link_name" "$source_file"
    check_result=$?
    
    case $check_result in
        0)  # Правильный симлинк
            log_info "Симлинк уже существует и ведет на правильный файл: $candidate → ${latest_files[$candidate]}"
            continue
            ;;
        1)  # Симлинк ведет не туда
            current_target=$(readlink -f "$link_name" 2>/dev/null || readlink "$link_name")
            current_target_name=$(basename "$current_target" 2>/dev/null || echo "неизвестно")
            log_warn "Симлинк существует, но ведет на другой файл: $candidate → $current_target_name"
            log_info "Исправляю симлинк на последнюю версию..."
            rm -f "$link_name"
            ;;
        2)  # Битый симлинк
            current_target=$(readlink "$link_name")
            log_warn "Битый симлинк: $candidate → $current_target"
            log_info "Исправляю битый симлинк..."
            rm -f "$link_name"
            ((fixed_count++))
            ;;
        3)  # Не симлинк (обычный файл)
            if [ -e "$link_name" ] && [ ! -L "$link_name" ]; then
                log_warn "Файл $candidate уже существует и не является симлинком"
                backup_name="${link_name}.backup.$(date +%Y%m%d_%H%M%S)"
                mv "$link_name" "$backup_name"
                log_info "Создана резервная копия: $(basename "$backup_name")"
            fi
            ;;
    esac
    
    # Создаем симлинк с относительным путем
    ln -sf "$relative_source_path" "$link_name"
    
    if [ $? -eq 0 ]; then
        if [ $check_result -eq 1 ] || [ $check_result -eq 2 ]; then
            log_info "Исправлен симлинк: $candidate → $relative_source_path"
        else
            log_info "Создан симлинк: $candidate → $relative_source_path"
        fi
        ((created_count++))
    else
        log_error "Ошибка при создании симлинка для $candidate"
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
        target_full=$(readlink -f "$item" 2>/dev/null)
        
        if [ ! -e "$item" ]; then
            log_error "Битый симлинк: $filename → $target_path"
            ((broken_count++))
        else
            # Получаем имя целевого файла (только имя, без пути)
            target_name=$(basename "$target_full" 2>/dev/null || echo "$target_path")
            log_info "Симлинк: $filename → $target_name"
        fi
        ((symlink_count++))
    fi
done

# Показываем статистику
log_info "\n=== Статистика ==="
log_info "Всего файлов в директории скрипта: ${#existing_files[@]}"
log_info "Найдено кандидатов с версиями: ${#candidates_to_process[@]}"
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
