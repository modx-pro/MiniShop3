/**
 * MiniShop3 - Order Addresses Module
 *
 * Handles selection of customer's saved addresses in order checkout form.
 * Works on SSR principle - addresses are loaded on server and rendered in select.
 * JavaScript only handles address selection and fills form fields.
 */
(function () {
  'use strict'

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
   * Initialize on DOM load
   */
  function init () {
    config.addressSelect = document.getElementById('saved_address_id')

    if (!config.addressSelect) {
      console.log('[MS3 Order Addresses] Select not found (customer not authenticated or no addresses)')
      return
    }

    console.log('[MS3 Order Addresses] Initializing address selection handler')

    config.addressSelect.addEventListener('change', handleAddressChange)
  }

  /**
   * Handle selected address change
   */
  function handleAddressChange (event) {
    const selectedOption = event.target.selectedOptions[0]
    const addressId = selectedOption.value

    if (!addressId) {
      console.log('[MS3 Order Addresses] New address selected, clearing fields')
      clearAddressFields()
      return
    }

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
   * Fill form fields with address data
   */
  function fillAddressFields (address) {
    config.addressFields.forEach(function (fieldName) {
      const input = document.querySelector('[name="' + fieldName + '"]')

      if (!input) {
        return
      }

      if (address[fieldName] !== undefined && address[fieldName] !== null) {
        input.value = address[fieldName]
      } else {
        input.value = ''
      }

      const event = new Event('change', { bubbles: true })
      input.dispatchEvent(event)
    })
  }

  /**
   * Clear address fields
   */
  function clearAddressFields () {
    config.addressFields.forEach(function (fieldName) {
      const input = document.querySelector('[name="' + fieldName + '"]')

      if (!input) {
        return
      }

      input.value = ''

      const event = new Event('change', { bubbles: true })
      input.dispatchEvent(event)
    })
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }
})()
