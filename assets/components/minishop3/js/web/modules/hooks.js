/**
 * Система хуков для расширения функциональности
 *
 * Позволяет разработчикам добавлять свою логику до/после операций:
 * - beforeAddCart / afterAddCart
 * - beforeChangeCart / afterChangeCart
 * - beforeSubmitOrder / afterSubmitOrder
 * - и т.д.
 */
window.ms3Hooks = {
  items: {},

  /**
   * Добавить хук
   *
   * @param {string} name - Название хука (например: 'beforeAddCart')
   * @param {Function} fn - Функция-обработчик
   *
   * @example
   * ms3Hooks.addHook('beforeAddCart', async (context) => {
   *   console.log('Добавляем товар:', context.id)
   *   // Можно отменить операцию:
   *   // context.cancel = true
   * })
   */
  addHook (name, fn) {
    if (!this.items[name]) this.items[name] = []
    this.items[name].push(fn)
  },

  /**
   * Запустить хуки
   *
   * @param {string} name - Название хука
   * @param {Object} context - Контекст выполнения (данные доступные в хуке)
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
  }
}

// Пример хука: переинициализация UI после обновления корзины
window.ms3Hooks.addHook('afterSendRequest', () => {
  // Даём время на обновление DOM
  setTimeout(() => {
    if (window.ms3 && window.ms3.cartUI) {
      window.ms3.cartUI.init()
    }
  }, 100)
})
