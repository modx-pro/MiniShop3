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
import InputNumber from 'primevue/inputnumber'
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
import ValidationRulesEditor from './ValidationRulesEditor.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const CONFIRM_GROUP = 'settings-deliveries'

// Bulk selection
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete,
} = useSelection({
  entityName: 'delivery',
  confirmGroup: CONFIRM_GROUP,
  deleteBulk: async ids => {
    await request.delete('/api/mgr/deliveries/bulk', { ids })
  },
  onSuccess: () => loadDeliveries(),
  getItemName: item => item.name,
})

const { columns, loadGridConfig } = useGridConfig({
  gridId: 'deliveries',
  responseKey: 'fields',
  getFallbackColumns,
})

const filterValues = ref({})
const filterableColumns = computed(() => columns.value.filter(col => col.filterable && col.visible))
const activeTab = ref('0')
const selectAll = ref(false)

const {
  loading,
  items: deliveries,
  total: totalRecords,
  load: loadDeliveries,
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

    return request.get('/api/mgr/deliveries', params, { signal })
  },
})

const {
  visible: editDialogVisible,
  isNew: isNewDelivery,
  saving,
  item: editingDelivery,
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
    weight_price: 0,
    distance_price: 0,
    free_delivery_amount: 0,
    position: 0,
    active: true,
    class: '',
    logo: '',
    validation_rules: '',
  }),
})

// Payments tab
const payments = ref([])
const deliveryPayments = ref([])
const loadingPayments = ref(false)

const deliveryAddCostBadgeKind = computed(() =>
  editingDelivery.value ? resolveAddCostPriceBadgeKind(editingDelivery.value.price) : null
)

const { onDragEnd } = useSortableList({
  items: deliveries,
  sortUrl: '/api/mgr/deliveries/sort',
  successMessage: _('delivery_order_saved'),
  reload: loadDeliveries,
})

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
 * Get fallback grid columns
 */
function getFallbackColumns() {
  return [
    { name: 'id', label: 'ID', visible: true, sortable: true, frozen: true, width: '5rem' },
    {
      name: 'name',
      label: _('delivery_name'),
      visible: true,
      sortable: true,
      filterable: true,
    },
    {
      name: 'price',
      label: _('delivery_price'),
      visible: true,
      sortable: true,
      width: '7.5rem',
    },
    {
      name: 'free_delivery_amount',
      label: _('delivery_free_amount'),
      visible: true,
      sortable: true,
      width: '9.375rem',
    },
    {
      name: 'active',
      label: _('delivery_active'),
      visible: true,
      sortable: true,
      type: 'boolean',
      width: '6.25rem',
    },
    {
      name: 'position',
      label: _('delivery_position'),
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
function createDelivery() {
  openCreate()
  activeTab.value = '0'
  deliveryPayments.value = []
}

/**
 * Open edit modal
 */
async function editDelivery(delivery) {
  openEdit(delivery)
  activeTab.value = '0'
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
 * Get translated payment name
 */
function getPaymentName(name) {
  return getDisplayName(name, _)
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
      await request.post(`/api/mgr/deliveries/${editingDelivery.value.id}/payments`, {
        payment_id: paymentId,
      })
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
      life: 5000,
    })
  }
}

/**
 * Save delivery (create or update)
 */
async function saveDelivery() {
  if (!editingDelivery.value.name) {
    toastWarn(_('delivery_name_required'))
    return
  }

  const created = isNewDelivery.value
  await runSave(async () => {
    if (created) {
      await request.post('/api/mgr/deliveries', editingDelivery.value)
      toastSuccess(_('delivery_created'))
    } else {
      await request.put(`/api/mgr/deliveries/${editingDelivery.value.id}`, editingDelivery.value)
      toastSuccess(_('delivery_updated'))
    }
    await loadDeliveries()
  })
}

/**
 * Delete delivery with confirmation
 */
function deleteDelivery(delivery) {
  confirm.require({
    group: CONFIRM_GROUP,
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
          life: 3000,
        })
        loadDeliveries()
      } catch (error) {
        console.error('[DeliveriesGrid] Error deleting delivery:', error)
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
  await loadDeliveries()
  await loadAllPayments()
})
</script>

<template>
  <div class="deliveries-grid">
    <Toast />
    <ConfirmDialog :group="CONFIRM_GROUP" append-to="self" />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('deliveries') }}</span>
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
        <table v-else class="deliveries-table">
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
                    :model-value="selectedItems.some(item => item.id === delivery.id)"
                    :binary="true"
                    @update:model-value="
                      val => {
                        if (val) {
                          selectedItems.push(delivery)
                        } else {
                          selectedItems = selectedItems.filter(item => item.id !== delivery.id)
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
                      :data="delivery"
                      :actions="getActionsConfig(column)"
                      :confirm-group="CONFIRM_GROUP"
                      grid-id="deliveries"
                      @edit="editDelivery"
                      @delete="deleteDelivery"
                      @refresh="loadDeliveries"
                    />
                  </td>
                  <!-- Name column with lexicon support -->
                  <td v-else-if="column.name === 'name'">
                    {{ resolveDisplayName(delivery.name) }}
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
                    <i
                      :class="
                        delivery[column.name]
                          ? 'pi pi-check text-success'
                          : 'pi pi-times text-danger'
                      "
                    ></i>
                  </td>
                  <!-- Regular column -->
                  <td v-else>
                    {{ formatCellValue(delivery[column.name], column) }}
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
      :style="{ width: '43.75rem' }"
      append-to="self"
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
                  <small class="form-hint">{{ _('delivery_name_hint') }}</small>
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
                  <div class="checkbox-field">
                    <Checkbox
                      v-model="editingDelivery.active"
                      :binary="true"
                      input-id="delivery-active"
                    />
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
                  <InputText
                    v-model="editingDelivery.class"
                    class="w-full"
                    :placeholder="_('delivery_class_placeholder')"
                  />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('ms3_validation_rules') }}</label>
                  <ValidationRulesEditor v-model="editingDelivery.validation_rules" />
                </div>

                <div class="form-row-group mb-3">
                  <div class="form-row">
                    <label>{{ _('ms3_add_cost') }}</label>
                    <div class="ms3-add-cost-field">
                      <InputText
                        v-model="editingDelivery.price"
                        class="w-full flex-1 min-w-[8rem]"
                      />
                      <Badge
                        v-if="deliveryAddCostBadgeKind === 'discount'"
                        severity="success"
                        :value="_('ms3_price_badge_discount')"
                        class="ms3-add-cost-badge"
                      />
                      <Badge
                        v-else-if="deliveryAddCostBadgeKind === 'markup'"
                        severity="secondary"
                        :value="_('ms3_price_badge_markup')"
                        class="ms3-add-cost-badge"
                      />
                    </div>
                    <small class="form-hint">{{ _('ms3_add_cost_help') }}</small>
                  </div>

                  <div class="form-row">
                    <label>{{ _('delivery_weight_price') }}</label>
                    <InputNumber
                      v-model="editingDelivery.weight_price"
                      class="w-full"
                      :min-fraction-digits="2"
                    />
                    <small class="form-hint">{{ _('ms3_weight_price_help') }}</small>
                  </div>
                </div>

                <div class="form-row-group mb-3">
                  <div class="form-row">
                    <label>{{ _('delivery_free_amount') }}</label>
                    <InputNumber
                      v-model="editingDelivery.free_delivery_amount"
                      class="w-full"
                      :min-fraction-digits="2"
                    />
                    <small class="form-hint">{{ _('ms3_free_delivery_amount_help') }}</small>
                  </div>

                  <div class="form-row">
                    <label>{{ _('ms3_distance_price') }}</label>
                    <InputNumber
                      v-model="editingDelivery.distance_price"
                      class="w-full"
                      :min-fraction-digits="2"
                    />
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
                  striped-rows
                  responsive-layout="scroll"
                  class="payments-table"
                >
                  <Column field="name" :header="_('payment_name')">
                    <template #body="{ data }">
                      <div class="payment-name-cell">
                        <img
                          v-if="data.logo"
                          :src="data.logo"
                          :alt="data.name"
                          class="payment-logo-small"
                        />
                        <span>{{ getPaymentName(data.name) }}</span>
                      </div>
                    </template>
                  </Column>
                  <Column field="price" :header="_('ms3_add_cost')" style="width: 9.375rem">
                    <template #body="{ data }">
                      <span v-if="data.price">{{ data.price }}</span>
                      <span v-else class="text-muted">—</span>
                    </template>
                  </Column>
                  <Column :header="_('delivery_active')" style="width: 6.25rem">
                    <template #body="{ data }">
                      <ToggleSwitch
                        :model-value="isPaymentEnabled(data.id)"
                        @update:model-value="val => togglePayment(data.id, val)"
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
        <Button :label="_('save')" icon="pi pi-check" :loading="saving" @click="saveDelivery" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.deliveries-grid {
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
  gap: 0.5rem;
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
  margin-bottom: 0.5rem;
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

.form-row-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 37.5rem) {
  .form-row-group {
    grid-template-columns: 1fr;
  }
}

/* Form hint */
.form-hint {
  color: var(--ms3-text-muted);
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

/* Payments tab styles */
.payments-list {
  padding: 1rem 0;
}

.payments-hint {
  color: var(--ms3-text-muted);
  font-size: 0.9rem;
}

.loading-payments {
  text-align: center;
  padding: 2rem;
  color: var(--ms3-text-muted);
}

.no-payments {
  text-align: center;
  padding: 2rem;
  color: var(--ms3-text-muted-light);
}

.payment-name-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.payment-logo-small {
  width: 1.5rem;
  height: 1.5rem;
  object-fit: contain;
  flex-shrink: 0;
}

.text-muted {
  color: var(--ms3-text-muted-light);
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
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color);
}

.deliveries-table th {
  background: var(--ms3-bg-slate);
  font-weight: 600;
  color: var(--ms3-text-header);
}

.deliveries-table tbody tr:hover {
  background: var(--ms3-bg-slate-alt);
}

/* Drag handle */
.drag-handle {
  cursor: grab;
  color: var(--ms3-text-light);
  padding: 0.25rem;
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
