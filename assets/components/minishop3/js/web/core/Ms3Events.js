/**
 * MiniShop3 DOM events helper (Issue #16)
 *
 * Single place for dispatching loading/loaded events.
 * Loaded before UI modules so dispatchMs3Loading is available globally.
 *
 * @see https://github.com/modx-pro/MiniShop3/issues/16
 */

/**
 * Dispatch a custom event on document (e.g. ms3:cart:adding, ms3:cart:added).
 * Detail: { entity, action, form, data, response? }
 *
 * @param {string} name - Event name
 * @param {Object} detail - Event detail
 */
window.dispatchMs3Loading = function (name, detail) {
  document.dispatchEvent(new CustomEvent(name, { detail, bubbles: true }))
}
