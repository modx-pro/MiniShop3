<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import request from '../request.js'
import { useLexicon } from '../composables/useLexicon.js'
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
  entityName: 'vendor',
  deleteBulk: async (ids) => {
    await request.delete('/api/mgr/vendors/bulk', { ids })
  },
  onSuccess: () => loadVendors(),
  getItemName: (item) => item.name
})

const columns = ref([])
const loading = ref(false)
const vendors = ref([])
const totalRecords = ref(0)
const first = ref(0)
const rows = ref(20)
const filterValues = ref({})
const filterableColumns = computed(() => columns.value.filter(col => col.filterable && col.visible))
const editDialogVisible = ref(false)
const editingVendor = ref(null)
const isNewVendor = ref(false)
const saving = ref(false)
const activeTab = ref('0')

/**
 * Load vendors list
 */
async function loadVendors() {
  loading.value = true

  try {
    const params = {
      start: first.value,
      limit: rows.value
    }

    Object.keys(filterValues.value).forEach(key => {
      const value = filterValues.value[key]
      if (value !== null && value !== undefined && value !== '') {
        params[`filter_${key}`] = value
      }
    })

    const response = await request.get('/api/mgr/vendors', params)

    if (response && response.results) {
      vendors.value = response.results
      totalRecords.value = response.total || 0
    } else {
      console.error('[VendorsGrid] Invalid response:', response)
      vendors.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[VendorsGrid] Error loading vendors:', error)
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
  loadVendors()
}

/**
 * Open create modal
 */
function createVendor() {
  editingVendor.value = {
    name: '',
    description: '',
    country: '',
    logo: '',
    address: '',
    phone: '',
    email: '',
    resource_id: null
  }
  isNewVendor.value = true
  activeTab.value = '0'
  editDialogVisible.value = true
}

/**
 * Open edit modal
 */
function editVendor(vendor) {
  editingVendor.value = { ...vendor }
  isNewVendor.value = false
  activeTab.value = '0'
  editDialogVisible.value = true
}

/**
 * Save vendor (create or update)
 */
async function saveVendor() {
  if (!editingVendor.value.name) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('vendor_name_required'),
      life: 3000
    })
    return
  }

  saving.value = true

  try {
    let response
    if (isNewVendor.value) {
      response = await request.post('/api/mgr/vendors', editingVendor.value)
    } else {
      response = await request.put(`/api/mgr/vendors/${editingVendor.value.id}`, editingVendor.value)
    }

    if (response) {
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: isNewVendor.value ? _('vendor_created') : _('vendor_updated'),
        life: 3000
      })
      editDialogVisible.value = false
      loadVendors()
    }
  } catch (error) {
    console.error('[VendorsGrid] Error saving vendor:', error)
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
 * Delete vendor with confirmation
 */
function deleteVendor(vendor) {
  confirm.require({
    message: _('vendor_delete_confirm_message').replace('{name}', vendor.name),
    header: _('confirm_delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/vendors/${vendor.id}`)
        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('vendor_deleted'),
          life: 3000
        })
        loadVendors()
      } catch (error) {
        console.error('[VendorsGrid] Error deleting vendor:', error)
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
  loadVendors()
}

/**
 * Clear all filters
 */
function clearFilters() {
  filterValues.value = {}
  first.value = 0
  loadVendors()
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
    const response = await request.get('/api/mgr/grid-config/vendors')

    if (response && response.fields) {
      columns.value = response.fields
    } else {
      // Fallback default columns
      columns.value = [
        { name: 'id', label: 'ID', visible: true, sortable: true, frozen: true, width: '80px' },
        { name: 'name', label: _('vendor_name'), visible: true, sortable: true, filterable: true },
        { name: 'country', label: _('vendor_country'), visible: true, sortable: true, width: '150px' },
        { name: 'email', label: _('vendor_email'), visible: true, sortable: true, width: '200px' },
        { name: 'phone', label: _('vendor_phone'), visible: true, sortable: true, width: '150px' },
        { name: 'actions', label: _('actions'), visible: true, frozen: true, type: 'actions', width: '120px', actions: [
          { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
          { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: false }
        ]}
      ]
    }
  } catch (error) {
    console.error('[VendorsGrid] Error loading grid config:', error)
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

onMounted(async () => {
  await loadGridConfig()
  await loadVendors()
})
</script>

<template>
  <div class="vendors-grid">
    <Toast />
    <ConfirmDialog />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('ms3_vendors') }}</span>
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
              @click="createVendor"
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
        <DataTable
          v-model:selection="selectedItems"
          :value="vendors"
          :loading="loading"
          :paginator="true"
          :rows="rows"
          :totalRecords="totalRecords"
          :lazy="true"
          @page="onPage"
          stripedRows
          responsiveLayout="scroll"
          dataKey="id"
        >
          <!-- Selection column -->
          <Column selectionMode="multiple" headerStyle="width: 3rem" frozen />

          <!-- Dynamic column rendering -->
          <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
            <!-- Actions column -->
            <Column
              v-if="column.type === 'actions'"
              :header="column.label"
              :frozen="column.frozen"
              :style="{ width: column.width }"
            >
              <template #body="{ data }">
                <ActionsColumn
                  :data="data"
                  :actions="getActionsConfig(column)"
                  grid-id="vendors"
                  @edit="editVendor"
                  @delete="deleteVendor"
                  @refresh="loadVendors"
                />
              </template>
            </Column>

            <!-- Boolean column -->
            <Column
              v-else-if="column.type === 'boolean'"
              :field="column.name"
              :header="column.label"
              :sortable="column.sortable"
              :frozen="column.frozen"
              :style="{ width: column.width }"
            >
              <template #body="{ data }">
                <i
                  :class="data[column.name] ? 'pi pi-check text-success' : 'pi pi-times text-danger'"
                ></i>
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
                {{ formatValue(data[column.name], column) }}
              </template>
            </Column>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Edit/Create Dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="isNewVendor ? _('vendor_create') : _('vendor_edit')"
      :modal="true"
      :closable="true"
      :style="{ width: '700px' }"
    >
      <div v-if="editingVendor">
        <Tabs v-model:value="activeTab">
          <TabList>
            <Tab value="0">{{ _('ms3_vendor') }}</Tab>
            <Tab value="1">{{ _('vendor_contacts') }}</Tab>
          </TabList>

          <TabPanels>
            <!-- Tab 1: Info -->
            <TabPanel value="0">
              <div class="edit-form">
                <div class="form-row mb-3">
                  <label>{{ _('vendor_name') }} *</label>
                  <InputText v-model="editingVendor.name" class="w-full" />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('vendor_country') }}</label>
                  <InputText v-model="editingVendor.country" class="w-full" />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('vendor_logo') }}</label>
                  <FileBrowser
                    v-model="editingVendor.logo"
                    :placeholder="_('vendor_logo_placeholder')"
                    allowed-file-types="jpg,jpeg,png,gif,webp,svg"
                  />
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('vendor_description') }}</label>
                  <Textarea v-model="editingVendor.description" class="w-full" rows="4" />
                </div>
              </div>
            </TabPanel>

            <!-- Tab 2: Contacts -->
            <TabPanel value="1">
              <div class="edit-form">
                <div class="form-row mb-3">
                  <label>{{ _('vendor_address') }}</label>
                  <Textarea v-model="editingVendor.address" class="w-full" rows="2" />
                </div>

                <div class="form-row-group mb-3">
                  <div class="form-row">
                    <label>{{ _('vendor_phone') }}</label>
                    <InputText v-model="editingVendor.phone" class="w-full" />
                  </div>

                  <div class="form-row">
                    <label>{{ _('vendor_email') }}</label>
                    <InputText v-model="editingVendor.email" class="w-full" type="email" />
                  </div>
                </div>

                <div class="form-row mb-3">
                  <label>{{ _('vendor_resource') }}</label>
                  <InputText v-model="editingVendor.resource_id" class="w-full" type="number" :placeholder="_('vendor_resource_placeholder')" />
                  <small class="form-hint">{{ _('vendor_resource_help') }}</small>
                </div>
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
          @click="saveVendor"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.vendors-grid {
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

/* Tabs styling */
:deep(.p-tabs) {
  margin-bottom: 0;
}

:deep(.p-tabpanel) {
  padding: 1rem 0;
}

</style>
