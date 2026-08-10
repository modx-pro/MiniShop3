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
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'

import request from '../request.js'
import {
  defaultKeyValueConfig,
  encodeKeyValueConfig,
  KEY_VALUE_XTYPE,
  parseKeyValueConfig,
} from '../utils/keyValueField.js'
import {
  defaultRepeaterConfig,
  parseRepeaterConfig,
  REPEATER_XTYPE,
} from '../utils/repeaterField.js'
import KeyValueSchemaEditor from './KeyValueSchemaEditor.vue'
import RepeaterSchemaEditor from './RepeaterSchemaEditor.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

// State
const loading = ref(false)
const saving = ref(false)
const fields = ref([])
const selectedClass = ref('MiniShop3\\Model\\msProductData')

// Create/edit field dialog
const dialogVisible = ref(false)
const editingField = ref(null)
const isEditMode = ref(false)

// New/edited field form
const fieldForm = ref({
  class: 'MiniShop3\\Model\\msProductData',
  key: '',
  label: '',
  description: '',
  xtype: 'textfield',
  dbtype: 'varchar',
  precision: '255',
  phptype: 'string',
  null: true,
  default: 'NULL',
  default_value: '',
  attributes: '',
  index_type: 'NONE',
  active: true,
  select_options: '',
  repeater_config: defaultRepeaterConfig(),
  key_value_config: defaultKeyValueConfig(),
})

/**
 * Available model classes
 */
const classOptions = computed(() => [
  // Товары
  { label: _('ms3_vue_class_product'), value: 'MiniShop3\\Model\\msProduct' },
  { label: _('ms3_vue_class_product_data'), value: 'MiniShop3\\Model\\msProductData' },
  { label: _('ms3_vue_class_category'), value: 'MiniShop3\\Model\\msCategory' },
  { label: _('ms3_vue_class_vendor'), value: 'MiniShop3\\Model\\msVendor' },
  { label: _('ms3_vue_class_option'), value: 'MiniShop3\\Model\\msOption' },
  { label: _('ms3_vue_class_link'), value: 'MiniShop3\\Model\\msLink' },
  // Заказы
  { label: _('ms3_vue_class_order'), value: 'MiniShop3\\Model\\msOrder' },
  { label: _('ms3_vue_class_order_address'), value: 'MiniShop3\\Model\\msOrderAddress' },
  { label: _('ms3_vue_class_order_product'), value: 'MiniShop3\\Model\\msOrderProduct' },
  { label: _('ms3_vue_class_order_status'), value: 'MiniShop3\\Model\\msOrderStatus' },
  // Клиенты
  { label: _('ms3_vue_class_customer'), value: 'MiniShop3\\Model\\msCustomer' },
  { label: _('ms3_vue_class_customer_address'), value: 'MiniShop3\\Model\\msCustomerAddress' },
  // Доставка и оплата
  { label: _('ms3_vue_class_delivery'), value: 'MiniShop3\\Model\\msDelivery' },
  { label: _('ms3_vue_class_payment'), value: 'MiniShop3\\Model\\msPayment' },
])

/**
 * Widget types (xtype)
 */
const xtypeOptions = computed(() => [
  { label: _('ms3_vue_xtype_textfield'), value: 'textfield' },
  { label: _('ms3_vue_xtype_numberfield'), value: 'numberfield' },
  { label: _('ms3_vue_xtype_textarea'), value: 'textarea' },
  { label: _('ms3_vue_xtype_xcheckbox'), value: 'xcheckbox' },
  { label: _('ms3_vue_xtype_combo_select'), value: 'ms3-combo-select' },
  { label: _('ms3_vue_xtype_repeater'), value: REPEATER_XTYPE },
  { label: _('ms3_vue_xtype_key_value'), value: KEY_VALUE_XTYPE },
  { label: _('ms3_vue_xtype_combo_vendor'), value: 'ms3-combo-vendor' },
  { label: _('ms3_vue_xtype_combo_autocomplete'), value: 'ms3-combo-autocomplete' },
  { label: _('ms3_vue_xtype_combo_options'), value: 'ms3-combo-options' },
])

/**
 * Database data types (dbtype)
 */
const dbtypeOptions = computed(() => [
  { label: _('ms3_vue_dbtype_varchar'), value: 'varchar' },
  { label: _('ms3_vue_dbtype_text'), value: 'text' },
  { label: _('ms3_vue_dbtype_int'), value: 'int' },
  { label: _('ms3_vue_dbtype_decimal'), value: 'decimal' },
  { label: _('ms3_vue_dbtype_datetime'), value: 'datetime' },
  { label: _('ms3_vue_dbtype_timestamp'), value: 'timestamp' },
  { label: _('ms3_vue_dbtype_tinyint'), value: 'tinyint' },
  { label: _('ms3_vue_dbtype_json'), value: 'json' },
])

/**
 * PHP types (phptype)
 */
const phptypeOptions = computed(() => [
  { label: _('ms3_vue_phptype_string'), value: 'string' },
  { label: _('ms3_vue_phptype_integer'), value: 'integer' },
  { label: _('ms3_vue_phptype_float'), value: 'float' },
  { label: _('ms3_vue_phptype_boolean'), value: 'boolean' },
  { label: _('ms3_vue_phptype_json'), value: 'json' },
  { label: _('ms3_vue_phptype_datetime'), value: 'datetime' },
  { label: _('ms3_vue_phptype_timestamp'), value: 'timestamp' },
])

/**
 * Default value types
 */
const defaultOptions = computed(() => [
  { label: _('ms3_vue_default_null'), value: 'NULL' },
  { label: _('ms3_vue_default_current_timestamp'), value: 'CURRENT_TIMESTAMP' },
  { label: _('ms3_vue_default_user_defined'), value: 'USER_DEFINED' },
  { label: _('ms3_vue_default_none'), value: 'NONE' },
])

/**
 * Index types
 */
const indexTypeOptions = computed(() => [
  { label: _('ms3_vue_index_none'), value: 'NONE' },
  { label: _('ms3_vue_index_index'), value: 'INDEX' },
  { label: _('ms3_vue_index_unique'), value: 'UNIQUE' },
  { label: _('ms3_vue_index_fulltext'), value: 'FULLTEXT' },
])

const isRepeaterField = computed(() => fieldForm.value.xtype === REPEATER_XTYPE)
const isKeyValueField = computed(() => fieldForm.value.xtype === KEY_VALUE_XTYPE)

watch(
  () => fieldForm.value.xtype,
  xtype => {
    if (xtype !== REPEATER_XTYPE && xtype !== KEY_VALUE_XTYPE) {
      return
    }

    fieldForm.value.dbtype = 'json'
    fieldForm.value.phptype = 'json'
    fieldForm.value.precision = ''
    fieldForm.value.null = true

    if (xtype === REPEATER_XTYPE && !fieldForm.value.repeater_config?.columns?.length) {
      fieldForm.value.repeater_config = defaultRepeaterConfig()
    }
    if (xtype === KEY_VALUE_XTYPE && !fieldForm.value.key_value_config?.mode) {
      fieldForm.value.key_value_config = defaultKeyValueConfig()
    }
  }
)

function buildRepeaterConfigPayload() {
  return JSON.stringify(parseRepeaterConfig(fieldForm.value.repeater_config))
}

/**
 * Load fields list
 */
async function loadFields() {
  loading.value = true

  try {
    const params = selectedClass.value ? { class: selectedClass.value } : {}
    const response = await request.get('/api/mgr/extra-fields', params)

    if (response && response.fields) {
      fields.value = response.fields
    } else {
      console.error('[ExtraFieldsManager] Invalid response:', response)
      fields.value = []
    }
  } catch (error) {
    console.error('[ExtraFieldsManager] Error loading fields:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error_loading'),
      detail: error.message || _('ms3_vue_error_loading_fields'),
      life: 5000,
    })
  } finally {
    loading.value = false
  }
}

/**
 * Open create field dialog
 */
function openCreateDialog() {
  isEditMode.value = false
  editingField.value = null

  // Reset form
  fieldForm.value = {
    class: selectedClass.value,
    key: '',
    label: '',
    description: '',
    xtype: 'textfield',
    dbtype: 'varchar',
    precision: '255',
    phptype: 'string',
    null: true,
    default: 'NULL',
    default_value: '',
    attributes: '',
    index_type: 'NONE',
    active: true,
    select_options: '',
    repeater_config: defaultRepeaterConfig(),
    key_value_config: defaultKeyValueConfig(),
  }

  dialogVisible.value = true
}

/**
 * Open edit field dialog
 */
function openEditDialog(field) {
  isEditMode.value = true
  editingField.value = field

  // Fill form with existing field data
  fieldForm.value = {
    id: field.id,
    class: field.class,
    key: field.key,
    label: field.label || '',
    description: field.description || '',
    xtype: field.xtype || 'textfield',
    dbtype: field.dbtype,
    precision: field.precision || '',
    phptype: field.phptype,
    null: field.null,
    default: field.default || 'NULL',
    default_value: field.default_value || '',
    attributes: field.attributes || '',
    index_type: field.index_type || 'NONE',
    active: field.active,
    select_options: field.select_options || '',
    repeater_config: parseRepeaterConfig(field.repeater_config),
    key_value_config: parseKeyValueConfig(field.key_value_config),
  }

  dialogVisible.value = true
}

/**
 * Save field (create or update)
 */
async function saveField() {
  saving.value = true

  try {
    if (isEditMode.value) {
      // Edit mode
      await updateField()
    } else {
      // Create mode
      await createField()
    }
  } finally {
    saving.value = false
  }
}

/**
 * Create new field
 */
async function createField() {
  try {
    // Validation
    if (!fieldForm.value.key) {
      toast.add({
        severity: 'warn',
        summary: _('ms3_vue_validation'),
        detail: _('ms3_vue_validation_key_required'),
        life: 3000,
      })
      return
    }

    if (!fieldForm.value.dbtype) {
      toast.add({
        severity: 'warn',
        summary: _('ms3_vue_validation'),
        detail: _('ms3_vue_validation_dbtype_required'),
        life: 3000,
      })
      return
    }

    // Convert null from string to boolean
    const payload = {
      ...fieldForm.value,
      null:
        fieldForm.value.null === true ||
        fieldForm.value.null === 'true' ||
        fieldForm.value.null === 1,
      active:
        fieldForm.value.active === true ||
        fieldForm.value.active === 'true' ||
        fieldForm.value.active === 1,
      repeater_config: isRepeaterField.value ? buildRepeaterConfigPayload() : '',
      key_value_config: isKeyValueField.value ? encodeKeyValueConfig(fieldForm.value.key_value_config) : '',
    }

    delete payload.id

    const response = await request.post('/api/mgr/extra-fields', payload)

    if (response && response.field) {
      toast.add({
        severity: 'success',
        summary: _('ms3_vue_success'),
        detail: `${_('ms3_vue_table_field_name')} "${response.field.key}" ${_('ms3_vue_field_created')}`,
        life: 3000,
      })

      dialogVisible.value = false
      await loadFields()
    } else {
      throw new Error('Invalid server response format')
    }
  } catch (error) {
    console.error('[ExtraFieldsManager] Error creating field:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error_creating'),
      detail: error.message || _('ms3_vue_error_creating_field'),
      life: 5000,
    })
  }
}

/**
 * Update existing field (metadata only)
 */
async function updateField() {
  try {
    if (!fieldForm.value.id) {
      throw new Error('Field ID not specified')
    }

    // Send only editable fields (metadata)
    const payload = {
      label: fieldForm.value.label || '',
      description: fieldForm.value.description || '',
      xtype: fieldForm.value.xtype || 'textfield',
      active:
        fieldForm.value.active === true ||
        fieldForm.value.active === 'true' ||
        fieldForm.value.active === 1,
      select_options:
        fieldForm.value.xtype === 'ms3-combo-select' ? fieldForm.value.select_options : '',
      repeater_config: isRepeaterField.value ? buildRepeaterConfigPayload() : '',
      key_value_config: isKeyValueField.value ? encodeKeyValueConfig(fieldForm.value.key_value_config) : '',
    }

    const response = await request.put(`/api/mgr/extra-fields/${fieldForm.value.id}`, payload)

    if (response && response.field) {
      toast.add({
        severity: 'success',
        summary: _('ms3_vue_success'),
        detail: `${_('ms3_vue_table_field_name')} "${response.field.key}" ${_('ms3_vue_field_updated')}`,
        life: 3000,
      })

      dialogVisible.value = false
      await loadFields()
    } else {
      throw new Error('Invalid server response format')
    }
  } catch (error) {
    console.error('[ExtraFieldsManager] Error updating field:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error_updating'),
      detail: error.message || _('ms3_vue_error_updating_field'),
      life: 5000,
    })
  }
}

// Flag to prevent double opening of confirm dialog
let confirmInProgress = false

/**
 * Delete field
 */
function confirmDelete(field) {
  // Protection against double click
  if (confirmInProgress) {
    console.warn('[ExtraFieldsManager] Confirm dialog already open, ignoring duplicate call')
    return
  }

  confirmInProgress = true

  confirm.require({
    group: 'extra-fields',
    message: _('ms3_vue_delete_confirm_message').replace('{0}', field.key),
    header: _('ms3_vue_delete_confirm_title'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('ms3_vue_delete_confirm_yes'),
    rejectLabel: _('ms3_vue_dialog_cancel'),
    acceptClass: 'p-button-danger',
    accept: () => {
      // Do NOT await - dialog will close immediately, deletion happens in background
      deleteField(field.id)
      confirmInProgress = false
    },
    reject: () => {
      confirmInProgress = false
    },
    onHide: () => {
      confirmInProgress = false
    },
  })
}

/**
 * Delete field (execution)
 */
async function deleteField(fieldId) {
  loading.value = true

  try {
    const response = await request.delete(`/api/mgr/extra-fields/${fieldId}`)

    if (response && response.message) {
      toast.add({
        severity: 'success',
        summary: _('ms3_vue_success'),
        detail: response.message,
        life: 5000,
      })

      await loadFields()
    } else {
      throw new Error('Invalid server response format')
    }
  } catch (error) {
    console.error('[ExtraFieldsManager] Error deleting field:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error_deleting'),
      detail: error.message || _('ms3_vue_error_deleting_field'),
      life: 5000,
    })
  } finally {
    loading.value = false
  }
}

/**
 * Get severity for Tag (active status)
 */
function getActiveSeverity(active) {
  return active ? 'success' : 'danger'
}

/**
 * Get severity for Tag (column existence)
 */
function getColumnExistsSeverity(exists) {
  return exists ? 'success' : 'warn'
}

/**
 * Class filter changed
 */
async function onClassFilterChange() {
  await loadFields()
}

// Load on mount
onMounted(() => {
  loadFields()
})
</script>

<template>
  <div class="extra-fields-manager">
    <Toast />
    <ConfirmDialog group="extra-fields" append-to="self" />

    <p class="tab-description">{{ _('ms3_utilities_extra_fields_description') }}</p>

    <div class="flex justify-content-between align-items-center mb-3">
      <div class="flex align-items-center gap-2">
        <label for="class-filter">{{ _('ms3_vue_extra_fields_class_filter') }}</label>
        <Select
          id="class-filter"
          v-model="selectedClass"
          :options="classOptions"
          option-label="label"
          option-value="value"
          :placeholder="_('ms3_vue_extra_fields_select_class')"
          style="min-width: 17.5rem"
          @change="onClassFilterChange"
        />
      </div>
      <Button
        :label="_('ms3_vue_extra_fields_create')"
        icon="pi pi-plus"
        :disabled="loading"
        @click="openCreateDialog"
      />
    </div>

    <Card>
      <template #content>
        <!-- Fields table -->
        <DataTable
          :value="fields"
          :loading="loading"
          striped-rows
          show-gridlines
          responsive-layout="scroll"
          :paginator="fields.length > 10"
          :rows="10"
          :rows-per-page-options="[10, 20, 50]"
          paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
          current-page-report-template="Showing {first} - {last} of {totalRecords} fields"
        >
          <Column field="id" :header="_('ms3_vue_table_id')" style="width: 3.75rem" sortable />

          <Column field="key" :header="_('ms3_vue_table_field_name')" sortable>
            <template #body="{ data }">
              <strong>{{ data.key }}</strong>
            </template>
          </Column>

          <Column field="label" :header="_('ms3_vue_table_label')" sortable />

          <Column field="dbtype" :header="_('ms3_vue_table_dbtype')" sortable style="width: 7.5rem">
            <template #body="{ data }">
              <Tag :value="data.dbtype.toUpperCase()" severity="info" />
            </template>
          </Column>

          <Column field="precision" :header="_('ms3_vue_table_precision')" style="width: 6.25rem" />

          <Column field="index_type" :header="_('ms3_vue_table_index')" style="width: 7.5rem">
            <template #body="{ data }">
              <Tag
                v-if="data.index_type && data.index_type !== 'NONE'"
                :value="data.index_type"
                :severity="data.index_type === 'UNIQUE' ? 'warning' : 'info'"
              />
              <span v-else class="text-500">{{ _('ms3_vue_table_no_index') }}</span>
            </template>
          </Column>

          <Column
            field="column_exists"
            :header="_('ms3_vue_table_column_exists')"
            style="width: 8.75rem"
          >
            <template #body="{ data }">
              <Tag
                :value="
                  data.column_exists
                    ? _('ms3_vue_table_column_exists_yes')
                    : _('ms3_vue_table_column_exists_no')
                "
                :severity="getColumnExistsSeverity(data.column_exists)"
              />
            </template>
          </Column>

          <Column field="active" :header="_('ms3_vue_table_active')" style="width: 6.25rem">
            <template #body="{ data }">
              <Tag
                :value="data.active ? _('ms3_vue_table_active_yes') : _('ms3_vue_table_active_no')"
                :severity="getActiveSeverity(data.active)"
              />
            </template>
          </Column>

          <Column :header="_('ms3_vue_table_actions')" style="width: 9.375rem">
            <template #body="{ data }">
              <Button
                v-tooltip.top="_('ms3_vue_extra_fields_edit')"
                icon="pi pi-pencil"
                severity="secondary"
                text
                rounded
                class="mr-1"
                @click.stop="openEditDialog(data)"
              />
              <Button
                v-tooltip.top="_('ms3_vue_extra_fields_delete')"
                icon="pi pi-trash"
                severity="danger"
                text
                rounded
                @click.stop="confirmDelete(data)"
              />
            </template>
          </Column>

          <template #empty>
            <div class="text-center p-4">
              {{ _('ms3_vue_table_empty') }}
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Create/Edit field dialog -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="isEditMode ? _('ms3_vue_dialog_edit_title') : _('ms3_vue_dialog_create_title')"
      :modal="true"
      :closable="!saving"
      :style="{ width: '43.75rem' }"
      append-to="self"
      @hide="saving = false"
    >
      <div class="edit-field-form">
        <!-- Basic information -->
        <Fieldset :legend="_('ms3_vue_dialog_fieldset_basic')" class="mb-3">
          <div class="form-grid">
            <!-- Model class -->
            <div class="field col-12">
              <label for="field-class">{{ _('ms3_vue_dialog_class') }}</label>
              <Select
                id="field-class"
                v-model="fieldForm.class"
                :options="classOptions"
                option-label="label"
                option-value="value"
                :placeholder="_('ms3_vue_extra_fields_select_class')"
                class="w-full"
                :disabled="isEditMode"
              />
            </div>

            <!-- Field name (key) -->
            <div class="field col-6">
              <label for="field-key">{{ _('ms3_vue_dialog_key') }}</label>
              <InputText
                id="field-key"
                v-model="fieldForm.key"
                :placeholder="_('ms3_vue_dialog_key_placeholder')"
                class="w-full"
                :disabled="isEditMode"
              />
              <small class="text-500">{{ _('ms3_vue_dialog_key_help') }}</small>
            </div>

            <!-- Label -->
            <div class="field col-6">
              <label for="field-label">{{ _('ms3_vue_dialog_label') }}</label>
              <InputText
                id="field-label"
                v-model="fieldForm.label"
                :placeholder="_('ms3_vue_dialog_label_placeholder')"
                class="w-full"
              />
            </div>

            <!-- Description -->
            <div class="field col-12">
              <label for="field-description">{{ _('ms3_vue_dialog_description') }}</label>
              <Textarea
                id="field-description"
                v-model="fieldForm.description"
                rows="2"
                class="w-full"
              />
            </div>

            <!-- Widget type (xtype) -->
            <div class="field col-12">
              <label for="field-xtype">{{ _('ms3_vue_dialog_xtype') }}</label>
              <Select
                id="field-xtype"
                v-model="fieldForm.xtype"
                :options="xtypeOptions"
                option-label="label"
                option-value="value"
                :placeholder="_('ms3_vue_dialog_xtype_select')"
                class="w-full"
              />
            </div>

            <!-- Dropdown options (only for ms3-combo-select) -->
            <div v-if="fieldForm.xtype === 'ms3-combo-select'" class="field col-12">
              <label for="field-select-options">{{ _('ms3_vue_select_options_label') }}</label>
              <Textarea
                id="field-select-options"
                v-model="fieldForm.select_options"
                :placeholder="_('ms3_vue_select_options_placeholder')"
                rows="5"
                class="w-full"
              />
              <small class="text-500">{{ _('ms3_vue_select_options_help') }}</small>
            </div>

            <!-- Repeater schema -->
            <div v-if="isRepeaterField" class="field col-12">
              <label>{{ _('ms3_vue_repeater_schema_label') }}</label>
              <RepeaterSchemaEditor v-model="fieldForm.repeater_config" />
              <small class="text-500">{{ _('ms3_vue_repeater_schema_help') }}</small>
            </div>

            <!-- Key-Value schema -->
            <div v-if="isKeyValueField" class="field col-12">
              <label>{{ _('ms3_vue_key_value_schema_label') }}</label>
              <KeyValueSchemaEditor v-model="fieldForm.key_value_config" />
              <small class="text-500">{{ _('ms3_vue_key_value_schema_help') }}</small>
            </div>
          </div>
        </Fieldset>

        <!-- Database parameters -->
        <Fieldset :legend="_('ms3_vue_dialog_fieldset_database')" class="mb-3">
          <div class="form-grid">
            <!-- DB type -->
            <div class="field col-6">
              <label for="field-dbtype">{{ _('ms3_vue_dialog_dbtype') }}</label>
              <Select
                id="field-dbtype"
                v-model="fieldForm.dbtype"
                :options="dbtypeOptions"
                option-label="label"
                option-value="value"
                :placeholder="_('ms3_vue_dialog_xtype_select')"
                class="w-full"
                :disabled="isEditMode || isRepeaterField || isKeyValueField"
              />
            </div>

            <!-- Precision -->
            <div class="field col-6">
              <label for="field-precision">{{ _('ms3_vue_dialog_precision') }}</label>
              <InputText
                id="field-precision"
                v-model="fieldForm.precision"
                :placeholder="_('ms3_vue_dialog_precision_placeholder')"
                class="w-full"
                :disabled="isEditMode || isRepeaterField || isKeyValueField"
              />
            </div>

            <!-- PHP type -->
            <div class="field col-6">
              <label for="field-phptype">{{ _('ms3_vue_dialog_phptype') }}</label>
              <Select
                id="field-phptype"
                v-model="fieldForm.phptype"
                :options="phptypeOptions"
                option-label="label"
                option-value="value"
                :placeholder="_('ms3_vue_dialog_xtype_select')"
                class="w-full"
                :disabled="isEditMode || isRepeaterField || isKeyValueField"
              />
            </div>

            <!-- Nullable -->
            <div class="field col-6">
              <label for="field-null">{{ _('ms3_vue_dialog_null') }}</label>
              <div class="flex align-items-center" style="height: 2.625rem">
                <Checkbox
                  id="field-null"
                  v-model="fieldForm.null"
                  :binary="true"
                  :disabled="isEditMode"
                />
                <label for="field-null" class="ml-2 cursor-pointer">{{
                  _('ms3_vue_dialog_null_label')
                }}</label>
              </div>
            </div>

            <!-- Default value -->
            <div class="field col-6">
              <label for="field-default">{{ _('ms3_vue_dialog_default') }}</label>
              <Select
                id="field-default"
                v-model="fieldForm.default"
                :options="defaultOptions"
                option-label="label"
                option-value="value"
                :placeholder="_('ms3_vue_dialog_xtype_select')"
                class="w-full"
                :disabled="isEditMode"
              />
            </div>

            <!-- User-defined default value -->
            <div v-if="fieldForm.default === 'USER_DEFINED'" class="field col-6">
              <label for="field-default-value">{{ _('ms3_vue_dialog_default_value') }}</label>
              <InputText
                id="field-default-value"
                v-model="fieldForm.default_value"
                :placeholder="_('ms3_vue_dialog_default_value_placeholder')"
                class="w-full"
                :disabled="isEditMode"
              />
            </div>

            <!-- Attributes -->
            <div class="field col-6">
              <label for="field-attributes">{{ _('ms3_vue_dialog_attributes') }}</label>
              <InputText
                id="field-attributes"
                v-model="fieldForm.attributes"
                :placeholder="_('ms3_vue_dialog_attributes_placeholder')"
                class="w-full"
                :disabled="isEditMode"
              />
              <small class="text-500">{{ _('ms3_vue_dialog_attributes_help') }}</small>
            </div>

            <!-- Index type -->
            <div class="field col-6">
              <label for="field-index-type">{{ _('ms3_vue_dialog_index_type') }}</label>
              <Select
                id="field-index-type"
                v-model="fieldForm.index_type"
                :options="indexTypeOptions"
                option-label="label"
                option-value="value"
                :placeholder="_('ms3_vue_dialog_xtype_select')"
                class="w-full"
                :disabled="isEditMode"
              />
            </div>

            <!-- Active status -->
            <div class="field col-12">
              <label for="field-active">{{ _('ms3_vue_dialog_active') }}</label>
              <div class="flex align-items-center" style="height: 2.625rem">
                <Checkbox id="field-active" v-model="fieldForm.active" :binary="true" />
                <label for="field-active" class="ml-2 cursor-pointer">{{
                  _('ms3_vue_dialog_active_label')
                }}</label>
              </div>
            </div>
          </div>
        </Fieldset>
      </div>

      <template #footer>
        <Button
          :label="_('ms3_vue_dialog_cancel')"
          icon="pi pi-times"
          text
          :disabled="saving"
          @click="dialogVisible = false"
        />
        <Button
          :label="isEditMode ? _('ms3_vue_dialog_save') : _('ms3_vue_dialog_create')"
          icon="pi pi-check"
          :loading="saving"
          @click="saveField"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.extra-fields-manager {
  padding: 1rem;
}
</style>

<style>
/* Modal window styles - work in both .vueApp and .p-dialog */
.vueApp .edit-field-form,
.p-dialog .edit-field-form {
  padding: 0.625rem 0;
}

.vueApp .form-grid,
.p-dialog .form-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin: -0.5rem;
}

.vueApp .edit-field-form .field,
.p-dialog .edit-field-form .field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.5rem;
  box-sizing: border-box;
}

.vueApp .edit-field-form .field label,
.p-dialog .edit-field-form .field label {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--ms3-text-primary);
}

.vueApp .edit-field-form .field small,
.p-dialog .edit-field-form .field small {
  color: var(--ms3-text-muted);
  font-size: 0.75rem;
  margin-top: -0.125rem;
}

.vueApp .edit-field-form .w-full,
.p-dialog .edit-field-form .w-full {
  width: 100%;
}

/* Grid for modal window */
.vueApp .col-6,
.p-dialog .col-6 {
  flex: 0 0 calc(50% - 1rem);
  max-width: calc(50% - 1rem);
}

.vueApp .col-12,
.p-dialog .col-12 {
  flex: 0 0 calc(100% - 1rem);
  max-width: calc(100% - 1rem);
}

/* Checkbox in modal window */
.vueApp .edit-field-form .checkbox-wrapper,
.p-dialog .edit-field-form .checkbox-wrapper {
  display: flex;
  gap: 0.625rem;
  align-items: center;
}

.vueApp .edit-field-form .checkbox-label,
.p-dialog .edit-field-form .checkbox-label {
  margin: 0;
  cursor: pointer;
  user-select: none;
}
</style>
