/**
 * MODX утилиты для работы с форматированием, валидацией и конвертацией данных
 */

/**
 * Форматирование цены согласно настройкам MODX/MiniShop3
 *
 * @param {number} price - Цена
 * @param {Object} options - Опции форматирования
 * @returns {string} - Отформатированная цена
 */
export function formatPrice(price, options = {}) {
  const {
    decimals = 2,
    decPoint = '.',
    thousandsSep = ' ',
    currency = window.ms3?.config?.price_format_currency || 'руб.',
    currencyPosition = window.ms3?.config?.price_format_currency_position || 'right'
  } = options;

  // Конвертация в число
  const numPrice = parseFloat(price) || 0;

  // Форматирование
  const parts = numPrice.toFixed(decimals).split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
  const formatted = parts.join(decPoint);

  // Добавление валюты
  return currencyPosition === 'left'
    ? `${currency} ${formatted}`
    : `${formatted} ${currency}`;
}

/**
 * Форматирование даты в формате MODX
 *
 * @param {string|number|Date} date - Дата
 * @param {string} format - Формат (php-style или готовые варианты)
 * @returns {string} - Отформатированная дата
 */
export function formatDate(date, format = 'datetime') {
  if (!date) return '';

  const d = date instanceof Date ? date : new Date(date);

  if (isNaN(d.getTime())) return '';

  // Готовые форматы
  const formats = {
    date: 'd.m.Y',
    datetime: 'd.m.Y H:i',
    time: 'H:i',
    full: 'd.m.Y H:i:s'
  };

  const formatString = formats[format] || format;

  // Простое форматирование (можно расширить)
  const pad = (num) => String(num).padStart(2, '0');

  return formatString
    .replace('d', pad(d.getDate()))
    .replace('m', pad(d.getMonth() + 1))
    .replace('Y', d.getFullYear())
    .replace('H', pad(d.getHours()))
    .replace('i', pad(d.getMinutes()))
    .replace('s', pad(d.getSeconds()));
}

/**
 * Парсинг MODX TV значения в массив
 *
 * @param {string} value - Значение TV (через ||, @EVAL и т.д.)
 * @param {string} separator - Разделитель
 * @returns {Array} - Массив значений
 */
export function parseTvValue(value, separator = '||') {
  if (!value) return [];
  if (Array.isArray(value)) return value;

  return String(value)
    .split(separator)
    .map(v => v.trim())
    .filter(v => v.length > 0);
}

/**
 * Конвертация MODX timestamp в Date объект
 *
 * @param {number|string} timestamp - Unix timestamp или строка даты
 * @returns {Date|null} - Date объект или null
 */
export function timestampToDate(timestamp) {
  if (!timestamp) return null;

  const num = Number(timestamp);
  if (isNaN(num)) return null;

  return new Date(num * 1000); // MODX использует секунды
}

/**
 * Получить иконку типа файла
 *
 * @param {string} filename - Имя файла
 * @returns {string} - CSS класс иконки
 */
export function getFileIcon(filename) {
  if (!filename) return 'pi pi-file';

  const ext = filename.split('.').pop().toLowerCase();

  const icons = {
    // Изображения
    jpg: 'pi pi-image',
    jpeg: 'pi pi-image',
    png: 'pi pi-image',
    gif: 'pi pi-image',
    webp: 'pi pi-image',
    svg: 'pi pi-image',

    // Документы
    pdf: 'pi pi-file-pdf',
    doc: 'pi pi-file-word',
    docx: 'pi pi-file-word',
    xls: 'pi pi-file-excel',
    xlsx: 'pi pi-file-excel',

    // Архивы
    zip: 'pi pi-file',
    rar: 'pi pi-file',
    '7z': 'pi pi-file',

    // Видео
    mp4: 'pi pi-video',
    avi: 'pi pi-video',
    mov: 'pi pi-video',
    wmv: 'pi pi-video'
  };

  return icons[ext] || 'pi pi-file';
}

/**
 * Проверка является ли значение JSON строкой
 *
 * @param {string} str - Строка для проверки
 * @returns {boolean} - Результат проверки
 */
export function isJsonString(str) {
  try {
    JSON.parse(str);
    return true;
  } catch (e) {
    return false;
  }
}

/**
 * Безопасный парсинг JSON с fallback
 *
 * @param {string} str - JSON строка
 * @param {any} defaultValue - Значение по умолчанию
 * @returns {any} - Распарсенное значение или defaultValue
 */
export function safeJsonParse(str, defaultValue = null) {
  try {
    return JSON.parse(str);
  } catch (e) {
    return defaultValue;
  }
}

/**
 * Генерация alias из строки (транслитерация)
 *
 * @param {string} str - Исходная строка
 * @returns {string} - Alias
 */
export function generateAlias(str) {
  if (!str) return '';

  const translitMap = {
    'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd',
    'е': 'e', 'ё': 'yo', 'ж': 'zh', 'з': 'z', 'и': 'i',
    'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n',
    'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't',
    'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch',
    'ш': 'sh', 'щ': 'sch', 'ъ': '', 'ы': 'y', 'ь': '',
    'э': 'e', 'ю': 'yu', 'я': 'ya'
  };

  return str
    .toLowerCase()
    .split('')
    .map(char => translitMap[char] || char)
    .join('')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/**
 * Обрезка строки с многоточием
 *
 * @param {string} str - Строка
 * @param {number} maxLength - Максимальная длина
 * @returns {string} - Обрезанная строка
 */
export function truncate(str, maxLength = 50) {
  if (!str || str.length <= maxLength) return str;
  return str.substring(0, maxLength) + '...';
}

/**
 * Экранирование HTML
 *
 * @param {string} str - Строка
 * @returns {string} - Экранированная строка
 */
export function escapeHtml(str) {
  if (!str) return '';

  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  return String(str).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Дебаунс функция
 *
 * @param {Function} func - Функция для дебаунса
 * @param {number} wait - Задержка в мс
 * @returns {Function} - Обернутая функция
 */
export function debounce(func, wait = 300) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}
