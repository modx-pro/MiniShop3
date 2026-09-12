/**
 * Hook system for extending functionality
 *
 * Allows developers to add custom logic before/after operations:
 * - beforeAddCart / afterAddCart
 * - beforeChangeCart / afterChangeCart
 * - beforeSubmitOrder / afterSubmitOrder
 * - etc.
 */
window.ms3Hooks = {
  items: {},

  /**
   * Add hook
   *
   * @param {string} name - Hook name (e.g., 'beforeAddCart')
   * @param {Function} fn - Handler function
   *
   * @example
   * ms3Hooks.addHook('beforeAddCart', async (context) => {
   *   console.log('Adding product:', context.id)
   *   // Can cancel operation:
   *   // context.cancel = true
   * })
   */
  addHook (name, fn) {
    if (!this.items[name]) this.items[name] = []
    this.items[name].push(fn)
  },

  /**
   * Run hooks
   *
   * @param {string} name - Hook name
   * @param {Object} context - Execution context (data available in hook)
   */
  async runHooks (name, context) {
    if (!this.items[name]) return

    for (const fn of this.items[name]) {
      if (context.cancel) {
        return false
      }

      try {
        await fn(context)
      } catch (error) {
        console.error(`Error executing hook "${name}":`, error)
      }
    }
  },
}

window.ms3Hooks.addHook('afterSendRequest', () => {
  setTimeout(() => {
    if (window.ms3 && window.ms3.cartUI) {
      window.ms3.cartUI.init()
    }
  }, 100)
})
