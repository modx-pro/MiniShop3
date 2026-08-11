/**
 * Shared display formatters for mgr grids / order UI.
 * Keep pure (no Vue state). Order-specific helpers stay in useOrderFormatters.
 */

export function formatDate(dateString, locale = 'ru-RU') {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString(locale, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Format date with a simple token pattern (e.g. dd.MM.yyyy HH:mm).
 */
export function formatDatePattern(dateString, format = 'dd.MM.yyyy HH:mm') {
  if (!dateString) return '-'
  const date = new Date(dateString)
  const pad = n => n.toString().padStart(2, '0')

  return format
    .replace('yyyy', date.getFullYear())
    .replace('yy', date.getFullYear().toString().slice(-2))
    .replace('MM', pad(date.getMonth() + 1))
    .replace('dd', pad(date.getDate()))
    .replace('HH', pad(date.getHours()))
    .replace('mm', pad(date.getMinutes()))
    .replace('ss', pad(date.getSeconds()))
}

export function formatPrice(value, locale = 'ru-RU') {
  if (value === null || value === undefined) return '-'
  return new Intl.NumberFormat(locale, {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value)
}

/**
 * Format price using column / ms3.config options (thousands, currency, decimals).
 */
export function formatPriceConfigured(value, column = {}, ms3Config = null) {
  if (value === null || value === undefined) return '-'

  const decimals = column.decimals ?? ms3Config?.price_decimals ?? 2
  const thousandsSeparator =
    column.thousands_separator ?? ms3Config?.price_thousands_separator ?? ' '
  const decimalSeparator = column.decimal_separator ?? ms3Config?.price_decimal_separator ?? ','
  const currency = column.currency ?? ms3Config?.price_currency ?? ''
  const currencyPosition = column.currency_position ?? ms3Config?.price_currency_position ?? 'after'

  const [integerPart, fractionPart] = Number(value).toFixed(decimals).split('.')
  const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSeparator)
  let formatted = fractionPart
    ? `${formattedInteger}${decimalSeparator}${fractionPart}`
    : formattedInteger

  if (!currency) {
    return formatted
  }

  return currencyPosition === 'before' ? `${currency}${formatted}` : `${formatted} ${currency}`
}

/**
 * Render a field value for a grid column (template tokens or plain name).
 */
export function renderField(data, column) {
  if (!column) return ''
  if (column.template) {
    return column.template.replace(/\{(\w+)\}/g, (match, key) => data?.[key] ?? '')
  }
  return data?.[column.name]
}

/**
 * Resolve lexicon key to display name; returns original when no translation exists.
 *
 * @param {string} name
 * @param {(key: string) => string} translate
 */
export function getDisplayName(name, translate) {
  if (!name) return ''
  const translated = translate(name)
  return translated !== name ? translated : name
}

/**
 * Format a grid cell value (boolean, number, plain).
 *
 * @param {*} value
 * @param {{ type?: string, format?: string }} column
 * @param {(key: string) => string} translate
 */
export function formatValue(value, column, translate) {
  if (value === null || value === undefined) return ''

  if (column?.type === 'boolean') {
    return value ? translate('yes') : translate('no')
  }

  if (column?.format === 'number') {
    return Number(value).toLocaleString()
  }

  return value
}

/**
 * Normalize media path for <img src>: keep absolute URLs, ensure leading slash.
 */
export function normalizeImagePath(path) {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
    return path
  }
  return `/${path}`
}
