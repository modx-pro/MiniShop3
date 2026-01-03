<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
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
import draggable from 'vuedraggable'
import request from '../request.js'
import { useLexicon } from '@modxprovuecore/useLexicon'
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
const selectAll = ref(false)

// Model fields configuration
const fieldsConfig = ref([])
const sectionsConfig = ref([])
const loadingConfig = ref(false)

/**
 * Fields grouped by sections for tabs
 */
const fieldsBySection = computed(() => {
  const result = {}

  // Initialize sections
  sectionsConfig.value.forEach(section => {
    result[section.id] = {
      section,
      fields: []
    }
  })

  // Add "no section" group for fields without section
  result['none'] = {
    section: { id: 'none', label: _('ms3_model_field_no_section'), section_key: 'none' },
    fields: []
  }

  // Group fields by section
  fieldsConfig.value.forEach(field => {
    const sectionId = field.section_id || 'none'
    if (result[sectionId]) {
      result[sectionId].fields.push(field)
    } else {
      result['none'].fields.push(field)
    }
  })

  // Filter out empty sections and sort by section order
  return Object.values(result)
    .filter(group => group.fields.length > 0)
    .sort((a, b) => {
      if (a.section.id === 'none') return 1
      if (b.section.id === 'none') return -1
      return (a.section.sort_order || 0) - (b.section.sort_order || 0)
    })
})

/**
 * Check if field should use FileBrowser (for image/file fields)
 */
function isFileBrowserField(field) {
  // Logo field always uses FileBrowser
  if (field.name === 'logo') return true

  // Check xtype for file-related types
  const fileXtypes = ['file', 'image', 'filebrowser', 'imagebrowser']
  return fileXtypes.includes(field.xtype?.toLowerCase())
}

/**
 * Get allowed file types for FileBrowser field
 */
function getAllowedFileTypes(field) {
  if (field.name === 'logo') {
    return 'jpg,jpeg,png,gif,webp,svg'
  }
  // Default for file fields
  return field.config?.allowedTypes || ''
}

/**
 * Load model fields configuration
 */
async function loadFieldsConfig() {
  loadingConfig.value = true

  try {
    const response = await request.get('/api/mgr/model-fields/visible/msVendor')

    if (response && response.results) {
      fieldsConfig.value = response.results
      sectionsConfig.value = response.sections || []
    }
  } catch (error) {
    console.error('[VendorsGrid] Error loading fields config:', error)
    // Fallback to hardcoded fields if config fails
    fieldsConfig.value = []
    sectionsConfig.value = []
  } finally {
    loadingConfig.value = false
  }
}

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
 * Create empty vendor object based on fields config
 */
function createEmptyVendor() {
  const vendor = {}

  // If we have fields config, use it
  if (fieldsConfig.value.length > 0) {
    fieldsConfig.value.forEach(field => {
      vendor[field.name] = getDefaultValueForField(field)
    })
  } else {
    // Fallback to default fields
    vendor.name = ''
    vendor.description = ''
    vendor.country = ''
    vendor.logo = ''
    vendor.address = ''
    vendor.phone = ''
    vendor.email = ''
    vendor.resource_id = null
    vendor.position = 0
  }

  return vendor
}

/**
 * Get default value for a field based on its type
 */
function getDefaultValueForField(field) {
  switch (field.xtype) {
    case 'numberfield':
      return field.name === 'position' ? 0 : null
    case 'textarea':
      return ''
    default:
      return ''
  }
}

/**
 * Open create modal
 */
function createVendor() {
  editingVendor.value = createEmptyVendor()
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
 * Handle drag end (vuedraggable)
 */
async function onDragEnd() {
  // Extract IDs in new order
  const ids = vendors.value.map(v => v.id)

  try {
    await request.post('/api/mgr/vendors/sort', { ids })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('vendor_order_saved'),
      life: 2000
    })
  } catch (error) {
    console.error('[VendorsGrid] Error saving order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000
    })
    // Reload to restore original order
    loadVendors()
  }
}

/**
 * Handle select all checkbox
 */
function onSelectAllChange() {
  if (selectAll.value) {
    selectedItems.value = [...vendors.value]
  } else {
    selectedItems.value = []
  }
}

/**
 * Get actions config for ActionsColumn
 */
function getActionsConfig(column) {
  // Fallback actions if not configured
  if (!column.actions || column.actions.length === 0) {
    return [
      { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: _('edit') },
      { name: 'delete', handler: 'delete', icon: 'pi-trash', label: _('delete'), severity: 'danger', confirm: true, confirmMessage: 'vendor_delete_confirm_message' }
    ]
  }

  return column.actions.map(action => ({
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
    columns.value = response.columns || []
  } catch (error) {
    console.error('[VendorsGrid] Failed to load grid config:', error)
    columns.value = getDefaultColumns()
  }
}

/**
 * Default columns (if API unavailable)
 */
function getDefaultColumns() {
  return [
    { name: 'id', label: 'ID', visible: true, sortable: true, frozen: true, width: '80px', isSystem: true },
    { name: 'name', label: _('vendor_name'), visible: true, sortable: true, filterable: true, minWidth: '200px' },
    { name: 'country', label: _('vendor_country'), visible: true, sortable: true, filterable: true, width: '150px' },
    { name: 'email', label: _('vendor_email'), visible: true, sortable: true, filterable: true, width: '200px' },
    { name: 'phone', label: _('vendor_phone'), visible: true, sortable: true, width: '150px' },
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
        { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: true, confirmMessage: 'vendor_delete_confirm_message' }
      ]
    }
  ]
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
 * Normalize image path to start with /
 */
function normalizeImagePath(path) {
  if (!path) return ''
  // If path is already absolute URL or starts with /, return as is
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
    return path
  }
  return '/' + path
}

/**
 * Get field label (translated)
 */
function getFieldLabel(field) {
  return field.label_display || field.label || field.name
}

/**
 * Check if field is required
 */
function isFieldRequired(field) {
  return field.required || field.name === 'name'
}

onMounted(async () => {
  await Promise.all([
    loadFieldsConfig(),
    loadGridConfig()
  ])
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

        <!-- Table with drag-drop reorder -->
        <div class="p-datatable p-component p-datatable-striped">
          <div class="p-datatable-wrapper">
            <table class="p-datatable-table">
              <thead class="p-datatable-thead">
                <tr>
                  <th style="width: 3rem"></th>
                  <th style="width: 3rem">
                    <Checkbox
                      v-model="selectAll"
                      :binary="true"
                      @change="onSelectAllChange"
                    />
                  </th>
                  <th
                    v-for="column in columns.filter(c => c.visible)"
                    :key="column.name"
                    :style="{ width: column.width, minWidth: column.minWidth }"
                  >
                    {{ column.label }}
                  </th>
                </tr>
              </thead>
              <draggable
                v-model="vendors"
                tag="tbody"
                class="p-datatable-tbody"
                handle=".drag-handle"
                item-key="id"
                @end="onDragEnd"
                :animation="200"
                ghost-class="ghost-row"
              >
                <template #item="{ element: vendor }">
                  <tr :class="{ 'p-row-odd': vendors.indexOf(vendor) % 2 === 1 }">
                    <td class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>
                      <Checkbox
                        v-model="selectedItems"
                        :value="vendor"
                        :binary="false"
                      />
                    </td>
                    <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
                      <!-- Actions column -->
                      <td v-if="column.type === 'actions'" :style="{ width: column.width }">
                        <ActionsColumn
                          :data="vendor"
                          :actions="getActionsConfig(column)"
                          grid-id="vendors"
                          @edit="editVendor"
                          @delete="deleteVendor"
                          @refresh="loadVendors"
                        />
                      </td>

                      <!-- Image column -->
                      <td v-else-if="column.type === 'image'" :style="{ width: column.width }">
                        <img
                          v-if="vendor[column.name]"
                          :src="normalizeImagePath(vendor[column.name])"
                          :alt="vendor.name || ''"
                          class="column-thumbnail"
                        />
                        <span v-else class="no-image">—</span>
                      </td>

                      <!-- Boolean column -->
                      <td v-else-if="column.type === 'boolean'" :style="{ width: column.width }">
                        <i
                          :class="vendor[column.name] ? 'pi pi-check text-success' : 'pi pi-times text-danger'"
                        ></i>
                      </td>

                      <!-- Regular columns -->
                      <td v-else :style="{ width: column.width, minWidth: column.minWidth }">
                        {{ formatValue(vendor[column.name], column) }}
                      </td>
                    </template>
                  </tr>
                </template>
              </draggable>
            </table>
          </div>
          <div v-if="loading" class="loading-overlay">
            <i class="pi pi-spinner pi-spin"></i>
          </div>
        </div>
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
        <!-- Dynamic form based on sections config -->
        <template v-if="fieldsBySection.length > 0">
          <Tabs v-model:value="activeTab">
            <TabList>
              <Tab
                v-for="(group, index) in fieldsBySection"
                :key="group.section.id"
                :value="String(index)"
              >
                {{ group.section.label }}
              </Tab>
            </TabList>

            <TabPanels>
              <TabPanel
                v-for="(group, index) in fieldsBySection"
                :key="group.section.id"
                :value="String(index)"
              >
                <div class="edit-form">
                  <div class="form-grid">
                    <template v-for="field in group.fields" :key="field.name">
                      <div
                        class="form-row mb-3"
                        :style="{ gridColumn: `span ${field.width || 6}` }"
                      >
                        <label>
                          {{ getFieldLabel(field) }}
                          <span v-if="isFieldRequired(field)" class="required">*</span>
                        </label>

                        <!-- FileBrowser for logo and file fields -->
                        <template v-if="isFileBrowserField(field)">
                          <FileBrowser
                            v-model="editingVendor[field.name]"
                            :placeholder="field.placeholder || ''"
                            :allowed-file-types="getAllowedFileTypes(field)"
                          />
                        </template>

                        <!-- Textarea for text fields -->
                        <template v-else-if="field.xtype === 'textarea'">
                          <Textarea
                            v-model="editingVendor[field.name]"
                            class="w-full"
                            :rows="3"
                            :placeholder="field.placeholder || ''"
                          />
                        </template>

                        <!-- Number field -->
                        <template v-else-if="field.xtype === 'numberfield'">
                          <InputNumber
                            v-model="editingVendor[field.name]"
                            class="w-full"
                            :placeholder="field.placeholder || ''"
                          />
                        </template>

                        <!-- Default: text field -->
                        <template v-else>
                          <InputText
                            v-model="editingVendor[field.name]"
                            class="w-full"
                            :placeholder="field.placeholder || ''"
                          />
                        </template>

                        <!-- Description/help text -->
                        <small v-if="field.description_display" class="form-hint">
                          {{ field.description_display }}
                        </small>
                      </div>
                    </template>
                  </div>
                </div>
              </TabPanel>
            </TabPanels>
          </Tabs>
        </template>

        <!-- Fallback static form if no config loaded -->
        <template v-else>
          <Tabs v-model:value="activeTab">
            <TabList>
              <Tab value="0">{{ _('ms3_section_vendor_info') || _('ms3_vendor') }}</Tab>
              <Tab value="1">{{ _('ms3_section_vendor_address') || _('vendor_contacts') }}</Tab>
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
        </template>
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

/* Image column thumbnail */
.column-thumbnail {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
}

.no-image {
  color: #94a3b8;
}

/* Edit form styles */
.edit-form {
  display: flex;
  flex-direction: column;
}

.mb-3 {
  margin-bottom: 1rem;
}

/* Grid-based form layout */
.form-grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 1rem;
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

.form-row label .required {
  color: #ef4444;
  margin-left: 0.25rem;
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

  .form-grid {
    grid-template-columns: 1fr;
  }

  .form-row {
    grid-column: span 1 !important;
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

/* Drag and drop styles */
.drag-handle-cell {
  text-align: center;
  vertical-align: middle;
  padding: 0.5rem;
}

.drag-handle {
  cursor: grab;
  color: #6c757d;
  font-size: 1.2rem;
  padding: 0.5rem;
  user-select: none;
}

.drag-handle:hover {
  color: #495057;
}

.drag-handle:active {
  cursor: grabbing;
}

:deep(.ghost-row) {
  opacity: 0.5;
  background: #f8f9fa;
}

:deep(.sortable-drag) {
  opacity: 0.9;
  background: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255,255,255,0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

.p-datatable {
  position: relative;
}

.p-datatable-wrapper {
  overflow: auto;
}

.p-datatable-table {
  width: 100%;
  border-collapse: collapse;
}

.p-datatable-thead th {
  text-align: left;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #dee2e6;
  background: #f8f9fa;
  font-weight: 600;
}

.p-datatable-tbody td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #dee2e6;
}

.p-datatable-tbody tr:hover {
  background: #f1f5f9;
}

.p-row-odd {
  background: #f8fafc;
}

</style>
