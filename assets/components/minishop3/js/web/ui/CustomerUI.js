/**
 * UI обработчики для данных покупателя
 *
 * Управляет формами профиля покупателя: автосохранение, валидация.
 */
class CustomerUI {
  /**
   * @param {CustomerAPI} customerAPI - API для работы с покупателем
   * @param {Object} hooks - Система хуков
   * @param {Object} message - Система уведомлений
   * @param {Object} config - Конфигурация
   */
  constructor (customerAPI, hooks, message, config) {
    this.customer = customerAPI
    this.hooks = hooks
    this.message = message
    this.config = config
  }

  /**
   * Инициализация UI обработчиков
   */
  init () {
    // Находим все формы покупателя
    document.querySelectorAll('.ms3_customer_form').forEach(form => {
      this.initForm(form)
    })
  }

  /**
   * Инициализация одной формы
   *
   * @param {HTMLFormElement} form - Форма покупателя
   */
  initForm (form) {
    const inputs = form.querySelectorAll('input, textarea')

    inputs.forEach(input => {
      this.initInput(input)
    })
  }

  /**
   * Инициализация поля ввода
   *
   * @param {HTMLInputElement} input - Поле ввода
   */
  initInput (input) {
    input.addEventListener('change', async () => {
      const form = input.closest('.ms3_customer_form')
      if (!form) return

      const parent = input.closest('div')
      if (!parent) return

      // Сброс состояния валидации
      form.classList.remove('was-validated')
      input.classList.remove('is-invalid')
      const feedback = parent.querySelector('.invalid-feedback')
      if (feedback) {
        feedback.textContent = ''
      }

      // Отправка данных на сервер
      const response = await this.handleAdd(input.name, input.value)

      if (response.success) {
        // Обновляем значение поля ответом от сервера
        if (response.data && response.data[input.name] !== undefined) {
          input.value = response.data[input.name]
        }
      } else {
        // Показываем ошибку валидации
        form.classList.add('was-validated')
        input.classList.add('is-invalid')

        if (feedback) {
          feedback.textContent = response.message || 'Ошибка валидации'
        }
      }
    })
  }

  /**
   * Добавление/обновление поля покупателя
   *
   * @param {string} key - Ключ поля
   * @param {string} value - Значение
   * @returns {Promise<Object>}
   */
  async handleAdd (key, value) {
    // Хук BEFORE
    const hookData = { key, value }
    await this.hooks.runHooks('beforeAddCustomer', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      // API запрос
      const response = await this.customer.add(key, value)

      // Хук AFTER
      await this.hooks.runHooks('afterAddCustomer', { key, value, response })

      // Уведомление
      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      return response
    } catch (error) {
      console.error('CustomerUI.handleAdd error:', error)
      this.message.error('Произошла ошибка')
      return { success: false, message: error.message }
    }
  }

  /**
   * Изменение адреса
   *
   * @param {string} key - Ключ (обычно 'address_hash')
   * @param {string} value - Значение
   * @returns {Promise<Object>}
   */
  async handleChangeAddress (key, value) {
    // Хук BEFORE
    const hookData = { key, value }
    await this.hooks.runHooks('beforeChangeAddressCustomer', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      // API запрос
      const response = await this.customer.changeAddress(key, value)

      // Хук AFTER
      await this.hooks.runHooks('afterChangeAddressCustomer', { key, value, response })

      // Уведомление
      if (response.success && response.message) {
        this.message.success(response.message)
      }

      if (!response.success && response.message) {
        this.message.error(response.message)
      }

      return response
    } catch (error) {
      console.error('CustomerUI.handleChangeAddress error:', error)
      this.message.error('Произошла ошибка')
      return { success: false }
    }
  }

  /**
   * Обновление профиля покупателя
   *
   * @param {FormData} formData - Данные формы
   * @returns {Promise<Object>}
   */
  async handleProfileUpdate (formData) {
    // Конвертируем FormData в обычный объект
    const data = {}
    for (const [key, value] of formData.entries()) {
      // Пропускаем служебные поля
      if (key === 'ms3_action') continue
      data[key] = value
    }

    // Хук BEFORE
    const hookData = { data }
    await this.hooks.runHooks('beforeUpdateProfile', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      // API запрос
      const response = await this.customer.updateProfile(data)

      // Хук AFTER
      await this.hooks.runHooks('afterUpdateProfile', { data, response })

      // Уведомление
      if (response.success) {
        this.message.success(response.message || 'Профиль успешно обновлен')

        // Перезагрузить страницу через 1 секунду
        setTimeout(() => {
          window.location.reload()
        }, 1000)
      } else {
        this.message.error(response.message || 'Ошибка при обновлении профиля')
      }

      return response
    } catch (error) {
      console.error('CustomerUI.handleProfileUpdate error:', error)
      this.message.error('Произошла ошибка при сохранении')
      return { success: false, message: error.message }
    }
  }

  /**
   * Создание нового адреса
   *
   * @param {FormData} formData - Данные формы
   * @returns {Promise<Object>}
   */
  async handleAddressCreate (formData) {
    // Конвертируем FormData в обычный объект
    const data = {}
    for (const [key, value] of formData.entries()) {
      // Пропускаем служебные поля
      if (key === 'ms3_action') continue
      data[key] = value
    }

    // Хук BEFORE
    const hookData = { data }
    await this.hooks.runHooks('beforeCreateAddress', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      // API запрос
      const response = await this.customer.createAddress(data)

      // Хук AFTER
      await this.hooks.runHooks('afterCreateAddress', { data, response })

      // Уведомление
      if (response.success) {
        this.message.success(response.message || 'Адрес успешно добавлен')

        // Перенаправить на список адресов через 1 секунду
        setTimeout(() => {
          window.location.href = window.location.pathname
        }, 1000)
      } else {
        this.message.error(response.message || 'Ошибка при добавлении адреса')
      }

      return response
    } catch (error) {
      console.error('CustomerUI.handleAddressCreate error:', error)
      this.message.error('Произошла ошибка при сохранении')
      return { success: false, message: error.message }
    }
  }

  /**
   * Обновление адреса
   *
   * @param {FormData} formData - Данные формы
   * @returns {Promise<Object>}
   */
  async handleAddressUpdate (formData) {
    // Конвертируем FormData в обычный объект
    const data = {}
    for (const [key, value] of formData.entries()) {
      // Пропускаем служебные поля
      if (key === 'ms3_action') continue
      data[key] = value
    }

    const addressId = data.id
    if (!addressId) {
      this.message.error('ID адреса не указан')
      return { success: false, message: 'ID адреса не указан' }
    }

    // Хук BEFORE
    const hookData = { addressId, data }
    await this.hooks.runHooks('beforeUpdateAddress', hookData)

    if (hookData.cancel) {
      return { success: false }
    }

    try {
      // API запрос
      const response = await this.customer.updateAddress(addressId, data)

      // Хук AFTER
      await this.hooks.runHooks('afterUpdateAddress', { addressId, data, response })

      // Уведомление
      if (response.success) {
        this.message.success(response.message || 'Адрес успешно обновлен')

        // Перенаправить на список адресов через 1 секунду
        setTimeout(() => {
          window.location.href = window.location.pathname
        }, 1000)
      } else {
        this.message.error(response.message || 'Ошибка при обновлении адреса')
      }

      return response
    } catch (error) {
      console.error('CustomerUI.handleAddressUpdate error:', error)
      this.message.error('Произошла ошибка при сохранении')
      return { success: false, message: error.message }
    }
  }
}
