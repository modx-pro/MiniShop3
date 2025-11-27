<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
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

// Конфигурация колонок грида (загружается из API)
const columns = ref([])

// Состояние таблицы
const loading = ref(false)
const customers = ref([])
const totalRecords = ref(0)
const first = ref(0)
const rows = ref(20)

// Фильтры (для формы)
const filterValues = ref({})

// Фильтруемые колонки (computed)
const filterableColumns = computed(() => columns.value.filter(col => col.filterable && col.visible))

// Поиск
const searchQuery = ref('')

// Модальное окно редактирования
const editDialogVisible = ref(false)
const editingCustomer = ref(null)
const saving = ref(false)

/**
 * Загрузить список клиентов
 */
async function loadCustomers() {
  loading.value = true

  try {
    const params = {
      start: first.value,
      limit: rows.value
    }

    if (searchQuery.value) {
      params.query = searchQuery.value
    }

    // Добавляем фильтры колонок
    Object.keys(filterValues.value).forEach(key => {
      const value = filterValues.value[key]
      if (value !== null && value !== undefined && value !== '') {
        params[`filter_${key}`] = value
      }
    })

    const response = await request.get('/api/mgr/customers', params)

    if (response && response.results) {
      customers.value = response.results
      totalRecords.value = response.total || 0
    } else {
      console.error('[CustomersGrid] Invalid response:', response)
      customers.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[CustomersGrid] Error loading customers:', error)
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
 * Обработчик пагинации
 */
function onPage(event) {
  first.value = event.first
  rows.value = event.rows
  loadCustomers()
}


/**
 * Обработчик поиска
 */
function onSearch() {
  first.value = 0 // Сбросить на первую страницу
  loadCustomers()
}

/**
 * Открыть модальное окно редактирования
 */
function editCustomer(customer) {
  editingCustomer.value = { ...customer }
  editDialogVisible.value = true
}

/**
 * Сохранить изменения клиента
 */
async function saveCustomer() {
  if (!editingCustomer.value) return

  saving.value = true

  try {
    await request.put(`/api/mgr/customers/${editingCustomer.value.id}`, editingCustomer.value)

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('customer_updated'),
      life: 3000
    })

    editDialogVisible.value = false
    await loadCustomers()
  } catch (error) {
    console.error('[CustomersGrid] Error saving customer:', error)
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
 * Удалить клиента
 */
function deleteCustomer(customer) {
  confirm.require({
    message: _('customer_delete_confirm_message').replace('{name}', `${customer.first_name} ${customer.last_name}`),
    header: _('customer_delete_confirm_title'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/customers/${customer.id}`)

        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('customer_deleted'),
          life: 3000
        })

        await loadCustomers()
      } catch (error) {
        console.error('[CustomersGrid] Error deleting customer:', error)
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
 * Форматирование даты
 */
function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

/**
 * Форматирование статуса email
 */
function formatEmailStatus(customer) {
  return customer.email_verified_at ? _('verified') : _('not_verified')
}

/**
 * Инициализировать фильтры для колонок
 */
function initFilters() {
  const newFilters = {}

  columns.value.forEach(column => {
    if (column.filterable) {
      newFilters[column.name] = ''
    }
  })

  filterValues.value = newFilters
}

/**
 * Применить фильтры
 */
function applyFilters() {
  first.value = 0
  loadCustomers()
}

/**
 * Сбросить фильтры
 */
function clearFilters() {
  initFilters()
  first.value = 0
  loadCustomers()
}

/**
 * Загрузить конфигурацию грида
 */
async function loadGridConfig() {
  try {
    const response = await request.get('/api/mgr/grid-config/customers')
    columns.value = response.columns || []
    initFilters()
  } catch (error) {
    console.error('[CustomersGrid] Failed to load grid config:', error)
    // Fallback на дефолтные колонки
    columns.value = getDefaultColumns()
    initFilters()
  }
}

/**
 * Дефолтные колонки (если API недоступен)
 */
function getDefaultColumns() {
  return [
    { name: 'id', label: 'ID', visible: true, sortable: true, frozen: true, width: '80px', isSystem: true },
    { name: 'name', label: _('customer_name'), visible: true, sortable: true, template: '{first_name} {last_name}' },
    { name: 'email', label: _('customer_email'), visible: true, sortable: true },
    { name: 'phone', label: _('customer_phone'), visible: true },
    { name: 'is_active', label: _('customer_active'), visible: true, sortable: true, type: 'boolean', width: '100px' },
    { name: 'created_at', label: _('created_at'), visible: true, sortable: true, format: 'datetime', width: '180px' },
    { name: 'actions', label: _('actions'), visible: true, isSystem: true, width: '180px', type: 'actions' },
  ]
}

/**
 * Рендерить значение колонки по template
 */
function renderField(data, column) {
  if (column.template) {
    // Поддержка шаблонов вида: "{first_name} {last_name}"
    return column.template.replace(/\{(\w+)\}/g, (match, key) => data[key] || '')
  }
  return data[column.name]
}

onMounted(async () => {
  await loadGridConfig()
  await loadCustomers()
})
</script>

<template>
  <div class="customers-grid">
    <Toast />
    <ConfirmDialog />

    <Card>
      <template #title>
        {{ _('customers_title') }}
      </template>

      <template #content>
        <!-- Поиск -->
        <div class="p-inputgroup mb-3">
          <InputText
            v-model="searchQuery"
            :placeholder="_('search_placeholder')"
            @keyup.enter="onSearch"
          />
          <Button
            icon="pi pi-search"
            :label="_('search')"
            @click="onSearch"
          />
        </div>

        <!-- Форма фильтров -->
        <div v-if="filterableColumns.length > 0" class="filters-form mb-3 p-3 surface-ground" style="border-radius: 6px;">
          <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
            <div
              v-for="column in filterableColumns"
              :key="column.name"
              style="flex: 1 1 300px; min-width: 250px;"
            >
              <div class="field">
                <label :for="`filter-${column.name}`" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">{{ column.label }}</label>
                <InputText
                  :id="`filter-${column.name}`"
                  v-model="filterValues[column.name]"
                  :placeholder="`Фильтр по ${column.label}`"
                  style="width: 100%;"
                  @keyup.enter="applyFilters"
                />
              </div>
            </div>
          </div>
          <div style="display: flex; gap: 0.5rem;">
            <Button
              label="Применить фильтры"
              icon="pi pi-filter"
              @click="applyFilters"
            />
            <Button
              label="Сбросить фильтры"
              icon="pi pi-filter-slash"
              severity="secondary"
              @click="clearFilters"
            />
          </div>
        </div>

        <!-- Таблица -->
        <DataTable
          :value="customers"
          :loading="loading"
          :paginator="true"
          :rows="rows"
          :totalRecords="totalRecords"
          :lazy="true"
          @page="onPage"
          stripedRows
          responsiveLayout="scroll"
        >
          <!-- Динамическое отображение колонок -->
          <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
            <!-- Колонка Actions (специальная обработка) -->
            <Column
              v-if="column.type === 'actions'"
              :header="column.label"
              :sortable="column.sortable"
              :frozen="column.frozen"
              :style="{ width: column.width }"
            >
              <template #body="{ data }">
                <Button
                  icon="pi pi-pencil"
                  class="p-button-sm p-button-text"
                  :title="_('edit')"
                  @click="editCustomer(data)"
                />
                <Button
                  icon="pi pi-trash"
                  class="p-button-sm p-button-text p-button-danger"
                  :title="_('delete')"
                  @click="deleteCustomer(data)"
                />
              </template>
            </Column>

            <!-- Обычные колонки -->
            <Column
              v-else
              :field="column.name"
              :header="column.label"
              :sortable="column.sortable"
              :frozen="column.frozen"
              :style="{ width: column.width }"
            >
              <template #body="{ data }">
                <!-- Boolean поле (checkbox) -->
                <Checkbox
                  v-if="column.type === 'boolean'"
                  :model-value="data[column.name]"
                  :binary="true"
                  disabled
                />
                <!-- Datetime поле -->
                <span v-else-if="column.format === 'datetime'">
                  {{ formatDate(data[column.name]) }}
                </span>
                <!-- Template поле (например: {first_name} {last_name}) -->
                <span v-else-if="column.template">
                  {{ renderField(data, column) }}
                </span>
                <!-- Обычное текстовое поле -->
                <span v-else>
                  {{ data[column.name] }}
                </span>
              </template>
            </Column>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Модальное окно редактирования -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="_('edit_customer')"
      :modal="true"
      :closable="true"
      :style="{ width: '600px' }"
    >
      <div v-if="editingCustomer" class="p-fluid">
        <div class="field mb-3">
          <label for="first_name">{{ _('customer_first_name') }}</label>
          <InputText id="first_name" v-model="editingCustomer.first_name" />
        </div>

        <div class="field mb-3">
          <label for="last_name">{{ _('customer_last_name') }}</label>
          <InputText id="last_name" v-model="editingCustomer.last_name" />
        </div>

        <div class="field mb-3">
          <label for="email">{{ _('customer_email') }}</label>
          <InputText id="email" v-model="editingCustomer.email" type="email" />
        </div>

        <div class="field mb-3">
          <label for="phone">{{ _('customer_phone') }}</label>
          <InputText id="phone" v-model="editingCustomer.phone" />
        </div>

        <div class="field-checkbox mb-3">
          <Checkbox id="is_active" v-model="editingCustomer.is_active" :binary="true" />
          <label for="is_active">{{ _('customer_active') }}</label>
        </div>

        <div class="field-checkbox mb-3">
          <Checkbox id="is_blocked" v-model="editingCustomer.is_blocked" :binary="true" />
          <label for="is_blocked">{{ _('customer_blocked') }}</label>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          class="p-button-text"
          @click="editDialogVisible = false"
        />
        <Button
          :label="_('save')"
          icon="pi pi-check"
          :loading="saving"
          @click="saveCustomer"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.customers-grid {
  padding: 20px;
}

.text-success {
  color: #22c55e;
}

.text-warning {
  color: #f59e0b;
}
</style>
