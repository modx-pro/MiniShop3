<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import ToggleSwitch from 'primevue/toggleswitch'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import draggable from 'vuedraggable'
import request from '../request.js'
import { useLexicon } from '@vuetools/useLexicon'
import { useSelection } from '../composables/useSelection.js'
import ActionsColumn from './ActionsColumn.vue'
import FileBrowser from './FileBrowser.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

// Bulk selection
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete
} = useSelection({
  entityName: 'payment',
  deleteBulk: async (ids) => {
    await request.delete('/api/mgr/payments/bulk', { ids })
  },
  onSuccess: () => loadPayments(),
  getItemName: (item) => item.name
})

const columns = ref([])
const loading = ref(false)
const payments = ref([])
const totalRecords = ref(0)
const first = ref(0)
const rows = ref(20)
const filterValues = ref({})
const filterableColumns = computed(() => columns.value.filter(col => col.filterable && col.visible))
const searchQuery = ref('')
const editDialogVisible = ref(false)
const editingPayment = ref(null)
const isNewPayment = ref(false)
const saving = ref(false)
const activeTab = ref('0')
const selectAll = ref(false)

// Deliveries tab
const deliveries = ref([])
const paymentDeliveries = ref([])
const loadingDeliveries = ref(false)

/**
 * Load payments list
 */
async function loadPayments() {
  loading.value = true

  try {
    const params = {
      start: first.value,
      limit: rows.value
    }

    if (searchQuery.value) {
      params.query = searchQuery.value
    }

    Object.keys(filterValues.value).forEach(key => {
      const value = filterValues.value[key]
      if (value !== null && value !== undefined && value !== '') {
        params[`filter_${key}`] = value
      }
    })

    const response = await request.get('/api/mgr/payments', params)

    if (response && response.results) {
      payments.value = response.results
      totalRecords.value = response.total || 0
    } else {
      console.error('[PaymentsGrid] Invalid response:', response)
      payments.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[PaymentsGrid] Error loading payments:', error)
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
  loadPayments()
}

/**
 * Handle search
 */
function onSearch() {
  first.value = 0
  loadPayments()
}

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
 * Handle drag-drop reorder
 */
async function onDragEnd() {
  const ids = payments.value.map(p => p.id)
  try {
    await request.post('/api/mgr/payments/sort', { ids })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('payment_order_saved'),
      life: 2000
    })
  } catch (error) {
    console.error('[PaymentsGrid] Error saving order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000
    })
    loadPayments()
  }
}

/**
 * Normalize image path to always start with /
 */
function normalizeImagePath(path) {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
    return path
  }
  return '/' + path
}

/**
 * Open create modal
 */
function createPayment() {
  editingPayment.value = {
    name: '',
    description: '',
    price: '0',
    position: 0,
    active: true,
    class: '',
    logo: ''
  }
  isNewPayment.value = true
  activeTab.value = '0'
  paymentDeliveries.value = []
  editDialogVisible.value = true
}

/**
 * Open edit modal
 */
async function editPayment(payment) {
  editingPayment.value = { ...payment }
  isNewPayment.value = false
  activeTab.value = '0'
  editDialogVisible.value = true

  // Load deliveries for this payment
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
      await request.post(`/api/mgr/payments/${editingPayment.value.id}/deliveries`, { delivery_id: deliveryId })
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
      life: 5000
    })
  }
}

/**
 * Save payment (create or update)
 */
async function savePayment() {
  if (!editingPayment.value.name) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('payment_name_required'),
      life: 3000
    })
    return
  }

  saving.value = true

  try {
    let response
    if (isNewPayment.value) {
      response = await request.post('/api/mgr/payments', editingPayment.value)
    } else {
      response = await request.put(`/api/mgr/payments/${editingPayment.value.id}`, editingPayment.value)
    }

    if (response) {
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: isNewPayment.value ? _('payment_created') : _('payment_updated'),
        life: 3000
      })
      editDialogVisible.value = false
      loadPayments()
    }
  } catch (error) {
    console.error('[PaymentsGrid] Error saving payment:', error)
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
 * Delete payment with confirmation
 */
function deletePayment(payment) {
  confirm.require({
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
          life: 3000
        })
        loadPayments()
      } catch (error) {
        console.error('[PaymentsGrid] Error deleting payment:', error)
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
 * Apply filters
 */
function applyFilters() {
  first.value = 0
  loadPayments()
}

/**
 * Clear all filters
 */
function clearFilters() {
  filterValues.value = {}
  first.value = 0
  loadPayments()
}

/**
 * Get actions config for ActionsColumn
 */
function getActionsConfig(column) {
  const config = column.actions || []
  return config.map(action => ({
    ...action,
    label: _(action.label) || action.label
  }))
}

/**
 * Load grid configuration
 */
async function loadGridConfig() {
  try {
    const response = await request.get('/api/mgr/grid-config/payments')

    if (response && response.fields) {
      columns.value = response.fields
    } else {
      // Fallback default columns
      columns.value = [
        { name: 'id', label: 'ID', visible: true, sortable: true, frozen: true, width: '80px' },
        { name: 'name', label: _('payment_name'), visible: true, sortable: true, filterable: true },
        { name: 'price', label: _('ms3_add_cost'), visible: true, sortable: true, width: '120px' },
        { name: 'active', label: _('payment_active'), visible: true, sortable: true, type: 'boolean', width: '100px' },
        { name: 'position', label: _('payment_position'), visible: true, sortable: true, width: '100px' },
        { name: 'actions', label: _('actions'), visible: true, frozen: true, type: 'actions', width: '120px', actions: [
          { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
          { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: false }
        ]}
      ]
    }
  } catch (error) {
    console.error('[PaymentsGrid] Error loading grid config:', error)
  }
}

/**
 * Format value for display
 */
function formatValue(value, column) {
  if (value === null || value === undefined) return ''

  if (column.type === 'boolean') {
    return value ? _('yes') : _('no')
  }

  if (column.format === 'number') {
    return Number(value).toLocaleString()
  }

  return value
}

/**
 * Get display name - check if value is a lexicon key
 * If translation exists, return it; otherwise return original value
 */
function getDisplayName(name) {
  if (!name) return ''
  const translated = _(name)
  return translated !== name ? translated : name
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
    <ConfirmDialog appendTo="self" />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('ms3_payments') }}</span>
            <div class="grid-stats">
              <span class="stat-item">
                <i class="pi pi-list"></i>
                <span>{{ _('total') }}: <strong>{{ totalRecords }}</strong></span>
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
              <InputText
                v-model="filterValues[column.name]"
                :placeholder="column.label"
              />
            </template>
          </div>
          <div class="filter-buttons">
            <Button
              :label="_('apply_filters')"
              icon="pi pi-filter"
              @click="applyFilters"
            />
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
              <th style="width: 40px">
                <Checkbox
                  :modelValue="selectAll"
                  :binary="true"
                  @update:modelValue="onSelectAllChange"
                />
              </th>
              <th style="width: 40px"></th>
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
                    :modelValue="selectedItems.some(item => item.id === payment.id)"
                    :binary="true"
                    @update:modelValue="(val) => {
                      if (val) {
                        selectedItems.push(payment)
                      } else {
                        selectedItems = selectedItems.filter(item => item.id !== payment.id)
                      }
                    }"
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
                      grid-id="payments"
                      @edit="editPayment"
                      @delete="deletePayment"
                      @refresh="loadPayments"
                    />
                  </td>
                  <!-- Name column with lexicon support -->
                  <td v-else-if="column.name === 'name'">
                    {{ getDisplayName(payment.name) }}
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
                    <i :class="payment[column.name] ? 'pi pi-check text-success' : 'pi pi-times text-danger'"></i>
                  </td>
                  <!-- Regular column -->
                  <td v-else>
                    {{ formatValue(payment[column.name], column) }}
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
      :style="{ width: '600px' }"
      appendTo="self"
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
                    <Checkbox v-model="editingPayment.active" :binary="true" inputId="payment-active" />
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
                  <InputText v-model="editingPayment.class" class="w-full" :placeholder="_('payment_class_placeholder')" />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('ms3_add_cost') }}</label>
                  <InputText v-model="editingPayment.price" class="w-full" />
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
                  stripedRows
                  responsiveLayout="scroll"
                  class="deliveries-table"
                >
                  <Column field="name" :header="_('delivery_name')">
                    <template #body="{ data }">
                      <div class="delivery-name-cell">
                        <img v-if="data.logo" :src="data.logo" :alt="data.name" class="delivery-logo-small" />
                        <span>{{ getDisplayName(data.name) }}</span>
                      </div>
                    </template>
                  </Column>
                  <Column field="price" :header="_('ms3_add_cost')" style="width: 150px">
                    <template #body="{ data }">
                      <span v-if="data.price">{{ data.price }}</span>
                      <span v-else class="text-muted">—</span>
                    </template>
                  </Column>
                  <Column :header="_('payment_active')" style="width: 100px">
                    <template #body="{ data }">
                      <ToggleSwitch
                        :modelValue="isDeliveryEnabled(data.id)"
                        @update:modelValue="(val) => toggleDelivery(data.id, val)"
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
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          severity="secondary"
          @click="editDialogVisible = false"
        />
        <Button
          :label="_('save')"
          icon="pi pi-check"
          :loading="saving"
          @click="savePayment"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.payments-grid {
  padding: 20px;
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

.filters-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 6px;
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
  align-items: flex-end;
}

/* Bulk actions toolbar */
.bulk-actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 6px;
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  color: #92400e;
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
  color: #22c55e;
}

.text-danger {
  color: #ef4444;
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
  color: #374151;
}

/* Form hint */
.form-hint {
  color: #6b7280;
  font-size: 0.8rem;
  margin-top: 0.25rem;
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
  color: #6b7280;
  font-size: 0.9rem;
}

.loading-deliveries {
  text-align: center;
  padding: 2rem;
  color: #6b7280;
}

.no-deliveries {
  text-align: center;
  padding: 2rem;
  color: #9ca3af;
}

.delivery-name-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.delivery-logo-small {
  width: 24px;
  height: 24px;
  object-fit: contain;
  flex-shrink: 0;
}

.text-muted {
  color: #9ca3af;
}

/* Custom table styles */
.payments-table {
  width: 100%;
  border-collapse: collapse;
}

.payments-table th,
.payments-table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}

.payments-table th {
  background: #f8fafc;
  font-weight: 600;
  color: #475569;
}

.payments-table tbody tr:hover {
  background: #f1f5f9;
}

/* Drag handle */
.drag-handle {
  cursor: grab;
  color: #94a3b8;
  padding: 0.25rem;
}

.drag-handle:hover {
  color: #64748b;
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
  width: 40px;
  height: 40px;
  object-fit: contain;
  border-radius: 4px;
}

/* Loading overlay */
.loading-overlay {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 3rem;
  color: #64748b;
}

</style>
