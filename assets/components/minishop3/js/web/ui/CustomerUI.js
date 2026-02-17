/**
 * UI handlers for customer data
 *
 * Manages customer profile forms: auto-save, validation.
 */

/** Default strings (EN) when window.ms3Lexicon is not set by template */
const CUSTOMER_UI_LEXICON = {
  ms3_customer_err_occurred: 'An error occurred',
  ms3_customer_err_occurred_saving: 'An error occurred while saving',
  ms3_customer_profile_updated: 'Profile successfully updated',
  ms3_customer_profile_update_error: 'Profile update error',
  ms3_customer_address_added: 'Address successfully added',
  ms3_customer_address_updated: 'Address successfully updated',
  ms3_customer_address_creation_error: 'Address creation error',
  ms3_customer_address_update_error: 'Address update error',
  ms3_customer_err_address_id_not_specified: 'Address ID not specified'
}

class CustomerUI {
  /**
   * @param {CustomerAPI} customerAPI - Customer API instance
   * @param {Object} hooks - Hook system
   * @param {Object} message - Message system
   * @param {Object} config - Configuration
   */
  constructor (customerAPI, hooks, message, config) {
    this.customer = customerAPI
    this.hooks = hooks
    this.message = message
    this.config = config
  }

  /**
   * Get lexicon string (window.ms3Lexicon, then fallback, then key)
   * @param {string} key - Lexicon key
   * @returns {string}
   */
  t (key) {
    if (typeof window !== 'undefined' && window.ms3Lexicon && window.ms3Lexicon[key]) {
      return window.ms3Lexicon[key]
    }
    return CUSTOMER_UI_LEXICON[key] || key
  }

  /**
   * Initialize UI handlers
   */
  init () {
    document.querySelectorAll('[data-ms3-form="customer"], .ms3_customer_form').forEach(form => {
      this.initForm(form)
    })
  }

  /**
   * Initialize single form
   *
   * @param {HTMLFormElement} form - Customer form
   */
  initForm (form) {
    const inputs = form.querySelectorAll('input, textarea')

    inputs.forEach(input => {
      this.initInput(input)
    })
  }

  /**
   * Initialize input field
   *
   * @param {HTMLInputElement} input - Input field
   */
  initInput (input) {
    input.addEventListener('change', async () => {
      const form = input.closest('[data-ms3-form="customer"], .ms3_customer_form')
      if (!form) return

      const parent = input.closest('div')
      if (!parent) return

      form.classList.remove('was-validated')
      input.classList.remove('is-invalid')
      const feedback = parent.querySelector('.invalid-feedback')
      if (feedback) {
        feedback.textContent = ''
      }

      const response = await this.handleAdd(input.name, input.value)

      if (response.success) {
        if (response.data && response.data[input.name] !== undefined) {
          input.value = response.data[input.name]
        }
      } else {
        form.classList.add('was-validated')
        input.classList.add('is-invalid')

        if (feedback) {
          feedback.textContent = response.message || 'Validation error'
        }
      }
    })
  }

  /**
   * Add/update customer field
   *
   * @param {string} key - Field key
   * @param {string} value - Field value
   * @returns {Promise<Object>}
   */
  async handleAdd (key, value) {
    const hookData = { key, value }
    await this.hooks.runHooks('beforeAddCustomer', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      dispatchMs3Loading('ms3:customer:adding', {
        entity: 'customer',
        action: 'add',
        form: null,
        data: { key, value },
        response: null
      })
      const response = await this.customer.add(key, value)

      await this.hooks.runHooks('afterAddCustomer', { key, value, response })

      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      dispatchMs3Loading('ms3:customer:added', {
        entity: 'customer',
        action: 'add',
        form: null,
        data: { key, value },
        response
      })
      return response
    } catch (error) {
      console.error('CustomerUI.handleAdd error:', error)
      this.message.error(this.t('ms3_customer_err_occurred'))
      dispatchMs3Loading('ms3:customer:added', {
        entity: 'customer',
        action: 'add',
        form: null,
        data: { key, value },
        response: { success: false }
      })
      return { success: false, message: error.message }
    }
  }

  /**
   * Change address
   *
   * @param {string} key - Key (usually 'address_hash')
   * @param {string} value - Value
   * @returns {Promise<Object>}
   */
  async handleChangeAddress (key, value) {
    const hookData = { key, value }
    await this.hooks.runHooks('beforeChangeAddressCustomer', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      dispatchMs3Loading('ms3:customer:changing-address', {
        entity: 'customer',
        action: 'change-address',
        form: null,
        data: { key, value },
        response: null
      })
      const response = await this.customer.changeAddress(key, value)

      await this.hooks.runHooks('afterChangeAddressCustomer', { key, value, response })

      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      dispatchMs3Loading('ms3:customer:changed-address', {
        entity: 'customer',
        action: 'change-address',
        form: null,
        data: { key, value },
        response
      })
      return response
    } catch (error) {
      console.error('CustomerUI.handleChangeAddress error:', error)
      this.message.error(this.t('ms3_customer_err_occurred'))
      dispatchMs3Loading('ms3:customer:changed-address', {
        entity: 'customer',
        action: 'change-address',
        form: null,
        data: { key, value },
        response: { success: false }
      })
      return { success: false }
    }
  }

  /**
   * Update customer profile
   *
   * @param {FormData} formData - Form data
   * @returns {Promise<Object>}
   */
  async handleProfileUpdate (formData) {
    const data = {}
    for (const [key, value] of formData.entries()) {
      if (key === 'ms3_action') continue
      data[key] = value
    }

    const hookData = { data }
    await this.hooks.runHooks('beforeUpdateProfile', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      dispatchMs3Loading('ms3:customer:updating-profile', {
        entity: 'customer',
        action: 'update-profile',
        form: null,
        data,
        response: null
      })
      const response = await this.customer.updateProfile(data)

      await this.hooks.runHooks('afterUpdateProfile', { data, response })

      if (response.success) {
        this.message.success(response.message || this.t('ms3_customer_profile_updated'))

        setTimeout(() => {
          window.location.reload()
        }, 1000)
      } else {
        this.message.error(response.message || this.t('ms3_customer_profile_update_error'))
      }

      dispatchMs3Loading('ms3:customer:updated-profile', {
        entity: 'customer',
        action: 'update-profile',
        form: null,
        data,
        response
      })
      return response
    } catch (error) {
      console.error('CustomerUI.handleProfileUpdate error:', error)
      this.message.error(this.t('ms3_customer_err_occurred_saving'))
      dispatchMs3Loading('ms3:customer:updated-profile', {
        entity: 'customer',
        action: 'update-profile',
        form: null,
        data,
        response: { success: false }
      })
      return { success: false, message: error.message }
    }
  }

  /**
   * Create new address
   *
   * @param {FormData} formData - Form data
   * @returns {Promise<Object>}
   */
  async handleAddressCreate (formData) {
    const data = {}
    for (const [key, value] of formData.entries()) {
      if (key === 'ms3_action') continue
      data[key] = value
    }

    const hookData = { data }
    await this.hooks.runHooks('beforeCreateAddress', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      dispatchMs3Loading('ms3:customer:creating-address', {
        entity: 'customer',
        action: 'create-address',
        form: null,
        data,
        response: null
      })
      const response = await this.customer.createAddress(data)

      await this.hooks.runHooks('afterCreateAddress', { data, response })

      if (response.success) {
        this.message.success(response.message || this.t('ms3_customer_address_added'))

        setTimeout(() => {
          window.location.href = window.location.pathname
        }, 1000)
      } else {
        this.message.error(response.message || this.t('ms3_customer_address_creation_error'))
      }

      dispatchMs3Loading('ms3:customer:created-address', {
        entity: 'customer',
        action: 'create-address',
        form: null,
        data,
        response
      })
      return response
    } catch (error) {
      console.error('CustomerUI.handleAddressCreate error:', error)
      this.message.error(this.t('ms3_customer_err_occurred_saving'))
      dispatchMs3Loading('ms3:customer:created-address', {
        entity: 'customer',
        action: 'create-address',
        form: null,
        data,
        response: { success: false }
      })
      return { success: false, message: error.message }
    }
  }

  /**
   * Update address
   *
   * @param {FormData} formData - Form data
   * @returns {Promise<Object>}
   */
  async handleAddressUpdate (formData) {
    const data = {}
    for (const [key, value] of formData.entries()) {
      if (key === 'ms3_action') continue
      data[key] = value
    }

    const addressId = data.id
    if (!addressId) {
      const msg = this.t('ms3_customer_err_address_id_not_specified')
      this.message.error(msg)
      return { success: false, message: msg }
    }

    const hookData = { addressId, data }
    await this.hooks.runHooks('beforeUpdateAddress', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      dispatchMs3Loading('ms3:customer:updating-address', {
        entity: 'customer',
        action: 'update-address',
        form: null,
        data: { addressId, ...data },
        response: null
      })
      const response = await this.customer.updateAddress(addressId, data)

      await this.hooks.runHooks('afterUpdateAddress', { addressId, data, response })

      if (response.success) {
        this.message.success(response.message || this.t('ms3_customer_address_updated'))

        setTimeout(() => {
          window.location.href = window.location.pathname
        }, 1000)
      } else {
        this.message.error(response.message || this.t('ms3_customer_address_update_error'))
      }

      dispatchMs3Loading('ms3:customer:updated-address', {
        entity: 'customer',
        action: 'update-address',
        form: null,
        data: { addressId, ...data },
        response
      })
      return response
    } catch (error) {
      console.error('CustomerUI.handleAddressUpdate error:', error)
      this.message.error(this.t('ms3_customer_err_occurred_saving'))
      dispatchMs3Loading('ms3:customer:updated-address', {
        entity: 'customer',
        action: 'update-address',
        form: null,
        data: { addressId, ...data },
        response: { success: false }
      })
      return { success: false, message: error.message }
    }
  }
}
