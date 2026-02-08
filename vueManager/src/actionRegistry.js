/**
 * MS3ActionRegistry - Action handlers registry for grids
 *
 * Features:
 * - Register custom action handlers
 * - Use built-in actions (edit, delete, view)
 * - Extend functionality through plugins
 *
 * Plugin usage:
 * ```javascript
 * MS3ActionRegistry.register('blockCustomer', async (data, context) => {
 *   await fetch(`/api/mgr/customers/${data.id}/block`, { method: 'POST' })
 *   context.refresh()
 * })
 * ```
 */

class ActionRegistry {
  constructor() {
    this.handlers = new Map()
    this.beforeHooks = new Map()
    this.afterHooks = new Map()

    this._registerBuiltinActions()
  }

  /**
   * Register built-in actions
   * @private
   */
  _registerBuiltinActions() {
    this.register('edit', (data, context) => {
      context.emit('edit', data)
    })

    this.register('delete', (data, context) => {
      context.emit('delete', data)
    })

    this.register('view', (data, context) => {
      context.emit('view', data)
    })

    this.register('addresses', (data, context) => {
      context.emit('addresses', data)
    })

    this.register('publish', (data, context) => {
      context.emit('publish', data)
    })

    this.register('duplicate', (data, context) => {
      context.emit('duplicate', data)
    })

    this.register('refresh', (data, context) => {
      context.refresh()
    })
  }

  /**
   * Register action handler
   *
   * @param {string} name - Action name (e.g.: 'edit', 'delete', 'blockCustomer')
   * @param {Function} handler - Handler function: (data, context) => void
   *   - data: grid row data object
   *   - context: context object {emit, refresh, toast, confirm, gridId}
   * @param {Object} options - Additional options
   *   - override: boolean - allow overriding existing handler
   */
  register(name, handler, options = {}) {
    if (typeof handler !== 'function') {
      console.error(`[MS3ActionRegistry] Handler for "${name}" must be a function`)
      return false
    }

    if (this.handlers.has(name) && !options.override) {
      console.warn(
        `[MS3ActionRegistry] Handler "${name}" already exists. Use override: true to replace`
      )
      return false
    }

    this.handlers.set(name, handler)
    return true
  }

  /**
   * Unregister handler
   * @param {string} name - Action name
   */
  unregister(name) {
    const builtins = ['edit', 'delete', 'view', 'refresh']
    if (builtins.includes(name)) {
      console.warn(`[MS3ActionRegistry] Cannot unregister builtin action "${name}"`)
      return false
    }

    return this.handlers.delete(name)
  }

  /**
   * Check if handler exists
   * @param {string} name - Action name
   */
  has(name) {
    return this.handlers.has(name)
  }

  /**
   * Get handler
   * @param {string} name - Action name
   */
  get(name) {
    return this.handlers.get(name)
  }

  /**
   * Execute action
   *
   * @param {string} name - Action name
   * @param {Object} data - Grid row data
   * @param {Object} context - Execution context
   * @returns {Promise<any>}
   */
  async execute(name, data, context) {
    const handler = this.handlers.get(name)

    if (!handler) {
      console.error(`[MS3ActionRegistry] Handler "${name}" not found`)
      return null
    }

    try {
      const beforeHooks = this.beforeHooks.get(name) || []
      for (const hook of beforeHooks) {
        const shouldContinue = await hook(data, context)
        if (shouldContinue === false) {
          return null
        }
      }

      const result = await handler(data, context)

      const afterHooks = this.afterHooks.get(name) || []
      for (const hook of afterHooks) {
        await hook(data, context, result)
      }

      return result
    } catch (error) {
      console.error(`[MS3ActionRegistry] Error executing "${name}":`, error)

      if (context.toast) {
        context.toast.add({
          severity: 'error',
          summary: 'Error',
          detail: error.message || 'An error occurred while executing action',
          life: 5000,
        })
      }

      throw error
    }
  }

  /**
   * Register before hook (executed before action)
   *
   * @param {string} actionName - Action name
   * @param {Function} hook - Hook function: (data, context) => boolean
   *   Return false to cancel action execution
   */
  registerBeforeHook(actionName, hook) {
    if (!this.beforeHooks.has(actionName)) {
      this.beforeHooks.set(actionName, [])
    }
    this.beforeHooks.get(actionName).push(hook)
  }

  /**
   * Register after hook (executed after action)
   *
   * @param {string} actionName - Action name
   * @param {Function} hook - Hook function: (data, context, result) => void
   */
  registerAfterHook(actionName, hook) {
    if (!this.afterHooks.has(actionName)) {
      this.afterHooks.set(actionName, [])
    }
    this.afterHooks.get(actionName).push(hook)
  }

  /**
   * Get list of all registered actions
   * @returns {string[]}
   */
  getRegisteredActions() {
    return Array.from(this.handlers.keys())
  }

  /**
   * Get action info for UI
   * @param {string} name - Action name
   * @returns {Object|null}
   */
  getActionInfo(name) {
    if (!this.handlers.has(name)) return null

    const builtinMeta = {
      edit: { icon: 'pi-pencil', labelKey: 'edit', severity: null },
      delete: { icon: 'pi-trash', labelKey: 'delete', severity: 'danger' },
      view: { icon: 'pi-eye', labelKey: 'view', severity: 'secondary' },
      addresses: { icon: 'pi-map-marker', labelKey: 'addresses', severity: 'secondary' },
      refresh: { icon: 'pi-refresh', labelKey: 'refresh', severity: 'secondary' },
    }

    return builtinMeta[name] || { icon: 'pi-cog', labelKey: name, severity: null }
  }
}

const actionRegistry = new ActionRegistry()

export default actionRegistry
if (typeof window !== 'undefined') {
  window.MS3ActionRegistry = actionRegistry
}
