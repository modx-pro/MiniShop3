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
import { useToast } from 'primevue/usetoast'
import request from '../request.js'
import { useLexicon } from '../composables/useLexicon.js'

const toast = useToast()
const { _ } = useLexicon()

const loading = ref(true)
const saving = ref(false)
const order = ref(null)
const products = ref([])
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

const orderId = computed(() => {
  // Try to get from ms3.config first
  if (window.ms3?.config?.order_id) {
    return window.ms3.config.order_id
  }
  // Fallback: get from URL
  const urlParams = new URLSearchParams(window.location.search)
  return parseInt(urlParams.get('id')) || 0
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

onMounted(() => {
  loadOrder()
})
</script>

<template>
  <div class="order-view">
    <Toast />
    <ConfirmDialog />

    <!-- Header -->
    <div class="order-header mb-3">
      <Button
        icon="pi pi-arrow-left"
        :label="_('back_to_orders')"
        severity="secondary"
        text
        @click="goBack"
      />
      <h2 v-if="order">{{ _('order') }} {{ order.num ? '#' + order.num : '' }}</h2>
    </div>

    <div v-if="loading" class="loading-state">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
      <p>{{ _('loading') }}</p>
    </div>

    <template v-else-if="order">
      <TabView>
        <!-- Order Info Tab -->
        <TabPanel :header="_('order_info')">
          <!-- Static Order Summary Section (always first) -->
          <Fieldset :legend="_('order_summary')" class="mb-3 order-summary-section" :toggleable="false">
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

          <!-- Save button -->
          <div class="actions-bar mt-3">
            <Button
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

        <!-- Products Tab -->
        <TabPanel :header="_('order_products')">
          <DataTable :value="products" stripedRows responsiveLayout="scroll">
            <Column field="name" :header="_('product_name')">
              <template #body="{ data }">
                <a v-if="data.product_id" :href="`?a=resource/update&id=${data.product_id}`" target="_blank">
                  {{ data.name || data.pagetitle }}
                </a>
                <span v-else>{{ data.name || data.pagetitle || '-' }}</span>
              </template>
            </Column>
            <Column field="article" :header="_('product_article')" style="width: 120px" />
            <Column field="count" :header="_('product_count')" style="width: 80px" />
            <Column field="price" :header="_('product_price')" style="width: 120px">
              <template #body="{ data }">
                {{ formatPrice(data.price) }}
              </template>
            </Column>
            <Column field="cost" :header="_('product_cost')" style="width: 120px">
              <template #body="{ data }">
                {{ formatPrice(data.cost) }}
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <!-- Address Tab (dynamic fields grouped by sections) -->
        <TabPanel :header="_('order_address')">
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

        <!-- History Tab -->
        <TabPanel :header="_('order_history')">
          <DataTable :value="logs" stripedRows responsiveLayout="scroll">
            <Column field="timestamp" :header="_('log_date')" style="width: 180px">
              <template #body="{ data }">
                {{ formatDate(data.timestamp || data.createdon) }}
              </template>
            </Column>
            <Column field="action" :header="_('log_action')" />
            <Column field="user_name" :header="_('log_user')" style="width: 150px" />
            <Column field="entry" :header="_('log_entry')">
              <template #body="{ data }">
                <span v-html="data.entry"></span>
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
</style>
