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

  /**
   * Обновить профиль покупателя
   *
   * PUT /api/v1/customer/profile
   *
   * @param {Object} data - Данные профиля (first_name, last_name, email, phone)
   * @returns {Promise<Object>}
   *
   * @example
   * await customer.updateProfile({
   *   first_name: 'Иван',
   *   last_name: 'Иванов',
   *   email: 'ivan@example.com',
   *   phone: '+79991234567'
   * })
   */
  async updateProfile (data) {
    return this.api.put('/api/v1/customer/profile', data)
  }

  /**
   * Создать новый адрес
   *
   * POST /api/v1/customer/addresses
   *
   * @param {Object} data - Данные адреса
   * @returns {Promise<Object>}
   *
   * @example
   * await customer.createAddress({
   *   name: 'Домашний адрес',
   *   city: 'Москва',
   *   street: 'Тверская',
   *   building: '1'
   * })
   */
  async createAddress (data) {
    return this.api.post('/api/v1/customer/addresses', data)
  }

  /**
   * Обновить адрес
   *
   * PUT /api/v1/customer/addresses/{id}
   *
   * @param {number} id - ID адреса
   * @param {Object} data - Данные адреса
   * @returns {Promise<Object>}
   */
  async updateAddress (id, data) {
    return this.api.put(`/api/v1/customer/addresses/${id}`, data)
  }

  /**
   * Удалить адрес
   *
   * DELETE /api/v1/customer/addresses/{id}
   *
   * @param {number} id - ID адреса
   * @returns {Promise<Object>}
   */
  async deleteAddress (id) {
    return this.api.delete(`/api/v1/customer/addresses/${id}`)
  }
}
