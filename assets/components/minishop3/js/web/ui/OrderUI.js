/**
 * UI обработчики для заказа
 *
 * Управляет формами заказа: автосохранение полей, валидация, оформление.
 */
class OrderUI {
  /**
   * @param {OrderAPI} orderAPI - API для работы с заказом
   * @param {Object} hooks - Система хуков
   * @param {Object} message - Система уведомлений
   * @param {Object} config - Конфигурация
   */
  constructor (orderAPI, hooks, message, config) {
    this.order = orderAPI
    this.hooks = hooks
    this.message = message
    this.config = config
  }

  /**
   * Инициализация UI обработчиков
   */
  init () {
    // Находим все формы заказа
    document.querySelectorAll('.ms3_order_form').forEach(form => {
      this.initForm(form)
    })
  }

  /**
   * Инициализация одной формы
   *
   * @param {HTMLFormElement} form - Форма заказа
   */
  initForm (form) {
    const inputs = form.querySelectorAll('input, textarea, select')

    inputs.forEach(input => {
      // Разные обработчики для разных типов полей
      if (input.name === 'address_hash') {
        this.initAddressInput(input)
      } else {
        this.initRegularInput(input)
      }
    })
  }

  /**
   * Обработчик для обычных полей (автосохранение при изменении)
   *
   * @param {HTMLInputElement} input - Поле ввода
   */
  initRegularInput (input) {
    input.addEventListener('change', async () => {
      const parent = input.closest('div')
      if (!parent) return

      // Сброс состояния валидации
      parent.classList.remove('was-validated')
      input.classList.remove('is-invalid')
      const feedback = parent.querySelector('.invalid-feedback')
      if (feedback) {
        feedback.textContent = ''
      }

      // Отправка данных на сервер
      const response = await this.handleAdd(input.name, input.value)

      if (response.success) {
        parent.classList.add('was-validated')

        // Обновляем значение поля ответом от сервера
        if (response.data && response.data[input.name] !== undefined) {
          input.value = response.data[input.name]
        }
      } else {
        // Показываем ошибку валидации
        parent.classList.add('was-validated')
        input.classList.add('is-invalid')

        if (feedback) {
          feedback.textContent = response.message || 'Ошибка валидации'
        }
      }
    })
  }

  /**
   * Обработчик для поля адреса
   *
   * @param {HTMLInputElement} input - Поле адреса
   */
  initAddressInput (input) {
    input.addEventListener('change', async () => {
      const form = input.closest('.ms3_order_form')
      const parent = input.closest('div')
      if (!parent) return

      // Сброс состояния валидации
      parent.classList.remove('was-validated')
      input.classList.remove('is-invalid')
      const feedback = parent.querySelector('.invalid-feedback')
      if (feedback) {
        feedback.textContent = ''
      }

      // Отправка данных на сервер (через CustomerAPI)
      // TODO: Требуется доступ к CustomerAPI
      // Пока просто логируем
      console.log('Address changed:', input.value)
    })
  }

  /**
   * Добавление/обновление поля заказа
   *
   * @param {string} key - Ключ поля
   * @param {string} value - Значение
   * @returns {Promise<Object>}
   */
  async handleAdd (key, value) {
    // Хук BEFORE
    const hookData = { key, value }
    await this.hooks.runHooks('beforeAddOrder', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      // API запрос
      const response = await this.order.add(key, value)

      // Хук AFTER
      await this.hooks.runHooks('afterAddOrder', { key, value, response })

      // Уведомление
      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      return response
    } catch (error) {
      console.error('OrderUI.handleAdd error:', error)
      this.message.error('Произошла ошибка')
      return { success: false, message: error.message }
    }
  }

  /**
   * Оформление заказа
   *
   * @returns {Promise<Object>}
   */
  async handleSubmit () {
    // Хук BEFORE
    const hookData = {}
    await this.hooks.runHooks('beforeSubmitOrder', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      // API запрос
      const response = await this.order.submit()

      // Хук AFTER
      await this.hooks.runHooks('afterSubmitOrder', { response })

      // Редирект если сервер прислал
      if (response.success && response.data && response.data.redirect) {
        window.location.href = response.data.redirect
        return response
      }

      // Уведомление
      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      return response
    } catch (error) {
      console.error('OrderUI.handleSubmit error:', error)
      this.message.error('Произошла ошибка при оформлении заказа')
      return { success: false }
    }
  }
}
