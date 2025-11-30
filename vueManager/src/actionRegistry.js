/**
 * MS3ActionRegistry - Реестр обработчиков действий для гридов
 *
 * Позволяет:
 * - Регистрировать кастомные обработчики действий
 * - Использовать встроенные действия (edit, delete, view)
 * - Расширять функциональность через плагины
 *
 * Использование в плагинах:
 * ```javascript
 * MS3ActionRegistry.register('blockCustomer', async (data, context) => {
 *   await fetch(`/api/mgr/customers/${data.id}/block`, { method: 'POST' })
 *   context.refresh() // Обновить грид
 * })
 * ```
 */

class ActionRegistry {
  constructor() {
    this.handlers = new Map()
    this.beforeHooks = new Map()
    this.afterHooks = new Map()

    // Регистрируем встроенные действия
    this._registerBuiltinActions()
  }

  /**
   * Регистрация встроенных действий
   * @private
   */
  _registerBuiltinActions() {
    // Edit - эмитит событие для открытия диалога редактирования
    this.register('edit', (data, context) => {
      context.emit('edit', data)
    })

    // Delete - эмитит событие для удаления с подтверждением
    this.register('delete', (data, context) => {
      context.emit('delete', data)
    })

    // View - эмитит событие для просмотра
    this.register('view', (data, context) => {
      context.emit('view', data)
    })

    // Addresses - эмитит событие для управления адресами
    this.register('addresses', (data, context) => {
      context.emit('addresses', data)
    })

    // Refresh - обновить грид
    this.register('refresh', (data, context) => {
      context.refresh()
    })
  }

  /**
   * Регистрация обработчика действия
   *
   * @param {string} name - Имя действия (например: 'edit', 'delete', 'blockCustomer')
   * @param {Function} handler - Функция обработчик: (data, context) => void
   *   - data: объект данных строки грида
   *   - context: объект контекста {emit, refresh, toast, confirm, gridId}
   * @param {Object} options - Дополнительные опции
   *   - override: boolean - разрешить перезапись существующего обработчика
   */
  register(name, handler, options = {}) {
    if (typeof handler !== 'function') {
      console.error(`[MS3ActionRegistry] Handler for "${name}" must be a function`)
      return false
    }

    if (this.handlers.has(name) && !options.override) {
      console.warn(`[MS3ActionRegistry] Handler "${name}" already exists. Use override: true to replace`)
      return false
    }

    this.handlers.set(name, handler)
    return true
  }

  /**
   * Удаление обработчика
   * @param {string} name - Имя действия
   */
  unregister(name) {
    // Защита встроенных действий
    const builtins = ['edit', 'delete', 'view', 'refresh']
    if (builtins.includes(name)) {
      console.warn(`[MS3ActionRegistry] Cannot unregister builtin action "${name}"`)
      return false
    }

    return this.handlers.delete(name)
  }

  /**
   * Проверка наличия обработчика
   * @param {string} name - Имя действия
   */
  has(name) {
    return this.handlers.has(name)
  }

  /**
   * Получение обработчика
   * @param {string} name - Имя действия
   */
  get(name) {
    return this.handlers.get(name)
  }

  /**
   * Выполнение действия
   *
   * @param {string} name - Имя действия
   * @param {Object} data - Данные строки грида
   * @param {Object} context - Контекст выполнения
   * @returns {Promise<any>}
   */
  async execute(name, data, context) {
    const handler = this.handlers.get(name)

    if (!handler) {
      console.error(`[MS3ActionRegistry] Handler "${name}" not found`)
      return null
    }

    try {
      // Выполняем before hooks
      const beforeHooks = this.beforeHooks.get(name) || []
      for (const hook of beforeHooks) {
        const shouldContinue = await hook(data, context)
        if (shouldContinue === false) {
          return null // Hook отменил выполнение
        }
      }

      // Выполняем основной обработчик
      const result = await handler(data, context)

      // Выполняем after hooks
      const afterHooks = this.afterHooks.get(name) || []
      for (const hook of afterHooks) {
        await hook(data, context, result)
      }

      return result
    } catch (error) {
      console.error(`[MS3ActionRegistry] Error executing "${name}":`, error)

      // Показываем toast с ошибкой если доступен
      if (context.toast) {
        context.toast.add({
          severity: 'error',
          summary: 'Ошибка',
          detail: error.message || 'Произошла ошибка при выполнении действия',
          life: 5000
        })
      }

      throw error
    }
  }

  /**
   * Регистрация хука before (выполняется до действия)
   *
   * @param {string} actionName - Имя действия
   * @param {Function} hook - Функция хука: (data, context) => boolean
   *   Возврат false отменяет выполнение действия
   */
  registerBeforeHook(actionName, hook) {
    if (!this.beforeHooks.has(actionName)) {
      this.beforeHooks.set(actionName, [])
    }
    this.beforeHooks.get(actionName).push(hook)
  }

  /**
   * Регистрация хука after (выполняется после действия)
   *
   * @param {string} actionName - Имя действия
   * @param {Function} hook - Функция хука: (data, context, result) => void
   */
  registerAfterHook(actionName, hook) {
    if (!this.afterHooks.has(actionName)) {
      this.afterHooks.set(actionName, [])
    }
    this.afterHooks.get(actionName).push(hook)
  }

  /**
   * Получение списка всех зарегистрированных действий
   * @returns {string[]}
   */
  getRegisteredActions() {
    return Array.from(this.handlers.keys())
  }

  /**
   * Получение информации о действии для UI
   * @param {string} name - Имя действия
   * @returns {Object|null}
   */
  getActionInfo(name) {
    if (!this.handlers.has(name)) return null

    // Встроенные действия с метаданными
    const builtinMeta = {
      edit: { icon: 'pi-pencil', labelKey: 'edit', severity: null },
      delete: { icon: 'pi-trash', labelKey: 'delete', severity: 'danger' },
      view: { icon: 'pi-eye', labelKey: 'view', severity: 'secondary' },
      addresses: { icon: 'pi-map-marker', labelKey: 'addresses', severity: 'secondary' },
      refresh: { icon: 'pi-refresh', labelKey: 'refresh', severity: 'secondary' }
    }

    return builtinMeta[name] || { icon: 'pi-cog', labelKey: name, severity: null }
  }
}

// Создаём синглтон
const actionRegistry = new ActionRegistry()

// Экспортируем для использования в Vue компонентах
export default actionRegistry

// Регистрируем глобально для доступа из плагинов
if (typeof window !== 'undefined') {
  window.MS3ActionRegistry = actionRegistry
}
