<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Fieldset from 'primevue/fieldset'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import request from '../request.js'
import { useLexicon } from '../composables/useLexicon.js'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

// Состояние
const loading = ref(false)
const saving = ref(false)
const fields = ref([])
const selectedClass = ref('MiniShop3\\Model\\msProductData')

// Диалог создания/редактирования поля
const dialogVisible = ref(false)
const editingField = ref(null)
const isEditMode = ref(false)

// Форма нового/редактируемого поля
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
  active: true
})

/**
 * Доступные классы моделей
 */
const classOptions = computed(() => [
  { label: _('class_product_data'), value: 'MiniShop3\\Model\\msProductData' },
  { label: _('class_vendor'), value: 'MiniShop3\\Model\\msVendor' },
  { label: _('class_order'), value: 'MiniShop3\\Model\\msOrder' },
  { label: _('class_category'), value: 'MiniShop3\\Model\\msCategory' }
])

/**
 * Типы виджетов (xtype)
 */
const xtypeOptions = computed(() => [
  { label: _('xtype_textfield'), value: 'textfield' },
  { label: _('xtype_numberfield'), value: 'numberfield' },
  { label: _('xtype_textarea'), value: 'textarea' },
  { label: _('xtype_xcheckbox'), value: 'xcheckbox' },
  { label: _('xtype_combo_vendor'), value: 'ms3-combo-vendor' },
  { label: _('xtype_combo_autocomplete'), value: 'ms3-combo-autocomplete' },
  { label: _('xtype_combo_options'), value: 'ms3-combo-options' }
])

/**
 * Типы данных БД (dbtype)
 */
const dbtypeOptions = computed(() => [
  { label: _('dbtype_varchar'), value: 'varchar' },
  { label: _('dbtype_text'), value: 'text' },
  { label: _('dbtype_int'), value: 'int' },
  { label: _('dbtype_decimal'), value: 'decimal' },
  { label: _('dbtype_datetime'), value: 'datetime' },
  { label: _('dbtype_timestamp'), value: 'timestamp' },
  { label: _('dbtype_tinyint'), value: 'tinyint' },
  { label: _('dbtype_json'), value: 'json' }
])

/**
 * PHP типы (phptype)
 */
const phptypeOptions = computed(() => [
  { label: _('phptype_string'), value: 'string' },
  { label: _('phptype_integer'), value: 'integer' },
  { label: _('phptype_float'), value: 'float' },
  { label: _('phptype_boolean'), value: 'boolean' },
  { label: _('phptype_json'), value: 'json' },
  { label: _('phptype_datetime'), value: 'datetime' },
  { label: _('phptype_timestamp'), value: 'timestamp' }
])

/**
 * Типы значений по умолчанию
 */
const defaultOptions = computed(() => [
  { label: _('default_null'), value: 'NULL' },
  { label: _('default_current_timestamp'), value: 'CURRENT_TIMESTAMP' },
  { label: _('default_user_defined'), value: 'USER_DEFINED' },
  { label: _('default_none'), value: 'NONE' }
])

/**
 * Типы индексов
 */
const indexTypeOptions = computed(() => [
  { label: _('index_none'), value: 'NONE' },
  { label: _('index_index'), value: 'INDEX' },
  { label: _('index_unique'), value: 'UNIQUE' },
  { label: _('index_fulltext'), value: 'FULLTEXT' }
])

/**
 * Загрузить список полей
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
      summary: _('error_loading'),
      detail: error.message || _('error_loading_fields'),
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

/**
 * Открыть диалог создания поля
 */
function openCreateDialog() {
  isEditMode.value = false
  editingField.value = null

  // Сбросить форму
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
    active: true
  }

  dialogVisible.value = true
}

/**
 * Открыть диалог редактирования поля
 */
function openEditDialog(field) {
  isEditMode.value = true
  editingField.value = field

  // Заполнить форму данными существующего поля
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
    active: field.active
  }

  dialogVisible.value = true
}

/**
 * Сохранить поле (создать или обновить)
 */
async function saveField() {
  saving.value = true

  try {
    if (isEditMode.value) {
      // Режим редактирования
      await updateField()
    } else {
      // Режим создания
      await createField()
    }
  } finally {
    saving.value = false
  }
}

/**
 * Создать новое поле
 */
async function createField() {
  try {
    // Валидация
    if (!fieldForm.value.key) {
      toast.add({
        severity: 'warn',
        summary: _('validation'),
        detail: _('validation_key_required'),
        life: 3000
      })
      return
    }

    if (!fieldForm.value.dbtype) {
      toast.add({
        severity: 'warn',
        summary: _('validation'),
        detail: _('validation_dbtype_required'),
        life: 3000
      })
      return
    }

    // Преобразуем null из строки в boolean
    const payload = {
      ...fieldForm.value,
      null: fieldForm.value.null === true || fieldForm.value.null === 'true' || fieldForm.value.null === 1,
      active: fieldForm.value.active === true || fieldForm.value.active === 'true' || fieldForm.value.active === 1
    }

    const response = await request.post('/api/mgr/extra-fields', payload)

    if (response && response.field) {
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: `${_('table_field_name')} "${response.field.key}" ${_('field_created')}`,
        life: 3000
      })

      dialogVisible.value = false
      await loadFields()
    } else {
      throw new Error('Неверный формат ответа от сервера')
    }
  } catch (error) {
    console.error('[ExtraFieldsManager] Error creating field:', error)
    toast.add({
      severity: 'error',
      summary: _('error_creating'),
      detail: error.message || _('error_creating_field'),
      life: 5000
    })
  }
}

/**
 * Обновить существующее поле (только метаданные)
 */
async function updateField() {
  try {
    if (!fieldForm.value.id) {
      throw new Error('ID поля не указан')
    }

    // Отправляем только редактируемые поля (метаданные)
    const payload = {
      label: fieldForm.value.label || '',
      description: fieldForm.value.description || '',
      xtype: fieldForm.value.xtype || 'textfield',
      active: fieldForm.value.active === true || fieldForm.value.active === 'true' || fieldForm.value.active === 1
    }

    const response = await request.put(`/api/mgr/extra-fields/${fieldForm.value.id}`, payload)

    if (response && response.field) {
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: `${_('table_field_name')} "${response.field.key}" ${_('field_updated')}`,
        life: 3000
      })

      dialogVisible.value = false
      await loadFields()
    } else {
      throw new Error('Неверный формат ответа от сервера')
    }
  } catch (error) {
    console.error('[ExtraFieldsManager] Error updating field:', error)
    toast.add({
      severity: 'error',
      summary: _('error_updating'),
      detail: error.message || _('error_updating_field'),
      life: 5000
    })
  }
}

// Флаг для предотвращения двойного открытия confirm диалога
let confirmInProgress = false

/**
 * Удалить поле
 */
function confirmDelete(field) {
  // Защита от двойного клика
  if (confirmInProgress) {
    console.warn('[ExtraFieldsManager] Confirm dialog already open, ignoring duplicate call')
    return
  }

  confirmInProgress = true

  confirm.require({
    message: _('delete_confirm_message').replace('{0}', field.key),
    header: _('delete_confirm_title'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete_confirm_yes'),
    rejectLabel: _('dialog_cancel'),
    acceptClass: 'p-button-danger',
    accept: () => {
      // НЕ делаем await - диалог закроется сразу, а удаление пойдёт в фоне
      deleteField(field.id)
      confirmInProgress = false
    },
    reject: () => {
      confirmInProgress = false
    },
    onHide: () => {
      confirmInProgress = false
    }
  })
}

/**
 * Удалить поле (выполнение)
 */
async function deleteField(fieldId) {
  loading.value = true

  try {
    const response = await request.delete(`/api/mgr/extra-fields/${fieldId}`)

    if (response && response.message) {
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: response.message,
        life: 5000
      })

      await loadFields()
    } else {
      throw new Error('Неверный формат ответа от сервера')
    }
  } catch (error) {
    console.error('[ExtraFieldsManager] Error deleting field:', error)
    toast.add({
      severity: 'error',
      summary: _('error_deleting'),
      detail: error.message || _('error_deleting_field'),
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

/**
 * Получить severity для Tag (статус активности)
 */
function getActiveSeverity(active) {
  return active ? 'success' : 'danger'
}

/**
 * Получить severity для Tag (существование колонки)
 */
function getColumnExistsSeverity(exists) {
  return exists ? 'success' : 'warn'
}

/**
 * Фильтр по классу изменён
 */
async function onClassFilterChange() {
  await loadFields()
}

// Загрузка при монтировании
onMounted(() => {
  loadFields()
})
</script>

<template>
  <div class="extra-fields-manager">
    <Toast />
    <ConfirmDialog />

    <Card>
      <template #title>
        <div class="flex justify-content-between align-items-center">
          <span>{{ _('extra_fields_title') }}</span>
          <Button
            :label="_('extra_fields_create')"
            icon="pi pi-plus"
            @click="openCreateDialog"
            :disabled="loading"
          />
        </div>
      </template>

      <template #content>
        <!-- Фильтр по классу -->
        <div class="field mb-4">
          <label for="class-filter">{{ _('extra_fields_class_filter') }}</label>
          <Dropdown
            id="class-filter"
            v-model="selectedClass"
            :options="classOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="_('extra_fields_select_class')"
            class="w-full md:w-20rem"
            @change="onClassFilterChange"
          />
        </div>

        <!-- Таблица полей -->
        <DataTable
          :value="fields"
          :loading="loading"
          stripedRows
          showGridlines
          responsiveLayout="scroll"
          :paginator="fields.length > 10"
          :rows="10"
          :rowsPerPageOptions="[10, 20, 50]"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
          currentPageReportTemplate="Показано {first} - {last} из {totalRecords} полей"
        >
          <Column field="id" :header="_('table_id')" style="width: 60px" sortable />

          <Column field="key" :header="_('table_field_name')" sortable>
            <template #body="{ data }">
              <strong>{{ data.key }}</strong>
            </template>
          </Column>

          <Column field="label" :header="_('table_label')" sortable />

          <Column field="dbtype" :header="_('table_dbtype')" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.dbtype.toUpperCase()" severity="info" />
            </template>
          </Column>

          <Column field="precision" :header="_('table_precision')" style="width: 100px" />

          <Column field="index_type" :header="_('table_index')" style="width: 120px">
            <template #body="{ data }">
              <Tag
                v-if="data.index_type && data.index_type !== 'NONE'"
                :value="data.index_type"
                :severity="data.index_type === 'UNIQUE' ? 'warning' : 'info'"
              />
              <span v-else class="text-500">{{ _('table_no_index') }}</span>
            </template>
          </Column>

          <Column field="column_exists" :header="_('table_column_exists')" style="width: 140px">
            <template #body="{ data }">
              <Tag
                :value="data.column_exists ? _('table_column_exists_yes') : _('table_column_exists_no')"
                :severity="getColumnExistsSeverity(data.column_exists)"
              />
            </template>
          </Column>

          <Column field="active" :header="_('table_active')" style="width: 100px">
            <template #body="{ data }">
              <Tag
                :value="data.active ? _('table_active_yes') : _('table_active_no')"
                :severity="getActiveSeverity(data.active)"
              />
            </template>
          </Column>

          <Column :header="_('table_actions')" style="width: 150px">
            <template #body="{ data }">
              <Button
                icon="pi pi-pencil"
                severity="secondary"
                text
                rounded
                @click.stop="openEditDialog(data)"
                v-tooltip.top="_('extra_fields_edit')"
                class="mr-1"
              />
              <Button
                icon="pi pi-trash"
                severity="danger"
                text
                rounded
                @click.stop="confirmDelete(data)"
                v-tooltip.top="_('extra_fields_delete')"
              />
            </template>
          </Column>

          <template #empty>
            <div class="text-center p-4">
              {{ _('table_empty') }}
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Диалог создания/редактирования поля -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="isEditMode ? _('dialog_edit_title') : _('dialog_create_title')"
      :modal="true"
      :closable="!saving"
      :style="{ width: '700px' }"
      @hide="saving = false"
    >
      <div class="edit-field-form">
        <!-- Основная информация -->
        <Fieldset :legend="_('dialog_fieldset_basic')" class="mb-3">
          <div class="form-grid">
            <!-- Класс модели -->
            <div class="field col-12">
              <label for="field-class">{{ _('dialog_class') }}</label>
              <Dropdown
                id="field-class"
                v-model="fieldForm.class"
                :options="classOptions"
                optionLabel="label"
                optionValue="value"
                :placeholder="_('extra_fields_select_class')"
                class="w-full"
                :disabled="isEditMode"
              />
            </div>

            <!-- Имя поля (key) -->
            <div class="field col-6">
              <label for="field-key">{{ _('dialog_key') }}</label>
              <InputText
                id="field-key"
                v-model="fieldForm.key"
                :placeholder="_('dialog_key_placeholder')"
                class="w-full"
                :disabled="isEditMode"
              />
              <small class="text-500">{{ _('dialog_key_help') }}</small>
            </div>

            <!-- Метка (label) -->
            <div class="field col-6">
              <label for="field-label">{{ _('dialog_label') }}</label>
              <InputText
                id="field-label"
                v-model="fieldForm.label"
                :placeholder="_('dialog_label_placeholder')"
                class="w-full"
              />
            </div>

            <!-- Описание -->
            <div class="field col-12">
              <label for="field-description">{{ _('dialog_description') }}</label>
              <Textarea
                id="field-description"
                v-model="fieldForm.description"
                rows="2"
                class="w-full"
              />
            </div>

            <!-- Тип виджета (xtype) -->
            <div class="field col-12">
              <label for="field-xtype">{{ _('dialog_xtype') }}</label>
              <Dropdown
                id="field-xtype"
                v-model="fieldForm.xtype"
                :options="xtypeOptions"
                optionLabel="label"
                optionValue="value"
                :placeholder="_('dialog_xtype_select')"
                class="w-full"
              />
            </div>
          </div>
        </Fieldset>

        <!-- Параметры БД -->
        <Fieldset :legend="_('dialog_fieldset_database')" class="mb-3">
          <div class="form-grid">
          <!-- Тип БД -->
          <div class="field col-6">
            <label for="field-dbtype">{{ _('dialog_dbtype') }}</label>
            <Dropdown
              id="field-dbtype"
              v-model="fieldForm.dbtype"
              :options="dbtypeOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="_('dialog_xtype_select')"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Точность (precision) -->
          <div class="field col-6">
            <label for="field-precision">{{ _('dialog_precision') }}</label>
            <InputText
              id="field-precision"
              v-model="fieldForm.precision"
              :placeholder="_('dialog_precision_placeholder')"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- PHP тип -->
          <div class="field col-6">
            <label for="field-phptype">{{ _('dialog_phptype') }}</label>
            <Dropdown
              id="field-phptype"
              v-model="fieldForm.phptype"
              :options="phptypeOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="_('dialog_xtype_select')"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Nullable -->
          <div class="field col-6">
            <label for="field-null">{{ _('dialog_null') }}</label>
            <div class="flex align-items-center" style="height: 42px">
              <Checkbox
                id="field-null"
                v-model="fieldForm.null"
                :binary="true"
                :disabled="isEditMode"
              />
              <label for="field-null" class="ml-2 cursor-pointer">{{ _('dialog_null_label') }}</label>
            </div>
          </div>

          <!-- Значение по умолчанию -->
          <div class="field col-6">
            <label for="field-default">{{ _('dialog_default') }}</label>
            <Dropdown
              id="field-default"
              v-model="fieldForm.default"
              :options="defaultOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="_('dialog_xtype_select')"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Пользовательское значение по умолчанию -->
          <div class="field col-6" v-if="fieldForm.default === 'USER_DEFINED'">
            <label for="field-default-value">{{ _('dialog_default_value') }}</label>
            <InputText
              id="field-default-value"
              v-model="fieldForm.default_value"
              :placeholder="_('dialog_default_value_placeholder')"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Атрибуты -->
          <div class="field col-6">
            <label for="field-attributes">{{ _('dialog_attributes') }}</label>
            <InputText
              id="field-attributes"
              v-model="fieldForm.attributes"
              :placeholder="_('dialog_attributes_placeholder')"
              class="w-full"
              :disabled="isEditMode"
            />
            <small class="text-500">{{ _('dialog_attributes_help') }}</small>
          </div>

          <!-- Тип индекса -->
          <div class="field col-6">
            <label for="field-index-type">{{ _('dialog_index_type') }}</label>
            <Dropdown
              id="field-index-type"
              v-model="fieldForm.index_type"
              :options="indexTypeOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="_('dialog_xtype_select')"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Активность -->
          <div class="field col-12">
            <label for="field-active">{{ _('dialog_active') }}</label>
            <div class="flex align-items-center" style="height: 42px">
              <Checkbox
                id="field-active"
                v-model="fieldForm.active"
                :binary="true"
              />
              <label for="field-active" class="ml-2 cursor-pointer">{{ _('dialog_active_label') }}</label>
            </div>
          </div>
          </div>
        </Fieldset>
      </div>

      <template #footer>
        <Button
          :label="_('dialog_cancel')"
          icon="pi pi-times"
          text
          @click="dialogVisible = false"
          :disabled="saving"
        />
        <Button
          :label="isEditMode ? _('dialog_save') : _('dialog_create')"
          icon="pi pi-check"
          @click="saveField"
          :loading="saving"
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
/* Стили для модального окна - работают как в .vueApp так и в .p-dialog */
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

/* Сетка для модального окна */
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

/* Чекбокс в модальном окне */
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
