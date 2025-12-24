<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
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
import { useLexicon } from '../composables/useLexicon.js'
import { useSelection } from '../composables/useSelection.js'
import ActionsColumn from './ActionsColumn.vue'
import ValidationRulesEditor from './ValidationRulesEditor.vue'
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
  entityName: 'delivery',
  deleteBulk: async (ids) => {
    await request.delete('/api/mgr/deliveries/bulk', { ids })
  },
  onSuccess: () => loadDeliveries(),
  getItemName: (item) => item.name
})

const columns = ref([])
const loading = ref(false)
const deliveries = ref([])
const totalRecords = ref(0)
const first = ref(0)
const rows = ref(20)
const filterValues = ref({})
const filterableColumns = computed(() => columns.value.filter(col => col.filterable && col.visible))
const searchQuery = ref('')
const editDialogVisible = ref(false)
const editingDelivery = ref(null)
const isNewDelivery = ref(false)
const saving = ref(false)
const activeTab = ref('0')
const selectAll = ref(false)

// Payments tab
const payments = ref([])
const deliveryPayments = ref([])
const loadingPayments = ref(false)

/**
 * Load deliveries list
 */
async function loadDeliveries() {
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

    const response = await request.get('/api/mgr/deliveries', params)

    if (response && response.results) {
      deliveries.value = response.results
      totalRecords.value = response.total || 0
    } else {
      console.error('[DeliveriesGrid] Invalid response:', response)
      deliveries.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[DeliveriesGrid] Error loading deliveries:', error)
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
  loadDeliveries()
}

/**
 * Handle search
 */
function onSearch() {
  first.value = 0
  loadDeliveries()
}

/**
 * Handle select all checkbox
 */
function onSelectAllChange(checked) {
  if (checked) {
    selectedItems.value = [...deliveries.value]
  } else {
    selectedItems.value = []
  }
  selectAll.value = checked
}

/**
 * Handle drag-drop reorder
 */
async function onDragEnd() {
  const ids = deliveries.value.map(d => d.id)
  try {
    await request.post('/api/mgr/deliveries/sort', { ids })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('delivery_order_saved'),
      life: 2000
    })
  } catch (error) {
    console.error('[DeliveriesGrid] Error saving order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000
    })
    loadDeliveries()
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
function createDelivery() {
  editingDelivery.value = {
    name: '',
    description: '',
    price: '0',
    weight_price: 0,
    distance_price: 0,
    free_delivery_amount: 0,
    position: 0,
    active: true,
    class: '',
    logo: '',
    validation_rules: ''
  }
  isNewDelivery.value = true
  activeTab.value = '0'
  deliveryPayments.value = []
  editDialogVisible.value = true
}

/**
 * Open edit modal
 */
async function editDelivery(delivery) {
  editingDelivery.value = { ...delivery }
  isNewDelivery.value = false
  activeTab.value = '0'
  editDialogVisible.value = true

  // Load payments for this delivery
  await loadDeliveryPayments(delivery.id)
}

/**
 * Load all payments
 */
async function loadAllPayments() {
  try {
    const response = await request.get('/api/mgr/payments', { limit: 0 })
    if (response && response.results) {
      payments.value = response.results
    }
  } catch (error) {
    console.error('[DeliveriesGrid] Error loading payments:', error)
  }
}

/**
 * Load payments for specific delivery
 */
async function loadDeliveryPayments(deliveryId) {
  loadingPayments.value = true
  try {
    const response = await request.get(`/api/mgr/deliveries/${deliveryId}/payments`)
    if (response && response.results) {
      deliveryPayments.value = response.results.map(p => p.payment_id)
    } else {
      deliveryPayments.value = []
    }
  } catch (error) {
    console.error('[DeliveriesGrid] Error loading delivery payments:', error)
    deliveryPayments.value = []
  } finally {
    loadingPayments.value = false
  }
}

/**
 * Check if payment is enabled for this delivery
 */
function isPaymentEnabled(paymentId) {
  return deliveryPayments.value.includes(paymentId)
}

/**
 * Toggle payment for delivery
 */
async function togglePayment(paymentId, newValue) {
  if (!editingDelivery.value?.id) return

  const wasEnabled = deliveryPayments.value.includes(paymentId)

  // Optimistic update
  if (newValue) {
    deliveryPayments.value.push(paymentId)
  } else {
    deliveryPayments.value = deliveryPayments.value.filter(id => id !== paymentId)
  }

  try {
    if (newValue) {
      await request.post(`/api/mgr/deliveries/${editingDelivery.value.id}/payments`, { payment_id: paymentId })
    } else {
      await request.delete(`/api/mgr/deliveries/${editingDelivery.value.id}/payments/${paymentId}`)
    }
  } catch (error) {
    console.error('[DeliveriesGrid] Error toggling payment:', error)
    // Revert on error
    if (wasEnabled) {
      deliveryPayments.value.push(paymentId)
    } else {
      deliveryPayments.value = deliveryPayments.value.filter(id => id !== paymentId)
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
 * Save delivery (create or update)
 */
async function saveDelivery() {
  if (!editingDelivery.value.name) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('delivery_name_required'),
      life: 3000
    })
    return
  }

  saving.value = true

  try {
    let response
    if (isNewDelivery.value) {
      response = await request.post('/api/mgr/deliveries', editingDelivery.value)
    } else {
      response = await request.put(`/api/mgr/deliveries/${editingDelivery.value.id}`, editingDelivery.value)
    }

    if (response) {
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: isNewDelivery.value ? _('delivery_created') : _('delivery_updated'),
        life: 3000
      })
      editDialogVisible.value = false
      loadDeliveries()
    }
  } catch (error) {
    console.error('[DeliveriesGrid] Error saving delivery:', error)
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
 * Delete delivery with confirmation
 */
function deleteDelivery(delivery) {
  confirm.require({
    message: _('delivery_delete_confirm_message').replace('{name}', delivery.name),
    header: _('confirm_delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/deliveries/${delivery.id}`)
        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('delivery_deleted'),
          life: 3000
        })
        loadDeliveries()
      } catch (error) {
        console.error('[DeliveriesGrid] Error deleting delivery:', error)
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
  loadDeliveries()
}

/**
 * Clear all filters
 */
function clearFilters() {
  filterValues.value = {}
  first.value = 0
  loadDeliveries()
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
    const response = await request.get('/api/mgr/grid-config/deliveries')

    if (response && response.fields) {
      columns.value = response.fields
    } else {
      // Fallback default columns
      columns.value = [
        { name: 'id', label: 'ID', visible: true, sortable: true, frozen: true, width: '80px' },
        { name: 'name', label: _('delivery_name'), visible: true, sortable: true, filterable: true },
        { name: 'price', label: _('delivery_price'), visible: true, sortable: true, width: '120px' },
        { name: 'free_delivery_amount', label: _('delivery_free_amount'), visible: true, sortable: true, width: '150px' },
        { name: 'active', label: _('delivery_active'), visible: true, sortable: true, type: 'boolean', width: '100px' },
        { name: 'position', label: _('delivery_position'), visible: true, sortable: true, width: '100px' },
        { name: 'actions', label: _('actions'), visible: true, frozen: true, type: 'actions', width: '120px', actions: [
          { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
          { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: false }
        ]}
      ]
    }
  } catch (error) {
    console.error('[DeliveriesGrid] Error loading grid config:', error)
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
  // If translation was found (different from key), return it
  // Otherwise return original name
  return translated !== name ? translated : name
}

onMounted(async () => {
  await loadGridConfig()
  await loadDeliveries()
  await loadAllPayments()
})
</script>

<template>
  <div class="deliveries-grid">
    <Toast />
    <ConfirmDialog />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('deliveries') }}</span>
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
              @click="createDelivery"
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
        <table v-else class="deliveries-table">
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
            v-model="deliveries"
            tag="tbody"
            handle=".drag-handle"
            item-key="id"
            @end="onDragEnd"
          >
            <template #item="{ element: delivery }">
              <tr>
                <td>
                  <Checkbox
                    :modelValue="selectedItems.some(item => item.id === delivery.id)"
                    :binary="true"
                    @update:modelValue="(val) => {
                      if (val) {
                        selectedItems.push(delivery)
                      } else {
                        selectedItems = selectedItems.filter(item => item.id !== delivery.id)
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
                      :data="delivery"
                      :actions="getActionsConfig(column)"
                      grid-id="deliveries"
                      @edit="editDelivery"
                      @delete="deleteDelivery"
                      @refresh="loadDeliveries"
                    />
                  </td>
                  <!-- Name column with lexicon support -->
                  <td v-else-if="column.name === 'name'">
                    {{ getDisplayName(delivery.name) }}
                  </td>
                  <!-- Image column -->
                  <td v-else-if="column.type === 'image'">
                    <img
                      v-if="delivery[column.name]"
                      :src="normalizeImagePath(delivery[column.name])"
                      :alt="delivery.name"
                      class="grid-thumbnail"
                    />
                  </td>
                  <!-- Boolean column -->
                  <td v-else-if="column.type === 'boolean'">
                    <i :class="delivery[column.name] ? 'pi pi-check text-success' : 'pi pi-times text-danger'"></i>
                  </td>
                  <!-- Regular column -->
                  <td v-else>
                    {{ formatValue(delivery[column.name], column) }}
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
      :header="isNewDelivery ? _('delivery_create') : _('delivery_edit')"
      :modal="true"
      :closable="true"
      :style="{ width: '700px' }"
    >
      <div v-if="editingDelivery">
        <Tabs v-model:value="activeTab">
          <TabList>
            <Tab value="0">{{ _('ms3_delivery') }}</Tab>
            <Tab value="1">{{ _('ms3_settings') }}</Tab>
            <Tab v-if="!isNewDelivery" value="2">{{ _('ms3_payments') }}</Tab>
          </TabList>

          <TabPanels>
            <!-- Tab 1: Info -->
            <TabPanel value="0">
              <div class="edit-form">
                <div class="form-row mb-3">
                  <label>{{ _('delivery_name') }} *</label>
                  <InputText v-model="editingDelivery.name" class="w-full" />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('delivery_description') }}</label>
                  <Textarea v-model="editingDelivery.description" class="w-full" rows="3" />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('delivery_logo') }}</label>
                  <FileBrowser
                    v-model="editingDelivery.logo"
                    :placeholder="_('delivery_logo_placeholder')"
                    allowed-file-types="jpg,jpeg,png,gif,webp,svg"
                  />
                </div>

                <div class="form-row">
                  <div class="flex align-items-center gap-2">
                    <Checkbox v-model="editingDelivery.active" :binary="true" inputId="delivery-active" />
                    <label for="delivery-active">{{ _('delivery_active') }}</label>
                  </div>
                </div>
              </div>
            </TabPanel>

            <!-- Tab 2: Settings -->
            <TabPanel value="1">
              <div class="edit-form">
                <div class="form-row mb-3">
                  <label>{{ _('delivery_class') }}</label>
                  <InputText v-model="editingDelivery.class" class="w-full" :placeholder="_('delivery_class_placeholder')" />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('ms3_validation_rules') }}</label>
                  <ValidationRulesEditor v-model="editingDelivery.validation_rules" />
                </div>

                <div class="form-row-group mb-3">
                  <div class="form-row">
                    <label>{{ _('ms3_add_cost') }}</label>
                    <InputText v-model="editingDelivery.price" class="w-full" />
                    <small class="form-hint">{{ _('ms3_add_cost_help') }}</small>
                  </div>

                  <div class="form-row">
                    <label>{{ _('delivery_weight_price') }}</label>
                    <InputNumber v-model="editingDelivery.weight_price" class="w-full" :minFractionDigits="2" />
                    <small class="form-hint">{{ _('ms3_weight_price_help') }}</small>
                  </div>
                </div>

                <div class="form-row-group mb-3">
                  <div class="form-row">
                    <label>{{ _('delivery_free_amount') }}</label>
                    <InputNumber v-model="editingDelivery.free_delivery_amount" class="w-full" :minFractionDigits="2" />
                    <small class="form-hint">{{ _('ms3_free_delivery_amount_help') }}</small>
                  </div>

                  <div class="form-row">
                    <label>{{ _('ms3_distance_price') }}</label>
                    <InputNumber v-model="editingDelivery.distance_price" class="w-full" :minFractionDigits="2" />
                    <small class="form-hint">{{ _('ms3_distance_price_help') }}</small>
                  </div>
                </div>
              </div>
            </TabPanel>

            <!-- Tab 3: Payments -->
            <TabPanel v-if="!isNewDelivery" value="2">
              <div class="payments-list">
                <p class="payments-hint mb-3">{{ _('ms3_delivery_payments_hint') }}</p>
                <div v-if="loadingPayments" class="loading-payments">
                  <i class="pi pi-spin pi-spinner"></i> {{ _('loading') }}
                </div>
                <div v-else-if="payments.length === 0" class="no-payments">
                  {{ _('ms3_no_payments') }}
                </div>
                <DataTable
                  v-else
                  :value="payments"
                  stripedRows
                  responsiveLayout="scroll"
                  class="payments-table"
                >
                  <Column field="name" :header="_('payment_name')">
                    <template #body="{ data }">
                      <div class="payment-name-cell">
                        <img v-if="data.logo" :src="data.logo" :alt="data.name" class="payment-logo-small" />
                        <span>{{ data.name }}</span>
                      </div>
                    </template>
                  </Column>
                  <Column field="price" :header="_('ms3_add_cost')" style="width: 150px">
                    <template #body="{ data }">
                      <span v-if="data.price">{{ data.price }}</span>
                      <span v-else class="text-muted">—</span>
                    </template>
                  </Column>
                  <Column :header="_('delivery_active')" style="width: 100px">
                    <template #body="{ data }">
                      <ToggleSwitch
                        :modelValue="isPaymentEnabled(data.id)"
                        @update:modelValue="(val) => togglePayment(data.id, val)"
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
          @click="saveDelivery"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.deliveries-grid {
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

.form-row-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 600px) {
  .form-row-group {
    grid-template-columns: 1fr;
  }
}

/* Form hint */
.form-hint {
  color: #6b7280;
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

/* Payments tab styles */
.payments-list {
  padding: 1rem 0;
}

.payments-hint {
  color: #6b7280;
  font-size: 0.9rem;
}

.loading-payments {
  text-align: center;
  padding: 2rem;
  color: #6b7280;
}

.no-payments {
  text-align: center;
  padding: 2rem;
  color: #9ca3af;
}

.payment-name-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.payment-logo-small {
  width: 24px;
  height: 24px;
  object-fit: contain;
  flex-shrink: 0;
}

.text-muted {
  color: #9ca3af;
}

/* Tabs styling */
:deep(.p-tabs) {
  margin-bottom: 0;
}

:deep(.p-tabpanel) {
  padding: 1rem 0;
}

/* Custom table styles */
.deliveries-table {
  width: 100%;
  border-collapse: collapse;
}

.deliveries-table th,
.deliveries-table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}

.deliveries-table th {
  background: #f8fafc;
  font-weight: 600;
  color: #475569;
}

.deliveries-table tbody tr:hover {
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
