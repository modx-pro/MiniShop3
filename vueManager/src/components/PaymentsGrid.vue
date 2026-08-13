<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import draggable from 'vuedraggable'

import { useCrudDialog } from '../composables/useCrudDialog.js'
import { useGridConfig } from '../composables/useGridConfig.js'
import { useResourceList } from '../composables/useResourceList.js'
import { useSelection } from '../composables/useSelection.js'
import { useSortableList } from '../composables/useSortableList.js'
import request from '../request.js'
import { resolveAddCostPriceBadgeKind } from '../utils/addCostPriceBadgeKind.js'
import { formatValue, getDisplayName, normalizeImagePath } from '../utils/displayFormatters.js'
import ActionsColumn from './ActionsColumn.vue'
import FileBrowser from './FileBrowser.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const CONFIRM_GROUP = 'settings-payments'

// Bulk selection
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete,
} = useSelection({
  entityName: 'payment',
  confirmGroup: CONFIRM_GROUP,
  deleteBulk: async ids => {
    await request.delete('/api/mgr/payments/bulk', { ids })
  },
  onSuccess: () => loadPayments(),
  getItemName: item => item.name,
})

const { columns, loadGridConfig } = useGridConfig({
  gridId: 'payments',
  responseKey: 'fields',
  getFallbackColumns,
})

const filterValues = ref({})
const filterableColumns = computed(() => columns.value.filter(col => col.filterable && col.visible))
const activeTab = ref('0')
const selectAll = ref(false)

const {
  loading,
  items: payments,
  total: totalRecords,
  load: loadPayments,
  resetPageAndLoad,
} = useResourceList({
  fetchPage: ({ first: start, rows: limit, signal }) => {
    const params = {
      start,
      limit,
    }

    Object.keys(filterValues.value).forEach(key => {
      const value = filterValues.value[key]
      if (value !== null && value !== undefined && value !== '') {
        params[`filter_${key}`] = value
      }
    })

    return request.get('/api/mgr/payments', params, { signal })
  },
})

const {
  visible: editDialogVisible,
  isNew: isNewPayment,
  saving,
  item: editingPayment,
  openCreate,
  openEdit,
  close,
  runSave,
  toastSuccess,
  toastWarn,
} = useCrudDialog({
  createDefaults: () => ({
    name: '',
    description: '',
    price: '0',
    position: 0,
    active: true,
    class: '',
    logo: '',
  }),
})

// Deliveries tab
const deliveries = ref([])
const paymentDeliveries = ref([])
const loadingDeliveries = ref(false)

const paymentAddCostBadgeKind = computed(() =>
  editingPayment.value ? resolveAddCostPriceBadgeKind(editingPayment.value.price) : null
)

const { onDragEnd } = useSortableList({
  items: payments,
  sortUrl: '/api/mgr/payments/sort',
  successMessage: _('payment_order_saved'),
  reload: loadPayments,
})

/**
 * Handle select all checkbox
 */
function onSelectAllChange(checked) {
  if (checked) {
    selectedItems.value = [...payments.value]
  } else {
    selectedItems.value = []
  }
  selectAll.value = checked
}

/**
 * Get fallback grid columns
 */
function getFallbackColumns() {
  return [
    { name: 'id', label: 'ID', visible: true, sortable: true, frozen: true, width: '5rem' },
    { name: 'name', label: _('payment_name'), visible: true, sortable: true, filterable: true },
    { name: 'price', label: _('ms3_add_cost'), visible: true, sortable: true, width: '7.5rem' },
    {
      name: 'active',
      label: _('payment_active'),
      visible: true,
      sortable: true,
      type: 'boolean',
      width: '6.25rem',
    },
    {
      name: 'position',
      label: _('payment_position'),
      visible: true,
      sortable: true,
      width: '6.25rem',
    },
    {
      name: 'actions',
      label: _('actions'),
      visible: true,
      frozen: true,
      type: 'actions',
      width: '7.5rem',
      actions: [
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        {
          name: 'delete',
          handler: 'delete',
          icon: 'pi-trash',
          label: 'delete',
          severity: 'danger',
          confirm: false,
        },
      ],
    },
  ]
}

/**
 * Open create modal
 */
function createPayment() {
  openCreate()
  activeTab.value = '0'
  paymentDeliveries.value = []
}

/**
 * Open edit modal
 */
async function editPayment(payment) {
  openEdit(payment)
  activeTab.value = '0'
  await loadPaymentDeliveries(payment.id)
}

/**
 * Load all deliveries
 */
async function loadAllDeliveries() {
  try {
    const response = await request.get('/api/mgr/deliveries', { limit: 0 })
    if (response && response.results) {
      deliveries.value = response.results
    }
  } catch (error) {
    console.error('[PaymentsGrid] Error loading deliveries:', error)
  }
}

/**
 * Load deliveries for specific payment
 */
async function loadPaymentDeliveries(paymentId) {
  loadingDeliveries.value = true
  try {
    const response = await request.get(`/api/mgr/payments/${paymentId}/deliveries`)
    if (response && response.results) {
      paymentDeliveries.value = response.results.map(d => d.delivery_id)
    } else {
      paymentDeliveries.value = []
    }
  } catch (error) {
    console.error('[PaymentsGrid] Error loading payment deliveries:', error)
    paymentDeliveries.value = []
  } finally {
    loadingDeliveries.value = false
  }
}

/**
 * Check if delivery is enabled for this payment
 */
function isDeliveryEnabled(deliveryId) {
  return paymentDeliveries.value.includes(deliveryId)
}

/**
 * Toggle delivery for payment
 */
async function toggleDelivery(deliveryId, newValue) {
  if (!editingPayment.value?.id) return

  const wasEnabled = paymentDeliveries.value.includes(deliveryId)

  // Optimistic update
  if (newValue) {
    paymentDeliveries.value.push(deliveryId)
  } else {
    paymentDeliveries.value = paymentDeliveries.value.filter(id => id !== deliveryId)
  }

  try {
    if (newValue) {
      await request.post(`/api/mgr/payments/${editingPayment.value.id}/deliveries`, {
        delivery_id: deliveryId,
      })
    } else {
      await request.delete(`/api/mgr/payments/${editingPayment.value.id}/deliveries/${deliveryId}`)
    }
  } catch (error) {
    console.error('[PaymentsGrid] Error toggling delivery:', error)
    // Revert on error
    if (wasEnabled) {
      paymentDeliveries.value.push(deliveryId)
    } else {
      paymentDeliveries.value = paymentDeliveries.value.filter(id => id !== deliveryId)
    }
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Save payment (create or update)
 */
async function savePayment() {
  if (!editingPayment.value.name) {
    toastWarn(_('payment_name_required'))
    return
  }

  const created = isNewPayment.value
  await runSave(async () => {
    if (created) {
      await request.post('/api/mgr/payments', editingPayment.value)
      toastSuccess(_('payment_created'))
    } else {
      await request.put(`/api/mgr/payments/${editingPayment.value.id}`, editingPayment.value)
      toastSuccess(_('payment_updated'))
    }
    await loadPayments()
  })
}

/**
 * Delete payment with confirmation
 */
function deletePayment(payment) {
  confirm.require({
    group: CONFIRM_GROUP,
    message: _('payment_delete_confirm_message').replace('{name}', payment.name),
    header: _('confirm_delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/payments/${payment.id}`)
        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('payment_deleted'),
          life: 3000,
        })
        loadPayments()
      } catch (error) {
        console.error('[PaymentsGrid] Error deleting payment:', error)
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
 * Apply filters
 */
function applyFilters() {
  resetPageAndLoad()
}

/**
 * Clear all filters
 */
function clearFilters() {
  filterValues.value = {}
  resetPageAndLoad()
}

/**
 * Get actions config for ActionsColumn
 */
function getActionsConfig(column) {
  const config = column.actions || []
  return config.map(action => ({
    ...action,
    label: _(action.label) || action.label,
  }))
}

/**
 * Format value for display
 */
function formatCellValue(value, column) {
  return formatValue(value, column, _)
}

/**
 * Get display name - check if value is a lexicon key
 */
function resolveDisplayName(name) {
  return getDisplayName(name, _)
}

onMounted(async () => {
  await loadGridConfig()
  await loadPayments()
  await loadAllDeliveries()
})
</script>

<template>
  <div class="payments-grid">
    <Toast />
    <ConfirmDialog :group="CONFIRM_GROUP" append-to="self" />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('ms3_payments') }}</span>
            <div class="grid-stats">
              <span class="stat-item">
                <i class="pi pi-list"></i>
                <span
                  >{{ _('total') }}: <strong>{{ totalRecords }}</strong></span
                >
              </span>
            </div>
          </div>
          <div class="grid-header-right">
            <Button
              :label="_('create')"
              icon="pi pi-plus"
              severity="success"
              @click="createPayment"
            />
          </div>
        </div>
      </template>

      <template #content>
        <!-- Filters -->
        <div v-if="filterableColumns.length > 0" class="filters-row">
          <div v-for="column in filterableColumns" :key="column.name" class="filter-item">
            <label>{{ column.label }}</label>
            <template v-if="column.type === 'boolean'">
              <select v-model="filterValues[column.name]" class="p-inputtext">
                <option value="">{{ _('all') }}</option>
                <option value="1">{{ _('yes') }}</option>
                <option value="0">{{ _('no') }}</option>
              </select>
            </template>
            <template v-else>
              <InputText v-model="filterValues[column.name]" :placeholder="column.label" />
            </template>
          </div>
          <div class="filter-buttons">
            <Button :label="_('apply_filters')" icon="pi pi-filter" @click="applyFilters" />
            <Button
              :label="_('clear_filters')"
              icon="pi pi-filter-slash"
              severity="secondary"
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
        <div v-if="loading" class="loading-overlay">
          <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
        </div>
        <table v-else class="payments-table">
          <thead>
            <tr>
              <th style="width: 2.5rem">
                <Checkbox
                  :model-value="selectAll"
                  :binary="true"
                  @update:model-value="onSelectAllChange"
                />
              </th>
              <th style="width: 2.5rem"></th>
              <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
                <th :style="{ width: column.width, minWidth: column.minWidth }">
                  {{ column.label }}
                </th>
              </template>
            </tr>
          </thead>
          <draggable
            v-model="payments"
            tag="tbody"
            handle=".drag-handle"
            item-key="id"
            @end="onDragEnd"
          >
            <template #item="{ element: payment }">
              <tr>
                <td>
                  <Checkbox
                    :model-value="selectedItems.some(item => item.id === payment.id)"
                    :binary="true"
                    @update:model-value="
                      val => {
                        if (val) {
                          selectedItems.push(payment)
                        } else {
                          selectedItems = selectedItems.filter(item => item.id !== payment.id)
                        }
                      }
                    "
                  />
                </td>
                <td>
                  <span class="drag-handle">
                    <i class="pi pi-bars"></i>
                  </span>
                </td>
                <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
                  <!-- Actions column -->
                  <td v-if="column.type === 'actions'">
                    <ActionsColumn
                      :data="payment"
                      :actions="getActionsConfig(column)"
                      :confirm-group="CONFIRM_GROUP"
                      grid-id="payments"
                      @edit="editPayment"
                      @delete="deletePayment"
                      @refresh="loadPayments"
                    />
                  </td>
                  <!-- Name column with lexicon support -->
                  <td v-else-if="column.name === 'name'">
                    {{ resolveDisplayName(payment.name) }}
                  </td>
                  <!-- Image column -->
                  <td v-else-if="column.type === 'image'">
                    <img
                      v-if="payment[column.name]"
                      :src="normalizeImagePath(payment[column.name])"
                      :alt="payment.name"
                      class="grid-thumbnail"
                    />
                  </td>
                  <!-- Boolean column -->
                  <td v-else-if="column.type === 'boolean'">
                    <i
                      :class="
                        payment[column.name]
                          ? 'pi pi-check text-success'
                          : 'pi pi-times text-danger'
                      "
                    ></i>
                  </td>
                  <!-- Regular column -->
                  <td v-else>
                    {{ formatCellValue(payment[column.name], column) }}
                  </td>
                </template>
              </tr>
            </template>
          </draggable>
        </table>
      </template>
    </Card>

    <!-- Edit/Create Dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="isNewPayment ? _('payment_create') : _('payment_edit')"
      :modal="true"
      :closable="true"
      :style="{ width: '37.5rem' }"
      append-to="self"
    >
      <div v-if="editingPayment">
        <Tabs v-model:value="activeTab">
          <TabList>
            <Tab value="0">{{ _('ms3_payment') }}</Tab>
            <Tab value="1">{{ _('ms3_settings') }}</Tab>
            <Tab v-if="!isNewPayment" value="2">{{ _('ms3_deliveries') }}</Tab>
          </TabList>

          <TabPanels>
            <!-- Tab 1: Info -->
            <TabPanel value="0">
              <div class="edit-form">
                <div class="form-row mb-3">
                  <label>{{ _('payment_name') }} *</label>
                  <InputText v-model="editingPayment.name" class="w-full" />
                  <small class="form-hint">{{ _('payment_name_hint') }}</small>
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('payment_description') }}</label>
                  <Textarea v-model="editingPayment.description" class="w-full" rows="3" />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('payment_logo') }}</label>
                  <FileBrowser
                    v-model="editingPayment.logo"
                    :placeholder="_('payment_logo_placeholder')"
                    allowed-file-types="jpg,jpeg,png,gif,webp,svg"
                  />
                </div>

                <div class="form-row">
                  <div class="checkbox-field">
                    <Checkbox
                      v-model="editingPayment.active"
                      :binary="true"
                      input-id="payment-active"
                    />
                    <label for="payment-active">{{ _('payment_active') }}</label>
                  </div>
                </div>
              </div>
            </TabPanel>

            <!-- Tab 2: Settings -->
            <TabPanel value="1">
              <div class="edit-form">
                <div class="form-row mb-3">
                  <label>{{ _('payment_class') }}</label>
                  <InputText
                    v-model="editingPayment.class"
                    class="w-full"
                    :placeholder="_('payment_class_placeholder')"
                  />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('ms3_add_cost') }}</label>
                  <div class="ms3-add-cost-field">
                    <InputText v-model="editingPayment.price" class="w-full flex-1 min-w-[8rem]" />
                    <Badge
                      v-if="paymentAddCostBadgeKind === 'discount'"
                      severity="success"
                      :value="_('ms3_price_badge_discount')"
                      class="ms3-add-cost-badge"
                    />
                    <Badge
                      v-else-if="paymentAddCostBadgeKind === 'markup'"
                      severity="secondary"
                      :value="_('ms3_price_badge_markup')"
                      class="ms3-add-cost-badge"
                    />
                  </div>
                  <small class="form-hint">{{ _('ms3_payment_add_cost_help') }}</small>
                </div>
              </div>
            </TabPanel>

            <!-- Tab 3: Deliveries -->
            <TabPanel v-if="!isNewPayment" value="2">
              <div class="deliveries-list">
                <p class="deliveries-hint mb-3">{{ _('ms3_payment_deliveries_hint') }}</p>
                <div v-if="loadingDeliveries" class="loading-deliveries">
                  <i class="pi pi-spin pi-spinner"></i> {{ _('loading') }}
                </div>
                <div v-else-if="deliveries.length === 0" class="no-deliveries">
                  {{ _('ms3_no_deliveries') }}
                </div>
                <DataTable
                  v-else
                  :value="deliveries"
                  striped-rows
                  responsive-layout="scroll"
                  class="deliveries-table"
                >
                  <Column field="name" :header="_('delivery_name')">
                    <template #body="{ data }">
                      <div class="delivery-name-cell">
                        <img
                          v-if="data.logo"
                          :src="data.logo"
                          :alt="data.name"
                          class="delivery-logo-small"
                        />
                        <span>{{ resolveDisplayName(data.name) }}</span>
                      </div>
                    </template>
                  </Column>
                  <Column field="price" :header="_('ms3_add_cost')" style="width: 9.375rem">
                    <template #body="{ data }">
                      <span v-if="data.price">{{ data.price }}</span>
                      <span v-else class="text-muted">—</span>
                    </template>
                  </Column>
                  <Column :header="_('payment_active')" style="width: 6.25rem">
                    <template #body="{ data }">
                      <ToggleSwitch
                        :model-value="isDeliveryEnabled(data.id)"
                        @update:model-value="val => toggleDelivery(data.id, val)"
                      />
                    </template>
                  </Column>
                </DataTable>
              </div>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </div>

      <template #footer>
        <Button :label="_('cancel')" icon="pi pi-times" severity="secondary" @click="close" />
        <Button :label="_('save')" icon="pi pi-check" :loading="saving" @click="savePayment" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.payments-grid {
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

.grid-stats {
  display: flex;
  gap: 1.5rem;
  font-size: 0.9rem;
  color: var(--ms3-text-muted);
}

.stat-item {
  display: flex;
  align-items: center;
  gap: var(--ms3-spacing-2);
}

.stat-item i {
  color: var(--ms3-text-light);
}

.stat-item strong {
  color: var(--ms3-text-dark);
}

.filters-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 1rem;
  background: var(--ms3-bg-slate);
  border-radius: 0.375rem;
}

.filter-item {
  display: flex;
  flex-direction: column;
  min-width: 9.375rem;
}

.filter-item label {
  display: block;
  margin-bottom: var(--ms3-spacing-2);
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--ms3-text-muted);
}

.filter-buttons {
  display: flex;
  gap: 0.5rem;
  align-items: flex-end;
}

/* Bulk actions toolbar */
.bulk-actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--ms3-spacing-3) var(--ms3-spacing-4);
  background: var(--ms3-bg-warning);
  border: var(--ms3-border-width) solid var(--ms3-border-warning);
  border-radius: var(--ms3-radius-md);
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: var(--ms3-spacing-2);
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

.w-full {
  width: 100%;
}

.text-success {
  color: var(--ms3-text-success);
}

.text-danger {
  color: var(--ms3-text-danger);
}

/* Edit form styles */
.edit-form {
  display: flex;
  flex-direction: column;
}

.mb-3 {
  margin-bottom: 1rem;
}

.form-row {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-row label {
  font-weight: 500;
  color: var(--ms3-text-primary);
}

/* Form hint */
.form-hint {
  color: var(--ms3-text-muted);
  font-size: 0.8rem;
  margin-top: var(--ms3-spacing-1);
}

/* Tabs styling */
:deep(.p-tabs) {
  margin-bottom: 0;
}

:deep(.p-tabpanel) {
  padding: 1rem 0;
}

/* Deliveries tab styles */
.deliveries-list {
  padding: 1rem 0;
}

.deliveries-hint {
  color: var(--ms3-text-muted);
  font-size: 0.9rem;
}

.loading-deliveries {
  text-align: center;
  padding: 2rem;
  color: var(--ms3-text-muted);
}

.no-deliveries {
  text-align: center;
  padding: 2rem;
  color: var(--ms3-text-muted-light);
}

.delivery-name-cell {
  display: flex;
  align-items: center;
  gap: var(--ms3-spacing-3);
}

.delivery-logo-small {
  width: 1.5rem;
  height: 1.5rem;
  object-fit: contain;
  flex-shrink: 0;
}

.text-muted {
  color: var(--ms3-text-muted-light);
}

/* Custom table styles */
.payments-table {
  width: 100%;
  border-collapse: collapse;
}

.payments-table th,
.payments-table td {
  padding: var(--ms3-spacing-3);
  text-align: left;
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color);
}

.payments-table th {
  background: var(--ms3-bg-slate);
  font-weight: 600;
  color: var(--ms3-text-header);
}

.payments-table tbody tr:hover {
  background: var(--ms3-bg-slate-alt);
}

/* Drag handle */
.drag-handle {
  cursor: grab;
  color: var(--ms3-text-light);
  padding: var(--ms3-spacing-1);
}

.drag-handle:hover {
  color: var(--ms3-text-muted);
}

.drag-handle:active {
  cursor: grabbing;
}

/* Checkbox with label */
.checkbox-field {
  display: flex;
  align-items: center;
}

.checkbox-field label {
  margin-left: 0.5rem;
  cursor: pointer;
}

/* Grid thumbnail */
.grid-thumbnail {
  width: 2.5rem;
  height: 2.5rem;
  object-fit: contain;
  border-radius: 0.25rem;
}

/* Loading overlay */
.loading-overlay {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 3rem;
  color: var(--ms3-text-muted);
}

.ms3-add-cost-field {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
}

.ms3-add-cost-badge {
  flex-shrink: 0;
}
</style>
