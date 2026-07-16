/**
 * Normalize list API payloads for useResourceList.
 *
 * @param {unknown} response
 * @param {string} [itemsKey='results']
 * @returns {{ items: Array, total: number, malformed: boolean }}
 */
export function parsePageResponse(response, itemsKey = 'results') {
  if (Array.isArray(response)) {
    return { items: response, total: response.length, malformed: false }
  }

  if (response && Array.isArray(response[itemsKey])) {
    return {
      items: response[itemsKey],
      total: response.total ?? response[itemsKey].length,
      malformed: false,
    }
  }

  return { items: [], total: 0, malformed: true }
}
