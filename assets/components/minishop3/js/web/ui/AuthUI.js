/**
 * UI handlers for authentication forms (login / register)
 *
 * Manages login and registration forms in the customer account area.
 * Uses CustomerAPI for network requests, hooks for extensibility,
 * and message for toast notifications.
 *
 * Form validation errors are shown inline (DOM alerts),
 * network errors use toast notifications via this.message.
 */

/** Default strings (EN) when window.ms3Lexicon is not set by template */
const AUTH_UI_LEXICON = {
  ms3_customer_err_login_required: 'Please enter email and password',
  ms3_customer_login_success: 'You have successfully logged in',
  ms3_customer_err_register_required: 'Please enter email and password to register',
  ms3_customer_err_password_mismatch: 'Passwords do not match',
  ms3_customer_err_privacy_required: 'You must accept the privacy policy',
  ms3_customer_register_success: 'Registration successful',
  ms3_err_unknown: 'An unknown error occurred',
  ms3_customer_password_recovery_not_available: 'Password recovery feature will be implemented in the next version'
}

class AuthUI {
  /**
   * @param {CustomerAPI} customerAPI - Customer API instance
   * @param {Object} hooks - Hook system
   * @param {Object} message - Message system (toast)
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

  /**
   * Get lexicon string (window.ms3Lexicon, then fallback, then key)
   * @param {string} key - Lexicon key
   * @returns {string}
   */
  t (key) {
    if (typeof window !== 'undefined' && window.ms3Lexicon && window.ms3Lexicon[key]) {
      return window.ms3Lexicon[key]
    }
    return AUTH_UI_LEXICON[key] || key
  }

  /**
   * Initialize UI handlers
   */
  init () {
    const loginForm = document.querySelector(this.selectors.authLoginForm)
    if (loginForm) {
      loginForm.addEventListener('submit', (e) => this.handleLogin(e))
    }

    const registerForm = document.querySelector(this.selectors.authRegisterForm)
    if (registerForm) {
      registerForm.addEventListener('submit', (e) => this.handleRegister(e))
    }

    const forgotLink = document.querySelector(this.selectors.authForgotPassword)
    if (forgotLink) {
      forgotLink.addEventListener('click', (e) => this.handleForgotPassword(e))
    }

    this.initTabSupport()
  }

  /**
   * Login form handler
   *
   * @param {Event} event - Submit event
   */
  async handleLogin (event) {
    event.preventDefault()

    const form = event.target
    const data = this.serializeForm(form)

    if (!data.email || !data.password) {
      this.showMessage('login-messages', this.t('ms3_customer_err_login_required'), 'danger')
      return
    }

    this.setButtonLoading('login-submit-btn', true)
    this.clearMessages('login-messages')

    const hookData = { email: data.email }
    await this.hooks.runHooks('beforeLogin', hookData)
    if (hookData.cancel) {
      this.setButtonLoading('login-submit-btn', false)
      return
    }

    try {
      const result = await this.customer.login(data.email, data.password)

      this.setButtonLoading('login-submit-btn', false)

      await this.hooks.runHooks('afterLogin', { email: data.email, response: result })

      if (result.success) {
        this.showMessage('login-messages', this.t('ms3_customer_login_success'), 'success')
        setTimeout(() => {
          this.handleRedirect(ApiClient.getPayload(result))
        }, 1000)
      } else {
        this.showMessage('login-messages', result.message || this.t('ms3_err_unknown'), 'danger')
      }
    } catch (error) {
      this.setButtonLoading('login-submit-btn', false)
      this.message.error(this.t('ms3_err_unknown'))
      console.error('AuthUI login error:', error)
    }
  }

  /**
   * Registration form handler
   *
   * @param {Event} event - Submit event
   */
  async handleRegister (event) {
    event.preventDefault()

    const form = event.target
    const data = this.serializeForm(form)

    if (!data.email || !data.password) {
      this.showMessage('register-messages', this.t('ms3_customer_err_register_required'), 'danger')
      return
    }

    if (data.password !== data.password_confirm) {
      this.showMessage('register-messages', this.t('ms3_customer_err_password_mismatch'), 'danger')
      return
    }

    if (!data.privacy_accepted) {
      this.showMessage('register-messages', this.t('ms3_customer_err_privacy_required'), 'danger')
      return
    }

    this.setButtonLoading('register-submit-btn', true)
    this.clearMessages('register-messages')

    const hookData = { email: data.email }
    await this.hooks.runHooks('beforeRegister', hookData)
    if (hookData.cancel) {
      this.setButtonLoading('register-submit-btn', false)
      return
    }

    try {
      const result = await this.customer.register({
        email: data.email,
        password: data.password,
        first_name: data.first_name || '',
        last_name: data.last_name || '',
        phone: data.phone || '',
        privacy_accepted: data.privacy_accepted ? '1' : '0'
      })

      this.setButtonLoading('register-submit-btn', false)

      await this.hooks.runHooks('afterRegister', { email: data.email, response: result })

      if (result.success) {
        this.showMessage('register-messages',
          result.message || this.t('ms3_customer_register_success'),
          'success'
        )

        const payload = ApiClient.getPayload(result)
        if (payload && payload.token) {
          setTimeout(() => {
            this.handleRedirect(payload)
          }, 1500)
        } else {
          setTimeout(() => {
            this.switchTab('login-tab')
            form.reset()
            this.clearMessages('register-messages')
          }, 2000)
        }
      } else {
        this.showMessage('register-messages', result.message || this.t('ms3_err_unknown'), 'danger')
      }
    } catch (error) {
      this.setButtonLoading('register-submit-btn', false)
      this.message.error(this.t('ms3_err_unknown'))
      console.error('AuthUI register error:', error)
    }
  }

  /**
   * Forgot password handler
   *
   * @param {Event} event - Click event
   */
  handleForgotPassword (event) {
    event.preventDefault()
    this.message.info(this.t('ms3_customer_password_recovery_not_available'))
  }

  /**
   * Handle redirect after successful login/registration
   *
   * @param {Object} responseObject - Response object from backend
   */
  handleRedirect (responseObject) {
    if (responseObject && responseObject.redirect_url) {
      window.location.href = responseObject.redirect_url
    } else {
      window.location.reload()
    }
  }

  /**
   * Show message in container (inline DOM alert)
   *
   * @param {string} containerId - Message container ID
   * @param {string} message - Message text
   * @param {string} type - Type (success, danger, warning, info)
   */
  showMessage (containerId, message, type = 'danger') {
    const container = document.getElementById(containerId)
    if (!container) return

    const alertDiv = document.createElement('div')
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`
    alertDiv.setAttribute('role', 'alert')
    alertDiv.textContent = message

    const closeBtn = document.createElement('button')
    closeBtn.type = 'button'
    closeBtn.className = 'btn-close'
    closeBtn.setAttribute('data-bs-dismiss', 'alert')
    closeBtn.setAttribute('aria-label', 'Close')
    alertDiv.appendChild(closeBtn)

    container.innerHTML = ''
    container.appendChild(alertDiv)

    if (type === 'success') {
      setTimeout(() => {
        alertDiv.classList.remove('show')
        setTimeout(() => alertDiv.remove(), 150)
      }, 5000)
    }
  }

  /**
   * Clear messages
   *
   * @param {string} containerId - Container ID
   */
  clearMessages (containerId) {
    const container = document.getElementById(containerId)
    if (container) {
      container.innerHTML = ''
    }
  }

  /**
   * Set button loading state
   *
   * @param {string} buttonId - Button ID
   * @param {boolean} isLoading - true to enable loading
   */
  setButtonLoading (buttonId, isLoading) {
    const btn = document.getElementById(buttonId)
    if (!btn) return

    if (isLoading) {
      btn.classList.add('btn-loading')
      btn.disabled = true
    } else {
      btn.classList.remove('btn-loading')
      btn.disabled = false
    }
  }

  /**
   * Serialize form to object
   *
   * @param {HTMLFormElement} form - Form
   * @returns {Object} - Object with form data
   */
  serializeForm (form) {
    const formData = new FormData(form)
    const data = {}

    for (const [key, value] of formData.entries()) {
      if (form.elements[key] && form.elements[key].type === 'checkbox') {
        data[key] = form.elements[key].checked
      } else {
        data[key] = value
      }
    }

    return data
  }

  /**
   * Switch tab
   *
   * @param {string} tabId - Tab button ID
   */
  switchTab (tabId) {
    const tabButton = document.getElementById(tabId)
    if (tabButton) {
      tabButton.click()
    }
  }

  /**
   * Nearest ancestor that contains auth form(s) and tab toggles (login/register UI).
   * Limits fallback tab handling to MiniShop3 auth block, not the whole page.
   *
   * @returns {HTMLElement|null}
   */
  _findAuthTabScope () {
    const login = document.querySelector(this.selectors.authLoginForm)
    const register = document.querySelector(this.selectors.authRegisterForm)
    const anchor = login || register
    if (!anchor) {
      return null
    }
    const mustContain = [login, register].filter(Boolean)
    let el = anchor.parentElement
    while (el) {
      if (!el.querySelector('[data-bs-toggle="tab"]')) {
        el = el.parentElement
        continue
      }
      if (!mustContain.every((node) => el.contains(node))) {
        el = el.parentElement
        continue
      }
      return el
    }
    return null
  }

  /**
   * Initialize tab support (fallback if Bootstrap JS is missing)
   */
  initTabSupport () {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
      return
    }
    if (this._tabSupportInitialized) {
      return
    }

    const scope = this._findAuthTabScope()
    if (!scope) {
      return
    }

    const tabButtons = scope.querySelectorAll('[data-bs-toggle="tab"]')
    if (tabButtons.length === 0) {
      return
    }

    this._tabSupportInitialized = true

    tabButtons.forEach((tabButton) => {
      tabButton.addEventListener('click', (e) => {
        e.preventDefault()

        const targetSelector = tabButton.getAttribute('data-bs-target')
        const targetPane = targetSelector
          ? document.querySelector(targetSelector)
          : null

        const nav = tabButton.closest('.nav')
        if (nav) {
          nav.querySelectorAll('.nav-link').forEach((link) => {
            link.classList.remove('active')
            link.setAttribute('aria-selected', 'false')
          })
        }

        const tabContent = targetPane?.parentElement
        if (tabContent) {
          tabContent.querySelectorAll(':scope > .tab-pane').forEach((pane) => {
            pane.classList.remove('show', 'active')
          })
        }

        tabButton.classList.add('active')
        tabButton.setAttribute('aria-selected', 'true')

        if (targetPane) {
          targetPane.classList.add('show', 'active')
        }
      })
    })
  }
}
