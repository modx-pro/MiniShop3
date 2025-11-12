/**
 * API для работы с корзиной
 *
 * Простая обёртка над REST endpoints корзины.
 * Все методы возвращают Promise с ответом от сервера.
 *
 * Формат ответа сервера:
 * {
 *   success: true/false,
 *   message: "Сообщение",
 *   data: {
 *     cart: [],           // Массив товаров
 *     status: {},         // Итоги корзины (total_cost, total_count и т.д.)
 *     render: {}          // HTML блоки для рендера (если запрошено)
 *   }
 * }
 *
 * @example
 * const cart = new CartAPI(apiClient)
 * const response = await cart.add(123, 2, { color: 'red' })
 * if (response.success) {
 *   console.log('Товар добавлен', response.data.cart)
 * }
 */
class CartAPI {
  /**
   * @param {ApiClient} apiClient - HTTP клиент
   */
  constructor (apiClient) {
    this.api = apiClient
  }

  /**
   * Получить корзину
   *
   * GET /api/v1/cart/get
   *
   * @param {Object} params - Дополнительные параметры
   * @param {Object} params.render - Конфигурация рендера (селекторы для обновления HTML)
   * @returns {Promise<Object>} - { success, message, data: { cart, status, render } }
   *
   * @example
   * const response = await cart.get()
   * console.log(response.data.cart) // Массив товаров
   * console.log(response.data.status.total_cost) // Общая стоимость
   */
  async get (params = {}) {
    const endpoint = '/api/v1/cart/get'

    // Если нужен рендер - передаём параметр
    if (params.render) {
      // TODO: добавить поддержку render параметра в backend
      // Пока просто возвращаем данные
    }

    return this.api.get(endpoint)
  }

  /**
   * Добавить товар в корзину
   *
   * POST /api/v1/cart/add
   *
   * @param {number} id - ID товара
   * @param {number} count - Количество (по умолчанию 1)
   * @param {Object} options - Опции товара (цвет, размер и т.д.)
   * @param {Object} render - Конфигурация рендера
   * @returns {Promise<Object>}
   *
   * @example
   * await cart.add(123, 2, { color: 'red', size: 'L' })
   */
  async add (id, count = 1, options = {}, render = null) {
    const data = {
      id,
      count,
      options
    }

    if (render) {
      data.render = JSON.stringify(render)
    }

    return this.api.post('/api/v1/cart/add', data)
  }

  /**
   * Изменить количество товара
   *
   * POST /api/v1/cart/change
   *
   * @param {string} productKey - Уникальный ключ товара в корзине
   * @param {number} count - Новое количество (0 = удалить)
   * @param {Object} render - Конфигурация рендера
   * @returns {Promise<Object>}
   *
   * @example
   * await cart.change('ms5d41d8cd98f00b204e9800998ecf8427e', 3)
   */
  async change (productKey, count, render = null) {
    const data = {
      product_key: productKey,
      count
    }

    if (render) {
      data.render = JSON.stringify(render)
    }

    return this.api.post('/api/v1/cart/change', data)
  }

  /**
   * Удалить товар из корзины
   *
   * POST /api/v1/cart/remove
   *
   * @param {string} productKey - Уникальный ключ товара
   * @param {Object} render - Конфигурация рендера
   * @returns {Promise<Object>}
   *
   * @example
   * await cart.remove('ms5d41d8cd98f00b204e9800998ecf8427e')
   */
  async remove (productKey, render = null) {
    const data = {
      product_key: productKey
    }

    if (render) {
      data.render = JSON.stringify(render)
    }

    return this.api.post('/api/v1/cart/remove', data)
  }

  /**
   * Очистить корзину (удалить все товары)
   *
   * POST /api/v1/cart/clean
   *
   * @param {Object} render - Конфигурация рендера
   * @returns {Promise<Object>}
   *
   * @example
   * await cart.clean()
   */
  async clean (render = null) {
    const data = {}

    if (render) {
      data.render = JSON.stringify(render)
    }

    return this.api.post('/api/v1/cart/clean', data)
  }
}
