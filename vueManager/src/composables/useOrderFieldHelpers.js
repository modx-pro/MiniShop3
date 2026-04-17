/**
 * Field combo / display helpers bound to order model refs from OrderView.
 *
 * @param {Object} deps
 * @param {Function} deps.formatDate
 * @param {Function} deps.formatPrice
 * @param {import('vue').Ref} deps.order
 * @param {import('vue').Ref} deps.orderComboOptions
 * @param {import('vue').Ref} deps.addressComboOptions
 * @param {import('vue').Ref} deps.statuses
 * @param {import('vue').Ref} deps.deliveries
 * @param {import('vue').Ref} deps.payments
 * @param {Function} deps._
 */
export function useOrderFieldHelpers(deps) {
  const {
    formatDate,
    formatPrice,
    order,
    orderComboOptions,
    addressComboOptions,
    statuses,
    deliveries,
    payments,
    _,
  } = deps

  function getFieldOptions(fieldName) {
    if (orderComboOptions.value[fieldName]) {
      const config = orderComboOptions.value[fieldName]
      if (config.options) {
        return config.options
      }
      return config
    }

    switch (fieldName) {
      case 'status_id':
        return statuses.value
      case 'delivery_id':
        return deliveries.value
      case 'payment_id':
        return payments.value
      default:
        return []
    }
  }

  function getFieldCompareField(fieldName) {
    if (orderComboOptions.value[fieldName]?.compareField) {
      return orderComboOptions.value[fieldName].compareField
    }
    return fieldName
  }

  function getAddressFieldOptions(fieldName) {
    if (addressComboOptions.value[fieldName]) {
      const config = addressComboOptions.value[fieldName]
      if (config.options) {
        return config.options
      }
      return config
    }
    return []
  }

  function getAddressFieldCompareField(fieldName) {
    if (addressComboOptions.value[fieldName]?.compareField) {
      return addressComboOptions.value[fieldName].compareField
    }
    return fieldName
  }

  function isFieldEditable(fieldName) {
    const readOnlyFields = [
      'num',
      'createdon',
      'updatedon',
      'cost',
      'cart_cost',
      'delivery_cost',
      'weight',
    ]
    return !readOnlyFields.includes(fieldName)
  }

  function getFieldDisplayValue(field, value) {
    if (value === null || value === undefined) return '-'

    switch (field.xtype) {
      case 'datefield': {
        return formatDate(value)
      }
      case 'numberfield': {
        return formatPrice(value)
      }
      case 'combo': {
        const compareField = getFieldCompareField(field.name)
        const actualValue = order.value?.[compareField] ?? value
        const options = getFieldOptions(field.name)
        const option = options.find(o => o.value === actualValue)
        return option?.label || actualValue
      }
      case 'checkbox': {
        return value ? _('yes') : _('no')
      }
      default: {
        return value
      }
    }
  }

  return {
    getFieldOptions,
    getFieldCompareField,
    getAddressFieldOptions,
    getAddressFieldCompareField,
    isFieldEditable,
    getFieldDisplayValue,
  }
}
