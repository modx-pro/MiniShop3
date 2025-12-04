/**
 * UI handlers for order
 *
 * Manages order forms: auto-save fields, validation, submission.
 */
class OrderUI {
  /**
   * @param {OrderAPI} orderAPI - Order API instance
   * @param {Object} hooks - Hook system
   * @param {Object} message - Message system
   * @param {Object} config - Configuration
   */
  constructor (orderAPI, hooks, message, config) {
    this.order = orderAPI
    this.hooks = hooks
    this.message = message
    this.config = config
  }

  /**
   * Initialize UI handlers
   */
  init () {
    document.querySelectorAll('.ms3_order_form').forEach(form => {
      this.initForm(form)
    })
  }

  /**
   * Initialize single form
   *
   * @param {HTMLFormElement} form - Order form
   */
  initForm (form) {
    const inputs = form.querySelectorAll('input, textarea, select')

    inputs.forEach(input => {
      if (input.name === 'address_hash') {
        this.initAddressInput(input)
      } else {
        this.initRegularInput(input)
      }
    })
  }

  /**
   * Handler for regular fields (auto-save on change)
   *
   * @param {HTMLInputElement} input - Input field
   */
  initRegularInput (input) {
    input.addEventListener('change', async () => {
      const parent = input.closest('div')
      if (!parent) return

      parent.classList.remove('was-validated')
      input.classList.remove('is-invalid')
      const feedback = parent.querySelector('.invalid-feedback')
      if (feedback) {
        feedback.textContent = ''
      }

      const response = await this.handleAdd(input.name, input.value)

      if (response.success) {
        parent.classList.add('was-validated')

        if (response.data && response.data[input.name] !== undefined) {
          input.value = response.data[input.name]
        }
      } else {
        parent.classList.add('was-validated')
        input.classList.add('is-invalid')

        if (feedback) {
          feedback.textContent = response.message || 'Validation error'
        }
      }
    })
  }

  /**
   * Handler for address field
   *
   * @param {HTMLInputElement} input - Address field
   */
  initAddressInput (input) {
    input.addEventListener('change', async () => {
      const form = input.closest('.ms3_order_form')
      const parent = input.closest('div')
      if (!parent) return

      parent.classList.remove('was-validated')
      input.classList.remove('is-invalid')
      const feedback = parent.querySelector('.invalid-feedback')
      if (feedback) {
        feedback.textContent = ''
      }
    })
  }

  /**
   * Add/update order field
   *
   * @param {string} key - Field key
   * @param {string} value - Value
   * @returns {Promise<Object>}
   */
  async handleAdd (key, value) {
    const hookData = { key, value }
    await this.hooks.runHooks('beforeAddOrder', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      const response = await this.order.add(key, value)

      await this.hooks.runHooks('afterAddOrder', { key, value, response })

      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      return response
    } catch (error) {
      this.message.error('An error occurred')
      return { success: false, message: error.message }
    }
  }

  /**
   * Submit order
   *
   * @returns {Promise<Object>}
   */
  async handleSubmit () {
    const hookData = {}
    await this.hooks.runHooks('beforeSubmitOrder', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      const response = await this.order.submit()

      await this.hooks.runHooks('afterSubmitOrder', { response })

      if (response.success && response.data && response.data.redirect) {
        window.location.href = response.data.redirect
        return response
      }

      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)

        if (response.errors && Array.isArray(response.errors)) {
          this.highlightErrors(response.errors)
        }
      }

      return response
    } catch (error) {
      this.message.error('Order submission error')
      return { success: false }
    }
  }

  /**
   * Clear order
   *
   * @returns {Promise<Object>}
   */
  async handleClean () {
    const hookData = {}
    await this.hooks.runHooks('beforeCleanOrder', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      const response = await this.order.clean()

      await this.hooks.runHooks('afterCleanOrder', { response })

      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      if (response.success) {
        document.querySelectorAll('.ms3_order_form').forEach(form => {
          form.reset()
        })
      }

      return response
    } catch (error) {
      this.message.error('Order clearing error')
      return { success: false }
    }
  }

  /**
   * Highlight fields with errors
   *
   * @param {Array<string>} errors - Array of field names with errors
   */
  highlightErrors (errors) {
    document.querySelectorAll('.ms3_field_error').forEach(el => {
      el.classList.remove('ms3_field_error')
    })

    errors.forEach(fieldName => {
      const selectors = [
        `[name="${fieldName}"]`,
        `[name="address_${fieldName}"]`,
        `[name="order_${fieldName}"]`
      ]

      selectors.forEach(selector => {
        const field = document.querySelector(selector)
        if (field) {
          field.classList.add('ms3_field_error')

          field.addEventListener('focus', function removeError () {
            field.classList.remove('ms3_field_error')
            field.removeEventListener('focus', removeError)
          }, { once: true })
        }
      })
    })
  }
}
