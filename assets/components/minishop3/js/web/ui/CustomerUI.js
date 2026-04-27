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
  ms3_customer_err_address_id_not_specified: 'Address ID not specified',
  ms3_customer_order_cancel_error: 'Failed to cancel order',
  ms3_customer_order_cancel_request_error: 'Request failed',
  ms3_customer_address_set_default_error: 'Failed to set default address',
  ms3_customer_address_delete_error: 'Failed to delete address',
  ms3_email_verification_sent: 'Verification email has been sent'
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

  get selectors () {
    return this.config?.selectors || {}
  }

  get confirm () {
    return this.config?.confirm || window.ms3Confirm || function (msg) { return Promise.resolve(window.confirm(msg)) }
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
    document.querySelectorAll(this.selectors.formCustomer).forEach(form => {
      this.initForm(form)
    })
    this.initOrderCancel()
    this.initAddressManagement()
    this.initResendVerification()
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
      const form = input.closest(this.selectors.formCustomer)
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
      const response = await this.customer.add(key, value)

      await this.hooks.runHooks('afterAddCustomer', { key, value, response })

      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      return response
    } catch (error) {
      console.error('CustomerUI.handleAdd error:', error)
      this.message.error(this.t('ms3_customer_err_occurred'))
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
      const response = await this.customer.changeAddress(key, value)

      await this.hooks.runHooks('afterChangeAddressCustomer', { key, value, response })

      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      return response
    } catch (error) {
      console.error('CustomerUI.handleChangeAddress error:', error)
      this.message.error(this.t('ms3_customer_err_occurred'))
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

      return response
    } catch (error) {
      console.error('CustomerUI.handleProfileUpdate error:', error)
      this.message.error(this.t('ms3_customer_err_occurred_saving'))
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

      return response
    } catch (error) {
      console.error('CustomerUI.handleAddressCreate error:', error)
      this.message.error(this.t('ms3_customer_err_occurred_saving'))
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

      return response
    } catch (error) {
      console.error('CustomerUI.handleAddressUpdate error:', error)
      this.message.error(this.t('ms3_customer_err_occurred_saving'))
      return { success: false, message: error.message }
    }
  }

  /**
   * Resend email verification (profile / cabinet)
   */
  initResendVerification () {
    const selector = this.selectors.resendVerificationEmail
    if (!selector) {
      return
    }
    document.querySelectorAll(selector).forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.preventDefault()
        if (btn.disabled) {
          return
        }
        const hookData = { button: btn }
        await this.hooks.runHooks('beforeResendVerificationEmail', hookData)
        if (hookData.cancel) {
          return
        }
        btn.disabled = true
        try {
          const response = await this.customer.resendVerificationEmail()
          await this.hooks.runHooks('afterResendVerificationEmail', { response, button: btn })
          if (response.success) {
            this.message.success(
              response.message || this.t('ms3_email_verification_sent')
            )
            setTimeout(() => {
              window.location.reload()
            }, 1200)
          } else {
            this.message.error(response.message || this.t('ms3_customer_err_occurred'))
            btn.disabled = false
          }
        } catch (error) {
          console.error('CustomerUI.initResendVerification error:', error)
          this.message.error(this.t('ms3_customer_err_occurred'))
          btn.disabled = false
        }
      })
    })
  }

  /**
   * Initialize order cancel buttons
   */
  initOrderCancel () {
    document.querySelectorAll(this.selectors.orderCancel).forEach(btn => {
      btn.addEventListener('click', async () => {
        const orderId = btn.getAttribute('data-order-id')
        const confirmMessage = btn.getAttribute('data-confirm') || 'Cancel this order?'

        if (!await this.confirm(confirmMessage, { confirmClass: 'btn-danger' })) return

        const hookData = { orderId }
        await this.hooks.runHooks('beforeCancelOrder', hookData)
        if (hookData.cancel) return

        btn.disabled = true

        try {
          const response = await this.customer.cancelOrder(orderId)

          await this.hooks.runHooks('afterCancelOrder', { orderId, response })

          if (response.success) {
            location.reload()
          } else {
            this.message.error(response.message || this.t('ms3_customer_order_cancel_error'))
            btn.disabled = false
          }
        } catch (error) {
          console.error('CustomerUI.initOrderCancel error:', error)
          this.message.error(this.t('ms3_customer_order_cancel_request_error'))
          btn.disabled = false
        }
      })
    })
  }

  /**
   * Initialize address management buttons (set default, delete)
   */
  initAddressManagement () {
    document.querySelectorAll(this.selectors.addressSetDefault).forEach(btn => {
      btn.addEventListener('click', async () => {
        const addressId = btn.dataset.addressId
        const container = btn.closest('.list-group-item')
        const confirmMessage = container?.dataset.confirmSetDefault || 'Set this address as default?'

        if (!await this.confirm(confirmMessage)) return

        const hookData = { addressId }
        await this.hooks.runHooks('beforeSetDefaultAddress', hookData)
        if (hookData.cancel) return

        try {
          const response = await this.customer.setDefaultAddress(addressId)

          await this.hooks.runHooks('afterSetDefaultAddress', { addressId, response })

          if (response.success) {
            location.reload()
          } else {
            this.message.error(response.message || this.t('ms3_customer_address_set_default_error'))
          }
        } catch (error) {
          console.error('CustomerUI.initAddressManagement setDefault error:', error)
          this.message.error(this.t('ms3_customer_address_set_default_error'))
        }
      })
    })

    document.querySelectorAll(this.selectors.addressDelete).forEach(btn => {
      btn.addEventListener('click', async () => {
        const addressId = btn.dataset.addressId
        const container = btn.closest('.list-group-item')
        const confirmMessage = container?.dataset.confirmDelete || 'Are you sure you want to delete this address?'

        if (!await this.confirm(confirmMessage, { confirmClass: 'btn-danger' })) return

        const hookData = { addressId }
        await this.hooks.runHooks('beforeDeleteAddress', hookData)
        if (hookData.cancel) return

        try {
          const response = await this.customer.deleteAddress(addressId)

          await this.hooks.runHooks('afterDeleteAddress', { addressId, response })

          if (response.success) {
            location.reload()
          } else {
            this.message.error(response.message || this.t('ms3_customer_address_delete_error'))
          }
        } catch (error) {
          console.error('CustomerUI.initAddressManagement delete error:', error)
          this.message.error(this.t('ms3_customer_address_delete_error'))
        }
      })
    })
  }
}
