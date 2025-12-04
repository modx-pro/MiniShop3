/**
 * MODX utilities for formatting, validation and data conversion
 */

/**
 * Format price according to MODX/MiniShop3 settings
 *
 * @param {number} price - Price
 * @param {Object} options - Formatting options
 * @returns {string} - Formatted price
 */
export function formatPrice(price, options = {}) {
  const {
    decimals = 2,
    decPoint = '.',
    thousandsSep = ' ',
    currency = window.ms3?.config?.price_format_currency || 'USD',
    currencyPosition = window.ms3?.config?.price_format_currency_position || 'right'
  } = options;

  const numPrice = parseFloat(price) || 0;

  const parts = numPrice.toFixed(decimals).split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
  const formatted = parts.join(decPoint);

  return currencyPosition === 'left'
    ? `${currency} ${formatted}`
    : `${formatted} ${currency}`;
}

/**
 * Format date in MODX format
 *
 * @param {string|number|Date} date - Date
 * @param {string} format - Format (php-style or predefined variants)
 * @returns {string} - Formatted date
 */
export function formatDate(date, format = 'datetime') {
  if (!date) return '';

  const d = date instanceof Date ? date : new Date(date);

  if (isNaN(d.getTime())) return '';

  const formats = {
    date: 'd.m.Y',
    datetime: 'd.m.Y H:i',
    time: 'H:i',
    full: 'd.m.Y H:i:s'
  };

  const formatString = formats[format] || format;

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
 * Parse MODX TV value to array
 *
 * @param {string} value - TV value (via ||, @EVAL, etc.)
 * @param {string} separator - Separator
 * @returns {Array} - Array of values
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
 * Convert MODX timestamp to Date object
 *
 * @param {number|string} timestamp - Unix timestamp or date string
 * @returns {Date|null} - Date object or null
 */
export function timestampToDate(timestamp) {
  if (!timestamp) return null;

  const num = Number(timestamp);
  if (isNaN(num)) return null;

  return new Date(num * 1000);
}

/**
 * Get file type icon
 *
 * @param {string} filename - File name
 * @returns {string} - CSS icon class
 */
export function getFileIcon(filename) {
  if (!filename) return 'pi pi-file';

  const ext = filename.split('.').pop().toLowerCase();

  const icons = {
    jpg: 'pi pi-image',
    jpeg: 'pi pi-image',
    png: 'pi pi-image',
    gif: 'pi pi-image',
    webp: 'pi pi-image',
    svg: 'pi pi-image',

    pdf: 'pi pi-file-pdf',
    doc: 'pi pi-file-word',
    docx: 'pi pi-file-word',
    xls: 'pi pi-file-excel',
    xlsx: 'pi pi-file-excel',

    zip: 'pi pi-file',
    rar: 'pi pi-file',
    '7z': 'pi pi-file',

    mp4: 'pi pi-video',
    avi: 'pi pi-video',
    mov: 'pi pi-video',
    wmv: 'pi pi-video'
  };

  return icons[ext] || 'pi pi-file';
}

/**
 * Check if value is JSON string
 *
 * @param {string} str - String to check
 * @returns {boolean} - Check result
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
 * Safe JSON parse with fallback
 *
 * @param {string} str - JSON string
 * @param {any} defaultValue - Default value
 * @returns {any} - Parsed value or defaultValue
 */
export function safeJsonParse(str, defaultValue = null) {
  try {
    return JSON.parse(str);
  } catch (e) {
    return defaultValue;
  }
}

/**
 * Generate alias from string (transliteration)
 *
 * @param {string} str - Source string
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
 * Truncate string with ellipsis
 *
 * @param {string} str - String
 * @param {number} maxLength - Maximum length
 * @returns {string} - Truncated string
 */
export function truncate(str, maxLength = 50) {
  if (!str || str.length <= maxLength) return str;
  return str.substring(0, maxLength) + '...';
}

/**
 * Escape HTML
 *
 * @param {string} str - String
 * @returns {string} - Escaped string
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
 * Debounce function
 *
 * @param {Function} func - Function to debounce
 * @param {number} wait - Delay in ms
 * @returns {Function} - Wrapped function
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
