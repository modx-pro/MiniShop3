/**
 * CustomerAddresses - управление адресами в личном кабинете клиента
 *
 * Обрабатывает действия:
 * - Установка адреса по умолчанию
 * - Удаление адреса
 *
 * @example
 * const customerAddresses = new CustomerAddresses({
 *   apiUrl: '/assets/components/minishop3/api.php'
 * })
 * customerAddresses.init()
 */
class CustomerAddresses {
  /**
   * @param {Object} config - Конфигурация
   * @param {string} config.apiUrl - URL API
   */
  constructor (config = {}) {
    this.config = {
      apiUrl: config.apiUrl || '/assets/components/minishop3/api.php',
      containerSelector: config.containerSelector || '.ms3-customer-addresses',
      setDefaultSelector: config.setDefaultSelector || '.set-default-address',
      deleteSelector: config.deleteSelector || '.delete-address',
      ...config
    }
  }

  /**
   * Инициализация обработчиков
   */
  init () {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.bindEvents())
    } else {
      this.bindEvents()
    }
  }

  /**
   * Привязка событий
   */
  bindEvents () {
    // Установка адреса по умолчанию
    document.querySelectorAll(this.config.setDefaultSelector).forEach(btn => {
      btn.addEventListener('click', (e) => this.handleSetDefault(e))
    })

    // Удаление адреса
    document.querySelectorAll(this.config.deleteSelector).forEach(btn => {
      btn.addEventListener('click', (e) => this.handleDelete(e))
    })
  }

  /**
   * Получить тексты из data-атрибутов контейнера
   * @param {HTMLElement} btn - Кнопка
   * @returns {Object} - Тексты для confirm и error
   */
  getTexts (btn) {
    const container = btn.closest('.list-group-item')
    return {
      confirmSetDefault: container?.dataset.confirmSetDefault || 'Сделать этот адрес основным?',
      confirmDelete: container?.dataset.confirmDelete || 'Вы уверены, что хотите удалить этот адрес?',
      errorUnknown: container?.dataset.errorUnknown || 'Произошла ошибка'
    }
  }

  /**
   * Установка адреса по умолчанию
   * @param {Event} e - Событие клика
   */
  async handleSetDefault (e) {
    const btn = e.currentTarget
    const texts = this.getTexts(btn)

    if (!confirm(texts.confirmSetDefault)) return

    const addressId = btn.dataset.addressId
    try {
      const response = await fetch(`${this.config.apiUrl}?route=/api/v1/customer/addresses/${addressId}/set-default`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' }
      })

      const data = await response.json()
      if (data.success) {
        location.reload()
      } else {
        alert(data.message || texts.errorUnknown)
      }
    } catch (error) {
      console.error('[CustomerAddresses] Set default error:', error)
      alert(texts.errorUnknown)
    }
  }

  /**
   * Удаление адреса
   * @param {Event} e - Событие клика
   */
  async handleDelete (e) {
    const btn = e.currentTarget
    const texts = this.getTexts(btn)

    if (!confirm(texts.confirmDelete)) return

    const addressId = btn.dataset.addressId
    try {
      const response = await fetch(`${this.config.apiUrl}?route=/api/v1/customer/addresses/${addressId}`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' }
      })

      const data = await response.json()
      if (data.success) {
        location.reload()
      } else {
        alert(data.message || texts.errorUnknown)
      }
    } catch (error) {
      console.error('[CustomerAddresses] Delete error:', error)
      alert(texts.errorUnknown)
    }
  }
}

// Экспорт для использования как модуль
if (typeof module !== 'undefined' && module.exports) {
  module.exports = CustomerAddresses
}
