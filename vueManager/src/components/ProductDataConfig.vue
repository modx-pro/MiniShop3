<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import draggable from 'vuedraggable'
import request from '../request.js'
import { useLexicon } from '@modxprovuecore/useLexicon'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

// State
const loading = ref(false)
const saving = ref(false)
const fields = ref([])
const sections = ref([])
const loadingSections = ref(false)
const pageKey = 'product_data'

// Field edit dialog
const editDialogVisible = ref(false)
const editingField = ref(null)
const editingFieldIndex = ref(-1)

// Section add dialog
const addSectionDialogVisible = ref(false)
const newSection = ref({
  section_key: '',
  lexicon_key: '',
  label: '',
  hidden: false,
  sort_order: 999
})

// Section edit dialog
const editSectionDialogVisible = ref(false)
const editingSection = ref(null)
const editingSectionIndex = ref(-1)

/**
 * Section selection options (computed)
 * Built from loaded sections, showing only !hidden
 */
const availableSectionOptions = computed(() => {
  const options = [{ label: _('ms3_vue_no_section'), value: null }]

  sections.value
    .filter(section => !section.hidden)
    .forEach(section => {
      options.push({
        label: section.label || section.key,
        value: section.id  // Using ID instead of key, since section in DB is FK to id
      })
    })

  return options
})

/**
 * Get section label by ID
 */
function getSectionLabel(sectionId) {
  if (!sectionId) return _('ms3_vue_no_section')
  const section = sections.value.find(s => s.id === sectionId)
  return section ? (section.label || section.key) : `ID: ${sectionId}`
}

/**
 * Load sections from config
 */
async function loadSections() {
  loadingSections.value = true

  try {
    const response = await request.get(`/api/mgr/config/sections/${pageKey}`)

    if (response && response.sections) {
      sections.value = response.sections
    } else {
      console.error('[ProductDataConfig] Invalid sections response:', response)
    }
  } catch (error) {
    console.error('[ProductDataConfig] Error loading sections:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_save_error'),
      detail: error.message || _('ms3_vue_error_loading_sections'),
      life: 5000
    })
  } finally {
    loadingSections.value = false
  }
}

/**
 * Delete section
 */
function deleteSection(sectionKey) {
  confirm.require({
    group: 'product-data-config',
    message: _('ms3_vue_section_delete_confirm_message'),
    header: _('ms3_vue_section_delete_confirm_title'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('ms3_vue_section_delete_btn'),
    rejectLabel: _('ms3_vue_section_cancel_btn'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/config/sections/${pageKey}/${sectionKey}`)

        toast.add({
          severity: 'success',
          summary: _('ms3_vue_save_success'),
          detail: _('ms3_vue_section_deleted'),
          life: 3000
        })

        // Reload sections
        await loadSections()
      } catch (error) {
        console.error('[ProductDataConfig] Error deleting section:', error)
        toast.add({
          severity: 'error',
          summary: _('ms3_vue_save_error'),
          detail: error.message || _('ms3_vue_error_deleting_section'),
          life: 5000
        })
      }
    }
  })
}

/**
 * Section drag end handler
 */
function onSectionDragEnd() {
  toast.add({
    severity: 'info',
    summary: _('ms3_vue_order_changed'),
    detail: _('ms3_vue_save_reminder'),
    life: 3000
  })
}

/**
 * Save sections (order and visibility)
 */
async function saveSections() {
  saving.value = true

  try {
    // Update sort_order based on current order
    const sectionsToSave = sections.value.map((section, index) => ({
      section_key: section.key,
      key: section.key,
      hidden: section.hidden,
      sort_order: index,
      is_default: section.is_default || false,
      lexicon_key: section.lexicon_key || null,
      label: section.label || null
    }))

    const response = await request.put(
      `/api/mgr/config/sections/${pageKey}`,
      { sections: sectionsToSave }
    )

    toast.add({
      severity: 'success',
      summary: _('ms3_vue_save_success'),
      detail: _('ms3_vue_sections_saved'),
      life: 3000
    })

    // Reload for sync with DB
    await loadSections()
  } catch (error) {
    console.error('[ProductDataConfig] Error saving sections:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_save_error'),
      detail: error.message || _('ms3_vue_error_saving_sections'),
      life: 5000
    })
  } finally {
    saving.value = false
  }
}

/**
 * Open section add dialog
 */
function openAddSectionDialog() {
  // Reset form
  newSection.value = {
    section_key: '',
    lexicon_key: '',
    label: '',
    hidden: false,
    sort_order: sections.value.length
  }
  addSectionDialogVisible.value = true
}

/**
 * Close section add dialog
 */
function closeAddSectionDialog() {
  addSectionDialogVisible.value = false
}

/**
 * Add new section
 */
async function addSection() {
  // Validation
  if (!newSection.value.section_key) {
    toast.add({
      severity: 'warn',
      summary: _('ms3_vue_warning'),
      detail: _('ms3_vue_section_key_required'),
      life: 3000
    })
    return
  }

  // Check for duplicates
  const exists = sections.value.find(s => s.key === newSection.value.section_key)
  if (exists) {
    toast.add({
      severity: 'warn',
      summary: _('ms3_vue_warning'),
      detail: _('ms3_vue_section_key_exists'),
      life: 3000
    })
    return
  }

  // Validation: must have either lexicon_key or label
  if (!newSection.value.lexicon_key && !newSection.value.label) {
    toast.add({
      severity: 'warn',
      summary: _('ms3_vue_warning'),
      detail: _('ms3_vue_section_lexicon_or_label_required'),
      life: 3000
    })
    return
  }

  try {
    // Add section to array locally
    const newSectionData = {
      key: newSection.value.section_key,
      section_key: newSection.value.section_key,
      lexicon_key: newSection.value.lexicon_key || null,
      label: newSection.value.label || null,
      hidden: newSection.value.hidden,
      sort_order: sections.value.length,
      is_default: false
    }

    sections.value.push(newSectionData)

    // Save to server
    await saveSections()

    toast.add({
      severity: 'success',
      summary: _('ms3_vue_success_title'),
      detail: _('ms3_vue_section_added'),
      life: 3000
    })

    closeAddSectionDialog()
  } catch (error) {
    console.error('[ProductDataConfig] Error adding section:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error'),
      detail: error.message || _('ms3_vue_error_adding_section_title'),
      life: 5000
    })
  }
}

/**
 * Open section edit dialog
 */
function openEditSectionDialog(section, index) {
  // Create copy of section for editing
  editingSection.value = {
    id: section.id,
    key: section.key,
    section_key: section.key,
    lexicon_key: section.lexicon_key || '',
    label: section.label || '',
    hidden: section.hidden || false,
    is_default: section.is_default || false
  }
  editingSectionIndex.value = index
  editSectionDialogVisible.value = true
}

/**
 * Close section edit dialog
 */
function closeEditSectionDialog() {
  editSectionDialogVisible.value = false
  editingSection.value = null
  editingSectionIndex.value = -1
}

/**
 * Save section changes
 */
async function saveEditedSection() {
  if (editingSectionIndex.value < 0 || !editingSection.value) {
    return
  }

  // Validation: must have either lexicon_key or label
  if (!editingSection.value.lexicon_key && !editingSection.value.label) {
    toast.add({
      severity: 'warn',
      summary: _('ms3_vue_warning'),
      detail: _('ms3_vue_section_lexicon_or_label_required'),
      life: 3000
    })
    return
  }

  try {
    // Update section in array
    sections.value[editingSectionIndex.value] = {
      ...sections.value[editingSectionIndex.value],
      lexicon_key: editingSection.value.lexicon_key || null,
      label: editingSection.value.label || null,
      hidden: editingSection.value.hidden
    }

    // Save all sections to server
    await saveSections()

    toast.add({
      severity: 'success',
      summary: _('ms3_vue_save_success'),
      detail: _('ms3_vue_section_updated'),
      life: 3000
    })

    closeEditSectionDialog()
  } catch (error) {
    console.error('[ProductDataConfig] Error updating section:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_save_error'),
      detail: error.message || _('ms3_vue_error_saving_sections'),
      life: 5000
    })
  }
}

/**
 * Load all fields (including hidden)
 */
async function loadFields() {
  loading.value = true

  try {
    const response = await request.get(`/api/mgr/config/page-fields/${pageKey}/all`)

    if (response && response.fields) {
      // API already returns fields with hidden and sort_order
      // Make sure all fields have visible (default true)
      fields.value = response.fields.map(field => ({
        ...field,
        visible: field.visible !== undefined ? field.visible : true
      }))
    } else {
      console.error('[ProductDataConfig] Invalid response:', response)
      toast.add({
        severity: 'error',
        summary: _('ms3_vue_error'),
        detail: _('ms3_vue_error_loading_fields_detail'),
        life: 5000
      })
    }
  } catch (error) {
    console.error('[ProductDataConfig] Error loading fields:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error'),
      detail: error.message || _('ms3_vue_error_loading_fields_message'),
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

/**
 * Save configuration
 */
async function saveConfig() {
  saving.value = true

  try {
    // Update sort_order based on current order
    const fieldsToSave = fields.value.map((field, index) => ({
      ...field,
      sort_order: index
    }))

    const response = await request.put(
      `/api/mgr/config/page-fields/${pageKey}`,
      { fields: fieldsToSave }
    )

    toast.add({
      severity: 'success',
      summary: _('ms3_vue_success_title'),
      detail: _('ms3_vue_config_saved'),
      life: 3000
    })

    // Reload for sync with DB
    await loadFields()
  } catch (error) {
    console.error('[ProductDataConfig] Error saving:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error'),
      detail: error.message || _('ms3_vue_error_saving_title'),
      life: 5000
    })
  } finally {
    saving.value = false
  }
}

/**
 * Fields drag end handler
 */
function onFieldsDragEnd() {
  toast.add({
    severity: 'info',
    summary: _('ms3_vue_order_changed'),
    detail: _('ms3_vue_save_reminder'),
    life: 3000
  })
}

/**
 * Open field edit dialog
 */
function openEditDialog(field, index) {
  // Create copy of field for editing
  editingField.value = {
    ...field,
    // Convert visible: 0/1 (number) or true/false (boolean) to boolean
    // Default true if not set
    visible: field.visible !== undefined && field.visible !== null
      ? Boolean(Number(field.visible))
      : true
  }
  editingFieldIndex.value = index
  editDialogVisible.value = true
}

/**
 * Close edit dialog
 */
function closeEditDialog() {
  editDialogVisible.value = false
  editingField.value = null
  editingFieldIndex.value = -1
}

/**
 * Save field changes to DB
 */
async function saveFieldChanges() {
  if (editingFieldIndex.value >= 0 && editingField.value) {
    saving.value = true

    try {
      // Update field in array
      fields.value[editingFieldIndex.value] = { ...editingField.value }

      // Save entire configuration to server
      const fieldsToSave = fields.value.map((field, index) => ({
        ...field,
        sort_order: index,
        // Make sure visible is present in all fields (default true)
        visible: field.visible !== undefined ? field.visible : true
      }))

      const response = await request.put(
        `/api/mgr/config/page-fields/${pageKey}`,
        { fields: fieldsToSave }
      )

      toast.add({
        severity: 'success',
        summary: _('ms3_vue_save_success'),
        detail: _('ms3_vue_field_saved'),
        life: 3000
      })

      closeEditDialog()

      // Reload fields for sync with DB
      await loadFields()
    } catch (error) {
      console.error('[ProductDataConfig] Error saving:', error)
      toast.add({
        severity: 'error',
        summary: _('ms3_vue_error'),
        detail: error.message || _('ms3_vue_save_error'),
        life: 5000
      })
    } finally {
      saving.value = false
    }
  }
}

onMounted(() => {
  loadSections()
  loadFields()
})
</script>

<template>
  <div class="product-data-config">
    <h2>{{ _('ms3_vue_product_fields_title') }}</h2>
    <p>{{ _('ms3_vue_product_fields_description') }}</p>

    <!-- Sections table -->
    <Card style="margin-top: 20px;">
      <template #title>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span>{{ _('ms3_vue_sections') }}</span>
          <div style="display: flex; gap: 10px;">
            <Button
              :label="_('ms3_vue_save_changes')"
              icon="pi pi-save"
              size="small"
              @click="saveSections"
              :loading="saving"
              :disabled="loadingSections"
            />
            <Button
              :label="_('ms3_vue_section_add')"
              icon="pi pi-plus"
              size="small"
              @click="openAddSectionDialog"
            />
          </div>
        </div>
      </template>

      <template #content>
        <!-- Sections table with VueDraggable -->
        <div class="p-datatable p-component p-datatable-striped" v-if="!loadingSections">
          <div class="p-datatable-wrapper">
            <table class="p-datatable-table" style="min-width: 50rem">
              <thead class="p-datatable-thead">
                <tr>
                  <th style="width: 3rem"></th>
                  <th style="width: 100px">{{ _('ms3_vue_visible') }}</th>
                  <th style="width: 200px">{{ _('ms3_vue_section_key') }}</th>
                  <th style="width: 250px">{{ _('ms3_vue_section_label') }}</th>
                  <th style="width: 100px">{{ _('ms3_vue_actions') }}</th>
                </tr>
              </thead>
              <draggable
                v-model="sections"
                tag="tbody"
                class="p-datatable-tbody"
                handle=".drag-handle"
                item-key="key"
                @end="onSectionDragEnd"
                :animation="200"
                ghost-class="ghost-row"
              >
                <template #item="{ element: section, index }">
                  <tr>
                    <td class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>
                      <Checkbox
                        v-model="section.hidden"
                        :binary="true"
                        :trueValue="false"
                        :falseValue="true"
                      />
                    </td>
                    <td>{{ section.key }}</td>
                    <td>{{ section.label }}</td>
                    <td>
                      <Button
                        icon="pi pi-pencil"
                        size="small"
                        text
                        @click="openEditSectionDialog(section, index)"
                        :title="_('ms3_vue_section_edit')"
                      />
                      <Button
                        icon="pi pi-trash"
                        size="small"
                        severity="danger"
                        text
                        @click="deleteSection(section.key)"
                        :title="_('ms3_vue_section_delete')"
                      />
                    </td>
                  </tr>
                </template>
              </draggable>
            </table>
          </div>
        </div>

        <!-- Loading indicator -->
        <div v-if="loadingSections" class="loading-indicator">
          <i class="pi pi-spinner pi-spin" style="font-size: 2rem"></i>
        </div>
      </template>
    </Card>

    <!-- Fields table -->
    <Card style="margin-top: 20px;">
      <template #title>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span>{{ _('ms3_vue_product_properties') }}</span>
          <Button
            :label="_('ms3_vue_save_changes')"
            icon="pi pi-save"
            @click="saveConfig"
            :loading="saving"
            :disabled="loading"
          />
        </div>
      </template>

      <template #content>
        <!-- Fields table with VueDraggable -->
        <div class="p-datatable p-component p-datatable-striped" v-if="!loading">
          <div class="p-datatable-wrapper">
            <table class="p-datatable-table" style="min-width: 50rem">
              <thead class="p-datatable-thead">
                <tr>
                  <th style="width: 3rem"></th>
                  <th style="width: 100px">{{ _('ms3_vue_visible_column') }}</th>
                  <th style="width: 200px">{{ _('ms3_vue_field_column') }}</th>
                  <th style="width: 200px">{{ _('ms3_vue_label_column') }}</th>
                  <th style="width: 150px">{{ _('ms3_vue_type_column') }}</th>
                  <th style="width: 150px">{{ _('ms3_vue_section_column') }}</th>
                  <th>{{ _('ms3_vue_description_column') }}</th>
                  <th style="width: 120px">{{ _('ms3_vue_actions_column') }}</th>
                </tr>
              </thead>
              <draggable
                v-model="fields"
                tag="tbody"
                class="p-datatable-tbody"
                handle=".drag-handle"
                item-key="name"
                @end="onFieldsDragEnd"
                :animation="200"
                ghost-class="ghost-row"
              >
                <template #item="{ element: field, index }">
                  <tr>
                    <td class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>
                      <Checkbox
                        v-model="field.hidden"
                        :binary="true"
                        :trueValue="false"
                        :falseValue="true"
                      />
                    </td>
                    <td>{{ field.name }}</td>
                    <td>{{ field.label }}</td>
                    <td>{{ field.xtype }}</td>
                    <td>{{ getSectionLabel(field.section) }}</td>
                    <td>{{ field.description }}</td>
                    <td>
                      <Button
                        icon="pi pi-pencil"
                        size="small"
                        outlined
                        @click="openEditDialog(field, index)"
                        :title="_('ms3_vue_edit_field_button')"
                      />
                    </td>
                  </tr>
                </template>
              </draggable>
            </table>
          </div>
        </div>

        <!-- Loading indicator -->
        <div v-if="loading" class="loading-indicator">
          <i class="pi pi-spinner pi-spin" style="font-size: 2rem"></i>
        </div>
      </template>
    </Card>

    <!-- Add section dialog -->
    <Dialog
      v-model:visible="addSectionDialogVisible"
      modal
      :header="_('ms3_vue_add_section_title')"
      :style="{ width: '600px' }"
    >
      <div class="edit-field-form">
        <div class="form-grid">
          <!-- Section key (required) -->
          <div class="field col-12">
            <label for="section-key">{{ _('ms3_vue_section_key_label') }}</label>
            <InputText
              id="section-key"
              v-model="newSection.section_key"
              :placeholder="_('ms3_vue_section_key_example')"
              class="w-full"
            />
            <small>{{ _('ms3_vue_section_key_hint') }}</small>
          </div>

          <!-- Lexicon key -->
          <div class="field col-6">
            <label for="section-lexicon-key">{{ _('ms3_vue_section_lexicon_key_label') }}</label>
            <InputText
              id="section-lexicon-key"
              v-model="newSection.lexicon_key"
              :placeholder="_('ms3_vue_section_lexicon_key_example')"
              class="w-full"
            />
            <small>{{ _('ms3_vue_section_lexicon_key_hint') }}</small>
          </div>

          <!-- Direct label text -->
          <div class="field col-6">
            <label for="section-label">{{ _('ms3_vue_section_label_label') }}</label>
            <InputText
              id="section-label"
              v-model="newSection.label"
              :placeholder="_('ms3_vue_section_label_example')"
              class="w-full"
            />
            <small>{{ _('ms3_vue_section_label_hint') }}</small>
          </div>

          <!-- Visibility -->
          <div class="field col-12">
            <div style="display: flex; align-items: center; gap: 8px;">
              <Checkbox
                inputId="section-hidden"
                v-model="newSection.hidden"
                :binary="true"
                :trueValue="false"
                :falseValue="true"
              />
              <label for="section-hidden" style="margin: 0; cursor: pointer;" @click="newSection.hidden = !newSection.hidden">{{ _('ms3_vue_section_visible_label') }}</label>
            </div>
            <small>{{ _('ms3_vue_section_visibility_hint') }}</small>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('ms3_vue_cancel_button')"
          icon="pi pi-times"
          severity="secondary"
          @click="closeAddSectionDialog"
        />
        <Button
          :label="_('ms3_vue_add_button')"
          icon="pi pi-check"
          @click="addSection"
        />
      </template>
    </Dialog>

    <!-- Edit section dialog -->
    <Dialog
      v-model:visible="editSectionDialogVisible"
      modal
      :header="editingSection ? `${_('ms3_vue_edit_section_title')}: ${editingSection.key}` : _('ms3_vue_edit_section_title')"
      :style="{ width: '600px' }"
    >
      <div v-if="editingSection" class="edit-field-form">
        <div class="form-grid">
          <!-- Section key (readonly) -->
          <div class="field col-12">
            <label for="edit-section-key">{{ _('ms3_vue_section_key_label') }}</label>
            <InputText
              id="edit-section-key"
              v-model="editingSection.key"
              disabled
              class="w-full"
            />
            <small>{{ _('ms3_vue_section_key_readonly_hint') }}</small>
          </div>

          <!-- Lexicon key -->
          <div class="field col-6">
            <label for="edit-section-lexicon-key">{{ _('ms3_vue_section_lexicon_key_label') }}</label>
            <InputText
              id="edit-section-lexicon-key"
              v-model="editingSection.lexicon_key"
              :placeholder="_('ms3_vue_section_lexicon_key_example')"
              class="w-full"
            />
            <small>{{ _('ms3_vue_section_lexicon_key_hint') }}</small>
          </div>

          <!-- Direct label text -->
          <div class="field col-6">
            <label for="edit-section-label">{{ _('ms3_vue_section_label_label') }}</label>
            <InputText
              id="edit-section-label"
              v-model="editingSection.label"
              :placeholder="_('ms3_vue_section_label_example')"
              class="w-full"
            />
            <small>{{ _('ms3_vue_section_label_hint') }}</small>
          </div>

          <!-- Visibility -->
          <div class="field col-12">
            <div style="display: flex; align-items: center; gap: 8px;">
              <Checkbox
                inputId="edit-section-hidden"
                v-model="editingSection.hidden"
                :binary="true"
                :trueValue="false"
                :falseValue="true"
              />
              <label for="edit-section-hidden" style="margin: 0; cursor: pointer;" @click="editingSection.hidden = !editingSection.hidden">{{ _('ms3_vue_section_visible_label') }}</label>
            </div>
            <small>{{ _('ms3_vue_section_visibility_hint') }}</small>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('ms3_vue_cancel_button')"
          icon="pi pi-times"
          severity="secondary"
          @click="closeEditSectionDialog"
        />
        <Button
          :label="_('ms3_vue_save_button')"
          icon="pi pi-save"
          @click="saveEditedSection"
        />
      </template>
    </Dialog>

    <!-- Edit field dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      modal
      :header="editingField ? `${_('ms3_vue_field_edit_title')}: ${editingField.name}` : _('ms3_vue_field_edit_title')"
      :style="{ width: '600px' }"
    >
      <div v-if="editingField" class="edit-field-form">
        <div class="form-grid">
          <!-- Field type (readonly) -->
          <div class="field col-6">
            <label for="field-xtype">{{ _('ms3_vue_field_xtype') }}</label>
            <InputText
              id="field-xtype"
              v-model="editingField.xtype"
              disabled
              class="w-full"
            />
            <small>{{ _('ms3_vue_field_xtype_readonly') }}</small>
          </div>

          <!-- Section -->
          <div class="field col-6">
            <label for="field-section">{{ _('ms3_vue_field_section') }}</label>
            <Dropdown
              id="field-section"
              v-model="editingField.section"
              :options="availableSectionOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="_('ms3_vue_field_section_placeholder')"
              showClear
              class="w-full"
            />
            <small>{{ _('ms3_vue_field_section_help') }}</small>
          </div>

          <!-- Label -->
          <div class="field col-6">
            <label for="field-label">{{ _('ms3_vue_field_label') }}</label>
            <InputText
              id="field-label"
              v-model="editingField.label"
              :placeholder="_('ms3_vue_field_label_placeholder')"
              class="w-full"
            />
            <small>{{ _('ms3_vue_field_label_help') }}</small>
          </div>

          <!-- Width -->
          <div class="field col-6">
            <label for="field-width">{{ _('ms3_vue_field_width') }}</label>
            <InputNumber
              id="field-width"
              v-model="editingField.width"
              :min="1"
              :max="12"
              :placeholder="_('ms3_vue_field_width_placeholder')"
              class="w-full"
            />
            <small>{{ _('ms3_vue_field_width_help') }}</small>
          </div>

          <!-- Placeholder -->
          <div class="field col-6">
            <label for="field-placeholder">{{ _('ms3_vue_field_placeholder') }}</label>
            <InputText
              id="field-placeholder"
              v-model="editingField.placeholder"
              :placeholder="_('ms3_vue_field_placeholder_placeholder')"
              class="w-full"
            />
          </div>

          <!-- Visibility -->
          <div class="field col-6 field-checkbox">
            <div class="checkbox-wrapper">
              <Checkbox
                inputId="field-visible"
                v-model="editingField.visible"
                :binary="true"
                :trueValue="true"
                :falseValue="false"
              />
              <label for="field-visible" class="field-label checkbox-label" @click="editingField.visible = !editingField.visible">
                {{ _('ms3_vue_field_visible') }}
              </label>
            </div>
            <small>{{ _('ms3_vue_field_visible_help') }}</small>
          </div>

          <!-- Description - full width -->
          <div class="field col-12">
            <label for="field-description">{{ _('ms3_vue_field_description') }}</label>
            <Textarea
              id="field-description"
              v-model="editingField.description"
              :placeholder="_('ms3_vue_field_description_placeholder')"
              :rows="3"
              class="w-full"
            />
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('ms3_vue_field_cancel')"
          icon="pi pi-times"
          severity="secondary"
          @click="closeEditDialog"
          :disabled="saving"
        />
        <Button
          :label="_('ms3_vue_field_save')"
          icon="pi pi-save"
          @click="saveFieldChanges"
          :loading="saving"
        />
      </template>
    </Dialog>

    <Toast />
    <ConfirmDialog group="product-data-config" />
  </div>
</template>

<style scoped>
.product-data-config {
  padding: 20px;
}

h2 {
  margin: 0 0 10px 0;
  font-size: 24px;
}

p {
  margin: 0 0 20px 0;
  color: #666;
}

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
  opacity: 0.8;
  background: #e9ecef;
  cursor: grabbing !important;
}

.loading-indicator {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 3rem;
  color: #6c757d;
}

:deep(.p-datatable-tbody tr:nth-child(even)) {
  background: #f8f9fa;
}

:deep(.p-datatable-tbody tr:hover) {
  background: #e9ecef;
}
</style>

<style>
/* Modal window styles - work in both .vueApp and .p-dialog */
.vueApp .edit-field-form,
.p-dialog .edit-field-form {
  padding: 10px 0;
}

.vueApp .form-grid,
.p-dialog .form-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin: -8px;
}

.vueApp .edit-field-form .field,
.p-dialog .edit-field-form .field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px;
  box-sizing: border-box;
}

.vueApp .edit-field-form .field label,
.p-dialog .edit-field-form .field label {
  font-weight: 600;
  font-size: 14px;
  color: #333;
}

.vueApp .edit-field-form .field small,
.p-dialog .edit-field-form .field small {
  color: #666;
  font-size: 12px;
  margin-top: -2px;
}

.vueApp .edit-field-form .w-full,
.p-dialog .edit-field-form .w-full {
  width: 100%;
}

/* Grid for modal window */
.vueApp .col-6,
.p-dialog .col-6 {
  flex: 0 0 calc(50% - 16px);
  max-width: calc(50% - 16px);
}

.vueApp .col-12,
.p-dialog .col-12 {
  flex: 0 0 calc(100% - 16px);
  max-width: calc(100% - 16px);
}

/* Checkbox in modal window */
.vueApp .edit-field-form .checkbox-wrapper,
.p-dialog .edit-field-form .checkbox-wrapper {
  display: flex;
  gap: 10px;
  align-items: center;
}

.vueApp .edit-field-form .checkbox-label,
.p-dialog .edit-field-form .checkbox-label {
  margin: 0;
  cursor: pointer;
  user-select: none;
}
</style>
