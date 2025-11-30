/**
 * MiniShop3 - Главный объект
 *
 * Инициализирует все компоненты системы:
 * - TokenManager: Управление токенами
 * - ApiClient: HTTP клиент
 * - API модули: CartAPI, OrderAPI, CustomerAPI
 * - UI модули: CartUI, OrderUI, CustomerUI
 * - Утилиты: Hooks, Message
 *
 * Конфигурация передаётся через window.ms3Config:
 * {
 *   actionUrl: '/assets/components/minishop3/connector.php',
 *   tokenName: 'ms3_token',
 *   render: { ... }
 * }
 */
const ms3 = {
  // Конфигурация
  config: {},

  // Core компоненты
  tokenManager: null,
  apiClient: null,

  // API модули
  cartAPI: null,
  orderAPI: null,
  customerAPI: null,

  // UI модули
  cartUI: null,
  orderUI: null,
  customerUI: null,

  // Утилиты (инициализируются из отдельных файлов)
  hooks: null,
  message: null,

  /**
   * Асинхронная инициализация
   */
  async init () {
    // 1. Загрузка конфигурации
    this.config = window.ms3Config || {}

    // 2. Инициализация утилит (hooks.js и message.js должны быть подключены)
    this.hooks = window.ms3Hooks || this.createFallbackHooks()
    this.message = window.ms3Message || this.createFallbackMessage()

    // 3. Инициализация TokenManager
    this.tokenManager = new TokenManager({
      tokenName: this.config.tokenName || 'ms3_token'
    })

    // 4. Инициализация ApiClient
    this.apiClient = new ApiClient({
      baseUrl: this.config.actionUrl || '/assets/components/minishop3/api.php',
      tokenManager: this.tokenManager
    })

    // 5. Связываем TokenManager с ApiClient
    this.tokenManager.setApiClient(this.apiClient)

    // 6. Получение/проверка токена
    await this.tokenManager.ensureToken()

    // 7. Инициализация API модулей
    this.cartAPI = new CartAPI(this.apiClient)
    this.orderAPI = new OrderAPI(this.apiClient)
    this.customerAPI = new CustomerAPI(this.apiClient)

    // 8. Инициализация UI модулей
    this.cartUI = new CartUI(this.cartAPI, this.hooks, this.message, this.config)
    this.orderUI = new OrderUI(this.orderAPI, this.hooks, this.message, this.config)
    this.customerUI = new CustomerUI(this.customerAPI, this.hooks, this.message, this.config)

    // 9. Инициализация UI обработчиков
    this.cartUI.init()
    this.orderUI.init()
    this.customerUI.init()

    // 10. Инициализация обработчика форм
    this.initFormHandler()

    // 11. Инициализация обработчика кликов по .ms3_link
    this.initLinkHandler()

    // 12. Событие готовности (для сторонних скриптов)
    document.dispatchEvent(new Event('ms3:ready'))

    console.log('MiniShop3 initialized')
  },

  /**
   * Обработчик отправки форм .ms3_form
   *
   * Автоматически вызывает нужный метод API на основе ms3_action:
   * - cart/add → cartUI.handleAdd()
   * - order/submit → orderUI.handleSubmit()
   * - и т.д.
   */
  initFormHandler () {
    document.addEventListener('submit', async (event) => {
      // Проверяем что это наша форма
      if (!event.target.classList.contains('ms3_form')) {
        return
      }

      event.preventDefault()

      const form = event.target
      const formData = new FormData(form)
      const action = formData.get('ms3_action')

      if (!action) {
        console.warn('ms3_action не указан в форме')
        return
      }

      // Парсим action: "cart/add" → entity="cart", method="add"
      const [entity, method] = action.split('/')

      // Вызываем соответствующий обработчик
      await this.handleFormSubmit(entity, method, formData)
    })
  },

  /**
   * Обработчик кликов по .ms3_link
   *
   * Обрабатывает клики по кнопкам/ссылкам с классом .ms3_link
   * внутри форм .ms3_form. Триггерит submit формы.
   */
  initLinkHandler () {
    document.addEventListener('click', async (event) => {
      // Проверяем что это наша ссылка/кнопка
      const link = event.target.closest('.ms3_link')
      if (!link) {
        return
      }

      // Находим родительскую форму
      const form = link.closest('.ms3_form')
      if (!form) {
        console.warn('.ms3_link должна быть внутри .ms3_form')
        return
      }

      event.preventDefault()

      // Триггерим submit формы
      const formData = new FormData(form)
      const action = formData.get('ms3_action')

      if (!action) {
        console.warn('ms3_action не указан в форме')
        return
      }

      // Парсим action: "cart/add" → entity="cart", method="add"
      const [entity, method] = action.split('/')

      // Вызываем обработчик
      await this.handleFormSubmit(entity, method, formData)
    })
  },

  /**
   * Обработка отправки формы
   *
   * @param {string} entity - Сущность (cart, order, customer)
   * @param {string} method - Метод (add, remove, submit и т.д.)
   * @param {FormData} formData - Данные формы
   */
  async handleFormSubmit (entity, method, formData) {
    try {
      // Хук BEFORE
      const hookData = { entity, method, formData }
      await this.hooks.runHooks('beforeFormSubmit', hookData)

      if (hookData.cancel) {
        return
      }

      // Маппинг entity/method → UI обработчики
      const handlers = {
        cart: {
          add: () => {
            const id = parseInt(formData.get('id'))
            const count = parseInt(formData.get('count')) || 1
            const options = this.parseOptions(formData.get('options'))
            return this.cartUI.handleAdd(id, count, options)
          },
          remove: () => {
            const productKey = formData.get('product_key')
            return this.cartUI.handleRemove(productKey)
          },
          clean: () => {
            return this.cartUI.handleClean()
          }
        },
        order: {
          submit: () => {
            return this.orderUI.handleSubmit()
          },
          clean: () => {
            return this.orderUI.handleClean()
          }
        },
        customer: {
          'update-profile': () => {
            return this.customerUI.handleProfileUpdate(formData)
          },
          'address-create': () => {
            return this.customerUI.handleAddressCreate(formData)
          },
          'address-update': () => {
            return this.customerUI.handleAddressUpdate(formData)
          }
        }
      }

      // Вызываем обработчик
      if (handlers[entity] && handlers[entity][method]) {
        await handlers[entity][method]()
      } else {
        console.warn(`Обработчик для ${entity}/${method} не найден`)
      }

      // Хук AFTER
      await this.hooks.runHooks('afterFormSubmit', { entity, method, formData })
    } catch (error) {
      console.error('Form submit error:', error)
      this.message.error('Произошла ошибка при отправке формы')
    }
  },

  /**
   * Парсинг опций из строки/JSON
   *
   * @param {string|Object} options
   * @returns {Object}
   */
  parseOptions (options) {
    if (!options) return {}

    if (typeof options === 'string') {
      try {
        return JSON.parse(options)
      } catch (e) {
        return {}
      }
    }

    return options
  },

  /**
   * Fallback для hooks (если hooks.js не подключен)
   */
  createFallbackHooks () {
    return {
      items: {},
      addHook (name, fn) {
        if (!this.items[name]) this.items[name] = []
        this.items[name].push(fn)
      },
      async runHooks (name, context) {
        if (!this.items[name]) return
        for (const fn of this.items[name]) {
          if (context.cancel) return false
          try {
            await fn(context)
          } catch (error) {
            console.error('Hook error:', name, error)
          }
        }
      }
    }
  },

  /**
   * Fallback для message (если message.js не подключен)
   */
  createFallbackMessage () {
    return {
      success (msg) {
        if (msg) alert(msg)
      },
      error (msg) {
        if (msg) alert(msg)
      }
    }
  },

  /**
   * Хелпер: проверка является ли строка валидным JSON
   *
   * @param {string} str
   * @returns {boolean}
   */
  isJSON (str) {
    try {
      JSON.parse(str)
      return true
    } catch (e) {
      return false
    }
  }
}

// Автоинициализация при загрузке DOM
document.addEventListener('DOMContentLoaded', () => {
  ms3.init()
})

// Экспорт для использования в других скриптах
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ms3
}
