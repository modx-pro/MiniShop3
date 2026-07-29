/**
 * Order manager screen state, API calls, plugin tabs, and product dialogs (#339).
 */
import { useLexicon } from '@vuetools/useLexicon'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'

import request from '../request.js'
import { normalizeOrderPluginTab } from '../utils/orderPluginTab.js'
import { parseRepeaterModelValue, REPEATER_XTYPE } from '../utils/repeaterField.js'
import { useOrderFieldHelpers } from './useOrderFieldHelpers.js'
import { useOrderFormatters } from './useOrderFormatters.js'

export function useOrderView() {
  const toast = useToast()
  const confirm = useConfirm()
  const { _ } = useLexicon()

  const loading = ref(true)
  const saving = ref(false)
  const order = ref(null)
  const products = ref([])
  const productsColumns = ref([])
  const logs = ref([])
  const statuses = ref([])
  const deliveries = ref([])
  const payments = ref([])

  // Model fields configuration
  const orderFields = ref([])
  const addressFields = ref([])

  // Sections
  const orderSections = ref([])
  const addressSections = ref([])

  // Combo options from API
  const orderComboOptions = ref({})
  const addressComboOptions = ref({})

  // Extra fields (from Object Extension)
  const orderExtraFields = ref([])
  const addressExtraFields = ref([])

  // Edit product dialog state
  const editProductDialogVisible = ref(false)
  const editingProduct = ref(null)
  const editProductForm = ref({
    count: 1,
    price: 0,
    weight: 0,
  })
  const savingProduct = ref(false)

  // Options editing state
  const optionsEditMode = ref('table') // 'table' or 'json'
  const optionsTableData = ref([]) // Array of { type, key, value, isComplex, fieldValues, loadingValues }
  const optionsJsonText = ref('') // JSON string for json mode
  const optionsJsonError = ref('') // JSON parse error message

  // Product option fields (color, size, etc.)
  const productOptionFields = ref([])
  const loadingOptionFields = ref(false)

  // Add product dialog state
  const addProductDialogVisible = ref(false)
  const selectedProduct = ref(null)
  const productSuggestions = ref([])
  const searchingProducts = ref(false)
  const addProductForm = ref({
    count: 1,
    price: 0,
    weight: 0,
    options: {},
  })
  const savingNewProduct = ref(false)

  // Customer search state (for create mode)
  const selectedCustomer = ref(null)
  const customerSuggestions = ref([])
  const searchingCustomers = ref(false)
  const createCustomerFromData = ref(false)

  // Order tabs: built-ins (info/products/address/history) + MS3OrderTabsRegistry plugin tabs
  const orderActiveTab = ref('info')
  const pluginTabs = ref([])
  /** ExtJS plugin panels mounted lazily per tab key; destroyed in onBeforeUnmount */
  const mountedExtPluginComponents = ref({})

  // Duplicate customer dialog state
  const showDuplicateDialog = ref(false)
  const duplicateCustomer = ref(null)
  const pendingOrderData = ref(null)

  // ms3 is a global (declared with `let`), not window.ms3
  const orderId = computed(() => {
    const ms3Config = typeof ms3 !== 'undefined' ? ms3.config : null
    const configId = ms3Config?.order_id
    const rawId = configId || new URLSearchParams(window.location.search).get('id')
    if (rawId === 'new') {
      return 'new'
    }
    return parseInt(rawId) || 0
  })

  const isCreateMode = computed(() => {
    return orderId.value === 'new' || orderId.value === 0
  })

  const managerConfig = computed(() => {
    return typeof ms3 !== 'undefined' ? ms3.config : {}
  })

  /** Fixed positions 0–3; must stay in sync with RESERVED_ORDER_TAB_KEYS in orderPluginTab.js */
  const builtInOrderTabs = computed(() => [
    { key: 'info', title: _('order_info'), position: 0, hideOnCreate: false, kind: 'builtin' },
    { key: 'products', title: _('order_products'), position: 1, hideOnCreate: true, kind: 'builtin' },
    { key: 'address', title: _('order_address'), position: 2, hideOnCreate: false, kind: 'builtin' },
    { key: 'history', title: _('order_history'), position: 3, hideOnCreate: true, kind: 'builtin' },
  ])

  /** Built-in + plugin tabs, sorted by `position`; respects hideOnCreate per tab */
  const orderTabsConfig = computed(() => {
    const builtIn = builtInOrderTabs.value.filter(t => !(t.hideOnCreate && isCreateMode.value))
    const plugins = pluginTabs.value
      .filter(t => !(t.hideOnCreate && isCreateMode.value))
      .map(t => ({ ...t, kind: 'plugin' }))
    return [...builtIn, ...plugins].sort((a, b) => (a.position ?? 100) - (b.position ?? 100))
  })

  // Draft status ID (typically 1)
  const draftStatusId = computed(() => {
    const ms3Config = typeof ms3 !== 'undefined' ? ms3.config : null
    return ms3Config?.status_draft || 1
  })

  // Check if order is in draft status
  const isDraft = computed(() => {
    if (!order.value) return false
    return parseInt(order.value.status_id) === parseInt(draftStatusId.value)
  })

  // Finalize state
  const finalizing = ref(false)

  // Order cost recalculation (#212): baseline delivery/payment after load/save
  const savedBaselineDeliveryId = ref(0)
  const savedBaselinePaymentId = ref(0)
  const recalculatingCost = ref(false)
  /** @type {import('vue').ShallowRef<string[]>} Warning codes from last recalculate API */
  const costRecalcWarnings = shallowRef([])
  /** Hint keys for cost recalculation warnings (ManagerOrderCostRecalculator). */
  const COST_RECALC_WARNING_HINTS = Object.freeze({
    delivery_manual_required: 'order_cost_recalc_delivery_manual_hint',
    payment_manual_required: 'order_cost_recalc_payment_manual_hint',
    delivery_provider_error: 'order_cost_recalc_delivery_manual_hint',
    payment_provider_error: 'order_cost_recalc_payment_manual_hint',
  })
  const manualDeliveryCost = ref(null)

  function syncShippingPaymentBaselineFromOrder() {
    if (!order.value || isCreateMode.value) {
      return
    }
    savedBaselineDeliveryId.value = Number.parseInt(order.value.delivery_id, 10) || 0
    savedBaselinePaymentId.value = Number.parseInt(order.value.payment_id, 10) || 0
  }

  const hasUnsavedShippingPaymentChanges = computed(() => {
    if (isCreateMode.value || !order.value) {
      return false
    }
    const d = Number.parseInt(order.value.delivery_id, 10) || 0
    const p = Number.parseInt(order.value.payment_id, 10) || 0

    return d !== savedBaselineDeliveryId.value || p !== savedBaselinePaymentId.value
  })

  /**
   * Group fields by sections
   */
  const orderFieldsBySection = computed(() => {
    return groupFieldsBySection(orderFields.value, orderSections.value)
  })

  const addressFieldsBySection = computed(() => {
    return groupFieldsBySection(addressFields.value, addressSections.value)
  })

  /**
   * Helper to group fields by section
   */
  function groupFieldsBySection(fields, sections) {
    // Create section map
    const sectionMap = new Map()

    // Add sections in order
    for (const section of sections) {
      sectionMap.set(section.id, {
        ...section,
        fields: [],
      })
    }

    // Add "no section" group for fields without section
    sectionMap.set(null, {
      id: null,
      label: _('ms3_model_field_no_section'),
      section_key: 'no_section',
      sort_order: 9999,
      fields: [],
    })

    // Assign fields to sections
    for (const field of fields) {
      const sectionId = field.section_id || null
      if (sectionMap.has(sectionId)) {
        sectionMap.get(sectionId).fields.push(field)
      } else {
        // If section doesn't exist, put in "no section"
        sectionMap.get(null).fields.push(field)
      }
    }

    // Convert to array and filter out empty sections
    return Array.from(sectionMap.values())
      .filter(section => section.fields.length > 0)
      .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
  }

  /** Parse repeater extra field values from API (string JSON → array). */
  function hydrateRepeaterExtraFields(fields) {
    if (!order.value || !Array.isArray(fields)) {
      return
    }

    for (const field of fields) {
      if (field.xtype === REPEATER_XTYPE && field.key) {
        order.value[field.key] = parseRepeaterModelValue(order.value[field.key])
      }
    }
  }

  /** Load order data and related resources. */
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

      hydrateRepeaterExtraFields(orderExtraFields.value)
      hydrateRepeaterExtraFields(addressExtraFields.value)
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
      // Combo options are now returned with visible fields
      orderComboOptions.value = response.comboOptions || {}

      // Populate legacy refs for backward compatibility (extract options from new format)
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
      // Combo options are now returned with visible fields
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

  const { formatDate, formatPrice, getFieldWidthClass } = useOrderFormatters()

  const fieldHelpers = useOrderFieldHelpers({
    formatDate,
    formatPrice,
    order,
    orderComboOptions,
    addressComboOptions,
    statuses,
    deliveries,
    payments,
    _,
  })

  /**
   * Handle product action (edit, delete)
   */
  function handleProductAction(action, data) {
    switch (action.handler) {
      case 'edit':
        editProduct(data)
        break
      case 'delete':
        deleteProduct(data)
        break
      default:
        console.warn('[OrderView] Unknown action handler:', action.handler)
    }
  }

  /**
   * Open edit product dialog
   */
  async function editProduct(product) {
    editingProduct.value = product
    editProductForm.value = {
      count: product.count || 1,
      price: product.price || 0,
      weight: product.weight || 0,
    }

    // Load product option fields if not already loaded
    if (productOptionFields.value.length === 0) {
      await loadProductOptionFields()
    }

    // Initialize options
    await initOptionsFromProduct(product.options)
    optionsEditMode.value = 'table'
    optionsJsonError.value = ''

    editProductDialogVisible.value = true
  }

  /**
   * Load product option fields (color, size, etc.)
   */
  async function loadProductOptionFields() {
    loadingOptionFields.value = true
    try {
      const response = await request.get('/api/mgr/references/product-option-fields')
      productOptionFields.value = response.fields || []
    } catch (error) {
      console.error('[OrderView] Error loading product option fields:', error)
      productOptionFields.value = []
    } finally {
      loadingOptionFields.value = false
    }
  }

  /**
   * Initialize options data from product
   */
  async function initOptionsFromProduct(options) {
    let parsed = {}

    if (options) {
      if (typeof options === 'string') {
        try {
          parsed = JSON.parse(options)
        } catch {
          parsed = {}
        }
      } else if (typeof options === 'object') {
        parsed = options
      }
    }

    // Convert to table format with type detection
    const tableData = []
    const fieldRowsToLoad = []
    for (const [key, value] of Object.entries(parsed)) {
      const isProductField = productOptionFields.value.some(f => f.name === key)
      const row = {
        type: isProductField ? 'field' : 'custom',
        key,
        value: isComplexValue(value) ? JSON.stringify(value, null, 2) : value,
        isComplex: isComplexValue(value),
        fieldValues: [],
        loadingValues: false,
      }

      if (isProductField) {
        fieldRowsToLoad.push(row)
      }

      tableData.push(row)
    }

    await Promise.all(fieldRowsToLoad.map(row => loadFieldValuesForRow(row)))

    optionsTableData.value = tableData

    // Store JSON text
    optionsJsonText.value = Object.keys(parsed).length > 0 ? JSON.stringify(parsed, null, 2) : ''
  }

  /**
   * Load field values for a specific row
   */
  async function loadFieldValuesForRow(row) {
    if (!row.key || row.type !== 'field') return

    // Get product_id from editing product
    const productId = editingProduct.value?.product_id
    if (!productId) {
      console.warn('[OrderView] No product_id found for loading field values')
      row.fieldValues = []
      return
    }

    row.loadingValues = true
    try {
      const response = await request.get('/api/mgr/references/product-field-values', {
        field: row.key,
        product_id: productId,
      })
      row.fieldValues = response.values || []
    } catch (error) {
      console.error('[OrderView] Error loading field values:', error)
      row.fieldValues = []
    } finally {
      row.loadingValues = false
    }
  }

  /**
   * Handle option type change (field/custom)
   */
  async function onOptionTypeChange(row) {
    if (row.type === 'field') {
      // Reset to first available field if key is not a valid field
      const isValidField = productOptionFields.value.some(f => f.name === row.key)
      if (!isValidField && productOptionFields.value.length > 0) {
        row.key = productOptionFields.value[0].name
      }
      row.value = ''
      row.isComplex = false
      await loadFieldValuesForRow(row)
    } else {
      // Custom type - clear field values
      row.fieldValues = []
      row.value = ''
      row.isComplex = false
    }
    syncTableToJson()
  }

  /**
   * Handle field selection change
   */
  async function onFieldKeyChange(row) {
    row.value = ''
    row.fieldValues = []
    await loadFieldValuesForRow(row)
    syncTableToJson()
  }

  /**
   * Check if value is complex (array or object)
   */
  function isComplexValue(value) {
    return Array.isArray(value) || (typeof value === 'object' && value !== null)
  }

  /**
   * Add new option row
   */
  function addOptionRow() {
    optionsTableData.value.push({
      type: 'custom',
      key: '',
      value: '',
      isComplex: false,
      fieldValues: [],
      loadingValues: false,
    })
  }

  /**
   * Remove option row
   */
  function removeOptionRow(index) {
    optionsTableData.value.splice(index, 1)
    syncTableToJson()
  }

  function buildOptionsObjectFromTable() {
    const obj = {}
    for (const row of optionsTableData.value) {
      if (!row.key.trim()) {
        continue
      }
      if (row.isComplex) {
        try {
          obj[row.key] = JSON.parse(row.value)
        } catch {
          obj[row.key] = row.value
        }
      } else {
        obj[row.key] = row.value
      }
    }
    return obj
  }

  /** Sync table data to JSON text. */
  function syncTableToJson() {
    const obj = buildOptionsObjectFromTable()
    optionsJsonText.value = Object.keys(obj).length > 0 ? JSON.stringify(obj, null, 2) : ''
    optionsJsonError.value = ''
  }

  /**
   * Sync JSON text to table data
   */
  function syncJsonToTable() {
    optionsJsonError.value = ''

    if (!optionsJsonText.value.trim()) {
      optionsTableData.value = []
      return true
    }

    try {
      const parsed = JSON.parse(optionsJsonText.value)
      if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
        optionsJsonError.value = _('options_json_must_be_object')
        return false
      }

      optionsTableData.value = Object.entries(parsed).map(([key, value]) => ({
        key,
        value: isComplexValue(value) ? JSON.stringify(value, null, 2) : value,
        isComplex: isComplexValue(value),
      }))
      return true
    } catch (e) {
      optionsJsonError.value = _('options_json_invalid') + ': ' + e.message
      return false
    }
  }

  /**
   * Switch options edit mode
   */
  function switchOptionsMode(mode) {
    if (mode === optionsEditMode.value) return

    if (mode === 'json') {
      // Switching to JSON mode - sync table to JSON
      syncTableToJson()
    } else {
      // Switching to table mode - sync JSON to table
      if (!syncJsonToTable()) {
        // JSON is invalid, stay in JSON mode
        return
      }
    }

    optionsEditMode.value = mode
  }

  /**
   * Get options object for saving
   */
  function getOptionsForSave() {
    if (optionsEditMode.value === 'json') {
      if (!optionsJsonText.value.trim()) return null
      try {
        return JSON.parse(optionsJsonText.value)
      } catch {
        return null
      }
    }
    const obj = buildOptionsObjectFromTable()
    return Object.keys(obj).length > 0 ? obj : null
  }

  /**
   * Save edited product
   */
  async function saveEditedProduct() {
    if (!editingProduct.value) return

    // Validate JSON mode if active
    if (optionsEditMode.value === 'json' && optionsJsonText.value.trim()) {
      try {
        JSON.parse(optionsJsonText.value)
      } catch (e) {
        optionsJsonError.value = _('options_json_invalid') + ': ' + e.message
        return
      }
    }

    savingProduct.value = true

    try {
      const data = {
        ...editProductForm.value,
        options: getOptionsForSave(),
      }

      await request.put(`/api/mgr/orders/${orderId.value}/products/${editingProduct.value.id}`, data)

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('order_product_saved'),
        life: 3000,
      })

      editProductDialogVisible.value = false

      // Reload products and order to update totals
      await Promise.all([loadProducts(), loadOrder()])
    } catch (error) {
      console.error('[OrderView] Error saving product:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('error_saving_data'),
        life: 5000,
      })
    } finally {
      savingProduct.value = false
    }
  }

  /**
   * Cancel product editing
   */
  function cancelEditProduct() {
    editProductDialogVisible.value = false
    editingProduct.value = null
  }

  function productLineCost(form) {
    return (form.count || 0) * (form.price || 0)
  }

  const editedProductCost = computed(() => productLineCost(editProductForm.value))

  const newProductCost = computed(() => productLineCost(addProductForm.value))

  /**
   * Open add product dialog
   */
  function openAddProductDialog() {
    selectedProduct.value = null
    productSuggestions.value = []
    addProductForm.value = {
      count: 1,
      price: 0,
      weight: 0,
      options: {},
    }
    addProductDialogVisible.value = true
  }

  /**
   * Search products for autocomplete
   */
  async function searchProducts(event) {
    const query = event.query
    if (!query || query.length < 2) {
      productSuggestions.value = []
      return
    }

    searchingProducts.value = true
    try {
      const response = await request.get('/api/mgr/references/products', { query })
      productSuggestions.value = response.results || []
    } catch (error) {
      console.error('[OrderView] Error searching products:', error)
      productSuggestions.value = []
    } finally {
      searchingProducts.value = false
    }
  }

  /**
   * Handle product selection from autocomplete
   */
  function onProductSelect(event) {
    const product = event.value
    if (product) {
      addProductForm.value.price = product.price || 0
      addProductForm.value.weight = product.weight || 0
    }
  }

  /**
   * Save new product to order
   */
  async function saveNewProduct() {
    if (!selectedProduct.value || !selectedProduct.value.id) {
      toast.add({
        severity: 'warn',
        summary: _('warning'),
        detail: _('order_product_select'),
        life: 3000,
      })
      return
    }

    savingNewProduct.value = true
    try {
      const data = {
        product_id: selectedProduct.value.id,
        count: addProductForm.value.count || 1,
        price: addProductForm.value.price || 0,
        weight: addProductForm.value.weight || 0,
        options: addProductForm.value.options || {},
      }

      await request.post(`/api/mgr/orders/${orderId.value}/products`, data)

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('order_product_added'),
        life: 3000,
      })

      addProductDialogVisible.value = false

      // Reload products and order to update totals
      await Promise.all([loadProducts(), loadOrder()])
    } catch (error) {
      console.error('[OrderView] Error adding product:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('error'),
        life: 5000,
      })
    } finally {
      savingNewProduct.value = false
    }
  }

  /**
   * Cancel add product
   */
  function cancelAddProduct() {
    addProductDialogVisible.value = false
    selectedProduct.value = null
  }

  /**
   * Delete product from order with confirmation
   */
  function deleteProduct(product) {
    // Check if this is the last product
    if (products.value.length <= 1) {
      toast.add({
        severity: 'warn',
        summary: _('warning'),
        detail: _('order_product_cannot_delete_last'),
        life: 5000,
      })
      return
    }

    confirm.require({
      message: _('order_product_delete_confirm'),
      header: _('confirm_delete'),
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: _('yes'),
      rejectLabel: _('no'),
      acceptClass: 'p-button-danger',
      accept: async () => {
        try {
          await request.delete(`/api/mgr/orders/${orderId.value}/products/${product.id}`)

          toast.add({
            severity: 'success',
            summary: _('success'),
            detail: _('order_product_deleted'),
            life: 3000,
          })

          // Reload products and order to update totals
          await Promise.all([loadProducts(), loadOrder()])
        } catch (error) {
          console.error('[OrderView] Error deleting product:', error)
          toast.add({
            severity: 'error',
            summary: _('error'),
            detail: error.message || _('error_deleting_data'),
            life: 5000,
          })
        }
      },
    })
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
   * Берём поля заказа из ответа пересчёта без служебных ключей breakdown/warnings.
   * @param {Record<string, unknown>|null|undefined} raw
   * @returns {{ orderPayload: Record<string, unknown>, warnings: string[] }}
   */
  function stripRecalculateCostMeta(raw) {
    if (!raw || typeof raw !== 'object') {
      return { orderPayload: {}, warnings: [] }
    }
    const merged = /** @type {Record<string, unknown>} */ ({ ...raw })
    delete merged.breakdown
    const w = merged.warnings
    delete merged.warnings
    return {
      orderPayload: merged,
      warnings: Array.isArray(w) ? w.map(String) : [],
    }
  }

  /**
   * Пересчитать стоимость заказа (Manager API, #212).
   * @param {{ mode?: string, manual_delivery_cost?: number }} opts
   */
  async function recalculateOrderCost(opts = {}) {
    if (isCreateMode.value || !orderId.value || orderId.value === 'new') {
      return
    }

    if (saving.value || recalculatingCost.value) {
      return
    }

    recalculatingCost.value = true
    costRecalcWarnings.value = []

    try {
      const body = {
        mode: opts.mode ?? 'auto',
      }
      if (opts.manual_delivery_cost !== undefined && opts.manual_delivery_cost !== null) {
        body.manual_delivery_cost = opts.manual_delivery_cost
      }

      const raw = await request.post(`/api/mgr/orders/${orderId.value}/recalculate-cost`, body)
      const { orderPayload, warnings } = stripRecalculateCostMeta(raw)
      order.value = { ...(order.value || {}), ...orderPayload }
      costRecalcWarnings.value = warnings
      syncShippingPaymentBaselineFromOrder()

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('order_cost_recalculated'),
        life: 3000,
      })
    } catch (error) {
      console.error('[OrderView] Error recalculateCost:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('error_saving_data'),
        life: 5000,
      })
    } finally {
      recalculatingCost.value = false
    }
  }

  /**
   * Save order
   */
  async function saveOrder() {
    if (recalculatingCost.value || saving.value) {
      return
    }

    saving.value = true

    try {
      // Collect all editable order fields
      const orderData = {}
      for (const field of orderFields.value) {
        if (fieldHelpers.isFieldEditable(field.name) && order.value[field.name] !== undefined) {
          orderData[field.name] = order.value[field.name]
        }
      }

      // Collect all editable address fields
      for (const field of addressFields.value) {
        if (order.value[field.name] !== undefined) {
          orderData[field.name] = order.value[field.name]
        }
      }

      // Collect extra fields for msOrder (from Object Extension)
      for (const field of orderExtraFields.value) {
        if (order.value[field.key] !== undefined) {
          orderData[field.key] = order.value[field.key]
        }
      }

      // Collect extra fields for msOrderAddress (from Object Extension)
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
      // Build request data
      const requestData = {}

      // If create customer checkbox is checked and no customer linked
      if (createCustomerFromData.value && !order.value.customer_id) {
        requestData.create_customer = true
        if (forceCreateCustomer) {
          requestData.force_create_customer = true
        }
      }

      const response = await request.post(`/api/mgr/orders/${orderId.value}/finalize`, requestData)

      // Check if duplicate customer was found
      if (response.duplicate_found) {
        // Store for later and show dialog
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

      // Reload order to get updated status and order number
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

      // Show each validation error as separate toast
      // Response structure: error.data.object.errors contains array of field names
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
        // Fallback to general error message
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
   * Initialize empty order for create mode
   */
  async function initEmptyOrder() {
    loading.value = true

    try {
      // Initialize empty order object
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

      // Load field configurations and combo options
      await Promise.all([
        loadOrderFields(),
        loadAddressFields(),
        loadOrderExtraFields(),
        loadAddressExtraFields(),
      ])

      // Initialize empty products and logs
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

  /**
   * Collect order data from form
   */
  function collectOrderData() {
    const orderData = {}

    // Order fields
    for (const field of orderFields.value) {
      if (order.value[field.name] !== undefined && order.value[field.name] !== null) {
        orderData[field.name] = order.value[field.name]
      }
    }

    // Address fields
    for (const field of addressFields.value) {
      if (order.value[field.name] !== undefined && order.value[field.name] !== null) {
        orderData[field.name] = order.value[field.name]
      }
    }

    // Extra fields
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

    // If customer was selected, add customer_id
    if (selectedCustomer.value?.id) {
      orderData.customer_id = selectedCustomer.value.id
    }

    // If create customer checkbox is checked and no customer selected
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
    // If creating customer, need at least email or phone
    if (orderData.create_customer) {
      const email = (orderData.email || '').trim()
      const phone = (orderData.phone || '').trim()

      if (!email && !phone) {
        return _('ms3_customer_validation_email_or_phone')
      }

      // Basic email validation
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

      // Add force flag if retrying after duplicate found
      if (forceCreateCustomer) {
        orderData.force_create_customer = true
      }

      // Validate customer data if creating customer
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

      // Create order via API
      const response = await request.post('/api/mgr/orders', orderData)

      // Check if duplicate customer was found
      if (response.duplicate_found) {
        // Store data for later and show dialog
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

      // Redirect to edit the created order
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

  /**
   * Handle duplicate dialog: use existing customer
   */
  async function useDuplicateCustomer() {
    showDuplicateDialog.value = false

    if (!duplicateCustomer.value || !pendingOrderData.value) {
      return
    }

    // Check if this is from finalize or create
    if (pendingOrderData.value.finalize) {
      // For finalize: update order's customer_id and retry finalize
      try {
        // Update order with existing customer
        await request.put(`/api/mgr/orders/${orderId.value}`, {
          customer_id: duplicateCustomer.value.id,
        })

        // Disable create customer flag and finalize
        createCustomerFromData.value = false
        await finalizeOrder()
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

    // For create: set existing customer and create order without create_customer flag
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

    // Check if this is from finalize or create
    if (pendingOrderData.value?.finalize) {
      // Retry finalize with force flag
      pendingOrderData.value = null
      await finalizeOrder(true)
    } else {
      // Retry create with force flag
      await createOrder(true)
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
      // Set customer_id in order
      order.value.customer_id = customer.id

      // Fill address fields from customer data
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
    // Clear customer_id in order
    if (order.value) {
      order.value.customer_id = 0
    }
  }

  /**
   * Go back to orders list
   */
  function goBack() {
    window.location.href = '?a=mgr/orders&namespace=minishop3'
  }

  /**
   * Registers a plugin tab (called by window.MS3OrderTabsRegistry or tests).
   * Validation lives in normalizeOrderPluginTab().
   */
  function registerPluginTab(tabData) {
    const normalized = normalizeOrderPluginTab(tabData)
    if (!normalized.ok) {
      console.error(`[OrderView] ${normalized.reason}`, tabData)
      return false
    }
    const exists = pluginTabs.value.some(t => t.key === normalized.tab.key)
    if (exists) {
      console.warn(`[OrderView] Tab with key "${normalized.tab.key}" already registered`)
      return false
    }
    pluginTabs.value.push(normalized.tab)
    return true
  }

  /**
   * Props passed to Vue plugin tab components (same contract as ExtJS tabs below).
   * User `tab.props` is spread first; core fields override name collisions intentionally.
   */
  function pluginVueProps(tab) {
    return {
      ...(tab.props || {}),
      orderId: orderId.value,
      order: order.value,
      config: managerConfig.value,
      isCreateMode: isCreateMode.value,
    }
  }

  /** Waits for TabPanel DOM element via MutationObserver (consistent with other entry points). */
  function waitForOrderTabElement(id, callback) {
    const element = document.getElementById(id)
    if (element) {
      callback(element)
      return
    }
    const observer = new MutationObserver(() => {
      const el = document.getElementById(id)
      if (el) {
        observer.disconnect()
        callback(el)
      }
    })
    observer.observe(document.body, { childList: true, subtree: true })
  }

  /**
   * Lazy-mounts an ExtJS panel into the plugin tab container.
   * Merge order: xtype/renderTo/width, then extConfig, then core fields (order, orderId, config, isCreateMode).
   *
   * The Ext instance is created once per tab key when the user first selects the tab. Later changes to
   * Vue’s `order` (after API load, save, etc.) are not pushed into Ext — plugin panels must implement
   * their own listeners, polling, or `load` hooks if they need live data.
   */
  function mountExtJSOrderPlugin(tab) {
    if (mountedExtPluginComponents.value[tab.key]) {
      return
    }
    const containerId = `ms3-order-tab-${tab.key}`
    waitForOrderTabElement(containerId, container => {
      try {
        if (typeof Ext === 'undefined') {
          console.error('[OrderView] Ext is not defined')
          return
        }
        const extComponent = Ext.create({
          xtype: tab.xtype,
          renderTo: container,
          width: '100%',
          ...tab.extConfig,
          order: order.value,
          orderId: orderId.value,
          config: managerConfig.value,
          isCreateMode: isCreateMode.value,
        })
        mountedExtPluginComponents.value[tab.key] = extComponent
      } catch (error) {
        console.error(`[OrderView] Failed to mount ExtJS order tab ${tab.key}:`, error)
      }
    })
  }

  function destroyPluginExtComponents() {
    Object.keys(mountedExtPluginComponents.value).forEach(key => {
      const component = mountedExtPluginComponents.value[key]
      if (component && typeof component.destroy === 'function') {
        try {
          component.destroy()
        } catch (e) {
          console.warn(`[OrderView] Error destroying plugin ExtJS component ${key}:`, e)
        }
      }
    })
    mountedExtPluginComponents.value = {}
  }

  watch(
    () => orderTabsConfig.value.map(t => t.key),
    keys => {
      if (keys.length === 0) return
      if (!keys.includes(orderActiveTab.value)) {
        orderActiveTab.value = keys[0]
      }
    },
    { immediate: true }
  )

  watch(orderActiveTab, () => {
    nextTick(() => {
      const tab = orderTabsConfig.value.find(t => t.key === orderActiveTab.value)
      if (
        tab &&
        tab.kind === 'plugin' &&
        tab.type === 'extjs' &&
        tab.xtype &&
        !mountedExtPluginComponents.value[tab.key]
      ) {
        mountExtJSOrderPlugin(tab)
      }
    })
  })

  onBeforeUnmount(() => {
    destroyPluginExtComponents()
    if (window.MS3OrderTabsRegistry) {
      window.MS3OrderTabsRegistry._onUnmounted()
    }
  })

  onMounted(async () => {
    if (isCreateMode.value) {
      await initEmptyOrder()
    } else {
      await loadOrder()
    }
  })

  const orderContext = {
    order,
    saving,
    isDraft,
    isCreateMode,
    finalizing,
    customerSuggestions,
    searchingCustomers,
    ...fieldHelpers,
    formatDate,
    formatPrice,
    getFieldWidthClass,
    saveOrder,
    createOrder,
    goBack,
    confirmFinalizeOrder,
    handleProductAction,
    openAddProductDialog,
    searchCustomers,
    onCustomerSelect,
    clearCustomer,
    recalculateOrderCost,
    recalculatingCost,
    costRecalcWarnings,
    manualDeliveryCost,
    hasUnsavedShippingPaymentChanges,
  }
  return {
    orderContext,
    registerPluginTab,
    pluginVueProps,

    loading,
    saving,
    order,
    products,
    productsColumns,
    logs,
    orderFieldsBySection,
    orderExtraFields,
    orderActiveTab,
    orderTabsConfig,
    isCreateMode,
    selectedCustomer,
    createCustomerFromData,
    addressFieldsBySection,
    addressExtraFields,
    goBack,
    formatPrice,

    editProductDialogVisible,
    editingProduct,
    editProductForm,
    savingProduct,
    optionsEditMode,
    optionsTableData,
    productOptionFields,
    optionsJsonText,
    optionsJsonError,
    editedProductCost,
    switchOptionsMode,
    onOptionTypeChange,
    onFieldKeyChange,
    syncTableToJson,
    addOptionRow,
    removeOptionRow,
    saveEditedProduct,
    cancelEditProduct,

    addProductDialogVisible,
    selectedProduct,
    productSuggestions,
    searchingProducts,
    addProductForm,
    savingNewProduct,
    newProductCost,
    searchProducts,
    onProductSelect,
    saveNewProduct,
    cancelAddProduct,

    showDuplicateDialog,
    duplicateCustomer,
    cancelDuplicateDialog,
    useDuplicateCustomer,
    createNewCustomerAnyway,
  }
}
