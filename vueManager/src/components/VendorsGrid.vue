<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import draggable from 'vuedraggable'

import { useGridConfig } from '../composables/useGridConfig.js'
import { useResourceList } from '../composables/useResourceList.js'
import { useSelection } from '../composables/useSelection.js'
import { useSortableList } from '../composables/useSortableList.js'
import request from '../request.js'
import { formatValue, normalizeImagePath } from '../utils/displayFormatters.js'
import ActionsColumn from './ActionsColumn.vue'
import DynamicField from './DynamicField.vue'
import FileBrowser from './FileBrowser.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const CONFIRM_GROUP = 'settings-vendors'

// Bulk selection
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete,
} = useSelection({
  entityName: 'vendor',
  confirmGroup: CONFIRM_GROUP,
  deleteBulk: async ids => {
    await request.delete('/api/mgr/vendors/bulk', { ids })
  },
  onSuccess: () => loadVendors(),
  getItemName: item => item.name,
})

const filterValues = ref({})
const editDialogVisible = ref(false)
const editingVendor = ref(null)
const isNewVendor = ref(false)
const saving = ref(false)
const activeTab = ref('0')
const selectAll = ref(false)

const { columns, loadGridConfig } = useGridConfig({
  gridId: 'vendors',
  responseKey: 'columns',
  getFallbackColumns: getDefaultColumns,
})

const {
  loading,
  items: vendors,
  total: totalRecords,
  first,
  rows,
  load: loadVendors,
  onPage,
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

    return request.get('/api/mgr/vendors', params, { signal })
  },
})

const { onDragEnd } = useSortableList({
  items: vendors,
  sortUrl: '/api/mgr/vendors/sort',
  successMessage: _('vendor_order_saved'),
  reload: loadVendors,
})

const filterableColumns = computed(() => columns.value.filter(col => col.filterable && col.visible))

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
      fields: [],
    }
  })

  // Add "no section" group for fields without section
  result['none'] = {
    section: { id: 'none', label: _('ms3_model_field_no_section'), section_key: 'none' },
    fields: [],
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
 * Build field config for DynamicField component.
 * Converts legacy fields (e.g. logo) to proper xtypes.
 */
function getDynamicFieldConfig(field) {
  // Logo and other file fields → filebrowser xtype
  if (
    field.name === 'logo' ||
    ['file', 'image', 'filebrowser', 'imagebrowser'].includes(field.xtype?.toLowerCase())
  ) {
    return {
      ...field,
      xtype: 'filebrowser',
      allowedFileTypes:
        field.name === 'logo' ? 'jpg,jpeg,png,gif,webp,svg' : field.config?.allowedTypes || '',
    }
  }

  // Default xtype for fields without one
  if (!field.xtype) {
    return { ...field, xtype: 'textfield' }
  }

  return field
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
      life: 3000,
    })
    return
  }

  saving.value = true

  try {
    let response
    if (isNewVendor.value) {
      response = await request.post('/api/mgr/vendors', editingVendor.value)
    } else {
      response = await request.put(
        `/api/mgr/vendors/${editingVendor.value.id}`,
        editingVendor.value
      )
    }

    if (response) {
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: isNewVendor.value ? _('vendor_created') : _('vendor_updated'),
        life: 3000,
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
      life: 5000,
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
    group: CONFIRM_GROUP,
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
          life: 3000,
        })
        loadVendors()
      } catch (error) {
        console.error('[VendorsGrid] Error deleting vendor:', error)
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
      {
        name: 'delete',
        handler: 'delete',
        icon: 'pi-trash',
        label: _('delete'),
        severity: 'danger',
        confirm: false,
        confirmMessage: 'vendor_delete_confirm_message',
      },
    ]
  }

  return column.actions.map(action => ({
    ...action,
    label: _(action.label) || action.label,
  }))
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
      name: 'name',
      label: _('vendor_name'),
      visible: true,
      sortable: true,
      filterable: true,
      minWidth: '12.5rem',
    },
    {
      name: 'country',
      label: _('vendor_country'),
      visible: true,
      sortable: true,
      filterable: true,
      width: '9.375rem',
    },
    {
      name: 'email',
      label: _('vendor_email'),
      visible: true,
      sortable: true,
      filterable: true,
      width: '12.5rem',
    },
    { name: 'phone', label: _('vendor_phone'), visible: true, sortable: true, width: '9.375rem' },
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
          confirm: false,
          confirmMessage: 'vendor_delete_confirm_message',
        },
      ],
    },
  ]
}

function formatCellValue(value, column) {
  return formatValue(value, column, _)
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
  await Promise.all([loadFieldsConfig(), loadGridConfig()])
  await loadVendors()
})
</script>

<template>
  <div class="vendors-grid">
    <Toast />
    <ConfirmDialog :group="CONFIRM_GROUP" append-to="self" />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('ms3_vendors') }}</span>
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

        <!-- Table with drag-drop reorder -->
        <div class="p-datatable p-component p-datatable-striped">
          <div class="p-datatable-wrapper">
            <table class="p-datatable-table">
              <thead class="p-datatable-thead">
                <tr>
                  <th style="width: 3rem"></th>
                  <th style="width: 3rem">
                    <Checkbox v-model="selectAll" :binary="true" @change="onSelectAllChange" />
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
                :animation="200"
                ghost-class="ghost-row"
                @end="onDragEnd"
              >
                <template #item="{ element: vendor }">
                  <tr :class="{ 'p-row-odd': vendors.indexOf(vendor) % 2 === 1 }">
                    <td class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>
                      <Checkbox v-model="selectedItems" :value="vendor" :binary="false" />
                    </td>
                    <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
                      <!-- Actions column -->
                      <td v-if="column.type === 'actions'" :style="{ width: column.width }">
                        <ActionsColumn
                          :data="vendor"
                          :actions="getActionsConfig(column)"
                          :confirm-group="CONFIRM_GROUP"
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
                          :class="
                            vendor[column.name]
                              ? 'pi pi-check text-success'
                              : 'pi pi-times text-danger'
                          "
                        ></i>
                      </td>

                      <!-- Regular columns -->
                      <td v-else :style="{ width: column.width, minWidth: column.minWidth }">
                        {{ formatCellValue(vendor[column.name], column) }}
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
          <!-- Pagination -->
          <Paginator
            :first="first"
            :rows="rows"
            :total-records="totalRecords"
            :rows-per-page-options="[10, 20, 50, 100]"
            @page="onPage"
          />
        </div>
      </template>
    </Card>

    <!-- Edit/Create Dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="isNewVendor ? _('vendor_create') : _('vendor_edit')"
      :modal="true"
      :closable="true"
      :style="{ width: '43.75rem' }"
      append-to="self"
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
                        <label :for="`vendor-field-${field.name}`">
                          {{ getFieldLabel(field) }}
                          <span v-if="isFieldRequired(field)" class="required">*</span>
                        </label>

                        <DynamicField
                          v-model="editingVendor[field.name]"
                          :field-config="getDynamicFieldConfig(field)"
                          id-prefix="vendor"
                        />

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
                    <InputText
                      v-model="editingVendor.resource_id"
                      class="w-full"
                      type="number"
                      :placeholder="_('vendor_resource_placeholder')"
                    />
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
        <Button :label="_('save')" icon="pi pi-check" :loading="saving" @click="saveVendor" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.vendors-grid {
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

/* Image column thumbnail */
.column-thumbnail {
  width: 2.5rem;
  height: 2.5rem;
  object-fit: cover;
  border-radius: 0.25rem;
  border: var(--ms3-border-width) solid var(--ms3-border-color);
}

.no-image {
  color: var(--ms3-text-light);
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
  color: var(--ms3-text-primary);
}

.form-row label .required {
  color: var(--ms3-text-danger);
  margin-left: 0.25rem;
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

  .form-grid {
    grid-template-columns: 1fr;
  }

  .form-row {
    grid-column: span 1 !important;
  }
}

/* Form hint */
.form-hint {
  color: var(--ms3-text-muted);
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
  color: var(--ms3-text-muted);
  font-size: 1.2rem;
  padding: 0.5rem;
  user-select: none;
}

.drag-handle:hover {
  color: var(--ms3-text-hint);
}

.drag-handle:active {
  cursor: grabbing;
}

:deep(.ghost-row) {
  opacity: 0.5;
  background: var(--ms3-bg-muted);
}

:deep(.sortable-drag) {
  opacity: 0.9;
  background: var(--ms3-bg-surface);
  box-shadow: var(--ms3-shadow-dropdown);
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--ms3-bg-overlay);
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
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color-alt);
  background: var(--ms3-bg-muted);
  font-weight: 600;
}

.p-datatable-tbody td {
  padding: 0.75rem 1rem;
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color-alt);
}

.p-datatable-tbody tr:hover {
  background: var(--ms3-bg-slate-alt);
}

.p-row-odd {
  background: var(--ms3-bg-slate);
}
</style>
