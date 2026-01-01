<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import draggable from 'vuedraggable'
import request from '../request.js'
import { useLexicon } from '../composables/useLexicon.js'
import ActionsEditor from './ActionsEditor.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const loading = ref(false)
const saving = ref(false)
const fields = ref([])
const selectedGrid = ref('customers')

const showAddDialog = ref(false)
const newField = ref({
  field_name: '',
  label: '',
  type: 'model',
  visible: true,
  sortable: false,
  filterable: false,
  frozen: false,
  width: '',
  config: {
    template: '',
    relation: {
      table: '',
      foreignKey: '',
      displayField: '',
      aggregation: null
    },
    computed: {
      className: ''
    },
    actions: []
  }
})

const showEditDialog = ref(false)
const editingField = ref(null)
const editingFieldIndex = ref(null)

/**
 * Available grids
 */
const gridOptions = computed(() => [
  { label: _('grid_customers'), value: 'customers' },
  { label: _('grid_orders'), value: 'orders' },
  { label: _('grid_order_products'), value: 'order_products' },
  { label: _('grid_vendors'), value: 'vendors' },
  { label: _('grid_category_products'), value: 'category-products' }
])

/**
 * Field types
 */
const fieldTypeOptions = computed(() => [
  { label: _('field_type_model'), value: 'model' },
  { label: _('field_type_template'), value: 'template' },
  { label: _('field_type_relation'), value: 'relation' },
  { label: _('field_type_computed'), value: 'computed' },
  { label: _('field_type_image'), value: 'image' },
  { label: _('field_type_boolean'), value: 'boolean' },
  { label: _('field_type_actions'), value: 'actions' }
])

/**
 * Aggregation types for relation fields
 */
const aggregationOptions = computed(() => [
  { label: _('relation_aggregation_none'), value: null },
  { label: _('relation_aggregation_count'), value: 'COUNT' },
  { label: _('relation_aggregation_sum'), value: 'SUM' },
  { label: _('relation_aggregation_avg'), value: 'AVG' },
  { label: _('relation_aggregation_min'), value: 'MIN' },
  { label: _('relation_aggregation_max'), value: 'MAX' }
])

/**
 * Load grid fields configuration
 */
async function loadFields() {
  loading.value = true

  try {
    const response = await request.get(`/api/mgr/grid-config/${selectedGrid.value}`)

    if (response && response.columns) {
      fields.value = response.columns.map((col, index) => ({
        name: col.name,
        label: col.label,
        visible: col.visible !== false,
        sortable: col.sortable !== false,
        filterable: col.filterable === true,
        frozen: col.frozen === true,
        width: col.width || '',
        minWidth: col.minWidth || '',
        isSystem: col.isSystem === true,
        sort_order: index,
        template: col.template || '',
        type: col.type || '',
        format: col.format || ''
      }))
    } else {
      console.error('[GridFieldsConfig] Invalid response:', response)
      fields.value = []
    }
  } catch (error) {
    console.error('[GridFieldsConfig] Error loading fields:', error)
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
 * Save configuration
 */
async function saveConfig() {
  saving.value = true

  try {
    const fieldsData = fields.value.map((field, index) => {
      const data = {
        name: field.name,
        label: field.label || null,
        visible: field.visible,
        sortable: field.sortable,
        filterable: field.filterable,
        frozen: field.frozen,
        sort_order: index,
        width: field.width || null,
        minWidth: field.minWidth || null
      }

      if (field.template) data.template = field.template
      if (field.type) data.type = field.type
      if (field.format) data.format = field.format

      return data
    })

    await request.put(`/api/mgr/grid-config/${selectedGrid.value}`, {
      fields: fieldsData
    })

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('grid_config_saved'),
      life: 3000
    })
  } catch (error) {
    console.error('[GridFieldsConfig] Error saving config:', error)
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
 * Handle row order change (VueDraggable)
 */
function onDragEnd() {
  toast.add({
    severity: 'info',
    summary: _('success'),
    detail: _('order_changed_save_reminder'),
    life: 3000
  })
}

/**
 * Delete field
 */
function deleteField(field, index) {
  if (field.isSystem) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('cannot_delete_system_field'),
      life: 3000
    })
    return
  }

  const confirmMessage = _('delete_field_confirm_message').replace('{name}', field.label || field.name)
  const confirmHeader = _('delete_field_confirm_title')
  const deleteLabel = _('delete')
  const cancelLabel = _('cancel')
  const successSummary = _('success')
  const successDetail = _('field_deleted')
  const errorSummary = _('error')
  const errorDetail = _('error_deleting_field')

  confirm.require({
    group: 'grid-fields-config',
    message: confirmMessage,
    header: confirmHeader,
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: deleteLabel,
    rejectLabel: cancelLabel,
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/grid-config/${selectedGrid.value}/${field.name}`)

        fields.value.splice(index, 1)

        toast.add({
          severity: 'success',
          summary: successSummary,
          detail: successDetail,
          life: 3000
        })
      } catch (error) {
        console.error('[GridFieldsConfig] Error deleting field:', error)
        toast.add({
          severity: 'error',
          summary: errorSummary,
          detail: error.message || errorDetail,
          life: 5000
        })
      }
    }
  })
}

function onGridChange() {
  loadFields()
}

function openAddDialog() {
  newField.value = {
    field_name: '',
    label: '',
    type: 'model',
    visible: true,
    sortable: false,
    filterable: false,
    frozen: false,
    width: '',
    config: {
      template: '',
      relation: {
        table: '',
        foreignKey: '',
        displayField: '',
        aggregation: null
      },
      computed: {
        className: ''
      },
      actions: [
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: true }
      ]
    }
  }
  showAddDialog.value = true
}

function closeAddDialog() {
  showAddDialog.value = false
}

/**
 * Add new field
 */
async function addField() {
  try {
    const data = {
      field_name: newField.value.field_name,
      label: newField.value.label,
      type: newField.value.type,
      visible: newField.value.visible,
      sortable: newField.value.sortable,
      filterable: newField.value.filterable,
      frozen: newField.value.frozen,
      width: newField.value.width || null,
      config: {}
    }

    switch (newField.value.type) {
      case 'template':
        data.config = {
          template: newField.value.config.template
        }
        break
      case 'relation':
        data.config = {
          relation: {
            table: newField.value.config.relation.table,
            foreignKey: newField.value.config.relation.foreignKey,
            displayField: newField.value.config.relation.displayField,
            aggregation: newField.value.config.relation.aggregation
          }
        }
        break
      case 'computed':
        data.config = {
          computed: {
            className: newField.value.config.computed.className
          }
        }
        break
      case 'actions':
        data.config = {
          actions: newField.value.config.actions || []
        }
        data.sortable = false
        data.filterable = false
        break
    }

    const result = await request.post(`/api/mgr/grid-config/${selectedGrid.value}/field`, data)

    if (result.field) {
      fields.value.push({
        name: result.field.field_name,
        label: result.field.label,
        visible: result.field.visible,
        sortable: result.field.sortable,
        filterable: result.field.filterable,
        frozen: result.field.frozen,
        width: result.field.width || '',
        minWidth: result.field.min_width || '',
        isSystem: result.field.is_system,
        sort_order: result.field.sort_order,
        template: result.field.template || '',
        type: result.field.type || '',
        format: result.field.format || ''
      })
    }

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('field_added'),
      life: 3000
    })

    closeAddDialog()
  } catch (error) {
    console.error('[GridFieldsConfig] Error adding field:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_adding_field'),
      life: 5000
    })
  }
}

/**
 * Open edit field dialog
 */
function openEditDialog(field, index) {
  editingFieldIndex.value = index

  const fieldType = field.type || 'model'

  editingField.value = {
    field_name: field.name,
    label: field.label || '',
    type: fieldType,
    visible: field.visible !== false,
    sortable: field.sortable !== false,
    filterable: field.filterable === true,
    frozen: field.frozen === true,
    width: field.width || '',
    config: {
      template: field.template || '',
      relation: {
        table: '',
        foreignKey: '',
        displayField: '',
        aggregation: null
      },
      computed: {
        className: ''
      },
      actions: field.actions || [
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: true }
      ]
    }
  }

  showEditDialog.value = true
}

function closeEditDialog() {
  showEditDialog.value = false
  editingField.value = null
  editingFieldIndex.value = null
}

async function saveEdit() {
  try {
    const data = {
      field_name: editingField.value.field_name,
      label: editingField.value.label,
      type: editingField.value.type,
      visible: editingField.value.visible,
      sortable: editingField.value.sortable,
      filterable: editingField.value.filterable,
      frozen: editingField.value.frozen,
      width: editingField.value.width || null,
      config: {}
    }

    switch (editingField.value.type) {
      case 'template':
        data.config = {
          template: editingField.value.config.template
        }
        break
      case 'relation':
        data.config = {
          relation: {
            table: editingField.value.config.relation.table,
            foreignKey: editingField.value.config.relation.foreignKey,
            displayField: editingField.value.config.relation.displayField,
            aggregation: editingField.value.config.relation.aggregation
          }
        }
        break
      case 'computed':
        data.config = {
          computed: {
            className: editingField.value.config.computed.className
          }
        }
        break
      case 'actions':
        data.config = {
          actions: editingField.value.config.actions || []
        }
        data.sortable = false
        data.filterable = false
        break
    }

    const result = await request.put(`/api/mgr/grid-config/${selectedGrid.value}/field/${editingField.value.field_name}`, data)

    if (editingFieldIndex.value !== null && result.field) {
      fields.value[editingFieldIndex.value] = {
        name: result.field.field_name,
        label: result.field.label,
        visible: result.field.visible,
        sortable: result.field.sortable,
        filterable: result.field.filterable,
        frozen: result.field.frozen,
        width: result.field.width || '',
        minWidth: result.field.min_width || '',
        isSystem: result.field.is_system,
        sort_order: result.field.sort_order,
        template: result.field.template || '',
        type: result.field.type || '',
        format: result.field.format || ''
      }
    }

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('field_updated'),
      life: 3000
    })

    closeEditDialog()
  } catch (error) {
    console.error('[GridFieldsConfig] Error updating field:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_updating_field'),
      life: 5000
    })
  }
}

onMounted(() => {
  loadFields()
})
</script>

<template>
  <div class="grid-fields-config">
    <Toast />
    <ConfirmDialog group="grid-fields-config" />

    <Card>
      <template #title>
        {{ _('grid_fields_config_title') }}
      </template>

      <template #content>
        <!-- Grid selector -->
        <div class="field mb-4">
          <label for="grid-select">{{ _('select_grid') }}</label>
          <Dropdown
            id="grid-select"
            v-model="selectedGrid"
            :options="gridOptions"
            option-label="label"
            option-value="value"
            @change="onGridChange"
            class="w-full md:w-14rem"
          />
        </div>

        <!-- Add field button -->
        <div class="mb-3">
          <Button
            :label="_('add_field')"
            icon="pi pi-plus"
            @click="openAddDialog"
            size="small"
          />
        </div>

        <!-- Fields table with VueDraggable -->
        <div class="p-datatable p-component p-datatable-striped" v-if="!loading">
          <div class="p-datatable-wrapper">
            <table class="p-datatable-table">
              <thead class="p-datatable-thead">
                <tr>
                  <th style="width: 4rem"></th>
                  <th style="width: 200px">{{ _('field_name') }}</th>
                  <th style="min-width: 200px">{{ _('field_label') }}</th>
                  <th style="width: 100px">{{ _('visible') }}</th>
                  <th style="width: 100px">{{ _('sortable') }}</th>
                  <th style="width: 100px">{{ _('filterable') }}</th>
                  <th style="width: 100px">{{ _('frozen') }}</th>
                  <th style="width: 120px">{{ _('width') }}</th>
                  <th style="width: 100px">{{ _('actions') }}</th>
                </tr>
              </thead>
              <draggable
                v-model="fields"
                tag="tbody"
                class="p-datatable-tbody"
                handle=".drag-handle"
                item-key="name"
                @end="onDragEnd"
                :animation="200"
                ghost-class="ghost-row"
              >
                <template #item="{ element: field, index }">
                  <tr>
                    <td class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>{{ field.name }}</td>
                    <td>
                      <InputText v-model="field.label" class="w-full" />
                    </td>
                    <td>
                      <Checkbox v-model="field.visible" :binary="true" :disabled="field.isSystem" />
                    </td>
                    <td>
                      <Checkbox v-model="field.sortable" :binary="true" />
                    </td>
                    <td>
                      <Checkbox v-model="field.filterable" :binary="true" />
                    </td>
                    <td>
                      <Checkbox v-model="field.frozen" :binary="true" />
                    </td>
                    <td>
                      <InputText v-model="field.width" placeholder="150px" class="w-full" />
                    </td>
                    <td>
                      <Button
                        icon="pi pi-pencil"
                        size="small"
                        text
                        :title="_('edit')"
                        @click="openEditDialog(field, index)"
                        class="mr-2"
                      />
                      <Button
                        icon="pi pi-trash"
                        size="small"
                        severity="danger"
                        text
                        :title="_('delete')"
                        :disabled="field.isSystem"
                        @click="deleteField(field, index)"
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

        <!-- Save button -->
        <div class="flex justify-content-end">
          <Button
            :label="_('save')"
            icon="pi pi-check"
            :loading="saving"
            @click="saveConfig"
          />
        </div>
      </template>
    </Card>

    <!-- Add field dialog -->
    <Dialog
      v-model:visible="showAddDialog"
      :header="_('add_field_dialog_title')"
      :modal="true"
      :closable="true"
      :style="{ width: '600px' }"
      @hide="closeAddDialog"
    >
      <div class="field mb-3">
        <label for="new-field-name" class="required">{{ _('field_name') }}</label>
        <InputText
          id="new-field-name"
          v-model="newField.field_name"
          class="w-full"
          :placeholder="_('field_name_placeholder')"
        />
      </div>

      <div class="field mb-3">
        <label for="new-field-label">{{ _('field_label') }}</label>
        <InputText
          id="new-field-label"
          v-model="newField.label"
          class="w-full"
          :placeholder="_('field_label_placeholder')"
        />
      </div>

      <div class="field mb-3">
        <label for="new-field-type" class="required">{{ _('field_type') }}</label>
        <Dropdown
          id="new-field-type"
          v-model="newField.type"
          :options="fieldTypeOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <!-- Dynamic fields based on type -->
      <div v-if="newField.type === 'template'" class="field mb-3">
        <label for="new-field-template" class="required">{{ _('field_template') }}</label>
        <Textarea
          id="new-field-template"
          v-model="newField.config.template"
          rows="3"
          class="w-full"
          :placeholder="_('field_template_placeholder')"
        />
        <small class="text-muted">{{ _('field_template_hint') }}</small>
      </div>

      <div v-if="newField.type === 'relation'" class="mb-3">
        <div class="field mb-2">
          <label for="new-field-relation-table" class="required">{{ _('relation_table') }}</label>
          <InputText
            id="new-field-relation-table"
            v-model="newField.config.relation.table"
            class="w-full"
            :placeholder="_('relation_table_placeholder')"
          />
        </div>
        <div class="field mb-2">
          <label for="new-field-relation-fk" class="required">{{ _('relation_foreign_key') }}</label>
          <InputText
            id="new-field-relation-fk"
            v-model="newField.config.relation.foreignKey"
            class="w-full"
            :placeholder="_('relation_foreign_key_placeholder')"
          />
        </div>
        <div class="field mb-2">
          <label for="new-field-relation-display" class="required">{{ _('relation_display_field') }}</label>
          <InputText
            id="new-field-relation-display"
            v-model="newField.config.relation.displayField"
            class="w-full"
            :placeholder="_('relation_display_field_placeholder')"
          />
        </div>
        <div class="field mb-2">
          <label for="new-field-relation-aggregation">{{ _('relation_aggregation') }}</label>
          <Dropdown
            id="new-field-relation-aggregation"
            v-model="newField.config.relation.aggregation"
            :options="aggregationOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <small class="text-muted">{{ _('relation_hint') }}</small>
      </div>

      <div v-if="newField.type === 'computed'" class="field mb-3">
        <label for="new-field-computed-class" class="required">{{ _('computed_class_name') }}</label>
        <InputText
          id="new-field-computed-class"
          v-model="newField.config.computed.className"
          class="w-full"
          :placeholder="_('computed_class_name_placeholder')"
        />
        <small class="text-muted">{{ _('computed_class_hint') }}</small>
      </div>

      <!-- Actions configuration for actions type -->
      <div v-if="newField.type === 'actions'" class="field mb-3">
        <label class="mb-2 block font-semibold">{{ _('actions_configuration') }}</label>
        <ActionsEditor
          v-model="newField.config.actions"
          :grid-id="selectedGrid"
        />
        <small class="text-muted">{{ _('actions_configuration_hint') }}</small>
      </div>

      <!-- General settings -->
      <div class="field mb-3">
        <label for="new-field-width">{{ _('width') }}</label>
        <InputText
          id="new-field-width"
          v-model="newField.width"
          class="w-full"
          placeholder="150px"
        />
      </div>

      <div class="flex flex-wrap gap-4 mb-3">
        <div class="flex align-items-center">
          <Checkbox
            input-id="new-field-visible"
            v-model="newField.visible"
            :binary="true"
          />
          <label for="new-field-visible" class="ml-2 cursor-pointer">{{ _('visible') }}</label>
        </div>

        <div class="flex align-items-center">
          <Checkbox
            input-id="new-field-sortable"
            v-model="newField.sortable"
            :binary="true"
            :disabled="newField.type === 'actions'"
          />
          <label for="new-field-sortable" class="ml-2 cursor-pointer" :class="{ 'opacity-50': newField.type === 'actions' }">{{ _('sortable') }}</label>
        </div>

        <div class="flex align-items-center">
          <Checkbox
            input-id="new-field-filterable"
            v-model="newField.filterable"
            :binary="true"
            :disabled="newField.type === 'template' || newField.type === 'actions'"
          />
          <label for="new-field-filterable" class="ml-2 cursor-pointer" :class="{ 'opacity-50': newField.type === 'template' || newField.type === 'actions' }">{{ _('filterable') }}</label>
        </div>

        <div class="flex align-items-center">
          <Checkbox
            input-id="new-field-frozen"
            v-model="newField.frozen"
            :binary="true"
          />
          <label for="new-field-frozen" class="ml-2 cursor-pointer">{{ _('frozen') }}</label>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          @click="closeAddDialog"
          severity="secondary"
          text
        />
        <Button
          :label="_('create')"
          icon="pi pi-check"
          @click="addField"
          :disabled="!newField.field_name"
        />
      </template>
    </Dialog>

    <!-- Edit field dialog -->
    <Dialog
      v-model:visible="showEditDialog"
      :header="_('edit_field_dialog_title')"
      :modal="true"
      :closable="true"
      :style="{ width: '600px' }"
      @hide="closeEditDialog"
    >
      <div v-if="editingField">
        <div class="field mb-3">
          <label for="edit-field-name" class="required">{{ _('field_name') }}</label>
          <InputText
            id="edit-field-name"
            v-model="editingField.field_name"
            class="w-full"
            disabled
          />
          <small class="text-muted">{{ _('field_name_readonly_hint') }}</small>
        </div>

        <div class="field mb-3">
          <label for="edit-field-label">{{ _('field_label') }}</label>
          <InputText
            id="edit-field-label"
            v-model="editingField.label"
            class="w-full"
            :placeholder="_('field_label_placeholder')"
          />
        </div>

        <div class="field mb-3">
          <label for="edit-field-type" class="required">{{ _('field_type') }}</label>
          <Dropdown
            id="edit-field-type"
            v-model="editingField.type"
            :options="fieldTypeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>

        <!-- Dynamic fields based on type -->
        <div v-if="editingField.type === 'template'" class="field mb-3">
          <label for="edit-field-template" class="required">{{ _('field_template') }}</label>
          <Textarea
            id="edit-field-template"
            v-model="editingField.config.template"
            rows="3"
            class="w-full"
            :placeholder="_('field_template_placeholder')"
          />
          <small class="text-muted">{{ _('field_template_hint') }}</small>
        </div>

        <div v-if="editingField.type === 'relation'" class="mb-3">
          <div class="field mb-2">
            <label for="edit-field-relation-table" class="required">{{ _('relation_table') }}</label>
            <InputText
              id="edit-field-relation-table"
              v-model="editingField.config.relation.table"
              class="w-full"
              :placeholder="_('relation_table_placeholder')"
            />
          </div>
          <div class="field mb-2">
            <label for="edit-field-relation-fk" class="required">{{ _('relation_foreign_key') }}</label>
            <InputText
              id="edit-field-relation-fk"
              v-model="editingField.config.relation.foreignKey"
              class="w-full"
              :placeholder="_('relation_foreign_key_placeholder')"
            />
          </div>
          <div class="field mb-2">
            <label for="edit-field-relation-display" class="required">{{ _('relation_display_field') }}</label>
            <InputText
              id="edit-field-relation-display"
              v-model="editingField.config.relation.displayField"
              class="w-full"
              :placeholder="_('relation_display_field_placeholder')"
            />
          </div>
          <div class="field mb-2">
            <label for="edit-field-relation-aggregation">{{ _('relation_aggregation') }}</label>
            <Dropdown
              id="edit-field-relation-aggregation"
              v-model="editingField.config.relation.aggregation"
              :options="aggregationOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
          <small class="text-muted">{{ _('relation_hint') }}</small>
        </div>

        <div v-if="editingField.type === 'computed'" class="field mb-3">
          <label for="edit-field-computed-class" class="required">{{ _('computed_class_name') }}</label>
          <InputText
            id="edit-field-computed-class"
            v-model="editingField.config.computed.className"
            class="w-full"
            :placeholder="_('computed_class_name_placeholder')"
          />
          <small class="text-muted">{{ _('computed_class_hint') }}</small>
        </div>

        <!-- Actions configuration for actions type -->
        <div v-if="editingField.type === 'actions'" class="field mb-3">
          <label class="mb-2 block font-semibold">{{ _('actions_configuration') }}</label>
          <ActionsEditor
            v-model="editingField.config.actions"
            :grid-id="selectedGrid"
          />
          <small class="text-muted">{{ _('actions_configuration_hint') }}</small>
        </div>

        <!-- General settings -->
        <div class="field mb-3">
          <label for="edit-field-width">{{ _('width') }}</label>
          <InputText
            id="edit-field-width"
            v-model="editingField.width"
            class="w-full"
            placeholder="150px"
          />
        </div>

        <div class="flex flex-wrap gap-4 mb-3">
          <div class="flex align-items-center">
            <Checkbox
              input-id="edit-field-visible"
              v-model="editingField.visible"
              :binary="true"
            />
            <label for="edit-field-visible" class="ml-2 cursor-pointer">{{ _('visible') }}</label>
          </div>

          <div class="flex align-items-center">
            <Checkbox
              input-id="edit-field-sortable"
              v-model="editingField.sortable"
              :binary="true"
              :disabled="editingField.type === 'actions'"
            />
            <label for="edit-field-sortable" class="ml-2 cursor-pointer" :class="{ 'opacity-50': editingField.type === 'actions' }">{{ _('sortable') }}</label>
          </div>

          <div class="flex align-items-center">
            <Checkbox
              input-id="edit-field-filterable"
              v-model="editingField.filterable"
              :binary="true"
              :disabled="editingField.type === 'template' || editingField.type === 'actions'"
            />
            <label for="edit-field-filterable" class="ml-2 cursor-pointer" :class="{ 'opacity-50': editingField.type === 'template' || editingField.type === 'actions' }">{{ _('filterable') }}</label>
          </div>

          <div class="flex align-items-center">
            <Checkbox
              input-id="edit-field-frozen"
              v-model="editingField.frozen"
              :binary="true"
            />
            <label for="edit-field-frozen" class="ml-2 cursor-pointer">{{ _('frozen') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          @click="closeEditDialog"
          severity="secondary"
          text
        />
        <Button
          :label="_('save')"
          icon="pi pi-check"
          @click="saveEdit"
          :disabled="!editingField || !editingField.field_name"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.grid-fields-config {
  padding: 20px;
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

label.required::after {
  content: ' *';
  color: #dc3545;
}

small.text-muted {
  display: block;
  margin-top: 0.25rem;
  color: #6c757d;
  font-size: 0.875rem;
}

label.cursor-pointer {
  cursor: pointer;
  user-select: none;
}

label.opacity-50 {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
