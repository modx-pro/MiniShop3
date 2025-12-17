<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Fieldset from 'primevue/fieldset'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import AutoComplete from 'primevue/autocomplete'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import request from '../request.js'
import { useLexicon } from '../composables/useLexicon.js'

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
  weight: 0
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
  options: {}
})
const savingNewProduct = ref(false)

// Customer search state (for create mode)
const selectedCustomer = ref(null)
const customerSuggestions = ref([])
const searchingCustomers = ref(false)
const createCustomerFromData = ref(false)

// Duplicate customer dialog state
const showDuplicateDialog = ref(false)
const duplicateCustomer = ref(null)
const pendingOrderData = ref(null)

const orderId = computed(() => {
  // Try to get from ms3.config first
  if (window.ms3?.config?.order_id) {
    const configId = window.ms3.config.order_id
    // Return 'new' string as is, otherwise parse as int
    return configId === 'new' ? 'new' : parseInt(configId) || 0
  }
  // Fallback: get from URL
  const urlParams = new URLSearchParams(window.location.search)
  const urlId = urlParams.get('id')
  // Return 'new' string as is, otherwise parse as int
  return urlId === 'new' ? 'new' : parseInt(urlId) || 0
})

const isCreateMode = computed(() => {
  return orderId.value === 'new' || orderId.value === 0
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
      fields: []
    })
  }

  // Add "no section" group for fields without section
  sectionMap.set(null, {
    id: null,
    label: _('ms3_model_field_no_section'),
    section_key: 'no_section',
    sort_order: 9999,
    fields: []
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

/**
 * Load order data
 */
async function loadOrder() {
  if (!orderId.value) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: _('order_id_required'),
      life: 5000
    })
    return
  }

  loading.value = true

  try {
    const response = await request.get(`/api/mgr/orders/${orderId.value}`)
    order.value = response

    await Promise.all([
      loadProducts(),
      loadProductsGridConfig(),
      loadLogs(),
      loadOrderFields(),
      loadAddressFields(),
      loadOrderExtraFields(),
      loadAddressExtraFields()
    ])
  } catch (error) {
    console.error('[OrderView] Error loading order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_loading_data'),
      life: 5000
    })
  } finally {
    loading.value = false
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
      statuses.value = orderComboOptions.value.status_id.options || orderComboOptions.value.status_id
    }
    if (orderComboOptions.value.delivery_id) {
      deliveries.value = orderComboOptions.value.delivery_id.options || orderComboOptions.value.delivery_id
    }
    if (orderComboOptions.value.payment_id) {
      payments.value = orderComboOptions.value.payment_id.options || orderComboOptions.value.payment_id
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
    { name: 'name', label: _('order_product_name'), visible: true, type: 'template', template: '{name}' },
    { name: 'count', label: _('order_product_count'), visible: true, type: 'number' },
    { name: 'price', label: _('order_product_price'), visible: true, type: 'price' },
    { name: 'cost', label: _('order_product_cost'), visible: true, type: 'price' }
  ]
}

/**
 * Render product field value based on column type
 */
function renderProductField(data, column) {
  if (column.template) {
    return column.template.replace(/\{(\w+)\}/g, (match, key) => data[key] ?? '')
  }
  return data[column.name]
}

/**
 * Format options for display
 */
function formatOptions(options) {
  if (!options) return []
  if (typeof options === 'string') {
    try {
      options = JSON.parse(options)
    } catch {
      return []
    }
  }
  if (Array.isArray(options)) {
    return options.map(opt => typeof opt === 'object' ? `${opt.key}: ${opt.value}` : opt)
  }
  if (typeof options === 'object') {
    return Object.entries(options).map(([key, value]) => `${key}: ${value}`)
  }
  return []
}

/**
 * Get product link URL
 */
function getProductLink(data, column) {
  if (!column.link?.condition || !data[column.link.condition]) return null
  return column.link.url.replace(/\{(\w+)\}/g, (match, key) => data[key] ?? '')
}

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
    weight: product.weight || 0
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
  for (const [key, value] of Object.entries(parsed)) {
    const isProductField = productOptionFields.value.some(f => f.name === key)
    const row = {
      type: isProductField ? 'field' : 'custom',
      key,
      value: isComplexValue(value) ? JSON.stringify(value, null, 2) : value,
      isComplex: isComplexValue(value),
      fieldValues: [],
      loadingValues: false
    }

    // If it's a product field, load its values
    if (isProductField) {
      await loadFieldValuesForRow(row)
    }

    tableData.push(row)
  }

  optionsTableData.value = tableData

  // Store JSON text
  optionsJsonText.value = Object.keys(parsed).length > 0
    ? JSON.stringify(parsed, null, 2)
    : ''
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
      product_id: productId
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
async function onOptionTypeChange(row, index) {
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
    loadingValues: false
  })
}

/**
 * Remove option row
 */
function removeOptionRow(index) {
  optionsTableData.value.splice(index, 1)
  syncTableToJson()
}

/**
 * Sync table data to JSON text
 */
function syncTableToJson() {
  const obj = {}
  for (const row of optionsTableData.value) {
    if (row.key.trim()) {
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
  }
  optionsJsonText.value = Object.keys(obj).length > 0
    ? JSON.stringify(obj, null, 2)
    : ''
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
      isComplex: isComplexValue(value)
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
  } else {
    const obj = {}
    for (const row of optionsTableData.value) {
      if (row.key.trim()) {
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
    }
    return Object.keys(obj).length > 0 ? obj : null
  }
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
      options: getOptionsForSave()
    }

    await request.put(
      `/api/mgr/orders/${orderId.value}/products/${editingProduct.value.id}`,
      data
    )

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('order_product_saved'),
      life: 3000
    })

    editProductDialogVisible.value = false

    // Reload products and order to update totals
    await Promise.all([
      loadProducts(),
      loadOrder()
    ])
  } catch (error) {
    console.error('[OrderView] Error saving product:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000
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

/**
 * Computed cost for edited product
 */
const editedProductCost = computed(() => {
  return (editProductForm.value.count || 0) * (editProductForm.value.price || 0)
})

/**
 * Computed cost for new product
 */
const newProductCost = computed(() => {
  return (addProductForm.value.count || 0) * (addProductForm.value.price || 0)
})

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
    options: {}
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
      life: 3000
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
      options: addProductForm.value.options || {}
    }

    await request.post(`/api/mgr/orders/${orderId.value}/products`, data)

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('order_product_added'),
      life: 3000
    })

    addProductDialogVisible.value = false

    // Reload products and order to update totals
    await Promise.all([
      loadProducts(),
      loadOrder()
    ])
  } catch (error) {
    console.error('[OrderView] Error adding product:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error'),
      life: 5000
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
      life: 5000
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
        await request.delete(
          `/api/mgr/orders/${orderId.value}/products/${product.id}`
        )

        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('order_product_deleted'),
          life: 3000
        })

        // Reload products and order to update totals
        await Promise.all([
          loadProducts(),
          loadOrder()
        ])
      } catch (error) {
        console.error('[OrderView] Error deleting product:', error)
        toast.add({
          severity: 'error',
          summary: _('error'),
          detail: error.message || _('error_deleting_data'),
          life: 5000
        })
      }
    }
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
 * Load statuses list
 */
async function loadStatuses() {
  try {
    const response = await request.get('/api/mgr/statuses')
    statuses.value = (response.results || response || []).map(s => ({
      value: s.id,
      label: s.name
    }))
  } catch (error) {
    console.error('[OrderView] Error loading statuses:', error)
    statuses.value = []
  }
}

/**
 * Load deliveries list
 */
async function loadDeliveries() {
  try {
    const response = await request.get('/api/mgr/deliveries')
    deliveries.value = (response.results || response || []).map(d => ({
      value: d.id,
      label: d.name
    }))
  } catch (error) {
    console.error('[OrderView] Error loading deliveries:', error)
    deliveries.value = []
  }
}

/**
 * Load payments list
 */
async function loadPayments() {
  try {
    const response = await request.get('/api/mgr/payments')
    payments.value = (response.results || response || []).map(p => ({
      value: p.id,
      label: p.name
    }))
  } catch (error) {
    console.error('[OrderView] Error loading payments:', error)
    payments.value = []
  }
}

/**
 * Get options for combo field (order fields)
 * Returns options array from combo config with metadata
 */
function getFieldOptions(fieldName) {
  // Check combo options from API first (new format with metadata)
  if (orderComboOptions.value[fieldName]) {
    const config = orderComboOptions.value[fieldName]
    // New format: { options: [...], compareField: '...', valueField: '...' }
    if (config.options) {
      return config.options
    }
    // Legacy format: direct array
    return config
  }

  // Legacy fallback for hardcoded refs
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

/**
 * Get compareField for a combo field (order fields)
 * Used to determine which order field to use for value binding
 */
function getFieldCompareField(fieldName) {
  if (orderComboOptions.value[fieldName]?.compareField) {
    return orderComboOptions.value[fieldName].compareField
  }
  // Default: use field name itself
  return fieldName
}

/**
 * Get options for combo field (address fields)
 */
function getAddressFieldOptions(fieldName) {
  // Check combo options from API (new format with metadata)
  if (addressComboOptions.value[fieldName]) {
    const config = addressComboOptions.value[fieldName]
    // New format: { options: [...], compareField: '...', valueField: '...' }
    if (config.options) {
      return config.options
    }
    // Legacy format: direct array
    return config
  }
  return []
}

/**
 * Get compareField for a combo field (address fields)
 * Used to determine which address field to use for value binding
 */
function getAddressFieldCompareField(fieldName) {
  if (addressComboOptions.value[fieldName]?.compareField) {
    return addressComboOptions.value[fieldName].compareField
  }
  // Default: use field name itself
  return fieldName
}

/**
 * Check if field is editable
 */
function isFieldEditable(fieldName) {
  // Read-only fields
  const readOnlyFields = ['num', 'createdon', 'updatedon', 'cost', 'cart_cost', 'delivery_cost', 'weight']
  return !readOnlyFields.includes(fieldName)
}

/**
 * Get CSS class for field width (12-column grid)
 */
function getFieldWidthClass(field) {
  const width = field.width || 6
  return `col-${width}`
}

/**
 * Save order
 */
async function saveOrder() {
  saving.value = true

  try {
    // Collect all editable order fields
    const orderData = {}
    for (const field of orderFields.value) {
      if (isFieldEditable(field.name) && order.value[field.name] !== undefined) {
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
      life: 3000
    })

    await loadLogs()
  } catch (error) {
    console.error('[OrderView] Error saving order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000
    })
  } finally {
    saving.value = false
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
      text_address: ''
    }

    // Load field configurations and combo options
    await Promise.all([
      loadOrderFields(),
      loadAddressFields(),
      loadOrderExtraFields(),
      loadAddressExtraFields()
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
      life: 5000
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
        life: 5000
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
      life: 3000
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
      life: 5000
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

  // Set existing customer and create order without create_customer flag
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
      life: 3000
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
      life: 5000
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

  // Retry with force flag
  await createOrder(true)
  pendingOrderData.value = null
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
}

/**
 * Go back to orders list
 */
function goBack() {
  window.location.href = '?a=mgr/orders&namespace=minishop3'
}

/**
 * Format date
 */
function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

/**
 * Format log entry (fallback when entry_formatted not available)
 */
function formatLogEntry(data) {
  if (!data || !data.entry) return '-'

  // If entry is already a string, return it
  if (typeof data.entry === 'string') {
    // Try to parse as JSON
    try {
      const parsed = JSON.parse(data.entry)
      return formatLogEntryObject(data.action, parsed)
    } catch {
      return data.entry
    }
  }

  // If entry is an object
  if (typeof data.entry === 'object') {
    return formatLogEntryObject(data.action, data.entry)
  }

  return String(data.entry)
}

/**
 * Format log entry object based on action type
 */
function formatLogEntryObject(action, entry) {
  if (!entry) return '-'

  switch (action) {
    case 'status':
      if (entry.new_status_name) {
        return entry.new_status_name
      }
      if (entry.status_id) {
        return `Status ID: ${entry.status_id}`
      }
      break

    case 'products':
      const op = entry.operation || 'unknown'
      const productName = entry.product_name || ''
      const count = entry.count ? ` (×${entry.count})` : ''
      return `${capitalizeFirst(op)}: ${productName}${count}`

    case 'field':
      if (entry.fields) {
        const fieldNames = Object.keys(entry.fields)
        return `Fields: ${fieldNames.join(', ')}`
      }
      break

    case 'address':
      if (entry.fields) {
        const fieldNames = Object.keys(entry.fields)
        return `Address: ${fieldNames.join(', ')}`
      }
      break

    case 'payment':
      const payOp = entry.operation || 'unknown'
      const amount = entry.amount || 0
      return `${capitalizeFirst(payOp)}: ${formatPrice(amount)}`

    default:
      // Generic fallback: show key-value pairs
      const pairs = Object.entries(entry)
        .filter(([, v]) => v !== null && v !== undefined)
        .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : v}`)
        .slice(0, 3) // Limit to 3 pairs
      return pairs.join(', ') || '-'
  }

  return JSON.stringify(entry)
}

/**
 * Capitalize first letter
 */
function capitalizeFirst(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

/**
 * Format price
 */
function formatPrice(value) {
  if (value === null || value === undefined) return '-'
  return new Intl.NumberFormat('ru-RU', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(value)
}

/**
 * Get display value for field
 * For combo fields, uses compareField to get the actual value from order
 */
function getFieldDisplayValue(field, value) {
  if (value === null || value === undefined) return '-'

  switch (field.xtype) {
    case 'datefield':
      return formatDate(value)
    case 'numberfield':
      return formatPrice(value)
    case 'combo':
      // For combo fields, use compareField to get the correct value
      const compareField = getFieldCompareField(field.name)
      const actualValue = order.value?.[compareField] ?? value
      const options = getFieldOptions(field.name)
      const option = options.find(o => o.value === actualValue)
      return option?.label || actualValue
    case 'checkbox':
      return value ? _('yes') : _('no')
    default:
      return value
  }
}

/**
 * Get status severity for tag
 */
function getStatusSeverity(color) {
  if (!color) return 'secondary'
  const colorMap = {
    '#97b94d': 'success',
    '#81d742': 'success',
    'green': 'success',
    '#dd3d36': 'danger',
    'red': 'danger',
    '#f0ad4e': 'warn',
    'yellow': 'warn',
    '#5bc0de': 'info',
    'blue': 'info'
  }
  return colorMap[color?.toLowerCase()] || 'secondary'
}

onMounted(async () => {
  if (isCreateMode.value) {
    // Create mode - init empty order and load field configs
    await initEmptyOrder()
  } else {
    // Edit mode - load existing order
    await loadOrder()
  }
})
</script>

<template>
  <div class="order-view">
    <Toast />
    <ConfirmDialog />

    <!-- Edit Product Dialog -->
    <Dialog
      v-model:visible="editProductDialogVisible"
      :header="_('order_product_edit')"
      :style="{ width: '650px' }"
      :modal="true"
      :closable="!savingProduct"
      :closeOnEscape="!savingProduct"
    >
      <div v-if="editingProduct" class="edit-product-form">
        <!-- Product name (readonly) -->
        <div class="field mb-3">
          <label>{{ _('order_product_name') }}</label>
          <div class="product-name-display">{{ editingProduct.name }}</div>
        </div>

        <!-- Count -->
        <div class="field mb-3">
          <label for="edit-count">{{ _('order_product_count') }}</label>
          <InputNumber
            id="edit-count"
            v-model="editProductForm.count"
            :min="1"
            :max="9999"
            showButtons
            class="w-full"
          />
        </div>

        <!-- Price -->
        <div class="field mb-3">
          <label for="edit-price">{{ _('order_product_price') }}</label>
          <InputNumber
            id="edit-price"
            v-model="editProductForm.price"
            :min="0"
            :minFractionDigits="0"
            :maxFractionDigits="2"
            class="w-full"
          />
        </div>

        <!-- Weight -->
        <div class="field mb-3">
          <label for="edit-weight">{{ _('order_product_weight') }}</label>
          <InputNumber
            id="edit-weight"
            v-model="editProductForm.weight"
            :min="0"
            :minFractionDigits="0"
            :maxFractionDigits="3"
            class="w-full"
          />
        </div>

        <!-- Calculated cost (readonly) -->
        <div class="field mb-3">
          <label>{{ _('order_product_cost') }}</label>
          <div class="cost-display">{{ formatPrice(editedProductCost) }}</div>
        </div>

        <!-- Options -->
        <div class="field mb-3">
          <div class="options-header">
            <label>{{ _('order_product_options') }}</label>
            <div class="options-mode-switch">
              <Button
                :label="_('options_mode_table')"
                :severity="optionsEditMode === 'table' ? 'primary' : 'secondary'"
                size="small"
                text
                @click="switchOptionsMode('table')"
              />
              <Button
                :label="_('options_mode_json')"
                :severity="optionsEditMode === 'json' ? 'primary' : 'secondary'"
                size="small"
                text
                @click="switchOptionsMode('json')"
              />
            </div>
          </div>

          <!-- Table mode -->
          <div v-if="optionsEditMode === 'table'" class="options-table">
            <div
              v-for="(row, index) in optionsTableData"
              :key="index"
              class="options-row"
            >
              <!-- Type selector -->
              <Select
                v-model="row.type"
                :options="[
                  { value: 'field', label: _('options_type_field') },
                  { value: 'custom', label: _('options_type_custom') }
                ]"
                optionLabel="label"
                optionValue="value"
                class="options-type-select"
                @change="onOptionTypeChange(row, index)"
              />

              <!-- Field type: Select field name -->
              <template v-if="row.type === 'field'">
                <Select
                  v-model="row.key"
                  :options="productOptionFields"
                  optionLabel="label"
                  optionValue="name"
                  :placeholder="_('options_select_field')"
                  class="options-key-input"
                  @change="onFieldKeyChange(row)"
                />
                <!-- Field value: Select from available values or input -->
                <Select
                  v-if="row.fieldValues.length > 0"
                  v-model="row.value"
                  :options="row.fieldValues"
                  optionLabel="label"
                  optionValue="value"
                  :placeholder="_('options_select_value')"
                  :loading="row.loadingValues"
                  editable
                  class="options-value-input"
                  @change="syncTableToJson"
                />
                <InputText
                  v-else
                  v-model="row.value"
                  :placeholder="row.loadingValues ? _('loading') : _('options_value')"
                  :disabled="row.loadingValues"
                  class="options-value-input"
                  @change="syncTableToJson"
                />
              </template>

              <!-- Custom type: Free text inputs -->
              <template v-else>
                <InputText
                  v-model="row.key"
                  :placeholder="_('options_key')"
                  class="options-key-input"
                  @change="syncTableToJson"
                />
                <Textarea
                  v-if="row.isComplex"
                  v-model="row.value"
                  :placeholder="_('options_value')"
                  class="options-value-input"
                  rows="2"
                  @change="syncTableToJson"
                />
                <InputText
                  v-else
                  v-model="row.value"
                  :placeholder="_('options_value')"
                  class="options-value-input"
                  @change="syncTableToJson"
                />
              </template>

              <Button
                icon="pi pi-times"
                severity="danger"
                text
                rounded
                size="small"
                @click="removeOptionRow(index)"
              />
            </div>
            <Button
              :label="_('options_add_row')"
              icon="pi pi-plus"
              severity="secondary"
              size="small"
              text
              @click="addOptionRow"
            />
          </div>

          <!-- JSON mode -->
          <div v-else class="options-json">
            <Textarea
              v-model="optionsJsonText"
              :placeholder="_('options_json_placeholder')"
              rows="6"
              class="w-full options-json-textarea"
              :class="{ 'p-invalid': optionsJsonError }"
            />
            <small v-if="optionsJsonError" class="p-error">{{ optionsJsonError }}</small>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          severity="secondary"
          @click="cancelEditProduct"
          :disabled="savingProduct"
        />
        <Button
          :label="_('save')"
          icon="pi pi-check"
          @click="saveEditedProduct"
          :loading="savingProduct"
        />
      </template>
    </Dialog>

    <!-- Add Product Dialog -->
    <Dialog
      v-model:visible="addProductDialogVisible"
      :header="_('order_add_product_title')"
      :style="{ width: '550px' }"
      :modal="true"
      :closable="!savingNewProduct"
      :closeOnEscape="!savingNewProduct"
    >
      <div class="add-product-form">
        <!-- Product search -->
        <div class="field mb-3">
          <label>{{ _('order_search_product') }}</label>
          <AutoComplete
            v-model="selectedProduct"
            :suggestions="productSuggestions"
            @complete="searchProducts"
            @item-select="onProductSelect"
            optionLabel="display"
            :placeholder="_('order_search_product')"
            :loading="searchingProducts"
            class="w-full"
            :minLength="2"
          >
            <template #option="{ option }">
              <div class="product-suggestion">
                <img
                  v-if="option.image"
                  :src="option.image"
                  :alt="option.pagetitle"
                  class="product-suggestion-image"
                />
                <div class="product-suggestion-info">
                  <div class="product-suggestion-name">{{ option.pagetitle }}</div>
                  <div class="product-suggestion-meta">
                    <span v-if="option.article" class="article">[{{ option.article }}]</span>
                    <span class="price">{{ formatPrice(option.price) }}</span>
                  </div>
                </div>
              </div>
            </template>
          </AutoComplete>
        </div>

        <!-- Product details (shown after selection) -->
        <div v-if="selectedProduct && selectedProduct.id" class="selected-product-details">
          <div class="selected-product-header mb-3">
            <img
              v-if="selectedProduct.image"
              :src="selectedProduct.image"
              :alt="selectedProduct.pagetitle"
              class="selected-product-image"
            />
            <div class="selected-product-name">{{ selectedProduct.pagetitle }}</div>
          </div>

          <div class="product-form-fields">
            <!-- Count -->
            <div class="field mb-3">
              <label>{{ _('order_product_count') }}</label>
              <InputNumber
                v-model="addProductForm.count"
                :min="1"
                class="w-full"
              />
            </div>

            <!-- Price -->
            <div class="field mb-3">
              <label>{{ _('order_product_price') }}</label>
              <InputNumber
                v-model="addProductForm.price"
                mode="decimal"
                :minFractionDigits="2"
                :maxFractionDigits="2"
                class="w-full"
              />
            </div>

            <!-- Weight -->
            <div class="field mb-3">
              <label>{{ _('order_product_weight') }}</label>
              <InputNumber
                v-model="addProductForm.weight"
                mode="decimal"
                :minFractionDigits="3"
                :maxFractionDigits="3"
                class="w-full"
              />
            </div>

            <!-- Calculated cost -->
            <div class="field mb-3">
              <label>{{ _('order_product_cost') }}</label>
              <div class="calculated-cost">{{ formatPrice(newProductCost) }}</div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          severity="secondary"
          @click="cancelAddProduct"
          :disabled="savingNewProduct"
        />
        <Button
          :label="_('save')"
          icon="pi pi-check"
          @click="saveNewProduct"
          :loading="savingNewProduct"
          :disabled="!selectedProduct || !selectedProduct.id"
        />
      </template>
    </Dialog>

    <!-- Duplicate Customer Dialog -->
    <Dialog
      v-model:visible="showDuplicateDialog"
      :header="_('ms3_customer_duplicate_found')"
      :style="{ width: '500px' }"
      :modal="true"
      :closable="true"
      @hide="cancelDuplicateDialog"
    >
      <div class="duplicate-customer-dialog">
        <div class="duplicate-warning">
          <i class="pi pi-exclamation-triangle"></i>
          <p>{{ _('ms3_customer_duplicate_message') }}</p>
        </div>

        <div v-if="duplicateCustomer" class="duplicate-customer-info">
          <div class="info-row">
            <span class="info-label">{{ _('customer_name') }}:</span>
            <span class="info-value">{{ duplicateCustomer.first_name }} {{ duplicateCustomer.last_name }}</span>
          </div>
          <div v-if="duplicateCustomer.email" class="info-row">
            <span class="info-label">Email:</span>
            <span class="info-value">{{ duplicateCustomer.email }}</span>
          </div>
          <div v-if="duplicateCustomer.phone" class="info-row">
            <span class="info-label">{{ _('phone') }}:</span>
            <span class="info-value">{{ duplicateCustomer.phone }}</span>
          </div>
          <div v-if="duplicateCustomer.orders_count" class="info-row">
            <span class="info-label">{{ _('orders') }}:</span>
            <span class="info-value">{{ duplicateCustomer.orders_count }}</span>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          severity="secondary"
          @click="cancelDuplicateDialog"
        />
        <Button
          :label="_('ms3_customer_use_existing')"
          icon="pi pi-user"
          severity="info"
          @click="useDuplicateCustomer"
        />
        <Button
          :label="_('ms3_customer_create_new')"
          icon="pi pi-plus"
          severity="warning"
          @click="createNewCustomerAnyway"
        />
      </template>
    </Dialog>

    <!-- Header -->
    <div class="order-header mb-3">
      <Button
        icon="pi pi-arrow-left"
        :label="_('back_to_orders')"
        severity="secondary"
        text
        @click="goBack"
      />
      <h2 v-if="isCreateMode">{{ _('ms3_order_new') }}</h2>
      <h2 v-else-if="order">{{ _('order') }} {{ order.num ? '#' + order.num : '' }}</h2>
    </div>

    <div v-if="loading" class="loading-state">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
      <p>{{ _('loading') }}</p>
    </div>

    <template v-else-if="order">
      <TabView>
        <!-- Order Info Tab -->
        <TabPanel :header="_('order_info')">
          <!-- Customer Search Section (only in create mode) -->
          <Fieldset v-if="isCreateMode" :legend="_('order_customer')" class="mb-3" :toggleable="true">
            <div class="customer-search-content">
              <div class="customer-search-field">
                <AutoComplete
                  v-model="selectedCustomer"
                  :suggestions="customerSuggestions"
                  @complete="searchCustomers"
                  @item-select="onCustomerSelect"
                  optionLabel="display"
                  :placeholder="_('ms3_order_search_customer')"
                  :loading="searchingCustomers"
                  class="w-full"
                  :minLength="2"
                >
                  <template #option="{ option }">
                    <div class="customer-suggestion">
                      <div class="customer-suggestion-info">
                        <div class="customer-suggestion-name">{{ option.first_name }} {{ option.last_name }}</div>
                        <div class="customer-suggestion-meta">
                          <span v-if="option.email" class="email">{{ option.email }}</span>
                          <span v-if="option.phone" class="phone">{{ option.phone }}</span>
                        </div>
                        <div class="customer-suggestion-stats">
                          <span v-if="option.orders_count">{{ _('orders') }}: {{ option.orders_count }}</span>
                          <span v-if="option.total_spent">{{ _('total') }}: {{ formatPrice(option.total_spent) }}</span>
                        </div>
                      </div>
                    </div>
                  </template>
                </AutoComplete>
                <small class="customer-search-hint">{{ _('ms3_order_customer_hint') }}</small>
              </div>

              <!-- Selected customer info -->
              <div v-if="selectedCustomer && selectedCustomer.id" class="selected-customer-info">
                <div class="selected-customer-badge">
                  <i class="pi pi-user"></i>
                  <span class="customer-name">{{ selectedCustomer.first_name }} {{ selectedCustomer.last_name }}</span>
                  <span v-if="selectedCustomer.email" class="customer-email">{{ selectedCustomer.email }}</span>
                  <Button
                    icon="pi pi-times"
                    severity="secondary"
                    text
                    rounded
                    size="small"
                    @click="clearCustomer"
                    :title="_('ms3_order_clear_customer')"
                  />
                </div>
                <small class="text-success">{{ _('ms3_order_customer_selected') }}</small>
              </div>
              <div v-else class="no-customer-hint">
                <i class="pi pi-info-circle"></i>
                <span>{{ _('ms3_order_no_customer') }}</span>
              </div>

              <!-- Create customer checkbox -->
              <div class="create-customer-checkbox mt-3">
                <Checkbox
                  v-model="createCustomerFromData"
                  inputId="createCustomer"
                  :binary="true"
                  :disabled="!!selectedCustomer?.id"
                />
                <label for="createCustomer" class="ml-2" :class="{ 'text-muted': !!selectedCustomer?.id }">
                  {{ _('ms3_order_create_customer_from_data') }}
                </label>
              </div>
            </div>
          </Fieldset>

          <!-- Static Order Summary Section (only in edit mode) -->
          <Fieldset v-if="!isCreateMode" :legend="_('order_summary')" class="mb-3 order-summary-section" :toggleable="false">
            <div class="order-summary-grid">
              <!-- Order number -->
              <div class="summary-item summary-num">
                <span class="summary-label">{{ _('order_num') }}</span>
                <span class="summary-value summary-value-lg">{{ order.num ? '#' + order.num : '-' }}</span>
              </div>
              <!-- Total cost -->
              <div class="summary-item summary-cost">
                <span class="summary-label">{{ _('order_cost') }}</span>
                <span class="summary-value summary-value-lg summary-value-primary">{{ order.cost_formatted || formatPrice(order.cost) }}</span>
              </div>
              <!-- Cart cost -->
              <div class="summary-item">
                <span class="summary-label">{{ _('order_cart_cost') }}</span>
                <span class="summary-value">{{ order.cart_cost_formatted || formatPrice(order.cart_cost) }}</span>
              </div>
              <!-- Delivery cost -->
              <div class="summary-item">
                <span class="summary-label">{{ _('order_delivery_cost') }}</span>
                <span class="summary-value">{{ order.delivery_cost_formatted || formatPrice(order.delivery_cost) }}</span>
              </div>
              <!-- Weight -->
              <div class="summary-item">
                <span class="summary-label">{{ _('order_weight') }}</span>
                <span class="summary-value">{{ order.weight_formatted || order.weight || '-' }}</span>
              </div>
              <!-- Created date -->
              <div class="summary-item">
                <span class="summary-label">{{ _('order_createdon') }}</span>
                <span class="summary-value">{{ formatDate(order.createdon) }}</span>
              </div>
              <!-- Updated date -->
              <div class="summary-item">
                <span class="summary-label">{{ _('order_updatedon') }}</span>
                <span class="summary-value">{{ formatDate(order.updatedon) }}</span>
              </div>
            </div>
          </Fieldset>

          <!-- Order sections with fields (dynamic from configuration) -->
          <template v-for="section in orderFieldsBySection" :key="section.id || 'no_section'">
            <Fieldset :legend="section.label" class="mb-3" :toggleable="true">
              <div class="fields-grid">
                <template v-for="field in section.fields" :key="field.id">
                  <div :class="['field-wrapper', getFieldWidthClass(field)]">
                    <div class="field">
                      <label>{{ field.label_display || field.label || field.name }}</label>

                      <!-- Read-only fields -->
                      <template v-if="!isFieldEditable(field.name)">
                        <div class="field-value">{{ getFieldDisplayValue(field, order[field.name]) }}</div>
                      </template>

                      <!-- Combo (Select) -->
                      <template v-else-if="field.xtype === 'combo'">
                        <Select
                          v-model="order[getFieldCompareField(field.name)]"
                          :options="getFieldOptions(field.name)"
                          optionLabel="label"
                          optionValue="value"
                          :placeholder="field.placeholder"
                          class="w-full"
                        />
                      </template>

                      <!-- Textarea -->
                      <template v-else-if="field.xtype === 'textarea'">
                        <Textarea
                          v-model="order[field.name]"
                          :placeholder="field.placeholder"
                          rows="3"
                          class="w-full"
                        />
                      </template>

                      <!-- Number field -->
                      <template v-else-if="field.xtype === 'numberfield'">
                        <InputNumber
                          v-model="order[field.name]"
                          :placeholder="field.placeholder"
                          class="w-full"
                          :minFractionDigits="0"
                          :maxFractionDigits="2"
                        />
                      </template>

                      <!-- Date field -->
                      <template v-else-if="field.xtype === 'datefield'">
                        <DatePicker
                          v-model="order[field.name]"
                          :placeholder="field.placeholder"
                          class="w-full"
                          dateFormat="dd.mm.yy"
                          showTime
                          hourFormat="24"
                        />
                      </template>

                      <!-- Checkbox -->
                      <template v-else-if="field.xtype === 'checkbox'">
                        <div class="flex align-items-center">
                          <Checkbox v-model="order[field.name]" :binary="true" />
                        </div>
                      </template>

                      <!-- Text field (default) -->
                      <template v-else>
                        <InputText
                          v-model="order[field.name]"
                          :placeholder="field.placeholder"
                          class="w-full"
                        />
                      </template>

                      <!-- Description/help text -->
                      <small v-if="field.description_display" class="field-description">
                        {{ field.description_display }}
                      </small>
                    </div>
                  </div>
                </template>
              </div>
            </Fieldset>
          </template>

          <!-- Fallback if no fields configured -->
          <div v-if="orderFieldsBySection.length === 0" class="no-fields-message">
            <p>{{ _('ms3_model_fields_empty') }}</p>
          </div>

          <!-- Save/Create button -->
          <div class="actions-bar mt-3">
            <Button
              v-if="isCreateMode"
              :label="_('ms3_order_create')"
              icon="pi pi-plus"
              severity="success"
              :loading="saving"
              @click="createOrder"
            />
            <Button
              v-else
              :label="_('save')"
              icon="pi pi-check"
              :loading="saving"
              @click="saveOrder"
            />
            <Button
              :label="_('cancel')"
              icon="pi pi-times"
              severity="secondary"
              @click="goBack"
            />
          </div>
        </TabPanel>

        <!-- Products Tab (dynamic columns from grid config) - hidden in create mode -->
        <TabPanel v-if="!isCreateMode" :header="_('order_products')">
          <div class="products-toolbar mb-3">
            <Button
              :label="_('order_add_product')"
              icon="pi pi-plus"
              severity="primary"
              size="small"
              @click="openAddProductDialog"
            />
          </div>
          <DataTable :value="products" stripedRows responsiveLayout="scroll">
            <template v-for="column in productsColumns.filter(c => c.visible)" :key="column.name">
              <!-- Image column -->
              <Column
                v-if="column.type === 'image'"
                :field="column.name"
                :header="column.label"
                :style="{ width: column.width }"
              >
                <template #body="{ data }">
                  <img
                    v-if="data[column.name]"
                    :src="data[column.name]"
                    :alt="data.name"
                    class="product-thumbnail"
                    :style="{ width: (column.width || 50) + 'px', height: (column.height || 50) + 'px', objectFit: 'cover' }"
                  />
                  <span v-else class="no-image">—</span>
                </template>
              </Column>

              <!-- Options column (chips) -->
              <Column
                v-else-if="column.type === 'options'"
                :field="column.name"
                :header="column.label"
                :style="{ width: column.width, minWidth: column.minWidth }"
              >
                <template #body="{ data }">
                  <div class="options-chips">
                    <Tag
                      v-for="(opt, idx) in formatOptions(data[column.name])"
                      :key="idx"
                      :value="opt"
                      severity="secondary"
                      class="mr-1 mb-1"
                    />
                    <span v-if="!formatOptions(data[column.name]).length">—</span>
                  </div>
                </template>
              </Column>

              <!-- Price column -->
              <Column
                v-else-if="column.type === 'price'"
                :field="column.name"
                :header="column.label"
                :sortable="column.sortable"
                :style="{ width: column.width, minWidth: column.minWidth }"
              >
                <template #body="{ data }">
                  {{ formatPrice(data[column.name]) }}
                </template>
              </Column>

              <!-- Number column -->
              <Column
                v-else-if="column.type === 'number'"
                :field="column.name"
                :header="column.label"
                :sortable="column.sortable"
                :style="{ width: column.width, minWidth: column.minWidth }"
              >
                <template #body="{ data }">
                  {{ data[column.name] }}
                </template>
              </Column>

              <!-- Weight column -->
              <Column
                v-else-if="column.type === 'weight'"
                :field="column.name"
                :header="column.label"
                :sortable="column.sortable"
                :style="{ width: column.width, minWidth: column.minWidth }"
              >
                <template #body="{ data }">
                  {{ data[column.name + '_formatted'] || data[column.name] }}
                </template>
              </Column>

              <!-- Template column (name with link) -->
              <Column
                v-else-if="column.type === 'template'"
                :field="column.name"
                :header="column.label"
                :sortable="column.sortable"
                :style="{ width: column.width, minWidth: column.minWidth }"
              >
                <template #body="{ data }">
                  <a
                    v-if="getProductLink(data, column)"
                    :href="getProductLink(data, column)"
                    target="_blank"
                    class="product-link"
                  >
                    {{ renderProductField(data, column) }}
                  </a>
                  <span v-else>
                    {{ renderProductField(data, column) || '—' }}
                  </span>
                </template>
              </Column>

              <!-- Actions column -->
              <Column
                v-else-if="column.type === 'actions'"
                :header="column.label"
                :frozen="column.frozen"
                :style="{ width: column.width }"
              >
                <template #body="{ data }">
                  <div class="actions-buttons">
                    <Button
                      v-for="action in (column.actions || [])"
                      :key="action.name"
                      :icon="'pi ' + action.icon"
                      :severity="action.severity || 'secondary'"
                      text
                      rounded
                      size="small"
                      @click="handleProductAction(action, data)"
                    />
                  </div>
                </template>
              </Column>

              <!-- Default column (text) -->
              <Column
                v-else
                :field="column.name"
                :header="column.label"
                :sortable="column.sortable"
                :style="{ width: column.width, minWidth: column.minWidth }"
              />
            </template>
          </DataTable>
        </TabPanel>

        <!-- Address Tab (dynamic fields grouped by sections) - hidden in create mode -->
        <TabPanel v-if="!isCreateMode" :header="_('order_address')">
          <template v-for="section in addressFieldsBySection" :key="section.id || 'no_section'">
            <Fieldset :legend="section.label" class="mb-3" :toggleable="true">
              <div class="fields-grid">
                <template v-for="field in section.fields" :key="field.id">
                  <div :class="['field-wrapper', getFieldWidthClass(field)]">
                    <div class="field">
                      <label>{{ field.label_display || field.label || field.name }}</label>

                      <!-- Combo (Select) -->
                      <template v-if="field.xtype === 'combo'">
                        <Select
                          v-model="order[getAddressFieldCompareField(field.name)]"
                          :options="getAddressFieldOptions(field.name)"
                          optionLabel="label"
                          optionValue="value"
                          :placeholder="field.placeholder"
                          class="w-full"
                        />
                      </template>

                      <!-- Textarea -->
                      <template v-else-if="field.xtype === 'textarea'">
                        <Textarea
                          v-model="order[field.name]"
                          :placeholder="field.placeholder"
                          rows="3"
                          class="w-full"
                        />
                      </template>

                      <!-- Number field -->
                      <template v-else-if="field.xtype === 'numberfield'">
                        <InputNumber
                          v-model="order[field.name]"
                          :placeholder="field.placeholder"
                          class="w-full"
                        />
                      </template>

                      <!-- Checkbox -->
                      <template v-else-if="field.xtype === 'checkbox'">
                        <div class="flex align-items-center">
                          <Checkbox v-model="order[field.name]" :binary="true" />
                        </div>
                      </template>

                      <!-- Text field (default) -->
                      <template v-else>
                        <InputText
                          v-model="order[field.name]"
                          :placeholder="field.placeholder"
                          class="w-full"
                        />
                      </template>

                      <!-- Description/help text -->
                      <small v-if="field.description_display" class="field-description">
                        {{ field.description_display }}
                      </small>
                    </div>
                  </div>
                </template>
              </div>
            </Fieldset>
          </template>

          <!-- Fallback if no fields configured -->
          <div v-if="addressFieldsBySection.length === 0" class="no-fields-message">
            <p>{{ _('ms3_model_fields_empty') }}</p>
          </div>

          <!-- Save button for address -->
          <div class="actions-bar mt-3">
            <Button
              :label="_('save')"
              icon="pi pi-check"
              :loading="saving"
              @click="saveOrder"
            />
          </div>
        </TabPanel>

        <!-- History Tab - hidden in create mode -->
        <TabPanel v-if="!isCreateMode" :header="_('order_history')">
          <DataTable :value="logs" stripedRows responsiveLayout="scroll">
            <Column field="timestamp" :header="_('log_date')" style="width: 180px">
              <template #body="{ data }">
                {{ formatDate(data.timestamp || data.createdon) }}
              </template>
            </Column>
            <Column field="action" :header="_('log_action')" />
            <Column field="user_name" :header="_('log_user')" style="width: 150px" />
            <Column field="entry_formatted" :header="_('log_entry')">
              <template #body="{ data }">
                <span v-html="data.entry_formatted || formatLogEntry(data)"></span>
              </template>
            </Column>
          </DataTable>
        </TabPanel>
      </TabView>
    </template>

    <div v-else class="error-state">
      <i class="pi pi-exclamation-triangle" style="font-size: 3rem; color: #f59e0b"></i>
      <p>{{ _('order_not_found') }}</p>
      <Button :label="_('back_to_orders')" @click="goBack" />
    </div>
  </div>
</template>

<style scoped>
.order-view {
  padding: 20px;
}

.order-header {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.order-header h2 {
  margin: 0;
  font-size: 1.5rem;
  color: #1e293b;
}

.loading-state,
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

/* 12-column grid for fields */
.fields-grid {
  display: flex;
  flex-wrap: wrap;
  margin: -0.5rem;
}

.field-wrapper {
  padding: 0.5rem;
  box-sizing: border-box;
}

/* Width classes (12-column grid) */
.col-1 { width: 8.333%; }
.col-2 { width: 16.666%; }
.col-3 { width: 25%; }
.col-4 { width: 33.333%; }
.col-5 { width: 41.666%; }
.col-6 { width: 50%; }
.col-7 { width: 58.333%; }
.col-8 { width: 66.666%; }
.col-9 { width: 75%; }
.col-10 { width: 83.333%; }
.col-11 { width: 91.666%; }
.col-12 { width: 100%; }

/* Responsive: on small screens all fields become full-width */
@media (max-width: 768px) {
  .col-1, .col-2, .col-3, .col-4, .col-5, .col-6,
  .col-7, .col-8, .col-9, .col-10, .col-11, .col-12 {
    width: 100%;
  }
}

.field {
  margin-bottom: 0;
}

.field label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #64748b;
  font-size: 0.875rem;
}

.field-value {
  font-size: 1rem;
  color: #1e293b;
  min-height: 2.5rem;
  display: flex;
  align-items: center;
}

.field-description {
  display: block;
  margin-top: 0.25rem;
  color: #94a3b8;
  font-size: 0.75rem;
}

.costs-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

@media (max-width: 768px) {
  .costs-grid {
    grid-template-columns: 1fr;
  }
}

/* Order Summary Section */
.order-summary-section :deep(.p-fieldset-legend) {
  background: #3b82f6;
  color: white;
}

.order-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

@media (max-width: 1200px) {
  .order-summary-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .order-summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .order-summary-grid {
    grid-template-columns: 1fr;
  }
}

.summary-item {
  display: flex;
  flex-direction: column;
  padding: 0.75rem 1rem;
  background: #f8fafc;
  border-radius: 8px;
  border-left: 3px solid #e2e8f0;
}

.summary-item.summary-num {
  border-left-color: #3b82f6;
}

.summary-item.summary-cost {
  border-left-color: #22c55e;
}

.summary-label {
  font-size: 0.75rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.25rem;
}

.summary-value {
  font-size: 1rem;
  font-weight: 500;
  color: #1e293b;
}

.summary-value-lg {
  font-size: 1.25rem;
  font-weight: 600;
}

.summary-value-primary {
  color: #22c55e;
}

.actions-bar {
  display: flex;
  gap: 0.5rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
}

.no-fields-message {
  padding: 1rem;
  text-align: center;
  color: #64748b;
  font-style: italic;
}

.mt-3 {
  margin-top: 1rem;
}

.mb-3 {
  margin-bottom: 1rem;
}

.w-full {
  width: 100%;
}

.flex {
  display: flex;
}

.align-items-center {
  align-items: center;
}

/* Fieldset styles */
:deep(.p-fieldset) {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

:deep(.p-fieldset .p-fieldset-legend) {
  font-size: 0.95rem;
  padding: 0.5rem 1rem;
  background: #f8fafc;
  border-radius: 4px;
}

:deep(.p-fieldset .p-fieldset-content) {
  padding: 1rem;
}

/* Products table styles */
.product-thumbnail {
  border-radius: 4px;
  object-fit: cover;
}

.no-image {
  color: #9ca3af;
}

.product-link {
  color: #3b82f6;
  text-decoration: none;
}

.product-link:hover {
  text-decoration: underline;
}

.options-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.actions-buttons {
  display: flex;
  gap: 0.25rem;
}

/* Edit product dialog styles */
.edit-product-form .field label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #64748b;
  font-size: 0.875rem;
}

.product-name-display {
  font-size: 1rem;
  font-weight: 500;
  color: #1e293b;
  padding: 0.5rem;
  background: #f8fafc;
  border-radius: 4px;
}

.cost-display {
  font-size: 1.125rem;
  font-weight: 600;
  color: #22c55e;
  padding: 0.5rem;
  background: #f0fdf4;
  border-radius: 4px;
  text-align: right;
}

/* Options editing styles */
.options-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.options-header label {
  margin-bottom: 0 !important;
}

.options-mode-switch {
  display: flex;
  gap: 0.25rem;
}

.options-table {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.75rem;
  background: #f8fafc;
}

.options-row {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  align-items: flex-start;
}

.options-row:last-of-type {
  margin-bottom: 0.75rem;
}

.options-type-select {
  flex: 0 0 100px;
  min-width: 100px;
}

.options-key-input {
  flex: 0 0 120px;
  min-width: 100px;
}

.options-value-input {
  flex: 1;
  min-width: 0;
}

.options-json {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.5rem;
  background: #f8fafc;
}

.options-json-textarea {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 0.875rem;
}

.options-json-textarea.p-invalid {
  border-color: #ef4444;
}

.p-error {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
  display: block;
}

/* Add Product Dialog styles */
.add-product-form .field label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #64748b;
  font-size: 0.875rem;
}

.product-suggestion {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.25rem 0;
}

.product-suggestion-image {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
}

.product-suggestion-info {
  flex: 1;
}

.product-suggestion-name {
  font-weight: 500;
  color: #1e293b;
}

.product-suggestion-meta {
  font-size: 0.75rem;
  color: #64748b;
  display: flex;
  gap: 0.5rem;
}

.product-suggestion-meta .article {
  color: #3b82f6;
}

.product-suggestion-meta .price {
  font-weight: 500;
}

.selected-product-details {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1rem;
  background: #f8fafc;
}

.selected-product-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e2e8f0;
}

.selected-product-image {
  width: 50px;
  height: 50px;
  object-fit: cover;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
}

.selected-product-name {
  font-weight: 600;
  font-size: 1rem;
  color: #1e293b;
}

.calculated-cost {
  font-size: 1.125rem;
  font-weight: 600;
  color: #22c55e;
  padding: 0.5rem;
  background: #f0fdf4;
  border-radius: 4px;
  text-align: right;
}

.products-toolbar {
  display: flex;
  justify-content: flex-end;
}

/* Customer Search Section Styles */
.customer-search-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.customer-search-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.customer-search-hint {
  color: #64748b;
  font-size: 0.75rem;
}

.customer-suggestion {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.25rem 0;
}

.customer-suggestion-info {
  flex: 1;
}

.customer-suggestion-name {
  font-weight: 500;
  color: #1e293b;
}

.customer-suggestion-meta {
  font-size: 0.75rem;
  color: #64748b;
  display: flex;
  gap: 0.75rem;
}

.customer-suggestion-meta .email {
  color: #3b82f6;
}

.customer-suggestion-meta .phone {
  color: #64748b;
}

.customer-suggestion-stats {
  font-size: 0.7rem;
  color: #94a3b8;
  display: flex;
  gap: 0.75rem;
  margin-top: 0.25rem;
}

.selected-customer-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.selected-customer-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
}

.selected-customer-badge i {
  color: #22c55e;
}

.selected-customer-badge .customer-name {
  font-weight: 500;
  color: #1e293b;
}

.selected-customer-badge .customer-email {
  color: #64748b;
  font-size: 0.875rem;
}

.no-customer-hint {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: #f8fafc;
  border: 1px dashed #e2e8f0;
  border-radius: 8px;
  color: #64748b;
  font-size: 0.875rem;
}

.no-customer-hint i {
  color: #94a3b8;
}

.text-success {
  color: #22c55e;
}

/* Create customer checkbox */
.create-customer-checkbox {
  display: flex;
  align-items: center;
  padding: 0.75rem;
  background: #fefce8;
  border: 1px solid #fef08a;
  border-radius: 8px;
}

.create-customer-checkbox label {
  cursor: pointer;
  font-size: 0.875rem;
  color: #854d0e;
}

.create-customer-checkbox label.text-muted {
  color: #94a3b8;
  cursor: not-allowed;
}

/* Duplicate customer dialog */
.duplicate-customer-dialog {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.duplicate-warning {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  background: #fef3c7;
  border-radius: 8px;
}

.duplicate-warning i {
  color: #f59e0b;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.duplicate-warning p {
  margin: 0;
  color: #92400e;
  font-size: 0.875rem;
}

.duplicate-customer-info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
}

.duplicate-customer-info .info-row {
  display: flex;
  gap: 0.5rem;
}

.duplicate-customer-info .info-label {
  color: #64748b;
  font-size: 0.875rem;
  min-width: 100px;
}

.duplicate-customer-info .info-value {
  color: #1e293b;
  font-size: 0.875rem;
  font-weight: 500;
}
</style>
