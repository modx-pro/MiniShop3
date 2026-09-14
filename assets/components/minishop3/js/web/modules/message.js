/**
 * Notification system
 *
 * Shows messages to user via iziToast (if available) or native alert.
 */
window.ms3Message = {
  /**
   * Show notification
   *
   * @param {string} type - Notification type (success, error, info, warning)
   * @param {string} message - Message text
   */
  show (type, message) {
    if (!message || message === '') return

    if (typeof iziToast !== 'undefined') {
       
      iziToast[type]({
        message,
        position: 'topRight',
        timeout: 3000,
      })
    } else {
      alert(message)
    }
  },

  /**
   * Success notification (green)
   *
   * @param {string} message
   */
  success (message) {
    this.show('success', message)
  },

  /**
   * Error notification (red)
   *
   * @param {string} message
   */
  error (message) {
    this.show('error', message)
  },

  /**
   * Info notification (blue)
   *
   * @param {string} message
   */
  info (message) {
    this.show('info', message)
  },

  /**
   * Warning notification (yellow)
   *
   * @param {string} message
   */
  warning (message) {
    this.show('warning', message)
  },
}
