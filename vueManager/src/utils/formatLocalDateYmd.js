/**
 * Format a date as YYYY-MM-DD in the browser's local calendar.
 *
 * Do not use Date.toISOString().split('T')[0] — ISO is UTC and shifts the
 * calendar day east/west of UTC (issue #386).
 *
 * @param {string|number|Date|null|undefined} date
 * @returns {string|null}
 */
export function formatLocalDateYmd(date) {
  if (date === null || date === undefined || date === '') {
    return null
  }

  const d = date instanceof Date ? date : new Date(date)
  if (Number.isNaN(d.getTime())) {
    return null
  }

  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}
