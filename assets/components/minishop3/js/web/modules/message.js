/**
 * Система уведомлений
 *
 * Показывает сообщения пользователю через iziToast (если подключен)
 * или через нативные alert.
 */
window.ms3Message = {
  /**
   * Показать уведомление
   *
   * @param {string} type - Тип уведомления (success, error, info, warning)
   * @param {string} message - Текст сообщения
   */
  show (type, message) {
    if (!message || message === '') return

    // Используем iziToast если доступен
    if (typeof iziToast !== 'undefined') {
      // eslint-disable-next-line no-undef
      iziToast[type]({
        message,
        position: 'topRight',
        timeout: 3000
      })
    } else {
      // Fallback на нативный alert
      alert(message)
    }
  },

  /**
   * Успешное уведомление (зелёное)
   *
   * @param {string} message
   */
  success (message) {
    this.show('success', message)
  },

  /**
   * Ошибка (красное)
   *
   * @param {string} message
   */
  error (message) {
    this.show('error', message)
  },

  /**
   * Информационное (синее)
   *
   * @param {string} message
   */
  info (message) {
    this.show('info', message)
  },

  /**
   * Предупреждение (жёлтое)
   *
   * @param {string} message
   */
  warning (message) {
    this.show('warning', message)
  }
}
