/**
 * API для работы с заказом
 *
 * Управление данными заказа: получение, обновление, оформление.
 *
 * @example
 * const order = new OrderAPI(apiClient)
 * await order.add('receiver', 'Иван Иванов')
 * await order.submit()
 */
class OrderAPI {
  /**
   * @param {ApiClient} apiClient - HTTP клиент
   */
  constructor (apiClient) {
    this.api = apiClient
  }

  /**
   * Добавить/обновить поле заказа
   *
   * POST /api/v1/order/add
   *
   * @param {string} key - Ключ поля (receiver, email, phone и т.д.)
   * @param {string} value - Значение поля
   * @returns {Promise<Object>}
   *
   * @example
   * await order.add('receiver', 'Иван Иванов')
   * await order.add('email', 'ivan@example.com')
   */
  async add (key, value) {
    return this.api.post('/api/v1/order/add', { key, value })
  }

  /**
   * Удалить поле заказа
   *
   * POST /api/v1/order/remove
   *
   * @param {string} key - Ключ поля
   * @returns {Promise<Object>}
   */
  async remove (key) {
    return this.api.post('/api/v1/order/remove', { key })
  }

  /**
   * Очистить заказ
   *
   * POST /api/v1/order/clean
   *
   * @returns {Promise<Object>}
   */
  async clean () {
    return this.api.post('/api/v1/order/clean')
  }

  /**
   * Оформить заказ (финальная отправка)
   *
   * POST /api/v1/order/submit
   *
   * @returns {Promise<Object>}
   */
  async submit () {
    return this.api.post('/api/v1/order/submit')
  }

  /**
   * Получить текущий заказ
   *
   * GET /api/v1/order/get
   *
   * @returns {Promise<Object>}
   */
  async get () {
    return this.api.get('/api/v1/order/get')
  }
}
