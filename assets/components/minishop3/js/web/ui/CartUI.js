/**
 * UI обработчики для корзины
 *
 * Класс управляет интерактивными элементами корзины:
 * - Кнопки увеличения/уменьшения количества
 * - Поля ввода количества
 * - Селекты опций товара
 * - Кнопки удаления товара
 *
 * Разделение ответственности:
 * - CartUI: UI логика (события, DOM)
 * - CartAPI: Запросы к серверу
 * - Hooks: Расширяемость
 * - Message: Уведомления
 */
class CartUI {
  /**
   * @param {CartAPI} cartAPI - API для работы с корзиной
   * @param {Object} hooks - Система хуков
   * @param {Object} message - Система уведомлений
   * @param {Object} config - Конфигурация (ms3Config)
   */
  constructor (cartAPI, hooks, message, config) {
    this.cart = cartAPI
    this.hooks = hooks
    this.message = message
    this.config = config
  }

  /**
   * Инициализация UI обработчиков
   */
  init () {
    this.initQuantityButtons()
    this.initQuantityInputs()
    this.initOptionSelects()
  }

  /**
   * Кнопки +/- количества товара
   */
  initQuantityButtons () {
    document.querySelectorAll('.qty-btn').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const form = e.target.closest('.ms3_form')
        if (!form) return

        const input = form.querySelector('.qty-input')
        const productKeyInput = form.querySelector('[name="product_key"]')

        if (!input || !productKeyInput) return

        let qty = parseInt(input.value) || 0

        // Увеличение
        if (e.target.classList.contains('inc-qty')) {
          qty++
        }

        // Уменьшение
        if (e.target.classList.contains('dec-qty') && qty > 0) {
          qty--
        }

        input.value = qty

        await this.handleChange(productKeyInput.value, qty)
      })
    })
  }

  /**
   * Поля ввода количества
   */
  initQuantityInputs () {
    document.querySelectorAll('.qty-input').forEach(input => {
      input.addEventListener('change', async (e) => {
        const form = e.target.closest('.ms3_form')
        if (!form) return

        const productKeyInput = form.querySelector('[name="product_key"]')
        if (!productKeyInput) return

        const qty = parseInt(e.target.value) || 0

        if (qty === 0) return

        await this.handleChange(productKeyInput.value, qty)
      })
    })
  }

  /**
   * Селекты опций товара (цвет, размер и т.д.)
   */
  initOptionSelects () {
    document.querySelectorAll('.ms3_cart_options').forEach(select => {
      select.addEventListener('change', async (e) => {
        const form = e.target.closest('.ms3_form')
        if (!form) return

        // TODO: Реализовать changeOption через API
        // Пока что просто логируем
        console.log('Option changed:', e.target.name, e.target.value)
      })
    })
  }

  /**
   * Обработка изменения количества товара
   *
   * @param {string} productKey - Ключ товара
   * @param {number} count - Новое количество
   */
  async handleChange (productKey, count) {
    // Хук BEFORE
    const hookData = { productKey, count }
    await this.hooks.runHooks('beforeChangeCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      // API запрос (с токенами рендера если есть)
      const renderTokens = this.getRenderTokens()
      const response = await this.cart.change(productKey, count, renderTokens)

      // Хук AFTER
      await this.hooks.runHooks('afterChangeCart', { productKey, count, response })

      // Обработка ответа
      if (response.success) {
        // Рендер HTML если backend прислал
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        // Событие для сторонних скриптов
        this.dispatchCartUpdated(response.data)

        // Уведомление
        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('CartUI.handleChange error:', error)
      this.message.error('Произошла ошибка при обновлении корзины')
    }
  }

  /**
   * Обработка добавления товара
   *
   * @param {number} id - ID товара
   * @param {number} count - Количество
   * @param {Object} options - Опции товара
   */
  async handleAdd (id, count = 1, options = {}) {
    // Хук BEFORE
    const hookData = { id, count, options }
    await this.hooks.runHooks('beforeAddCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      // API запрос (с токенами рендера если есть)
      const renderTokens = this.getRenderTokens()
      const response = await this.cart.add(id, count, options, renderTokens)

      // Хук AFTER
      await this.hooks.runHooks('afterAddCart', { id, count, options, response })

      // Обработка ответа
      if (response.success) {
        // Рендер HTML
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        // Событие
        this.dispatchCartUpdated(response.data)

        // Уведомление
        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('[CartUI] handleAdd error:', error)
      this.message.error('Произошла ошибка при добавлении товара')
    }
  }

  /**
   * Обработка удаления товара
   *
   * @param {string} productKey - Ключ товара
   */
  async handleRemove (productKey) {
    // Хук BEFORE
    const hookData = { productKey }
    await this.hooks.runHooks('beforeRemoveCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      // API запрос (с токенами рендера если есть)
      const renderTokens = this.getRenderTokens()
      const response = await this.cart.remove(productKey, renderTokens)

      // Хук AFTER
      await this.hooks.runHooks('afterRemoveCart', { productKey, response })

      // Обработка ответа
      if (response.success) {
        // Рендер HTML
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        // Событие
        this.dispatchCartUpdated(response.data)

        // Уведомление
        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('CartUI.handleRemove error:', error)
      this.message.error('Произошла ошибка при удалении товара')
    }
  }

  /**
   * Обработка очистки корзины
   */
  async handleClean () {
    // Хук BEFORE
    const hookData = {}
    await this.hooks.runHooks('beforeCleanCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      // API запрос (с токенами рендера если есть)
      const renderTokens = this.getRenderTokens()
      const response = await this.cart.clean(renderTokens)

      // Хук AFTER
      await this.hooks.runHooks('afterCleanCart', { response })

      // Обработка ответа
      if (response.success) {
        // Рендер HTML
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        // Событие
        this.dispatchCartUpdated(response.data)

        // Уведомление
        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('CartUI.handleClean error:', error)
      this.message.error('Произошла ошибка при очистке корзины')
    }
  }

  /**
   * Получить токены рендера из конфига
   *
   * @returns {Array|null} Массив токенов или null
   */
  getRenderTokens () {
    if (!this.config || !this.config.render || !this.config.render.cart) {
      return null
    }

    const cartRenderConfig = this.config.render.cart

    if (!Array.isArray(cartRenderConfig) || cartRenderConfig.length === 0) {
      return null
    }

    // Извлекаем только токены из массива объектов
    const tokens = cartRenderConfig.map(item => item.token).filter(Boolean)
    return tokens
  }

  /**
   * Рендеринг HTML блоков корзины
   *
   * Backend отдаёт HTML по токенам:
   * {
   *   "token1": "<div>HTML корзины 1</div>",
   *   "token2": "<div>HTML корзины 2</div>"
   * }
   *
   * Сопоставляем токены с селекторами из ms3Config.render.cart:
   * [{token: "token1", selector: "#headerMiniCart"}, ...]
   *
   * @param {Object} renderData - Объект {token: html}
   */
  renderCart (renderData) {
    if (!renderData || typeof renderData !== 'object') {
      return
    }

    if (!this.config || !this.config.render || !this.config.render.cart) {
      return
    }

    const cartRenderConfig = this.config.render.cart

    // Для каждого токена находим селектор и обновляем DOM
    for (const token in renderData) {
      const html = renderData[token]

      // Находим конфигурацию по токену
      const config = cartRenderConfig.find(item => item.token === token)

      if (!config || !config.selector) {
        continue
      }

      const element = document.querySelector(config.selector)

      if (element) {
        element.innerHTML = html
      }
    }

    // Переинициализация обработчиков после рендера
    // (т.к. DOM обновился)
    setTimeout(() => {
      this.init()
    }, 100)
  }

  /**
   * Отправка события обновления корзины
   *
   * Позволяет сторонним скриптам подписаться на изменения корзины:
   * document.addEventListener('ms3:cart:updated', (e) => {
   *   console.log('Корзина обновлена', e.detail)
   * })
   *
   * @param {Object} data - Данные корзины
   */
  dispatchCartUpdated (data) {
    document.dispatchEvent(new CustomEvent('ms3:cart:updated', {
      detail: data
    }))
  }
}
