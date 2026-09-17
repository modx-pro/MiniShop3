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
      'text_address',
    ],
  }

  /**
   * Initialize on DOM load
   */
  function init () {
    config.addressSelect = document.getElementById('saved_address_id')

    if (!config.addressSelect) {
      return
    }

    config.addressSelect.addEventListener('change', handleAddressChange)
  }

  /**
   * Handle selected address change
   */
  function handleAddressChange (event) {
    const selectedOption = event.target.selectedOptions[0]
    const addressId = selectedOption.value

    if (!addressId) {
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
