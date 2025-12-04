<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import request from '../request.js'
import { useLexicon } from '../composables/useLexicon.js'
import ActionsColumn from './ActionsColumn.vue'

const toast = useToast()
const { _ } = useLexicon()

const columns = ref([])
const filters = ref({})
const loading = ref(false)
const orders = ref([])
const totalRecords = ref(0)
const first = ref(0)
const rows = ref(20)
const filterValues = ref({})
const stats = ref({
  month_sum: '0',
  month_total: '0'
})

/**
 * Get sorted filters list
 */
const sortedFilters = computed(() => {
  return Object.entries(filters.value)
    .map(([key, config]) => ({ key, ...config }))
    .sort((a, b) => (a.position || 100) - (b.position || 100))
})

/**
 * Load orders list
 */
async function loadOrders() {
  loading.value = true

  try {
    const params = {
      start: first.value,
      limit: rows.value
    }

    // Apply filter values
    Object.keys(filterValues.value).forEach(key => {
      const value = filterValues.value[key]
      if (value !== null && value !== undefined && value !== '') {
        // Handle daterange type
        const filterConfig = filters.value[key]
        if (filterConfig?.type === 'daterange' && Array.isArray(value)) {
          if (value[0]) {
            params[filterConfig.fields?.from || `${key}_from`] = formatDateForApi(value[0])
          }
          if (value[1]) {
            params[filterConfig.fields?.to || `${key}_to`] = formatDateForApi(value[1])
          }
        } else if (filterConfig?.type === 'datepicker' && value) {
          params[key] = formatDateForApi(value)
        } else {
          params[key] = value
        }
      }
    })

    const response = await request.get('/api/mgr/orders', params)

    if (response && response.results) {
      orders.value = response.results
      totalRecords.value = response.total || 0
      if (response.stats) {
        stats.value = response.stats
      }
    } else {
      console.error('[OrdersGrid] Invalid response:', response)
      orders.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[OrdersGrid] Error loading orders:', error)
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
 * Handle pagination
 */
function onPage(event) {
  first.value = event.first
  rows.value = event.rows
  loadOrders()
}

/**
 * Format date for API (YYYY-MM-DD)
 */
function formatDateForApi(date) {
  if (!date) return null
  const d = new Date(date)
  return d.toISOString().split('T')[0]
}

/**
 * Open order for editing (redirect to order page)
 */
function editOrder(order) {
  const url = `?a=mgr/order&namespace=minishop3&id=${order.id}`
  window.location.href = url
}

/**
 * Delete order (called after confirmation in ActionsColumn)
 */
async function deleteOrder(order) {
  try {
    await request.delete(`/api/mgr/orders/${order.id}`)

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('order_deleted'),
      life: 3000
    })

    await loadOrders()
  } catch (error) {
    console.error('[OrdersGrid] Error deleting order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_deleting_data'),
      life: 5000
    })
  }
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
 * Get status color for tag
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

  return colorMap[color.toLowerCase()] || 'secondary'
}

/**
 * Load filters configuration
 */
async function loadFiltersConfig() {
  try {
    const response = await request.get('/api/mgr/orders/filters')
    console.log('[OrdersGrid] Filters response:', response)
    filters.value = response.filters || response || {}
    console.log('[OrdersGrid] Filters loaded:', filters.value)
    initFilterValues()
  } catch (error) {
    console.error('[OrdersGrid] Failed to load filters config:', error)
    filters.value = {}
  }
}

/**
 * Initialize filter values
 */
function initFilterValues() {
  const newValues = {}
  Object.keys(filters.value).forEach(key => {
    const filterConfig = filters.value[key]
    if (filterConfig.type === 'daterange') {
      newValues[key] = null
    } else {
      newValues[key] = null
    }
  })
  filterValues.value = newValues
}

/**
 * Apply filters
 */
function applyFilters() {
  first.value = 0
  loadOrders()
}

/**
 * Clear filters
 */
function clearFilters() {
  initFilterValues()
  first.value = 0
  loadOrders()
}

/**
 * Check if any filter has value
 */
const hasActiveFilters = computed(() => {
  return Object.values(filterValues.value).some(v => v !== null && v !== '' && v !== undefined)
})

/**
 * Load grid configuration
 */
async function loadGridConfig() {
  try {
    const response = await request.get('/api/mgr/grid-config/orders')
    columns.value = response.columns || []
  } catch (error) {
    console.error('[OrdersGrid] Failed to load grid config:', error)
    columns.value = getDefaultColumns()
  }
}

/**
 * Default columns (if API unavailable)
 */
function getDefaultColumns() {
  return [
    { name: 'id', label: 'ID', visible: true, sortable: true, frozen: true, width: '80px', isSystem: true },
    { name: 'num', label: _('order_num'), visible: true, sortable: true, filterable: true, width: '100px' },
    { name: 'customer', label: _('order_customer'), visible: true, filterable: true, type: 'template', template: '{first_name} {last_name}', minWidth: '150px' },
    { name: 'status_name', label: _('order_status'), visible: true, sortable: true, filterable: true, type: 'badge', width: '120px' },
    { name: 'cost', label: _('order_cost'), visible: true, sortable: true, type: 'price', width: '120px' },
    { name: 'delivery_name', label: _('order_delivery'), visible: true, sortable: true, filterable: true, width: '150px' },
    { name: 'payment_name', label: _('order_payment'), visible: true, sortable: true, filterable: true, width: '150px' },
    { name: 'createdon', label: _('order_createdon'), visible: true, sortable: true, type: 'datetime', width: '150px' },
    {
      name: 'actions',
      label: _('actions'),
      visible: true,
      isSystem: true,
      frozen: true,
      width: '120px',
      type: 'actions',
      actions: [
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: true, confirmMessage: 'order_delete_confirm_message' }
      ]
    }
  ]
}

/**
 * Get action configuration for column
 */
function getActionsConfig(column) {
  if (!column.actions || column.actions.length === 0) {
    return [
      { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
      { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: true, confirmMessage: 'order_delete_confirm_message' }
    ]
  }
  return column.actions
}

/**
 * Render column value by template
 */
function renderField(data, column) {
  if (column.template) {
    return column.template.replace(/\{(\w+)\}/g, (match, key) => data[key] || '')
  }
  return data[column.name]
}

/**
 * Get customer link URL
 */
function getCustomerLink(data) {
  if (data.customer_id) {
    return `?a=mgr/customers&namespace=minishop3&customer=${data.customer_id}`
  }
  return null
}

onMounted(async () => {
  await Promise.all([
    loadGridConfig(),
    loadFiltersConfig()
  ])
  await loadOrders()
})
</script>

<template>
  <div class="orders-grid">
    <Toast />
    <ConfirmDialog />

    <Card>
      <template #title>
        <div class="grid-header">
          <span>{{ _('orders_title') }}</span>
          <div class="grid-stats">
            <span class="stat-item">
              <i class="pi pi-calendar"></i>
              {{ _('orders_month') }}: <strong>{{ stats.month_total }}</strong>
            </span>
            <span class="stat-item">
              <i class="pi pi-wallet"></i>
              {{ _('orders_month_sum') }}: <strong>{{ stats.month_sum }}</strong>
            </span>
          </div>
        </div>
      </template>

      <template #content>
        <!-- Filters form -->
        <div v-if="sortedFilters.length > 0" class="filters-form mb-3 p-3 surface-ground" style="border-radius: 6px;">
          <div class="filters-row">
            <template v-for="filter in sortedFilters" :key="filter.key">
              <!-- Text input filter -->
              <div v-if="filter.type === 'text'" class="filter-item" :style="{ width: filter.width || '200px' }">
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <InputText
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  :placeholder="_(filter.placeholder || filter.label)"
                  class="w-full"
                  @keyup.enter="applyFilters"
                />
              </div>

              <!-- Select filter -->
              <div v-else-if="filter.type === 'select'" class="filter-item" :style="{ width: filter.width || '180px' }">
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <Select
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  :options="filter.options || []"
                  optionLabel="label"
                  optionValue="value"
                  :placeholder="_(filter.placeholder || 'all')"
                  :showClear="true"
                  class="w-full"
                  @change="applyFilters"
                />
              </div>

              <!-- Date picker filter -->
              <div v-else-if="filter.type === 'datepicker'" class="filter-item" :style="{ width: filter.width || '150px' }">
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <DatePicker
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  dateFormat="dd.mm.yy"
                  :showIcon="true"
                  :showButtonBar="true"
                  class="w-full"
                  @date-select="applyFilters"
                />
              </div>

              <!-- Date range filter -->
              <div v-else-if="filter.type === 'daterange'" class="filter-item" :style="{ width: filter.width || '280px' }">
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <DatePicker
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  selectionMode="range"
                  dateFormat="dd.mm.yy"
                  :showIcon="true"
                  :showButtonBar="true"
                  class="w-full"
                  @date-select="applyFilters"
                />
              </div>
            </template>
          </div>

          <!-- Filter buttons -->
          <div class="filter-buttons">
            <Button
              :label="_('apply_filters')"
              icon="pi pi-filter"
              size="small"
              @click="applyFilters"
            />
            <Button
              v-if="hasActiveFilters"
              :label="_('clear_filters')"
              icon="pi pi-filter-slash"
              severity="secondary"
              size="small"
              @click="clearFilters"
            />
          </div>
        </div>

        <!-- Table -->
        <DataTable
          :value="orders"
          :loading="loading"
          :paginator="true"
          :rows="rows"
          :totalRecords="totalRecords"
          :lazy="true"
          @page="onPage"
          stripedRows
          responsiveLayout="scroll"
        >
          <!-- Dynamic column rendering -->
          <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
            <!-- Actions column (special handling) -->
            <Column
              v-if="column.type === 'actions'"
              :header="column.label"
              :sortable="column.sortable"
              :frozen="column.frozen"
              :style="{ width: column.width }"
            >
              <template #body="{ data }">
                <ActionsColumn
                  :data="data"
                  :actions="getActionsConfig(column)"
                  grid-id="orders"
                  @edit="editOrder"
                  @delete="deleteOrder"
                  @refresh="loadOrders"
                />
              </template>
            </Column>

            <!-- Regular columns -->
            <Column
              v-else
              :field="column.name"
              :header="column.label"
              :sortable="column.sortable"
              :frozen="column.frozen"
              :style="{ width: column.width, minWidth: column.minWidth }"
            >
              <template #body="{ data }">
                <!-- Badge field (status) -->
                <Tag
                  v-if="column.type === 'badge'"
                  :value="data[column.name]"
                  :severity="getStatusSeverity(data.color)"
                  :style="data.color ? { backgroundColor: data.color, color: '#fff' } : {}"
                />
                <!-- Datetime field -->
                <span v-else-if="column.type === 'datetime'">
                  {{ formatDate(data[column.name]) }}
                </span>
                <!-- Price field -->
                <span v-else-if="column.type === 'price'">
                  {{ data[column.name + '_formatted'] || formatPrice(data[column.name]) }}
                </span>
                <!-- Weight field -->
                <span v-else-if="column.type === 'weight'">
                  {{ data[column.name + '_formatted'] || data[column.name] }}
                </span>
                <!-- Template field with customer link -->
                <template v-else-if="column.type === 'template' && column.name === 'customer'">
                  <a
                    v-if="getCustomerLink(data)"
                    :href="getCustomerLink(data)"
                    class="customer-link"
                    target="_blank"
                  >
                    {{ renderField(data, column) }}
                  </a>
                  <span v-else>
                    {{ renderField(data, column) }}
                  </span>
                </template>
                <!-- Template field (generic) -->
                <span v-else-if="column.template">
                  {{ renderField(data, column) }}
                </span>
                <!-- Regular text field -->
                <span v-else>
                  {{ data[column.name] }}
                </span>
              </template>
            </Column>
          </template>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.orders-grid {
  padding: 20px;
}

.grid-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.grid-stats {
  display: flex;
  gap: 1.5rem;
  font-size: 0.9rem;
  color: #64748b;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.stat-item i {
  color: #94a3b8;
}

.stat-item strong {
  color: #334155;
}

.customer-link {
  color: #3b82f6;
  text-decoration: none;
}

.customer-link:hover {
  text-decoration: underline;
}

.w-full {
  width: 100%;
}

.filters-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1rem;
}

.filter-item {
  display: flex;
  flex-direction: column;
  min-width: 150px;
}

.filter-item label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  color: #64748b;
}

.filter-buttons {
  display: flex;
  gap: 0.5rem;
}
</style>
