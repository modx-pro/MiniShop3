import { computed, ref } from 'vue'

import { useGridFilterParams } from './useGridFilterParams.js'

/**
 * Filter state helpers for mgr grids (sorted list, apply/clear, daterange params).
 * Builds on useGridFilterParams for direct_filter_keys serialization.
 *
 * @param {Object} [options]
 * @param {() => void|Promise<void>} [options.onApply] Called after apply/clear (usually reload list)
 * @param {(first: number) => void} [options.setFirst] Optional pagination reset hook
 * @returns {Object}
 */
export function useGridFilters(options = {}) {
  const { onApply = null, setFirst = null } = options
  const { setDirectFilterKeys, addFilterParam } = useGridFilterParams()

  const filters = ref({})
  const filterValues = ref({})

  const sortedFilters = computed(() =>
    Object.entries(filters.value)
      .map(([key, config]) => ({ key, ...config }))
      .sort((a, b) => (a.position || 100) - (b.position || 100))
  )

  const hasActiveFilters = computed(() =>
    Object.values(filterValues.value).some(value => !isEmptyFilterValue(value))
  )

  function formatDateForApi(date) {
    if (!date) return null
    return new Date(date).toISOString().split('T')[0]
  }

  function initFilterValues() {
    filterValues.value = Object.fromEntries(Object.keys(filters.value).map(key => [key, null]))
  }

  function setFilters(nextFilters) {
    filters.value = nextFilters || {}
    initFilterValues()
  }

  function appendFilterParams(params) {
    for (const [key, value] of Object.entries(filterValues.value)) {
      if (isEmptyFilterValue(value)) {
        continue
      }

      const filterConfig = filters.value[key]
      if (filterConfig?.type === 'daterange' && Array.isArray(value)) {
        if (value[0]) {
          addFilterParam(
            params,
            filterConfig.fields?.from || `${key}_from`,
            formatDateForApi(value[0])
          )
        }
        if (value[1]) {
          addFilterParam(params, filterConfig.fields?.to || `${key}_to`, formatDateForApi(value[1]))
        }
        continue
      }

      if (filterConfig?.type === 'datepicker' && value) {
        addFilterParam(params, key, formatDateForApi(value))
        continue
      }

      addFilterParam(params, key, value)
    }
  }

  async function applyFilters() {
    await runFilterAction()
  }

  async function clearFilters() {
    await runFilterAction(true)
  }

  async function runFilterAction(clearValues = false) {
    if (clearValues) {
      initFilterValues()
    }
    setFirst?.(0)
    if (onApply) {
      await onApply()
    }
  }

  return {
    filters,
    filterValues,
    sortedFilters,
    hasActiveFilters,
    setFilters,
    setDirectFilterKeys,
    addFilterParam,
    appendFilterParams,
    initFilterValues,
    applyFilters,
    clearFilters,
    formatDateForApi,
  }
}

function isEmptyFilterValue(value) {
  return value === null || value === undefined || value === ''
}
