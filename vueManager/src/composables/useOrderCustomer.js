/**
 * Customer search / selection and duplicate-customer dialog handling.
 *
 * @param {Object} deps
 * @param {Function} deps._
 * @param {Object} deps.toast
 * @param {import('vue').Ref} deps.order
 * @param {import('vue').ComputedRef} deps.orderId
 * @param {import('vue').Ref} deps.saving
 * @param {Object} deps.orderActions
 * @param {Function} deps.orderActions.finalizeOrder
 * @param {Function} deps.orderActions.createOrder
 */
import { ref } from 'vue'

import request from '../request.js'

export function useOrderCustomer(deps) {
  const { _, toast, order, orderId, saving, orderActions } = deps

  const selectedCustomer = ref(null)
  const customerSuggestions = ref([])
  const searchingCustomers = ref(false)
  const createCustomerFromData = ref(false)

  const showDuplicateDialog = ref(false)
  const duplicateCustomer = ref(null)
  const pendingOrderData = ref(null)

  /**
   * Search customers for autocomplete
   */
  async function searchCustomers(event) {
    const query = event.query
    if (!query || query.length < 2) {
      customerSuggestions.value = []
      return
    }

    searchingCustomers.value = true
    try {
      const response = await request.get('/api/mgr/references/customers', { query })
      customerSuggestions.value = response.results || []
    } catch (error) {
      console.error('[OrderView] Error searching customers:', error)
      customerSuggestions.value = []
    } finally {
      searchingCustomers.value = false
    }
  }

  /**
   * Handle customer selection from autocomplete
   */
  function onCustomerSelect(event) {
    const customer = event.value
    if (customer) {
      order.value.customer_id = customer.id

      order.value.first_name = customer.first_name || order.value.first_name
      order.value.last_name = customer.last_name || order.value.last_name
      order.value.email = customer.email || order.value.email
      order.value.phone = customer.phone || order.value.phone
    }
  }

  /**
   * Clear selected customer
   */
  function clearCustomer() {
    selectedCustomer.value = null
    if (order.value) {
      order.value.customer_id = 0
    }
  }

  /**
   * Handle duplicate dialog: use existing customer
   */
  async function useDuplicateCustomer() {
    showDuplicateDialog.value = false

    if (!duplicateCustomer.value || !pendingOrderData.value) {
      return
    }

    if (pendingOrderData.value.finalize) {
      try {
        await request.put(`/api/mgr/orders/${orderId.value}`, {
          customer_id: duplicateCustomer.value.id,
        })

        createCustomerFromData.value = false
        await orderActions.finalizeOrder()
      } catch (error) {
        console.error('[OrderView] Error updating order customer:', error)
        toast.add({
          severity: 'error',
          summary: _('error'),
          detail: error.message || _('error_saving_data'),
          life: 5000,
        })
      } finally {
        pendingOrderData.value = null
        duplicateCustomer.value = null
      }
      return
    }

    selectedCustomer.value = duplicateCustomer.value
    pendingOrderData.value.customer_id = duplicateCustomer.value.id
    delete pendingOrderData.value.create_customer
    delete pendingOrderData.value.force_create_customer

    saving.value = true
    try {
      const response = await request.post('/api/mgr/orders', pendingOrderData.value)

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
      pendingOrderData.value = null
      duplicateCustomer.value = null
    }
  }

  /**
   * Handle duplicate dialog: create new customer anyway
   */
  async function createNewCustomerAnyway() {
    showDuplicateDialog.value = false
    duplicateCustomer.value = null

    if (pendingOrderData.value?.finalize) {
      pendingOrderData.value = null
      await orderActions.finalizeOrder(true)
    } else {
      await orderActions.createOrder(true)
      pendingOrderData.value = null
    }
  }

  /**
   * Handle duplicate dialog: cancel
   */
  function cancelDuplicateDialog() {
    showDuplicateDialog.value = false
    duplicateCustomer.value = null
    pendingOrderData.value = null
  }

  return {
    selectedCustomer,
    customerSuggestions,
    searchingCustomers,
    createCustomerFromData,
    showDuplicateDialog,
    duplicateCustomer,
    pendingOrderData,
    searchCustomers,
    onCustomerSelect,
    clearCustomer,
    useDuplicateCustomer,
    createNewCustomerAnyway,
    cancelDuplicateDialog,
  }
}
