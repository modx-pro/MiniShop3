<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'

import { useGridConfig } from '../composables/useGridConfig.js'
import { useGridFilters } from '../composables/useGridFilters.js'
import { useResourceList } from '../composables/useResourceList.js'
import { useSelection } from '../composables/useSelection.js'
import request from '../request.js'
import {
  formatDatePattern,
  formatPriceConfigured,
  renderField,
} from '../utils/displayFormatters.js'
import ActionsColumn from './ActionsColumn.vue'

const toast = useToast()
const { _ } = useLexicon()

const ms3Config = typeof ms3 !== 'undefined' ? ms3.config : null
const SHOW_DRAFTS_STORAGE_KEY = 'ms3_orders_show_drafts'

function readShowDraftsPreference() {
  try {
    const stored = localStorage.getItem(SHOW_DRAFTS_STORAGE_KEY)
    if (stored !== null) {
      return stored === '1'
    }
  } catch {
    /* localStorage unavailable */
  }
  return Boolean(ms3Config?.order_show_drafts)
}

const showDrafts = ref(readShowDraftsPreference())
const stats = ref({
  month_sum: '0',
  month_total: '0',
})

const {
  filterValues,
  sortedFilters,
  hasActiveFilters,
  setFilters,
  setDirectFilterKeys,
  appendFilterParams,
  initFilterValues,
} = useGridFilters()

const { columns, loadGridConfig } = useGridConfig({
  gridId: 'orders',
  responseKey: 'columns',
  getFallbackColumns: getDefaultColumns,
  onLoaded: response => {
    setDirectFilterKeys(response?.direct_filter_keys || [])
  },
})


/**
 * Build GET params shared by list and stats endpoints (#469).
 *
 * @param {{ includePagination?: boolean, start?: number, limit?: number, sort?: string|null, dir?: number }} options
 */
function buildOrderListParams({
  includePagination = true,
  start = null,
  limit = null,
  sort = null,
  dir = null,
} = {}) {
  const params = {
    show_drafts: showDrafts.value ? 1 : 0,
  }

  if (includePagination) {
    params.start = start ?? 0
    params.limit = limit ?? 20
    params.sort = sort || 'id'
    params.dir = dir === 1 ? 'ASC' : 'DESC'
  }

  appendFilterParams(params)
  return params
}

const {
  loading,
  items: orders,
  total: totalRecords,
  first,
  rows,
  sortField,
  sortOrder,
  load: loadOrders,
  onPage,
  onSort,
} = useResourceList({
  defaultSortField: 'id',
  defaultSortOrder: -1,
  fetchPage: async ({ first: start, rows: limit, sortField: sort, sortOrder: dir, signal }) => {
    const params = buildOrderListParams({
      includePagination: true,
      start,
      limit,
      sort,
      dir,
    })
    return request.get('/api/mgr/orders', params, { signal })
  },
})

// After useResourceList so onSuccess can call loadOrders from the list composable.
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete,
} = useSelection({
  entityName: 'order',
  deleteBulk: async ids => {
    await request.delete('/api/mgr/orders/bulk', { ids })
  },
  onSuccess: () => refreshGrid(),
  getItemName: item => `#${item.num || item.id}`,
})

/**
 * Load header stats for current filters (parallel to list, no pagination).
 */
async function loadOrderStats() {
  try {
    const response = await request.get(
      '/api/mgr/orders/stats',
      buildOrderListParams({ includePagination: false })
    )
    if (response && (response.month_total !== undefined || response.month_sum !== undefined)) {
      stats.value = response
    }
  } catch (error) {
    console.error('[OrdersGrid] Error loading order stats:', error)
  }
}

async function refreshGrid() {
  await Promise.all([loadOrders(), loadOrderStats()])
}

function applyFilters() {
  first.value = 0
  return refreshGrid()
}

function clearFilters() {
  initFilterValues()
  first.value = 0
  return refreshGrid()
}

/**
 * Open order for editing (redirect to order page)
 */
function editOrder(order) {
  const url = `?a=mgr/order&namespace=minishop3&id=${order.id}`
  window.location.href = url
}

/**
 * Create new order (redirect to create page)
 */
function createNewOrder() {
  window.location.href = '?a=mgr/order&namespace=minishop3&id=new'
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
      life: 3000,
    })

    await refreshGrid()
  } catch (error) {
    console.error('[OrdersGrid] Error deleting order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_deleting_data'),
      life: 5000,
    })
  }
}

/**
 * Format date with configurable format
 */
function formatDate(dateString, column = {}) {
  return formatDatePattern(dateString, column.format || 'dd.MM.yyyy HH:mm')
}

/**
 * Format price with configurable options
 */
function formatPrice(value, column = {}) {
  return formatPriceConfigured(value, column, ms3Config)
}

/**
 * Format weight with configurable options
 * @param {number} value - Weight value
 * @param {object} column - Column config with optional formatting properties
 */
function formatWeight(value, column = {}) {
  if (value === null || value === undefined) return '-'

  // Get config from column or use defaults from ms3.config

  const ms3Config = typeof ms3 !== 'undefined' ? ms3.config : null
  const decimals = column.decimals ?? ms3Config?.weight_decimals ?? 2
  const unit = column.unit ?? ms3Config?.weight_unit ?? 'кг'
  const unitPosition = column.unit_position ?? 'after'

  // Format number
  let formatted = Number(value).toFixed(decimals)

  // Add unit
  if (unit) {
    formatted = unitPosition === 'before' ? `${unit} ${formatted}` : `${formatted} ${unit}`
  }

  return formatted
}

/**
 * Get status color for tag
 */
function getStatusSeverity(color) {
  if (!color) return 'secondary'

  const colorMap = {
    '#97b94d': 'success',
    '#81d742': 'success',
    green: 'success',
    '#dd3d36': 'danger',
    red: 'danger',
    '#f0ad4e': 'warn',
    yellow: 'warn',
    '#5bc0de': 'info',
    blue: 'info',
  }

  return colorMap[color.toLowerCase()] || 'secondary'
}

/**
 * Get badge value from row data
 * Uses source_field from column config if specified, otherwise column.name
 */
function getBadgeValue(data, column) {
  const sourceField = column.source_field || column.name
  return data[sourceField] || ''
}

/**
 * Get badge color from row data using column config
 * Uses color_field from column config or defaults to 'color'
 */
function getBadgeColor(data, column) {
  const colorField = column.color_field || 'color'
  let color = data[colorField] || null
  // Add # prefix if color is hex without #
  if (color && !color.startsWith('#')) {
    color = '#' + color
  }
  return color
}

/**
 * Load filters configuration
 */
async function loadFiltersConfig() {
  try {
    const response = await request.get('/api/mgr/orders/filters')
    setFilters(response.filters || response || {})
  } catch (error) {
    console.error('[OrdersGrid] Failed to load filters config:', error)
    setFilters({})
  }
}

function toggleShowDrafts() {
  try {
    localStorage.setItem(SHOW_DRAFTS_STORAGE_KEY, showDrafts.value ? '1' : '0')
  } catch {
    /* localStorage unavailable */
  }
  first.value = 0
  return refreshGrid()
}

/**
 * Default columns (if API unavailable)
 */
function getDefaultColumns() {
  return [
    {
      name: 'id',
      label: 'ID',
      visible: true,
      sortable: true,
      frozen: true,
      width: '5rem',
      isSystem: true,
    },
    {
      name: 'num',
      label: _('order_num'),
      visible: true,
      sortable: true,
      filterable: true,
      width: '6.25rem',
    },
    {
      name: 'customer',
      label: _('order_customer'),
      visible: true,
      filterable: true,
      type: 'template',
      template: '{first_name} {last_name}',
      minWidth: '9.375rem',
    },
    {
      name: 'status_name',
      label: _('order_status'),
      visible: true,
      sortable: true,
      filterable: true,
      type: 'badge',
      width: '7.5rem',
    },
    {
      name: 'cost',
      label: _('order_cost'),
      visible: true,
      sortable: true,
      type: 'price',
      width: '7.5rem',
    },
    {
      name: 'delivery_name',
      label: _('order_delivery'),
      visible: true,
      sortable: true,
      filterable: true,
      width: '9.375rem',
    },
    {
      name: 'payment_name',
      label: _('order_payment'),
      visible: true,
      sortable: true,
      filterable: true,
      width: '9.375rem',
    },
    {
      name: 'createdon',
      label: _('order_createdon'),
      visible: true,
      sortable: true,
      type: 'datetime',
      width: '9.375rem',
    },
    {
      name: 'actions',
      label: _('actions'),
      visible: true,
      isSystem: true,
      frozen: true,
      width: '7.5rem',
      type: 'actions',
      actions: [
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        {
          name: 'delete',
          handler: 'delete',
          icon: 'pi-trash',
          label: 'delete',
          severity: 'danger',
          confirm: true,
          confirmMessage: 'order_delete_confirm_message',
        },
      ],
    },
  ]
}

/**
 * Get action configuration for column
 */
function getActionsConfig(column) {
  if (!column.actions || column.actions.length === 0) {
    return [
      { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
      {
        name: 'delete',
        handler: 'delete',
        icon: 'pi-trash',
        label: 'delete',
        severity: 'danger',
        confirm: true,
        confirmMessage: 'order_delete_confirm_message',
      },
    ]
  }
  return column.actions
}

/**
 * Get customer link URL
 */
function getCustomerLink(data) {
  if (data.customer_id) {
    return `?a=mgr/customers&namespace=minishop3&customer_id=${data.customer_id}`
  }
  return null
}

onMounted(async () => {
  await Promise.all([loadGridConfig(), loadFiltersConfig()])
  await refreshGrid()
})
</script>

<template>
  <div class="orders-grid">
    <Toast />
    <ConfirmDialog append-to="self" />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('orders_title') }}</span>
            <Button
              :label="_('ms3_order_create')"
              icon="pi pi-plus"
              severity="success"
              size="small"
              @click="createNewOrder"
            />
          </div>
          <div class="grid-header-right">
            <div class="grid-stats" :title="_('orders_stat_tooltip')">
              <span class="stat-item">
                <i class="pi pi-calendar"></i>
                {{ _('orders_month') }}: <strong>{{ stats.month_total }}</strong>
              </span>
              <span class="stat-item">
                <i class="pi pi-wallet"></i>
                {{ _('orders_month_sum') }}: <strong>{{ stats.month_sum }}</strong>
              </span>
            </div>
            <div class="show-drafts-toggle">
              <Checkbox
                v-model="showDrafts"
                input-id="orders-show-drafts"
                binary
                @change="toggleShowDrafts"
              />
              <label for="orders-show-drafts">{{ _('ms3_orders_show_drafts') }}</label>
            </div>
          </div>
        </div>
      </template>

      <template #content>
        <!-- Filters form -->
        <div
          v-if="sortedFilters.length > 0"
          class="filters-form mb-3 p-3 surface-ground"
          style="border-radius: 0.375rem"
        >
          <div class="filters-row">
            <template v-for="filter in sortedFilters" :key="filter.key">
              <!-- Text input filter -->
              <div
                v-if="filter.type === 'text'"
                class="filter-item"
                :style="{ width: filter.width || '12.5rem' }"
              >
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
              <div
                v-else-if="filter.type === 'select'"
                class="filter-item"
                :style="{ width: filter.width || '11.25rem' }"
              >
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <Select
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  :options="filter.options || []"
                  option-label="label"
                  option-value="value"
                  :placeholder="_(filter.placeholder || 'all')"
                  :show-clear="true"
                  class="w-full"
                  @change="applyFilters"
                />
              </div>

              <!-- Date picker filter -->
              <div
                v-else-if="filter.type === 'datepicker'"
                class="filter-item"
                :style="{ width: filter.width || '9.375rem' }"
              >
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <DatePicker
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  date-format="dd.mm.yy"
                  show-icon
                  fluid
                  icon-display="input"
                  :show-button-bar="true"
                  @date-select="applyFilters"
                />
              </div>

              <!-- Date range filter -->
              <div
                v-else-if="filter.type === 'daterange'"
                class="filter-item"
                :style="{ width: filter.width || '17.5rem' }"
              >
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <DatePicker
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  selection-mode="range"
                  date-format="dd.mm.yy"
                  show-icon
                  fluid
                  icon-display="input"
                  :show-button-bar="true"
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

        <!-- Bulk actions toolbar -->
        <div v-if="hasSelection" class="bulk-actions-bar mb-3">
          <div class="bulk-info">
            <i class="pi pi-check-square"></i>
            <span>{{ _('selected_count').replace('{count}', selectionCount) }}</span>
          </div>
          <div class="bulk-buttons">
            <Button
              :label="_('clear_selection')"
              icon="pi pi-times"
              severity="secondary"
              size="small"
              text
              @click="clearSelection"
            />
            <Button
              :label="_('delete_selected')"
              icon="pi pi-trash"
              severity="danger"
              size="small"
              :loading="bulkProcessing"
              @click="confirmBulkDelete"
            />
          </div>
        </div>

        <!-- Table -->
        <DataTable
          v-model:selection="selectedItems"
          :value="orders"
          :loading="loading"
          :paginator="true"
          :rows="rows"
          :total-records="totalRecords"
          :lazy="true"
          :sort-field="sortField"
          :sort-order="sortOrder"
          striped-rows
          responsive-layout="scroll"
          data-key="id"
          @page="onPage"
          @sort="onSort"
        >
          <!-- Selection column -->
          <Column selection-mode="multiple" header-style="width: 3rem" frozen />

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
                  @refresh="refreshGrid"
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
                <!-- Badge field (uses source_field for value, color_field for color) -->
                <Tag
                  v-if="column.type === 'badge'"
                  :value="getBadgeValue(data, column)"
                  :severity="getStatusSeverity(getBadgeColor(data, column))"
                  :style="
                    getBadgeColor(data, column)
                      ? {
                          backgroundColor: getBadgeColor(data, column),
                          color: 'var(--ms3-text-on-primary)',
                        }
                      : {}
                  "
                />
                <!-- Datetime field -->
                <span v-else-if="column.type === 'datetime'">
                  {{ formatDate(data[column.name], column) }}
                </span>
                <!-- Price field -->
                <span v-else-if="column.type === 'price'">
                  {{ data[column.name + '_formatted'] || formatPrice(data[column.name], column) }}
                </span>
                <!-- Weight field -->
                <span v-else-if="column.type === 'weight'">
                  {{ data[column.name + '_formatted'] || formatWeight(data[column.name], column) }}
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
  padding: 1.25rem;
}

.grid-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.grid-header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.grid-header-right {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-left: auto;
  font-size: 1rem;
}

.grid-stats {
  display: flex;
  gap: 1.5rem;
  color: var(--ms3-text-muted);
  cursor: help;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.stat-item i {
  color: var(--ms3-text-light);
}

.stat-item strong {
  color: var(--ms3-text-dark);
}

.customer-link {
  color: var(--ms3-accent-primary);
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
  min-width: 9.375rem;
}

.filter-item label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--ms3-text-muted);
}

.filter-buttons {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.show-drafts-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
}

.show-drafts-toggle label {
  cursor: pointer;
  user-select: none;
  font-size: 1rem;
}

/* Bulk actions toolbar */
.bulk-actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: var(--ms3-bg-warning);
  border: var(--ms3-border-width) solid var(--ms3-border-warning);
  border-radius: 0.375rem;
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  color: var(--ms3-text-warning);
}

.bulk-info i {
  font-size: 1.1rem;
}

.bulk-buttons {
  display: flex;
  gap: 0.5rem;
}
</style>
