/**
 * CustomerAddresses - address management in customer account
 *
 * Handles actions:
 * - Set default address
 * - Delete address
 *
 * @example
 * const customerAddresses = new CustomerAddresses({
 *   apiUrl: '/assets/components/minishop3/api.php'
 * })
 * customerAddresses.init()
 */
class CustomerAddresses {
  /**
   * @param {Object} config - Configuration
   * @param {string} config.apiUrl - API URL
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
   * Bind events
   */
  bindEvents () {
    document.querySelectorAll(this.config.setDefaultSelector).forEach(btn => {
      btn.addEventListener('click', (e) => this.handleSetDefault(e))
    })

    document.querySelectorAll(this.config.deleteSelector).forEach(btn => {
      btn.addEventListener('click', (e) => this.handleDelete(e))
    })
  }

  /**
   * Get texts from data attributes
   * @param {HTMLElement} btn - Button element
   * @returns {Object} - Texts for confirm and error
   */
  getTexts (btn) {
    const container = btn.closest('.list-group-item')
    return {
      confirmSetDefault: container?.dataset.confirmSetDefault || 'Set this address as default?',
      confirmDelete: container?.dataset.confirmDelete || 'Are you sure you want to delete this address?',
      errorUnknown: container?.dataset.errorUnknown || 'An error occurred'
    }
  }

  /**
   * Set default address
   * @param {Event} e - Click event
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
   * Delete address
   * @param {Event} e - Click event
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

if (typeof module !== 'undefined' && module.exports) {
  module.exports = CustomerAddresses
}
