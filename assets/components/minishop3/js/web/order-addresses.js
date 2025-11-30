/**
 * MiniShop3 - Order Addresses Module
 *
 * Обработка выбора сохранённых адресов клиента в форме оформления заказа.
 * Работает по принципу SSR - адреса загружаются на сервере и рендерятся в select.
 * JavaScript только обрабатывает выбор адреса и заполнение полей формы.
 */
(function () {
  'use strict'

  // Конфигурация
  const config = {
    addressSelect: null,
    addressFields: [
      'country',
      'index',
      'region',
      'city',
      'metro',
      'street',
      'building',
      'entrance',
      'floor',
      'room',
      'text_address'
    ]
  }

  /**
   * Инициализация при загрузке DOM
   */
  function init () {
    // Находим select с адресами
    config.addressSelect = document.getElementById('saved_address_id')

    if (!config.addressSelect) {
      console.log('[MS3 Order Addresses] Select not found (customer not authenticated or no addresses)')
      return
    }

    console.log('[MS3 Order Addresses] Initializing address selection handler')

    // Обработчик выбора адреса
    config.addressSelect.addEventListener('change', handleAddressChange)
  }

  /**
   * Обработчик изменения выбранного адреса
   */
  function handleAddressChange (event) {
    const selectedOption = event.target.selectedOptions[0]
    const addressId = selectedOption.value

    if (!addressId) {
      // Выбран "Новый адрес" - очищаем поля
      console.log('[MS3 Order Addresses] New address selected, clearing fields')
      clearAddressFields()
      return
    }

    // Получаем данные адреса из data-атрибута
    const addressData = selectedOption.getAttribute('data-address')

    if (!addressData) {
      console.error('[MS3 Order Addresses] Address data not found in option')
      return
    }

    try {
      const address = JSON.parse(addressData)
      console.log('[MS3 Order Addresses] Selected address #' + addressId)
      fillAddressFields(address)
    } catch (error) {
      console.error('[MS3 Order Addresses] Failed to parse address data:', error)
    }
  }

  /**
   * Заполнение полей формы данными адреса
   */
  function fillAddressFields (address) {
    config.addressFields.forEach(function (fieldName) {
      const input = document.querySelector('[name="' + fieldName + '"]')

      if (!input) {
        return
      }

      // Устанавливаем значение поля (если есть в данных адреса)
      if (address[fieldName] !== undefined && address[fieldName] !== null) {
        input.value = address[fieldName]
      } else {
        input.value = ''
      }

      // Триггерим событие change для валидации и обновления UI
      const event = new Event('change', { bubbles: true })
      input.dispatchEvent(event)
    })
  }

  /**
   * Очистка полей адреса
   */
  function clearAddressFields () {
    config.addressFields.forEach(function (fieldName) {
      const input = document.querySelector('[name="' + fieldName + '"]')

      if (!input) {
        return
      }

      input.value = ''

      // Триггерим событие change для валидации и обновления UI
      const event = new Event('change', { bubbles: true })
      input.dispatchEvent(event)
    })
  }

  // Инициализация при загрузке DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    // DOM уже загружен
    init()
  }
})()
