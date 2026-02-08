<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Fieldset from 'primevue/fieldset'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Panel from 'primevue/panel'
import Select from 'primevue/select'
import Slider from 'primevue/slider'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import draggable from 'vuedraggable'

import request from '../request.js'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const loading = ref(false)
const fields = ref([])
const totalRecords = ref(0)

const models = ref([])
const filterModel = ref(null)

// Sections
const sections = ref([])
const sectionsLoading = ref(false)
const sectionDialogVisible = ref(false)
const editingSection = ref(null)
const isNewSection = ref(false)
const savingSec = ref(false)

// Fields
const editDialogVisible = ref(false)
const editingField = ref(null)
const saving = ref(false)
const isNewRecord = ref(false)

// Combo source config
const comboSourceJson = ref('')
const comboSourceError = ref('')

// Sections panel collapsed state
const sectionsPanelCollapsed = ref(false)

const xtypeOptions = [
  { value: 'textfield', label: 'Text Field' },
  { value: 'numberfield', label: 'Number Field' },
  { value: 'textarea', label: 'Text Area' },
  { value: 'combo', label: 'Dropdown' },
  { value: 'datefield', label: 'Date Field' },
  { value: 'checkbox', label: 'Checkbox' },
]

const widthOptions = [
  { value: 3, label: '3 (25%)' },
  { value: 4, label: '4 (33%)' },
  { value: 6, label: '6 (50%)' },
  { value: 8, label: '8 (67%)' },
  { value: 12, label: '12 (100%)' },
]

/**
 * Section options for dropdown
 */
const sectionOptions = computed(() => {
  return [
    { id: null, label: _('ms3_model_field_no_section') },
    ...sections.value.map(s => ({ id: s.id, label: s.label })),
  ]
})

/**
 * Load available models
 */
async function loadModels() {
  try {
    const response = await request.get('/api/mgr/model-fields/models')
    if (response && response.models) {
      models.value = response.models
      // Set default filter to first model
      if (models.value.length > 0 && !filterModel.value) {
        filterModel.value = models.value[0].value
      }
    }
  } catch (error) {
    console.error('[ModelFieldsGrid] Error loading models:', error)
  }
}

/**
 * Load fields list
 */
async function loadFields() {
  loading.value = true

  try {
    const params = {}

    if (filterModel.value) {
      params.model = filterModel.value
    }

    const response = await request.get('/api/mgr/model-fields', params)

    if (response && response.results) {
      fields.value = response.results
      totalRecords.value = response.total || 0
      // Also update sections from response
      if (response.sections) {
        sections.value = response.sections
      }
    } else {
      fields.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[ModelFieldsGrid] Error loading fields:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_loading_data'),
      life: 5000,
    })
  } finally {
    loading.value = false
  }
}

/**
 * Load sections for current model
 */
async function loadSections() {
  if (!filterModel.value) return

  sectionsLoading.value = true

  try {
    const response = await request.get(`/api/mgr/model-fields/sections/${filterModel.value}`)

    if (response && response.results) {
      sections.value = response.results
    } else {
      sections.value = []
    }
  } catch (error) {
    console.error('[ModelFieldsGrid] Error loading sections:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_loading_data'),
      life: 5000,
    })
  } finally {
    sectionsLoading.value = false
  }
}

function onModelChange() {
  loadFields()
  loadSections()
}

// =========================================================================
// FIELD OPERATIONS
// =========================================================================

function createField() {
  editingField.value = {
    model: filterModel.value || 'msOrder',
    name: '',
    label: '',
    xtype: 'textfield',
    visible: true,
    required: false,
    sort_order: fields.value.length * 10,
    section_id: null,
    width: 6,
    placeholder: '',
    description: '',
    config: null,
  }
  isNewRecord.value = true
  comboSourceJson.value = ''
  comboSourceError.value = ''
  editDialogVisible.value = true
}

function editField(field) {
  editingField.value = { ...field }
  isNewRecord.value = false

  // Parse combo source from config
  if (isComboXtype(field.xtype)) {
    comboSourceJson.value = parseComboSource(field.config)
  } else {
    comboSourceJson.value = ''
  }
  comboSourceError.value = ''

  editDialogVisible.value = true
}

async function saveField() {
  if (!editingField.value) return

  if (!editingField.value.name) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('ms3_model_field_name_required'),
      life: 3000,
    })
    return
  }

  // Validate and set combo source config
  if (isComboXtype(editingField.value.xtype)) {
    const validation = validateComboSource(comboSourceJson.value)
    if (!validation.valid) {
      comboSourceError.value = validation.error
      toast.add({
        severity: 'warn',
        summary: _('warning'),
        detail: validation.error,
        life: 5000,
      })
      return
    }
    // Set config from combo source
    editingField.value.config = validation.data
  }

  saving.value = true

  try {
    if (isNewRecord.value) {
      await request.post('/api/mgr/model-fields', editingField.value)
    } else {
      await request.put(`/api/mgr/model-fields/${editingField.value.id}`, editingField.value)
    }

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: isNewRecord.value ? _('ms3_model_field_created') : _('ms3_model_field_updated'),
      life: 3000,
    })

    editDialogVisible.value = false
    await loadFields()
  } catch (error) {
    console.error('[ModelFieldsGrid] Error saving field:', error)
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

function deleteField(field) {
  confirm.require({
    group: 'model-fields',
    message: _('ms3_model_field_delete_confirm'),
    header: _('confirm_delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/model-fields/${field.id}`)

        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('ms3_model_field_deleted'),
          life: 3000,
        })

        await loadFields()
      } catch (error) {
        console.error('[ModelFieldsGrid] Error deleting field:', error)
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

async function toggleVisible(field) {
  try {
    await request.put(`/api/mgr/model-fields/${field.id}`, {
      visible: !field.visible,
    })

    field.visible = !field.visible

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: field.visible ? _('ms3_model_field_shown') : _('ms3_model_field_hidden'),
      life: 2000,
    })
  } catch (error) {
    console.error('[ModelFieldsGrid] Error toggling visible:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Handle drag end - save new order
 */
async function onDragEnd() {
  // Update sort_order based on new order
  const ranks = fields.value.map((field, index) => ({
    id: field.id,
    sort_order: index * 10,
  }))

  try {
    await request.put('/api/mgr/model-fields/ranks', { ranks })

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('ms3_model_field_order_updated'),
      life: 2000,
    })
  } catch (error) {
    console.error('[ModelFieldsGrid] Error updating ranks:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
    // Reload to restore original order
    await loadFields()
  }
}

// =========================================================================
// SECTION OPERATIONS
// =========================================================================

function createSection() {
  editingSection.value = {
    model: filterModel.value || 'msOrder',
    section_key: '',
    label: '',
    lexicon_key: '',
    hidden: false,
    sort_order: sections.value.length * 10,
    is_default: false,
  }
  isNewSection.value = true
  sectionDialogVisible.value = true
}

function editSection(section) {
  editingSection.value = { ...section }
  isNewSection.value = false
  sectionDialogVisible.value = true
}

async function saveSection() {
  if (!editingSection.value) return

  if (!editingSection.value.section_key) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('ms3_model_section_key_required'),
      life: 3000,
    })
    return
  }

  savingSec.value = true

  try {
    if (isNewSection.value) {
      await request.post('/api/mgr/model-fields/sections', editingSection.value)
    } else {
      await request.put(
        `/api/mgr/model-fields/sections/${editingSection.value.id}`,
        editingSection.value
      )
    }

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: isNewSection.value ? _('ms3_model_section_created') : _('ms3_model_section_updated'),
      life: 3000,
    })

    sectionDialogVisible.value = false
    await loadSections()
    await loadFields() // Refresh fields to get updated section names
  } catch (error) {
    console.error('[ModelFieldsGrid] Error saving section:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000,
    })
  } finally {
    savingSec.value = false
  }
}

function deleteSection(section) {
  if (section.is_default) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('ms3_model_section_cannot_delete_default'),
      life: 3000,
    })
    return
  }

  confirm.require({
    group: 'model-fields',
    message: _('ms3_model_section_delete_confirm'),
    header: _('confirm_delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/model-fields/sections/${section.id}`)

        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('ms3_model_section_deleted'),
          life: 3000,
        })

        await loadSections()
        await loadFields()
      } catch (error) {
        console.error('[ModelFieldsGrid] Error deleting section:', error)
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

async function toggleSectionHidden(section) {
  try {
    await request.put(`/api/mgr/model-fields/sections/${section.id}`, {
      hidden: !section.hidden,
    })

    section.hidden = !section.hidden

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: section.hidden ? _('ms3_model_section_hidden') : _('ms3_model_section_shown'),
      life: 2000,
    })
  } catch (error) {
    console.error('[ModelFieldsGrid] Error toggling section hidden:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
  }
}

// eslint-disable-next-line no-unused-vars
async function onSectionDragEnd() {
  const ranks = sections.value.map((section, index) => ({
    id: section.id,
    sort_order: index * 10,
  }))

  try {
    await request.put('/api/mgr/model-fields/sections/ranks', { ranks })

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('ms3_model_section_order_updated'),
      life: 2000,
    })
  } catch (error) {
    console.error('[ModelFieldsGrid] Error updating section ranks:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
    await loadSections()
  }
}

// =========================================================================
// HELPERS
// =========================================================================

function getXtypeLabel(xtype) {
  const option = xtypeOptions.find(o => o.value === xtype)
  return option?.label || xtype
}

function getSectionLabel(sectionId) {
  if (!sectionId) return '-'
  const section = sections.value.find(s => s.id === sectionId)
  return section?.label || '-'
}

// eslint-disable-next-line no-unused-vars
function getWidthLabel(width) {
  const option = widthOptions.find(o => o.value === width)
  return option?.label || `${width}/12`
}

/**
 * Check if xtype is a combo/dropdown type
 */
function isComboXtype(xtype) {
  return ['combo', 'combobox', 'select', 'dropdown'].includes(xtype)
}

/**
 * Placeholder for combo source JSON
 * labelField - simple single field for label (backward compatible)
 * labelTemplate - template with {field} placeholders, e.g. "{first_name} {last_name}"
 * compareField - specifies which order field to use for value comparison (defaults to field name)
 */
const comboSourcePlaceholder = `{
  "source": {
    "type": "model",
    "class": "MiniShop3\\\\Model\\\\ClassName",
    "valueField": "id",
    "labelTemplate": "{first_name} {last_name}",
    "compareField": "user_id",
    "where": {"active": true},
    "sort": {"id": "DESC"},
    "limit": 500
  }
}`

/**
 * Parse combo source from field config
 */
function parseComboSource(config) {
  if (!config) return ''

  try {
    const parsed = typeof config === 'string' ? JSON.parse(config) : config
    return JSON.stringify(parsed, null, 2)
  } catch {
    return ''
  }
}

/**
 * Validate combo source JSON
 */
function validateComboSource(jsonStr) {
  if (!jsonStr || !jsonStr.trim()) {
    return { valid: true, data: null }
  }

  try {
    const parsed = JSON.parse(jsonStr)

    // Validate structure
    if (parsed.source) {
      const source = parsed.source
      if (!source.type) {
        return { valid: false, error: 'source.type is required' }
      }
      if (!['model', 'static'].includes(source.type)) {
        return { valid: false, error: 'source.type must be "model" or "static"' }
      }
      if (source.type === 'model' && !source.class) {
        return { valid: false, error: 'source.class is required for model type' }
      }
      if (source.type === 'static' && !Array.isArray(source.options)) {
        return { valid: false, error: 'source.options array is required for static type' }
      }
    }

    return { valid: true, data: parsed }
  } catch (e) {
    return { valid: false, error: `Invalid JSON: ${e.message}` }
  }
}

onMounted(async () => {
  await loadModels()
  await loadFields()
  await loadSections()
})
</script>

<template>
  <div class="model-fields-grid">
    <Toast />
    <ConfirmDialog group="model-fields" appendTo="self" />

    <p class="tab-description">{{ _('ms3_utilities_model_fields_description') }}</p>

    <!-- Model filter - top bar -->
    <div class="model-filter-bar mb-3">
      <div style="display: flex; align-items: center; gap: 0.5rem">
        <label style="font-weight: 500">{{ _('ms3_model_field_model') }}:</label>
        <Select
          v-model="filterModel"
          :options="models"
          optionLabel="label"
          optionValue="value"
          style="width: 12.5rem"
          @change="onModelChange"
        />
      </div>
    </div>

    <!-- Sections Panel (collapsible) -->
    <Panel
      :header="_('ms3_model_sections_title')"
      :toggleable="true"
      v-model:collapsed="sectionsPanelCollapsed"
      class="sections-panel mb-3"
    >
      <template #icons>
        <Button
          :label="_('ms3_model_section_add')"
          icon="pi pi-plus"
          class="p-button-sm"
          @click.stop="createSection"
        />
      </template>

      <DataTable
        :value="sections"
        :loading="sectionsLoading"
        size="small"
        stripedRows
        class="sections-table"
      >
        <Column style="width: 3rem">
          <template #body>
            <i class="pi pi-bars drag-handle"></i>
          </template>
        </Column>
        <Column field="section_key" :header="_('ms3_model_section_key')" style="width: 9.375rem">
          <template #body="{ data }">
            <strong>{{ data.section_key }}</strong>
          </template>
        </Column>
        <Column field="label" :header="_('ms3_model_section_label')">
          <template #body="{ data }">
            {{ data.label || '-' }}
          </template>
        </Column>
        <Column :header="_('ms3_model_section_hidden')" style="width: 6.25rem">
          <template #body="{ data }">
            <Button
              :icon="data.hidden ? 'pi pi-eye-slash' : 'pi pi-eye'"
              :class="data.hidden ? 'p-button-secondary' : 'p-button-success'"
              class="p-button-sm p-button-text"
              @click="toggleSectionHidden(data)"
            />
          </template>
        </Column>
        <Column :header="_('ms3_model_section_default')" style="width: 6.25rem">
          <template #body="{ data }">
            <i
              :class="data.is_default ? 'pi pi-check text-green-500' : 'pi pi-minus text-gray-400'"
            />
          </template>
        </Column>
        <Column :header="_('actions')" style="width: 7.5rem">
          <template #body="{ data }">
            <Button
              icon="pi pi-pencil"
              class="p-button-sm p-button-text p-button-warning"
              @click="editSection(data)"
            />
            <Button
              icon="pi pi-trash"
              class="p-button-sm p-button-text p-button-danger"
              @click="deleteSection(data)"
              :disabled="data.is_default"
            />
          </template>
        </Column>
        <template #empty>
          <div class="text-center p-3">{{ _('ms3_model_sections_empty') }}</div>
        </template>
      </DataTable>
    </Panel>

    <!-- Fields Card -->
    <Card>
      <template #title>
        <div style="display: flex; justify-content: space-between; align-items: center">
          <span>{{ _('ms3_model_fields_title') }}</span>
          <Button
            :label="_('ms3_model_field_add')"
            icon="pi pi-plus"
            class="p-button-sm"
            @click="createField"
          />
        </div>
      </template>

      <template #content>
        <!-- Fields table with VueDraggable -->
        <div class="p-datatable p-component p-datatable-striped p-datatable-sm" v-if="!loading">
          <div class="p-datatable-wrapper">
            <table class="p-datatable-table">
              <thead class="p-datatable-thead">
                <tr>
                  <th style="width: 3rem"></th>
                  <th>{{ _('ms3_model_field_name') }}</th>
                  <th>{{ _('ms3_model_field_label') }}</th>
                  <th style="width: 7.5rem">{{ _('ms3_model_field_section') }}</th>
                  <th style="width: 6.25rem">{{ _('ms3_model_field_width') }}</th>
                  <th style="width: 7.5rem">{{ _('ms3_model_field_xtype') }}</th>
                  <th style="width: 5rem">{{ _('ms3_model_field_visible') }}</th>
                  <th style="width: 5rem">{{ _('ms3_model_field_required') }}</th>
                  <th style="width: 7.5rem">{{ _('actions') }}</th>
                </tr>
              </thead>
              <draggable
                v-model="fields"
                tag="tbody"
                class="p-datatable-tbody"
                handle=".drag-handle"
                item-key="id"
                @end="onDragEnd"
                :animation="200"
                ghost-class="ghost-row"
              >
                <template #item="{ element: field }">
                  <tr>
                    <td class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>
                      <strong>{{ field.name }}</strong>
                    </td>
                    <td>
                      {{ field.label_translated || field.label || '-' }}
                    </td>
                    <td>
                      {{ getSectionLabel(field.section_id) }}
                    </td>
                    <td>{{ field.width }}/12</td>
                    <td>
                      {{ getXtypeLabel(field.xtype) }}
                    </td>
                    <td>
                      <Button
                        :icon="field.visible ? 'pi pi-eye' : 'pi pi-eye-slash'"
                        :class="field.visible ? 'p-button-success' : 'p-button-secondary'"
                        class="p-button-sm p-button-text"
                        @click="toggleVisible(field)"
                      />
                    </td>
                    <td>
                      <i
                        :class="
                          field.required
                            ? 'pi pi-check text-green-500'
                            : 'pi pi-minus text-gray-400'
                        "
                      />
                    </td>
                    <td>
                      <Button
                        icon="pi pi-pencil"
                        class="p-button-sm p-button-text p-button-warning"
                        @click="editField(field)"
                      />
                      <Button
                        icon="pi pi-trash"
                        class="p-button-sm p-button-text p-button-danger"
                        @click="deleteField(field)"
                      />
                    </td>
                  </tr>
                </template>
              </draggable>
            </table>
          </div>
          <div v-if="fields.length === 0" class="text-center p-4">
            {{ _('ms3_model_fields_empty') }}
          </div>
        </div>

        <!-- Loading indicator -->
        <div v-if="loading" class="loading-indicator">
          <i class="pi pi-spinner pi-spin" style="font-size: 2rem"></i>
        </div>
      </template>
    </Card>

    <!-- Edit Field Dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="isNewRecord ? _('ms3_model_field_add') : _('ms3_model_field_edit')"
      :modal="true"
      :closable="true"
      style="width: 37.5rem"
      appendTo="self"
    >
      <div v-if="editingField" class="edit-form">
        <!-- Basic info -->
        <Fieldset :legend="_('ms3_model_field_basic_info')" class="mb-3">
          <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem">
            <div class="field">
              <label class="block mb-2 font-medium">{{ _('ms3_model_field_model') }} *</label>
              <Select
                v-model="editingField.model"
                :options="models"
                optionLabel="label"
                optionValue="value"
                :disabled="!isNewRecord"
                style="width: 100%"
              />
            </div>

            <div class="field">
              <label class="block mb-2 font-medium">{{ _('ms3_model_field_name') }} *</label>
              <InputText
                v-model="editingField.name"
                style="width: 100%"
                :placeholder="_('ms3_model_field_name_placeholder')"
              />
            </div>
          </div>

          <div class="field mt-3">
            <label class="block mb-2 font-medium">{{ _('ms3_model_field_label') }}</label>
            <InputText
              v-model="editingField.label"
              style="width: 100%"
              :placeholder="_('ms3_model_field_label_placeholder')"
            />
          </div>

          <div class="grid mt-3" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem">
            <div class="field">
              <label class="block mb-2 font-medium">{{ _('ms3_model_field_xtype') }}</label>
              <Select
                v-model="editingField.xtype"
                :options="xtypeOptions"
                optionLabel="label"
                optionValue="value"
                style="width: 100%"
              />
            </div>

            <div class="field">
              <label class="block mb-2 font-medium">{{ _('ms3_model_field_section') }}</label>
              <Select
                v-model="editingField.section_id"
                :options="sectionOptions"
                optionLabel="label"
                optionValue="id"
                style="width: 100%"
              />
            </div>
          </div>
        </Fieldset>

        <!-- Display settings -->
        <Fieldset :legend="_('ms3_model_field_display_settings')" class="mb-3">
          <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem">
            <div class="field">
              <label class="block mb-2 font-medium"
                >{{ _('ms3_model_field_width') }} ({{ editingField.width }}/12)</label
              >
              <Slider v-model="editingField.width" :min="1" :max="12" style="width: 100%" />
            </div>

            <div class="field">
              <label class="block mb-2 font-medium">{{ _('ms3_model_field_sort_order') }}</label>
              <InputNumber v-model="editingField.sort_order" :min="0" style="width: 100%" />
            </div>
          </div>

          <div class="field mt-3">
            <label class="block mb-2 font-medium">{{ _('ms3_model_field_placeholder') }}</label>
            <InputText
              v-model="editingField.placeholder"
              style="width: 100%"
              :placeholder="_('ms3_model_field_placeholder_hint')"
            />
          </div>

          <div class="field mt-3">
            <label class="block mb-2 font-medium">{{ _('ms3_model_field_description') }}</label>
            <Textarea
              v-model="editingField.description"
              style="width: 100%"
              rows="2"
              :placeholder="_('ms3_model_field_description_hint')"
            />
          </div>

          <div class="field mt-3" style="display: flex; gap: 2rem">
            <div style="display: flex; align-items: center; gap: 0.5rem">
              <Checkbox v-model="editingField.visible" :binary="true" inputId="visible" />
              <label for="visible">{{ _('ms3_model_field_visible') }}</label>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem">
              <Checkbox v-model="editingField.required" :binary="true" inputId="required" />
              <label for="required">{{ _('ms3_model_field_required') }}</label>
            </div>
          </div>
        </Fieldset>

        <!-- Combo Source Config (only shown when xtype is combo/dropdown) -->
        <Fieldset
          v-if="isComboXtype(editingField.xtype)"
          :legend="_('ms3_model_field_combo_config')"
          class="mb-3"
        >
          <div class="field">
            <label class="block mb-2 font-medium">{{ _('ms3_model_field_combo_source') }}</label>
            <Textarea
              v-model="comboSourceJson"
              style="width: 100%; font-family: monospace; font-size: 0.75rem"
              rows="10"
              :placeholder="comboSourcePlaceholder"
              :class="{ 'p-invalid': comboSourceError }"
            />
            <small v-if="comboSourceError" class="p-error">{{ comboSourceError }}</small>
            <small v-else class="text-gray-500">
              {{ _('ms3_model_field_combo_source_hint') }}
            </small>
          </div>
        </Fieldset>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          class="p-button-text"
          @click="editDialogVisible = false"
        />
        <Button :label="_('save')" icon="pi pi-check" :loading="saving" @click="saveField" />
      </template>
    </Dialog>

    <!-- Edit Section Dialog -->
    <Dialog
      v-model:visible="sectionDialogVisible"
      :header="isNewSection ? _('ms3_model_section_add') : _('ms3_model_section_edit')"
      :modal="true"
      :closable="true"
      style="width: 31.25rem"
      appendTo="self"
    >
      <div v-if="editingSection" class="edit-form">
        <div class="field mb-3">
          <label class="block mb-2 font-medium">{{ _('ms3_model_field_model') }} *</label>
          <Select
            v-model="editingSection.model"
            :options="models"
            optionLabel="label"
            optionValue="value"
            :disabled="!isNewSection"
            style="width: 100%"
          />
        </div>

        <div class="field mb-3">
          <label class="block mb-2 font-medium">{{ _('ms3_model_section_key') }} *</label>
          <InputText
            v-model="editingSection.section_key"
            :disabled="!isNewSection"
            style="width: 100%"
            :placeholder="_('ms3_model_section_key_placeholder')"
          />
          <small v-if="isNewSection" class="text-gray-500">
            {{ _('ms3_model_section_key_hint') }}
          </small>
        </div>

        <div class="field mb-3">
          <label class="block mb-2 font-medium">{{ _('ms3_model_section_label') }}</label>
          <InputText
            v-model="editingSection.label"
            style="width: 100%"
            :placeholder="_('ms3_model_section_label_placeholder')"
          />
        </div>

        <div class="field mb-3">
          <label class="block mb-2 font-medium">{{ _('ms3_model_section_lexicon_key') }}</label>
          <InputText
            v-model="editingSection.lexicon_key"
            style="width: 100%"
            :placeholder="_('ms3_model_section_lexicon_key_placeholder')"
          />
          <small class="text-gray-500">
            {{ _('ms3_model_section_lexicon_key_hint') }}
          </small>
        </div>

        <div class="field mb-3">
          <label class="block mb-2 font-medium">{{ _('ms3_model_section_sort_order') }}</label>
          <InputNumber v-model="editingSection.sort_order" :min="0" style="width: 100%" />
        </div>

        <div class="field mb-3">
          <div style="display: flex; align-items: center; gap: 0.5rem">
            <Checkbox v-model="editingSection.hidden" :binary="true" inputId="section_hidden" />
            <label for="section_hidden">{{ _('ms3_model_section_hidden') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          class="p-button-text"
          @click="sectionDialogVisible = false"
        />
        <Button :label="_('save')" icon="pi pi-check" :loading="savingSec" @click="saveSection" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.model-fields-grid {
  padding: 1rem;
  width: 100%;
  box-sizing: border-box;
}

.model-filter-bar {
  padding: 0.75rem 1rem;
  background: var(--ms3-bg-muted);
  border-radius: 0.375rem;
  border: var(--ms3-border-width) solid var(--ms3-border-color-alt);
}

.sections-panel {
  margin-bottom: 1rem;
}

.sections-panel :deep(.p-panel-header) {
  padding: 0.75rem 1rem;
  background: var(--ms3-bg-muted);
}

.sections-panel :deep(.p-panel-content) {
  padding: 0;
}

.sections-panel :deep(.p-panel-icons) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.sections-table :deep(.p-datatable-header) {
  display: none;
}

.model-fields-grid :deep(.p-card) {
  width: 100%;
}

.model-fields-grid :deep(.p-datatable-wrapper) {
  width: 100%;
}

.model-fields-grid :deep(.p-datatable-table) {
  width: 100%;
  table-layout: fixed;
}

.edit-form .field label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

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
  opacity: 0.8;
  background: var(--ms3-bg-neutral);
  cursor: grabbing !important;
}

.loading-indicator {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 3rem;
  color: var(--ms3-text-muted);
}

:deep(.p-datatable-tbody tr:nth-child(even)) {
  background: var(--ms3-bg-muted);
}

:deep(.p-datatable-tbody tr:hover) {
  background: var(--ms3-bg-neutral);
}

:deep(.p-fieldset) {
  margin-bottom: 1rem;
}

:deep(.p-fieldset .p-fieldset-legend) {
  font-size: 0.9rem;
  padding: 0.5rem 1rem;
}

.mb-3 {
  margin-bottom: 1rem;
}

.text-center {
  text-align: center;
}

.p-3 {
  padding: 1rem;
}

.p-4 {
  padding: 1.5rem;
}
</style>
