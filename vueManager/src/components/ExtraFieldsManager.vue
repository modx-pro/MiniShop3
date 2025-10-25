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
const classOptions = [
  { label: 'msProductData (Товары)', value: 'MiniShop3\\Model\\msProductData' },
  { label: 'msVendor (Производители)', value: 'MiniShop3\\Model\\msVendor' },
  { label: 'msOrder (Заказы)', value: 'MiniShop3\\Model\\msOrder' },
  { label: 'msCategory (Категории)', value: 'MiniShop3\\Model\\msCategory' }
]

/**
 * Типы виджетов (xtype)
 */
const xtypeOptions = [
  { label: 'Текстовое поле', value: 'textfield' },
  { label: 'Числовое поле', value: 'numberfield' },
  { label: 'Текстовая область', value: 'textarea' },
  { label: 'Дата', value: 'datefield' },
  { label: 'Дата и время', value: 'datetimefield' },
  { label: 'Комбинированный список', value: 'combo' },
  { label: 'Флажок', value: 'checkbox' }
]

/**
 * Типы данных БД (dbtype)
 */
const dbtypeOptions = [
  { label: 'VARCHAR (строка)', value: 'varchar' },
  { label: 'TEXT (текст)', value: 'text' },
  { label: 'INT (целое число)', value: 'int' },
  { label: 'DECIMAL (число с точностью)', value: 'decimal' },
  { label: 'DATETIME (дата и время)', value: 'datetime' },
  { label: 'TIMESTAMP', value: 'timestamp' },
  { label: 'TINYINT (0/1)', value: 'tinyint' },
  { label: 'JSON', value: 'json' }
]

/**
 * PHP типы (phptype)
 */
const phptypeOptions = [
  { label: 'string (строка)', value: 'string' },
  { label: 'integer (целое)', value: 'integer' },
  { label: 'float (дробное)', value: 'float' },
  { label: 'boolean (да/нет)', value: 'boolean' },
  { label: 'json (массив)', value: 'json' },
  { label: 'datetime', value: 'datetime' },
  { label: 'timestamp', value: 'timestamp' }
]

/**
 * Типы значений по умолчанию
 */
const defaultOptions = [
  { label: 'NULL', value: 'NULL' },
  { label: 'Текущее время', value: 'CURRENT_TIMESTAMP' },
  { label: 'Пользовательское значение', value: 'USER_DEFINED' },
  { label: 'Без значения', value: 'NONE' }
]

/**
 * Типы индексов
 */
const indexTypeOptions = [
  { label: 'Без индекса', value: 'NONE' },
  { label: 'Обычный индекс (INDEX)', value: 'INDEX' },
  { label: 'Уникальный индекс (UNIQUE)', value: 'UNIQUE' },
  { label: 'Полнотекстовый (FULLTEXT)', value: 'FULLTEXT' }
]

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
      summary: 'Ошибка загрузки',
      detail: error.message || 'Не удалось загрузить список полей',
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
        summary: 'Валидация',
        detail: 'Укажите имя поля (key)',
        life: 3000
      })
      return
    }

    if (!fieldForm.value.dbtype) {
      toast.add({
        severity: 'warn',
        summary: 'Валидация',
        detail: 'Укажите тип данных БД (dbtype)',
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
        summary: 'Успешно',
        detail: `Поле "${response.field.key}" создано`,
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
      summary: 'Ошибка создания',
      detail: error.message || 'Не удалось создать поле',
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
        summary: 'Успешно',
        detail: `Поле "${response.field.key}" обновлено`,
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
      summary: 'Ошибка обновления',
      detail: error.message || 'Не удалось обновить поле',
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
    message: `Вы уверены, что хотите удалить поле "${field.key}"? Это удалит колонку из таблицы БД!`,
    header: 'Подтверждение удаления',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Да, удалить',
    rejectLabel: 'Отмена',
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
        summary: 'Успешно',
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
      summary: 'Ошибка удаления',
      detail: error.message || 'Не удалось удалить поле',
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
          <span>Управление дополнительными полями</span>
          <Button
            label="Создать поле"
            icon="pi pi-plus"
            @click="openCreateDialog"
            :disabled="loading"
          />
        </div>
      </template>

      <template #content>
        <!-- Фильтр по классу -->
        <div class="field mb-4">
          <label for="class-filter">Класс модели:</label>
          <Dropdown
            id="class-filter"
            v-model="selectedClass"
            :options="classOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Выберите класс"
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
          <Column field="id" header="ID" style="width: 60px" sortable />

          <Column field="key" header="Имя поля" sortable>
            <template #body="{ data }">
              <strong>{{ data.key }}</strong>
            </template>
          </Column>

          <Column field="label" header="Метка" sortable />

          <Column field="dbtype" header="Тип БД" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.dbtype.toUpperCase()" severity="info" />
            </template>
          </Column>

          <Column field="precision" header="Точность" style="width: 100px" />

          <Column field="index_type" header="Индекс" style="width: 120px">
            <template #body="{ data }">
              <Tag
                v-if="data.index_type && data.index_type !== 'NONE'"
                :value="data.index_type"
                :severity="data.index_type === 'UNIQUE' ? 'warning' : 'info'"
              />
              <span v-else class="text-500">Нет</span>
            </template>
          </Column>

          <Column field="column_exists" header="Колонка в БД" style="width: 140px">
            <template #body="{ data }">
              <Tag
                :value="data.column_exists ? 'Существует' : 'Не создана'"
                :severity="getColumnExistsSeverity(data.column_exists)"
              />
            </template>
          </Column>

          <Column field="active" header="Активно" style="width: 100px">
            <template #body="{ data }">
              <Tag
                :value="data.active ? 'Да' : 'Нет'"
                :severity="getActiveSeverity(data.active)"
              />
            </template>
          </Column>

          <Column header="Действия" style="width: 150px">
            <template #body="{ data }">
              <Button
                icon="pi pi-pencil"
                severity="secondary"
                text
                rounded
                @click.stop="openEditDialog(data)"
                v-tooltip.top="'Редактировать поле'"
                class="mr-1"
              />
              <Button
                icon="pi pi-trash"
                severity="danger"
                text
                rounded
                @click.stop="confirmDelete(data)"
                v-tooltip.top="'Удалить поле'"
              />
            </template>
          </Column>

          <template #empty>
            <div class="text-center p-4">
              Поля не найдены. Создайте первое дополнительное поле.
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Диалог создания/редактирования поля -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="isEditMode ? 'Редактирование поля' : 'Создание дополнительного поля'"
      :modal="true"
      :closable="!saving"
      :style="{ width: '700px' }"
      @hide="saving = false"
    >
      <div class="edit-field-form">
        <!-- Основная информация -->
        <Fieldset legend="Основная информация" class="mb-3">
          <div class="form-grid">
            <!-- Класс модели -->
            <div class="field col-12">
              <label for="field-class">Класс модели *</label>
              <Dropdown
                id="field-class"
                v-model="fieldForm.class"
                :options="classOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Выберите класс"
                class="w-full"
                :disabled="isEditMode"
              />
            </div>

            <!-- Имя поля (key) -->
            <div class="field col-6">
              <label for="field-key">Имя поля (key) *</label>
              <InputText
                id="field-key"
                v-model="fieldForm.key"
                placeholder="warranty_months"
                class="w-full"
                :disabled="isEditMode"
              />
              <small class="text-500">Латинские буквы, цифры, подчеркивание. Без пробелов.</small>
            </div>

            <!-- Метка (label) -->
            <div class="field col-6">
              <label for="field-label">Метка</label>
              <InputText
                id="field-label"
                v-model="fieldForm.label"
                placeholder="Гарантия (месяцев)"
                class="w-full"
              />
            </div>

            <!-- Описание -->
            <div class="field col-12">
              <label for="field-description">Описание</label>
              <Textarea
                id="field-description"
                v-model="fieldForm.description"
                rows="2"
                class="w-full"
              />
            </div>

            <!-- Тип виджета (xtype) -->
            <div class="field col-12">
              <label for="field-xtype">Тип виджета (xtype)</label>
              <Dropdown
                id="field-xtype"
                v-model="fieldForm.xtype"
                :options="xtypeOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Выберите тип"
                class="w-full"
              />
            </div>
          </div>
        </Fieldset>

        <!-- Параметры БД -->
        <Fieldset legend="Параметры базы данных" class="mb-3">
          <div class="form-grid">
          <!-- Тип БД -->
          <div class="field col-6">
            <label for="field-dbtype">Тип данных БД (dbtype) *</label>
            <Dropdown
              id="field-dbtype"
              v-model="fieldForm.dbtype"
              :options="dbtypeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Выберите тип"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Точность (precision) -->
          <div class="field col-6">
            <label for="field-precision">Точность (precision)</label>
            <InputText
              id="field-precision"
              v-model="fieldForm.precision"
              placeholder="255 или 12,2 для decimal"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- PHP тип -->
          <div class="field col-6">
            <label for="field-phptype">PHP тип (phptype) *</label>
            <Dropdown
              id="field-phptype"
              v-model="fieldForm.phptype"
              :options="phptypeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Выберите тип"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Nullable -->
          <div class="field col-6">
            <label for="field-null">Разрешить NULL</label>
            <div class="flex align-items-center" style="height: 42px">
              <Checkbox
                id="field-null"
                v-model="fieldForm.null"
                :binary="true"
                :disabled="isEditMode"
              />
              <label for="field-null" class="ml-2 cursor-pointer">Разрешено</label>
            </div>
          </div>

          <!-- Значение по умолчанию -->
          <div class="field col-6">
            <label for="field-default">Значение по умолчанию</label>
            <Dropdown
              id="field-default"
              v-model="fieldForm.default"
              :options="defaultOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Выберите тип"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Пользовательское значение по умолчанию -->
          <div class="field col-6" v-if="fieldForm.default === 'USER_DEFINED'">
            <label for="field-default-value">Пользовательское значение</label>
            <InputText
              id="field-default-value"
              v-model="fieldForm.default_value"
              placeholder="12"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Атрибуты -->
          <div class="field col-6">
            <label for="field-attributes">Атрибуты (attributes)</label>
            <InputText
              id="field-attributes"
              v-model="fieldForm.attributes"
              placeholder="unsigned, auto_increment"
              class="w-full"
              :disabled="isEditMode"
            />
            <small class="text-500">Например: unsigned</small>
          </div>

          <!-- Тип индекса -->
          <div class="field col-6">
            <label for="field-index-type">Тип индекса</label>
            <Dropdown
              id="field-index-type"
              v-model="fieldForm.index_type"
              :options="indexTypeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Выберите тип"
              class="w-full"
              :disabled="isEditMode"
            />
          </div>

          <!-- Активность -->
          <div class="field col-12">
            <label for="field-active">Активность</label>
            <div class="flex align-items-center" style="height: 42px">
              <Checkbox
                id="field-active"
                v-model="fieldForm.active"
                :binary="true"
              />
              <label for="field-active" class="ml-2 cursor-pointer">Активировать поле сразу после создания</label>
            </div>
          </div>
          </div>
        </Fieldset>
      </div>

      <template #footer>
        <Button
          label="Отмена"
          icon="pi pi-times"
          text
          @click="dialogVisible = false"
          :disabled="saving"
        />
        <Button
          :label="isEditMode ? 'Сохранить' : 'Создать поле'"
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
