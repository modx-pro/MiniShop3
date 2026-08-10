/**
 * Order loading, references, and initial/empty state for the order view.
 *
 * @param {Object} deps
 * @param {Function} deps._
 * @param {Object} deps.toast
 * @param {import('vue').Ref} deps.order
 * @param {import('vue').ComputedRef} deps.orderId
 * @param {import('vue').Ref} deps.selectedCustomer
 * @param {import('vue').ShallowRef} deps.costRecalcWarnings
 * @param {Function} deps.syncShippingPaymentBaselineFromOrder
 */
import { computed, ref } from 'vue'

import request from '../request.js'
import { parseStructuredExtraFieldValue } from '../utils/structuredExtraField.js'

export function useOrderLoad(deps) {
  const {
    _,
    toast,
    order,
    orderId,
    selectedCustomer,
    costRecalcWarnings,
    syncShippingPaymentBaselineFromOrder,
  } = deps

  const loading = ref(true)
  const products = ref([])
  const productsColumns = ref([])
  const logs = ref([])
  const statuses = ref([])
  const deliveries = ref([])
  const payments = ref([])

  const orderFields = ref([])
  const addressFields = ref([])
  const orderSections = ref([])
  const addressSections = ref([])

  const orderComboOptions = ref({})
  const addressComboOptions = ref({})

  const orderExtraFields = ref([])
  const addressExtraFields = ref([])

  /**
   * Helper to group fields by section
   */
  function groupFieldsBySection(fields, sections) {
    const sectionMap = new Map()

    for (const section of sections) {
      sectionMap.set(section.id, {
        ...section,
        fields: [],
      })
    }

    sectionMap.set(null, {
      id: null,
      label: _('ms3_model_field_no_section'),
      section_key: 'no_section',
      sort_order: 9999,
      fields: [],
    })

    for (const field of fields) {
      const sectionId = field.section_id || null
      if (sectionMap.has(sectionId)) {
        sectionMap.get(sectionId).fields.push(field)
      } else {
        sectionMap.get(null).fields.push(field)
      }
    }

    return Array.from(sectionMap.values())
      .filter(section => section.fields.length > 0)
      .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
  }

  const orderFieldsBySection = computed(() => {
    return groupFieldsBySection(orderFields.value, orderSections.value)
  })

  const addressFieldsBySection = computed(() => {
    return groupFieldsBySection(addressFields.value, addressSections.value)
  })

  /** Parse structured JSON extra fields from API (string JSON → object/array). */
  function hydrateStructuredExtraFields(fields) {
    if (!order.value || !Array.isArray(fields)) {
      return
    }

    for (const field of fields) {
      if (!field.key) {
        continue
      }
      order.value[field.key] = parseStructuredExtraFieldValue(field.xtype, order.value[field.key])
    }
  }

  /**
   * Load order data and related resources.
   */
  async function loadOrder() {
    if (!orderId.value) {
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: _('order_id_required'),
        life: 5000,
      })
      return
    }

    loading.value = true

    try {
      const response = await request.get(`/api/mgr/orders/${orderId.value}`)
      order.value = response
      syncShippingPaymentBaselineFromOrder()
      costRecalcWarnings.value = []

      await Promise.all([
        loadProducts(),
        loadProductsGridConfig(),
        loadLogs(),
        loadOrderFields(),
        loadAddressFields(),
        loadOrderExtraFields(),
        loadAddressExtraFields(),
        loadOrderCustomer(),
      ])

      hydrateStructuredExtraFields(orderExtraFields.value)
      hydrateStructuredExtraFields(addressExtraFields.value)
    } catch (error) {
      console.error('[OrderView] Error loading order:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('error_loading_data'),
        life: 5000,
      })
    } finally {
      loading.value = false
    }
  }

  /**
   * Load customer data for the order (if customer_id exists)
   */
  async function loadOrderCustomer() {
    const customerId = order.value?.customer_id
    if (!customerId || customerId === 0) {
      selectedCustomer.value = null
      return
    }

    try {
      const response = await request.get(`/api/mgr/customers/${customerId}`)
      if (response && response.id) {
        selectedCustomer.value = {
          ...response,
          display: `${response.first_name || ''} ${response.last_name || ''} (${response.email || response.phone || ''})`,
        }
      }
    } catch (error) {
      console.error('[OrderView] Error loading customer:', error)
      selectedCustomer.value = null
    }
  }

  /**
   * Load visible fields for msOrder model (includes combo options)
   */
  async function loadOrderFields() {
    try {
      const response = await request.get('/api/mgr/model-fields/visible/msOrder')
      orderFields.value = response.results || []
      orderSections.value = response.sections || []
      orderComboOptions.value = response.comboOptions || {}

      if (orderComboOptions.value.status_id) {
        statuses.value =
          orderComboOptions.value.status_id.options || orderComboOptions.value.status_id
      }
      if (orderComboOptions.value.delivery_id) {
        deliveries.value =
          orderComboOptions.value.delivery_id.options || orderComboOptions.value.delivery_id
      }
      if (orderComboOptions.value.payment_id) {
        payments.value =
          orderComboOptions.value.payment_id.options || orderComboOptions.value.payment_id
      }
    } catch (error) {
      console.error('[OrderView] Error loading order fields:', error)
      orderFields.value = []
      orderSections.value = []
      orderComboOptions.value = {}
    }
  }

  /**
   * Load visible fields for msOrderAddress model (includes combo options)
   */
  async function loadAddressFields() {
    try {
      const response = await request.get('/api/mgr/model-fields/visible/msOrderAddress')
      addressFields.value = response.results || []
      addressSections.value = response.sections || []
      addressComboOptions.value = response.comboOptions || {}
    } catch (error) {
      console.error('[OrderView] Error loading address fields:', error)
      addressFields.value = []
      addressSections.value = []
      addressComboOptions.value = {}
    }
  }

  /**
   * Load extra fields for msOrder (from Object Extension)
   */
  async function loadOrderExtraFields() {
    try {
      const response = await request.get('/api/mgr/extra-fields', { class: 'msOrder' })
      orderExtraFields.value = (response.fields || []).filter(f => f.active)
    } catch (error) {
      console.error('[OrderView] Error loading order extra fields:', error)
      orderExtraFields.value = []
    }
  }

  /**
   * Load extra fields for msOrderAddress (from Object Extension)
   */
  async function loadAddressExtraFields() {
    try {
      const response = await request.get('/api/mgr/extra-fields', { class: 'msOrderAddress' })
      addressExtraFields.value = (response.fields || []).filter(f => f.active)
    } catch (error) {
      console.error('[OrderView] Error loading address extra fields:', error)
      addressExtraFields.value = []
    }
  }

  /**
   * Load order products
   */
  async function loadProducts() {
    try {
      const response = await request.get(`/api/mgr/orders/${orderId.value}/products`)
      products.value = response.results || response || []
    } catch (error) {
      console.error('[OrderView] Error loading products:', error)
      products.value = []
    }
  }

  /**
   * Load products grid configuration
   */
  async function loadProductsGridConfig() {
    try {
      const response = await request.get('/api/mgr/grid-config/order_products')
      productsColumns.value = response.columns || []
    } catch (error) {
      console.error('[OrderView] Error loading products grid config:', error)
      productsColumns.value = getDefaultProductsColumns()
    }
  }

  /**
   * Default columns for products grid (fallback)
   */
  function getDefaultProductsColumns() {
    return [
      {
        name: 'name',
        label: _('order_product_name'),
        visible: true,
        type: 'template',
        template: '{name}',
      },
      { name: 'count', label: _('order_product_count'), visible: true, type: 'number' },
      { name: 'price', label: _('order_product_price'), visible: true, type: 'price' },
      { name: 'cost', label: _('order_product_cost'), visible: true, type: 'price' },
    ]
  }

  /**
   * Load order logs
   */
  async function loadLogs() {
    try {
      const response = await request.get(`/api/mgr/orders/${orderId.value}/logs`)
      logs.value = response.results || response || []
    } catch (error) {
      console.error('[OrderView] Error loading logs:', error)
      logs.value = []
    }
  }

  /**
   * Initialize empty order for create mode
   */
  async function initEmptyOrder() {
    loading.value = true

    try {
      order.value = {
        id: null,
        num: '',
        status_id: 1, // Draft status
        delivery_id: null,
        payment_id: null,
        customer_id: 0,
        order_comment: '',
        cart_cost: 0,
        delivery_cost: 0,
        cost: 0,
        weight: 0,
        // Address fields
        first_name: '',
        last_name: '',
        phone: '',
        email: '',
        country: '',
        index: '',
        region: '',
        city: '',
        metro: '',
        street: '',
        building: '',
        entrance: '',
        floor: '',
        room: '',
        comment: '',
        text_address: '',
      }

      await Promise.all([
        loadOrderFields(),
        loadAddressFields(),
        loadOrderExtraFields(),
        loadAddressExtraFields(),
      ])

      products.value = []
      logs.value = []
    } catch (error) {
      console.error('[OrderView] Error initializing empty order:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: _('error_loading_config'),
        life: 5000,
      })
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    products,
    productsColumns,
    logs,
    statuses,
    deliveries,
    payments,
    orderFields,
    addressFields,
    orderSections,
    addressSections,
    orderComboOptions,
    addressComboOptions,
    orderExtraFields,
    addressExtraFields,
    orderFieldsBySection,
    addressFieldsBySection,
    loadOrder,
    loadProducts,
    loadLogs,
    initEmptyOrder,
  }
}
