/**
 * AuthForms - обработка форм авторизации и регистрации
 *
 * Работает с формами через connector.php (MODX процессоры).
 * Не зависит от ApiClient, использует прямые вызовы через Fetch API.
 *
 * @example
 * // Автоинициализация при загрузке DOM
 * const authForms = new AuthForms({
 *   connectorUrl: '/assets/components/minishop3/connector.php',
 *   loginAction: 'MiniShop3\\Processors\\Api\\Customer\\Login',
 *   registerAction: 'MiniShop3\\Processors\\Api\\Customer\\Register'
 * })
 * authForms.init()
 */
class AuthForms {
  /**
   * @param {Object} config - Конфигурация
   * @param {string} config.apiUrl - URL api.php (frontend API)
   * @param {string} config.loginRoute - Роут для входа
   * @param {string} config.registerRoute - Роут для регистрации
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
   * Инициализация обработчиков
   */
  init () {
    // Инициализация после загрузки DOM
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.bindEvents())
    } else {
      this.bindEvents()
    }
  }

  /**
   * Привязка событий к формам
   */
  bindEvents () {
    // Форма входа
    this.forms.login = document.getElementById(this.config.loginFormId)
    if (this.forms.login) {
      this.forms.login.addEventListener('submit', (e) => this.handleLogin(e))
    }

    // Форма регистрации
    this.forms.register = document.getElementById(this.config.registerFormId)
    if (this.forms.register) {
      this.forms.register.addEventListener('submit', (e) => this.handleRegister(e))
    }

    // Обработчик "Забыли пароль?" (заглушка)
    const forgotPasswordLink = document.getElementById('forgot-password-link')
    if (forgotPasswordLink) {
      forgotPasswordLink.addEventListener('click', (e) => this.handleForgotPassword(e))
    }

    // Поддержка табов если нет Bootstrap JS
    this.initTabSupport()
  }

  /**
   * Обработчик формы входа
   *
   * @param {Event} event - Submit событие
   */
  async handleLogin (event) {
    event.preventDefault()

    const form = event.target
    const data = this.serializeForm(form)

    // Валидация на клиенте
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

        // Редирект через 1 секунду
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
   * Обработчик формы регистрации
   *
   * @param {Event} event - Submit событие
   */
  async handleRegister (event) {
    event.preventDefault()

    const form = event.target
    const data = this.serializeForm(form)

    // Валидация на клиенте
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

        // Если автовход включен - редиректим
        if (result.object && result.object.token) {
          setTimeout(() => {
            this.handleRedirect(result.object)
          }, 1500)
        } else {
          // Иначе переключаем на форму входа через 2 секунды
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
   * Обработчик "Забыли пароль?"
   *
   * @param {Event} event - Click событие
   */
  handleForgotPassword (event) {
    event.preventDefault()
    // TODO: Реализовать в будущем
    alert('Функция восстановления пароля будет реализована в следующей версии')
  }

  /**
   * Обработка редиректа после успешной авторизации/регистрации
   *
   * @param {Object} responseObject - Объект ответа от backend
   */
  handleRedirect (responseObject) {
    // Если backend вернул redirect_url - используем его
    if (responseObject && responseObject.redirect_url) {
      window.location.href = responseObject.redirect_url
    } else {
      // Иначе перезагружаем текущую страницу
      window.location.reload()
    }
  }

  /**
   * Отправка данных к Frontend API через api.php
   *
   * @param {string} route - API роут (например: /api/v1/customer/login)
   * @param {Object} data - Данные для отправки
   * @returns {Promise<Object>} - Ответ от API
   */
  async sendToApi (route, data) {
    const url = new URL(this.config.apiUrl, window.location.origin)
    url.searchParams.set('route', route)

    const response = await fetch(url.toString(), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(data)
    })

    return response.json()
  }

  /**
   * Показать сообщение в контейнере
   *
   * @param {string} containerId - ID контейнера для сообщений
   * @param {string} message - Текст сообщения
   * @param {string} type - Тип (success, danger, warning, info)
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

    // Автоматическое закрытие через 5 секунд для success сообщений
    if (type === 'success') {
      setTimeout(() => {
        alertDiv.classList.remove('show')
        setTimeout(() => alertDiv.remove(), 150)
      }, 5000)
    }
  }

  /**
   * Очистить сообщения
   *
   * @param {string} containerId - ID контейнера
   */
  clearMessages (containerId) {
    const container = document.getElementById(containerId)
    if (container) {
      container.innerHTML = ''
    }
  }

  /**
   * Установить состояние загрузки для кнопки
   *
   * @param {string} buttonId - ID кнопки
   * @param {boolean} isLoading - true для включения загрузки
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
   * Сериализация формы в объект
   *
   * @param {HTMLFormElement} form - Форма
   * @returns {Object} - Объект с данными формы
   */
  serializeForm (form) {
    const formData = new FormData(form)
    const data = {}

    for (const [key, value] of formData.entries()) {
      // Чекбоксы
      if (form.elements[key] && form.elements[key].type === 'checkbox') {
        data[key] = form.elements[key].checked
      } else {
        data[key] = value
      }
    }

    return data
  }

  /**
   * Переключить таб
   *
   * @param {string} tabId - ID кнопки таба
   */
  switchTab (tabId) {
    const tabButton = document.getElementById(tabId)
    if (tabButton) {
      tabButton.click()
    }
  }

  /**
   * Инициализация поддержки табов (fallback если нет Bootstrap JS)
   */
  initTabSupport () {
    // Проверка наличия Bootstrap
    if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
      return // Bootstrap уже инициализирован
    }

    // Fallback: ручная реализация табов
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]')
    tabButtons.forEach((tabButton) => {
      tabButton.addEventListener('click', (e) => {
        e.preventDefault()

        // Убрать активность со всех табов
        document.querySelectorAll('.nav-link').forEach((link) => {
          link.classList.remove('active')
          link.setAttribute('aria-selected', 'false')
        })

        // Убрать активность со всех панелей
        document.querySelectorAll('.tab-pane').forEach((pane) => {
          pane.classList.remove('show', 'active')
        })

        // Активировать текущий таб
        tabButton.classList.add('active')
        tabButton.setAttribute('aria-selected', 'true')

        // Активировать соответствующую панель
        const targetId = tabButton.getAttribute('data-bs-target')
        const targetPane = document.querySelector(targetId)
        if (targetPane) {
          targetPane.classList.add('show', 'active')
        }
      })
    })
  }

  /**
   * Получить лексикон (из глобального объекта или fallback)
   *
   * @param {string} key - Ключ лексикона
   * @returns {string} - Значение
   */
  getLexicon (key) {
    // Если есть глобальный объект с лексиконами
    if (window.ms3Lexicon && window.ms3Lexicon[key]) {
      return window.ms3Lexicon[key]
    }

    // Fallback значения (английский)
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

// Экспорт для использования в других скриптах
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AuthForms
}

// Глобальный объект для доступа из других скриптов
window.AuthForms = AuthForms
