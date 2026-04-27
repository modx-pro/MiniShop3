/**
 * Manager URLs for MiniShop3 «Поля форм» (Utilities → model fields).
 * Action `mgr/utilities` — MiniShop3 `controllers/mgr/utilities.class.php`.
 * @see https://github.com/modx-pro/MiniShop3/issues/234
 */

export const MS3_MODEL_ORDER = 'msOrder'
export const MS3_MODEL_ORDER_ADDRESS = 'msOrderAddress'

/**
 * Ext tab id — must match Ext `items[].id` in `assets/.../utilities.panel.js`
 * (component id `ms3-utilities-model-fields-tab`). If you rename one, rename both.
 */
export const MS3_UTILITIES_MODEL_FIELDS_TAB_ID = 'ms3-utilities-model-fields-tab'

/**
 * Same-origin link: Utilities, «Поля форм» tab, optional `model` for ModelFieldsGrid.
 * @param {string} model — API model id, e.g. {@link MS3_MODEL_ORDER}
 * @returns {string} relative query starting with `?` (same manager origin)
 */
export function buildManagerModelFieldsSettingsUrl(model) {
  const q = new URLSearchParams()
  q.set('a', 'mgr/utilities')
  q.set('namespace', 'minishop3')
  q.set('tab', MS3_UTILITIES_MODEL_FIELDS_TAB_ID)
  if (model) {
    q.set('model', model)
  }
  return `?${q.toString()}`
}

/**
 * Read `model` from current location (hash is ignored).
 * @returns {string|null}
 */
export function getModelQueryParam() {
  try {
    return new URLSearchParams(window.location.search).get('model')
  } catch {
    return null
  }
}
