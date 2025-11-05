/**
 * API для работы с данными покупателя
 *
 * Управление профилем покупателя: контактные данные, адреса.
 *
 * @example
 * const customer = new CustomerAPI(apiClient)
 * await customer.add('email', 'user@example.com')
 * await customer.changeAddress('address_hash', 'addr_123')
 */
class CustomerAPI {
  /**
   * @param {ApiClient} apiClient - HTTP клиент
   */
  constructor (apiClient) {
    this.api = apiClient
  }

  /**
   * Добавить/обновить поле покупателя
   *
   * POST /api/v1/customer/add
   *
   * @param {string} key - Ключ поля (email, phone, fullname и т.д.)
   * @param {string} value - Значение поля
   * @returns {Promise<Object>}
   *
   * @example
   * await customer.add('email', 'user@example.com')
   * await customer.add('phone', '+7 900 123-45-67')
   */
  async add (key, value) {
    return this.api.post('/api/v1/customer/add', { key, value })
  }

  /**
   * Изменить адрес доставки
   *
   * POST /api/v1/customer/changeAddress
   *
   * @param {string} key - Ключ (обычно 'address_hash')
   * @param {string} value - Хэш адреса
   * @returns {Promise<Object>}
   */
  async changeAddress (key, value) {
    return this.api.post('/api/v1/customer/changeAddress', { key, value })
  }
}
