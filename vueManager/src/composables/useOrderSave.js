/**
 * Order save / finalize / create actions for the order view.
 *
 * @param {Object} deps
 * @param {Function} deps._
 * @param {Object} deps.toast
 * @param {Object} deps.confirm
 * @param {import('vue').Ref} deps.order
 * @param {import('vue').ComputedRef} deps.orderId
 * @param {import('vue').Ref} deps.saving
 * @param {import('vue').Ref} deps.orderFields
 * @param {import('vue').Ref} deps.addressFields
 * @param {import('vue').Ref} deps.orderExtraFields
 * @param {import('vue').Ref} deps.addressExtraFields
 * @param {import('vue').Ref} deps.selectedCustomer
 * @param {import('vue').Ref} deps.createCustomerFromData
 * @param {import('vue').Ref} deps.showDuplicateDialog
 * @param {import('vue').Ref} deps.duplicateCustomer
 * @param {import('vue').Ref} deps.pendingOrderData
 * @param {Object} deps.fieldHelpers
 * @param {Function} deps.loadOrder
 * @param {Function} deps.loadLogs
 * @param {Function} deps.syncShippingPaymentBaselineFromOrder
 * @param {import('vue').Ref} deps.recalculatingCost
 * @param {import('vue').ShallowRef} deps.costRecalcWarnings
 * @param {Record<string, string>} deps.COST_RECALC_WARNING_HINTS
 */
import { ref } from 'vue'

import request from '../request.js'

export function useOrderSave(deps) {
  const {
    _,
    toast,
    confirm,
    order,
    orderId,
    saving,
    orderFields,
    addressFields,
    orderExtraFields,
    addressExtraFields,
    selectedCustomer,
    createCustomerFromData,
    showDuplicateDialog,
    duplicateCustomer,
    pendingOrderData,
    fieldHelpers,
    loadOrder,
    loadLogs,
    syncShippingPaymentBaselineFromOrder,
    recalculatingCost,
    costRecalcWarnings,
    COST_RECALC_WARNING_HINTS,
  } = deps

  const finalizing = ref(false)

  /**
   * Save order
   */
  async function saveOrder() {
    if (recalculatingCost.value || saving.value) {
      return
    }

    saving.value = true

    try {
      const orderData = {}
      for (const field of orderFields.value) {
        if (fieldHelpers.isFieldEditable(field.name) && order.value[field.name] !== undefined) {
          orderData[field.name] = order.value[field.name]
        }
      }

      for (const field of addressFields.value) {
        if (order.value[field.name] !== undefined) {
          orderData[field.name] = order.value[field.name]
        }
      }

      for (const field of orderExtraFields.value) {
        if (order.value[field.key] !== undefined) {
          orderData[field.key] = order.value[field.key]
        }
      }

      for (const field of addressExtraFields.value) {
        if (order.value[field.key] !== undefined) {
          orderData[field.key] = order.value[field.key]
        }
      }

      await request.put(`/api/mgr/orders/${orderId.value}`, orderData)

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('order_saved'),
        life: 3000,
      })

      syncShippingPaymentBaselineFromOrder()

      await loadLogs()
    } catch (error) {
      console.error('[OrderView] Error saving order:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('error_saving_data'),
        life: 5000,
      })
    } finally {
      saving.value = false
    }
  }

  /**
   * Finalize order (convert draft to final order)
   * Shows confirmation dialog first
   */
  function confirmFinalizeOrder() {
    confirm.require({
      message: _('ms3_order_finalize_confirm_desc'),
      header: _('ms3_order_finalize_confirm'),
      icon: 'pi pi-check-circle',
      acceptLabel: _('ms3_order_finalize_btn'),
      rejectLabel: _('cancel'),
      accept: () => {
        finalizeOrder()
      },
    })
  }

  /**
   * Finalize order API call
   */
  async function finalizeOrder(forceCreateCustomer = false) {
    finalizing.value = true

    try {
      const requestData = {}

      if (createCustomerFromData.value && !order.value.customer_id) {
        requestData.create_customer = true
        if (forceCreateCustomer) {
          requestData.force_create_customer = true
        }
      }

      const response = await request.post(`/api/mgr/orders/${orderId.value}/finalize`, requestData)

      if (response.duplicate_found) {
        pendingOrderData.value = { finalize: true }
        duplicateCustomer.value = response.customer
        showDuplicateDialog.value = true
        finalizing.value = false
        return
      }

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('ms3_order_finalized'),
        life: 3000,
      })

      await loadOrder()
      await loadLogs()
    } catch (error) {
      console.error('[OrderView] Error finalizing order:', error)

      const apiErrors = error.data?.errors
      const costWarnings = Array.isArray(apiErrors?.warnings)
        ? apiErrors.warnings.map(String)
        : []

      if (costWarnings.length > 0) {
        costRecalcWarnings.value = costWarnings
        costWarnings.forEach(code => {
          const hintKey = COST_RECALC_WARNING_HINTS[code]
          if (hintKey) {
            toast.add({
              severity: 'warn',
              summary: _('error'),
              detail: _(hintKey),
              life: 7000,
            })
          }
        })
      }

      const validationErrors = error.data?.object?.errors
      if (validationErrors && Array.isArray(validationErrors) && validationErrors.length > 0) {
        validationErrors.forEach(field => {
          const fieldError = _(`ms3_order_err_${field}`) || field
          toast.add({
            severity: 'error',
            summary: _('ms3_order_err_validation'),
            detail: fieldError,
            life: 5000,
          })
        })
      } else if (costWarnings.length === 0) {
        toast.add({
          severity: 'error',
          summary: _('error'),
          detail: error.message || _('ms3_order_finalize_error'),
          life: 5000,
        })
      }
    } finally {
      finalizing.value = false
    }
  }

  /**
   * Collect order data from form
   */
  function collectOrderData() {
    const orderData = {}

    for (const field of orderFields.value) {
      if (order.value[field.name] !== undefined && order.value[field.name] !== null) {
        orderData[field.name] = order.value[field.name]
      }
    }

    for (const field of addressFields.value) {
      if (order.value[field.name] !== undefined && order.value[field.name] !== null) {
        orderData[field.name] = order.value[field.name]
      }
    }

    for (const field of orderExtraFields.value) {
      if (order.value[field.key] !== undefined && order.value[field.key] !== null) {
        orderData[field.key] = order.value[field.key]
      }
    }

    for (const field of addressExtraFields.value) {
      if (order.value[field.key] !== undefined && order.value[field.key] !== null) {
        orderData[field.key] = order.value[field.key]
      }
    }

    if (selectedCustomer.value?.id) {
      orderData.customer_id = selectedCustomer.value.id
    }

    if (createCustomerFromData.value && !selectedCustomer.value?.id) {
      orderData.create_customer = true
    }

    return orderData
  }

  /**
   * Validate customer data before creation
   * @returns {string|null} Error message or null if valid
   */
  function validateCustomerData(orderData) {
    if (orderData.create_customer) {
      const email = (orderData.email || '').trim()
      const phone = (orderData.phone || '').trim()

      if (!email && !phone) {
        return _('ms3_customer_validation_email_or_phone')
      }

      if (email && !email.includes('@')) {
        return _('ms3_customer_validation_invalid_email')
      }
    }

    return null
  }

  /**
   * Create new order
   */
  async function createOrder(forceCreateCustomer = false) {
    saving.value = true

    try {
      const orderData = collectOrderData()

      if (forceCreateCustomer) {
        orderData.force_create_customer = true
      }

      const validationError = validateCustomerData(orderData)
      if (validationError) {
        toast.add({
          severity: 'warn',
          summary: _('warning'),
          detail: validationError,
          life: 5000,
        })
        saving.value = false
        return
      }

      const response = await request.post('/api/mgr/orders', orderData)

      if (response.duplicate_found) {
        pendingOrderData.value = orderData
        duplicateCustomer.value = response.customer
        showDuplicateDialog.value = true
        saving.value = false
        return
      }

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('ms3_order_created'),
        life: 3000,
      })

      const createdOrderId = response.id || response.object?.id
      if (createdOrderId) {
        window.location.href = `?a=mgr/order&namespace=minishop3&id=${createdOrderId}`
      }
    } catch (error) {
      console.error('[OrderView] Error creating order:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('error_saving_data'),
        life: 5000,
      })
    } finally {
      saving.value = false
    }
  }

  return {
    saving,
    finalizing,
    saveOrder,
    confirmFinalizeOrder,
    finalizeOrder,
    collectOrderData,
    validateCustomerData,
    createOrder,
  }
}
