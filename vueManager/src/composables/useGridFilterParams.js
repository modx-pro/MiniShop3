import { ref } from 'vue'

/**
 * Grid filter param serialization using direct_filter_keys from grid-config API.
 *
 * Keys listed by the backend are sent as-is; others get the filter_ prefix.
 */
export function useGridFilterParams() {
  const directFilterKeys = ref(new Set())

  function setDirectFilterKeys(keys) {
    directFilterKeys.value = new Set(keys || [])
  }

  function addFilterParam(params, key, value) {
    if (directFilterKeys.value.has(key)) {
      params[key] = value
      return
    }

    params[`filter_${key}`] = value
  }

  return {
    directFilterKeys,
    setDirectFilterKeys,
    addFilterParam,
  }
}
