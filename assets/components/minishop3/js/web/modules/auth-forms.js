/**
 * AuthForms - login and registration form handling
 *
 * Works with forms via connector.php (MODX processors).
 * Independent from ApiClient, uses direct Fetch API calls.
 *
 * @example
 * const authForms = new AuthForms({
 *   apiUrl: '/assets/components/minishop3/api.php',
 *   loginRoute: '/api/v1/customer/login',
 *   registerRoute: '/api/v1/customer/register'
 * })
 * authForms.init()
 */
class AuthForms {
  /**
   * @param {Object} config - Configuration
   * @param {string} config.apiUrl - URL api.php (frontend API)
   * @param {string} config.loginRoute - Login route
   * @param {string} config.registerRoute - Registration route
   */
  constructor (config = {}) {
    this.config = {
      apiUrl: config.apiUrl || '/assets/components/minishop3/api.php',
      loginRoute: config.loginRoute || '/api/v1/customer/login',
      registerRoute: config.registerRoute || '/api/v1/customer/register',
      loginFormId: config.loginFormId || 'ms3-login-form',
      registerFormId: config.registerFormId || 'ms3-register-form',
      ...config
    }

    this.forms = {
      login: null,
      register: null
    }
  }

  /**
   * Initialize handlers
   */
  init () {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.bindEvents())
    } else {
      this.bindEvents()
    }
  }

  /**
   * Bind form events
   */
  bindEvents () {
    this.forms.login = document.getElementById(this.config.loginFormId)
    if (this.forms.login) {
      this.forms.login.addEventListener('submit', (e) => this.handleLogin(e))
    }

    this.forms.register = document.getElementById(this.config.registerFormId)
    if (this.forms.register) {
      this.forms.register.addEventListener('submit', (e) => this.handleRegister(e))
    }

    const forgotPasswordLink = document.getElementById('forgot-password-link')
    if (forgotPasswordLink) {
      forgotPasswordLink.addEventListener('click', (e) => this.handleForgotPassword(e))
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
      this.showMessage('login-messages', this.getLexicon('ms3_customer_err_login_required'), 'danger')
      return
    }

    this.setButtonLoading('login-submit-btn', true)
    this.clearMessages('login-messages')

    try {
      const result = await this.sendToApi(this.config.loginRoute, {
        email: data.email,
        password: data.password
      })

      this.setButtonLoading('login-submit-btn', false)

      if (result.success) {
        this.showMessage('login-messages', this.getLexicon('ms3_customer_login_success'), 'success')

        setTimeout(() => {
          this.handleRedirect(result.object)
        }, 1000)
      } else {
        this.showMessage('login-messages', result.message || this.getLexicon('ms3_err_unknown'), 'danger')
      }
    } catch (error) {
      this.setButtonLoading('login-submit-btn', false)
      this.showMessage('login-messages', this.getLexicon('ms3_err_unknown'), 'danger')
      console.error('Login error:', error)
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
      this.showMessage('register-messages', this.getLexicon('ms3_customer_err_register_required'), 'danger')
      return
    }

    if (data.password !== data.password_confirm) {
      this.showMessage('register-messages', this.getLexicon('ms3_customer_err_password_mismatch'), 'danger')
      return
    }

    if (!data.privacy_accepted) {
      this.showMessage('register-messages', this.getLexicon('ms3_customer_err_privacy_required'), 'danger')
      return
    }

    this.setButtonLoading('register-submit-btn', true)
    this.clearMessages('register-messages')

    try {
      const result = await this.sendToApi(this.config.registerRoute, {
        email: data.email,
        password: data.password,
        first_name: data.first_name || '',
        last_name: data.last_name || '',
        phone: data.phone || '',
        privacy_accepted: data.privacy_accepted ? '1' : '0'
      })

      this.setButtonLoading('register-submit-btn', false)

      if (result.success) {
        this.showMessage('register-messages',
          result.message || this.getLexicon('ms3_customer_register_success'),
          'success'
        )

        if (result.object && result.object.token) {
          // Auto-login: redirect to account page
          setTimeout(() => {
            this.handleRedirect(result.object)
          }, 1500)
        } else {
          setTimeout(() => {
            this.switchTab('login-tab')
            form.reset()
            this.clearMessages('register-messages')
          }, 2000)
        }
      } else {
        this.showMessage('register-messages', result.message || this.getLexicon('ms3_err_unknown'), 'danger')
      }
    } catch (error) {
      this.setButtonLoading('register-submit-btn', false)
      this.showMessage('register-messages', this.getLexicon('ms3_err_unknown'), 'danger')
      console.error('Register error:', error)
    }
  }

  /**
   * Forgot password handler
   *
   * @param {Event} event - Click event
   */
  handleForgotPassword (event) {
    event.preventDefault()
    alert('Password recovery feature will be implemented in the next version')
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
   * Save authorization token — no-op (httpOnly cookie managed by server)
   * Cleans up legacy localStorage.
   *
   * @param {string} _token - Unused
   */
  saveToken (_token) {
    // Clean up legacy localStorage
    try {
      localStorage.removeItem('ms3_token')
    } catch (e) {
      // Ignore
    }
  }

  /**
   * Get saved token — returns null (httpOnly cookie, not accessible from JS)
   *
   * @returns {null}
   */
  getToken () {
    return null
  }

  /**
   * Remove token (on logout) — cleans up legacy localStorage
   */
  clearToken () {
    try {
      localStorage.removeItem('ms3_token')
    } catch (e) {
      // Ignore
    }
  }

  /**
   * Send data to Frontend API via api.php
   *
   * @param {string} route - API route (e.g., /api/v1/customer/login)
   * @param {Object} data - Data to send
   * @returns {Promise<Object>} - API response
   */
  async sendToApi (route, data) {
    const url = new URL(this.config.apiUrl, window.location.origin)
    url.searchParams.set('route', route)

    const response = await fetch(url.toString(), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
      },
      body: JSON.stringify(data)
    })

    return response.json()
  }

  /**
   * Show message in container
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
    alertDiv.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `

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
   * Initialize tab support (fallback if Bootstrap JS is missing)
   */
  initTabSupport () {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
      return
    }

    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]')
    tabButtons.forEach((tabButton) => {
      tabButton.addEventListener('click', (e) => {
        e.preventDefault()

        document.querySelectorAll('.nav-link').forEach((link) => {
          link.classList.remove('active')
          link.setAttribute('aria-selected', 'false')
        })

        document.querySelectorAll('.tab-pane').forEach((pane) => {
          pane.classList.remove('show', 'active')
        })

        tabButton.classList.add('active')
        tabButton.setAttribute('aria-selected', 'true')

        const targetId = tabButton.getAttribute('data-bs-target')
        const targetPane = document.querySelector(targetId)
        if (targetPane) {
          targetPane.classList.add('show', 'active')
        }
      })
    })
  }

  /**
   * Get lexicon (from global object or fallback)
   *
   * @param {string} key - Lexicon key
   * @returns {string} - Value
   */
  getLexicon (key) {
    if (window.ms3Lexicon && window.ms3Lexicon[key]) {
      return window.ms3Lexicon[key]
    }

    const fallbacks = {
      ms3_customer_err_login_required: 'Please enter email and password',
      ms3_customer_login_success: 'You have successfully logged in',
      ms3_customer_err_register_required: 'Please enter email and password to register',
      ms3_customer_err_password_mismatch: 'Passwords do not match',
      ms3_customer_err_privacy_required: 'You must accept the privacy policy',
      ms3_customer_register_success: 'Registration successful',
      ms3_err_unknown: 'An unknown error occurred'
    }

    return fallbacks[key] || key
  }
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = AuthForms
}

window.AuthForms = AuthForms
