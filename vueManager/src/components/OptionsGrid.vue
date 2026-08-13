<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import request from '../request.js'
import { onOptionGroupsChanged } from '../utils/optionGroupsBus.js'
import OptionCategoryTree from './OptionCategoryTree.vue'
import OptionValuesEditor from './OptionValuesEditor.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const CONFIRM_GROUP = 'settings-options'

// Grid state
const options = ref([])
const totalRecords = ref(0)
const loading = ref(false)
const first = ref(0)
const rows = ref(20)
const optionGroupFilter = ref(null)
const selectedCategories = ref([])
const selectedRows = ref([])

// Reference data
const optionTypes = ref([])
const optionGroups = ref([])

// Dialog state
const dialogVisible = ref(false)
const dialogSaving = ref(false)
const isNewOption = ref(false)
const editing = ref(createBlankOption())
const editingCategories = ref([])
const editingPropertiesValues = ref([])

// Bulk assign dialog
const assignDialogVisible = ref(false)
const assignCategories = ref([])
const assigning = ref(false)

const MULTI_VALUE_TYPES = ['combobox', 'comboMultiple', 'comboColors']
const COLOR_VARIANT_TYPES = ['comboColors']

function createBlankOption() {
  return {
    id: null,
    key: '',
    caption: '',
    description: '',
    measure_unit: '',
    option_group_id: null,
    type: 'textfield',
    properties: {},
  }
}

const showValuesEditor = computed(() => MULTI_VALUE_TYPES.includes(editing.value.type))
const valuesVariant = computed(() =>
  COLOR_VARIANT_TYPES.includes(editing.value.type) ? 'colors' : 'simple'
)

async function loadOptionTypes() {
  const r = await request.get('/api/mgr/options/types')
  optionTypes.value = r?.results || []
}

async function loadOptionGroups() {
  const r = await request.get('/api/mgr/option-groups', { limit: 0 })
  optionGroups.value = r?.results || []
}

async function loadOptions() {
  loading.value = true
  try {
    const params = { start: first.value, limit: rows.value }
    if (optionGroupFilter.value !== null && optionGroupFilter.value !== '') {
      params.option_group_id = optionGroupFilter.value
    }
    if (selectedCategories.value.length > 0) {
      params.categories = JSON.stringify(selectedCategories.value)
    }

    const r = await request.get('/api/mgr/options', params)
    options.value = r?.results || []
    totalRecords.value = r?.total || 0
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error'), detail: e.message, life: 5000 })
  } finally {
    loading.value = false
  }
}

function onPage(event) {
  first.value = event.first
  rows.value = event.rows
  loadOptions()
}

watch(optionGroupFilter, () => {
  first.value = 0
  loadOptions()
})

watch(selectedCategories, () => {
  first.value = 0
  loadOptions()
})

/**
 * Open "create option" dialog.
 */
function openCreateDialog() {
  editing.value = createBlankOption()
  editingCategories.value = []
  editingPropertiesValues.value = []
  isNewOption.value = true
  dialogVisible.value = true
}

/**
 * Open "edit option" dialog — fetches detail including categories map.
 */
async function openEditDialog(row) {
  isNewOption.value = false
  dialogVisible.value = true
  dialogSaving.value = true

  try {
    const data = await request.get(`/api/mgr/options/${row.id}`)
    editing.value = {
      id: data.id,
      key: data.key || '',
      caption: data.caption || '',
      description: data.description || '',
      measure_unit: data.measure_unit || '',
      option_group_id: data.option_group_id ?? null,
      type: data.type || 'textfield',
      properties: data.properties || {},
    }
    const cats = data.categories || {}
    editingCategories.value = Object.entries(cats)
      .filter(([, v]) => !!v)
      .map(([k]) => parseInt(k, 10))
      .filter(n => n > 0)
    editingPropertiesValues.value = Array.isArray(data.properties?.values)
      ? [...data.properties.values]
      : []
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error'), detail: e.message, life: 5000 })
    dialogVisible.value = false
  } finally {
    dialogSaving.value = false
  }
}

async function saveOption() {
  if (!editing.value.key?.trim()) {
    toast.add({
      severity: 'warn',
      summary: _('error'),
      detail: _('ms3_option_err_name_ns') || 'Ключ обязателен',
    })
    return
  }

  dialogSaving.value = true
  try {
    const payload = {
      key: editing.value.key.trim(),
      caption: editing.value.caption,
      description: editing.value.description,
      measure_unit: editing.value.measure_unit,
      option_group_id: editing.value.option_group_id,
      type: editing.value.type,
      properties: buildProperties(),
      categories: editingCategories.value,
    }

    if (isNewOption.value) {
      await request.post('/api/mgr/options', payload)
      toast.add({
        severity: 'success',
        summary: _('success') || 'OK',
        detail: _('ms3_option_created') || 'Опция создана',
        life: 3000,
      })
    } else {
      await request.put(`/api/mgr/options/${editing.value.id}`, payload)
      toast.add({
        severity: 'success',
        summary: _('success') || 'OK',
        detail: _('ms3_option_updated') || 'Опция сохранена',
        life: 3000,
      })
    }

    dialogVisible.value = false
    loadOptions()
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error'), detail: e.message, life: 5000 })
  } finally {
    dialogSaving.value = false
  }
}

function buildProperties() {
  if (!MULTI_VALUE_TYPES.includes(editing.value.type)) {
    return {}
  }
  if (valuesVariant.value === 'colors') {
    // Drop empty rows; keep pairs with at least a value or color.
    const clean = editingPropertiesValues.value
      .map(r =>
        typeof r === 'object' && r !== null
          ? { value: r.value || '', name: r.name || '' }
          : { value: String(r), name: '' }
      )
      .filter(r => r.value !== '' || r.name !== '')
    return { values: clean }
  }
  const clean = editingPropertiesValues.value
    .map(v => (typeof v === 'string' ? v.trim() : v))
    .filter(v => v !== '' && v !== null && v !== undefined)
  return { values: clean }
}

function confirmDelete(row) {
  confirm.require({
    group: CONFIRM_GROUP,
    message:
      _('ms3_option_remove_confirm') ||
      `Удалить опцию «${row.caption || row.key}»? Значения у товаров будут удалены.`,
    header: _('confirm') || 'Подтверждение',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/options/${row.id}`)
        toast.add({
          severity: 'success',
          summary: _('success') || 'OK',
          detail: _('ms3_option_deleted') || 'Опция удалена',
          life: 3000,
        })
        loadOptions()
      } catch (e) {
        toast.add({ severity: 'error', summary: _('error'), detail: e.message, life: 5000 })
      }
    },
  })
}

function confirmBulkDelete() {
  if (selectedRows.value.length === 0) return
  const ids = selectedRows.value.map(r => r.id)
  confirm.require({
    group: CONFIRM_GROUP,
    message:
      _('ms3_options_remove_confirm') ||
      `Удалить выбранные опции (${ids.length})? Значения у товаров будут удалены.`,
    header: _('confirm') || 'Подтверждение',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete('/api/mgr/options/bulk', { ids })
        toast.add({
          severity: 'success',
          summary: _('success') || 'OK',
          detail: `Удалено: ${ids.length}`,
          life: 3000,
        })
        selectedRows.value = []
        loadOptions()
      } catch (e) {
        toast.add({ severity: 'error', summary: _('error'), detail: e.message, life: 5000 })
      }
    },
  })
}

function openAssignDialog() {
  if (selectedRows.value.length === 0) return
  assignCategories.value = []
  assignDialogVisible.value = true
}

async function performAssign() {
  if (selectedRows.value.length === 0 || assignCategories.value.length === 0) return
  assigning.value = true
  try {
    await request.post('/api/mgr/options/bulk/assign', {
      options: selectedRows.value.map(r => r.id),
      categories: assignCategories.value,
    })
    toast.add({
      severity: 'success',
      summary: _('success') || 'OK',
      detail: _('ms3_options_assigned') || 'Опции назначены категориям',
      life: 3000,
    })
    assignDialogVisible.value = false
    selectedRows.value = []
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error'), detail: e.message, life: 5000 })
  } finally {
    assigning.value = false
  }
}

function typeCaption(typeName) {
  const row = optionTypes.value.find(t => t.name === typeName)
  return row ? row.caption : typeName
}

// Sync with OptionGroupsGrid (sibling tab): refresh dropdown when groups are
// created / updated / deleted / reordered there. See utils/optionGroupsBus.js.
let unsubscribeOptionGroupsChanged = null

onMounted(() => {
  Promise.all([loadOptionTypes(), loadOptionGroups()]).then(() => loadOptions())
  unsubscribeOptionGroupsChanged = onOptionGroupsChanged(loadOptionGroups)
})

onBeforeUnmount(() => {
  if (unsubscribeOptionGroupsChanged) {
    unsubscribeOptionGroupsChanged()
    unsubscribeOptionGroupsChanged = null
  }
})
</script>

<template>
  <div class="options-grid-app">
    <Toast />
    <ConfirmDialog :group="CONFIRM_GROUP" />

    <div class="options-grid-layout">
      <!-- Category filter tree (left pane) -->
      <aside class="options-tree-pane">
        <h4 class="pane-title">{{ _('ms3_categories') || 'Категории' }}</h4>
        <OptionCategoryTree v-model="selectedCategories" />
      </aside>

      <!-- Main grid + toolbar + dialog (right pane) -->
      <section class="options-grid-pane">
        <div class="toolbar">
          <Button
            icon="pi pi-plus"
            :label="_('ms3_option_create') || 'Создать опцию'"
            severity="primary"
            @click="openCreateDialog"
          />

          <Select
            v-model="optionGroupFilter"
            :options="optionGroups"
            option-label="name"
            option-value="id"
            :placeholder="_('ms3_option_group_filter')"
            show-clear
            class="option-group-filter"
          />

          <Button
            v-if="selectedRows.length > 0"
            icon="pi pi-link"
            :label="`${_('ms3_options_assign') || 'Назначить в категории'} (${selectedRows.length})`"
            severity="secondary"
            @click="openAssignDialog"
          />
          <Button
            v-if="selectedRows.length > 0"
            icon="pi pi-trash"
            :label="`${_('delete') || 'Удалить'} (${selectedRows.length})`"
            severity="danger"
            @click="confirmBulkDelete"
          />
        </div>

        <DataTable
          v-model:selection="selectedRows"
          :value="options"
          :loading="loading"
          :paginator="true"
          :rows="rows"
          :first="first"
          :total-records="totalRecords"
          lazy
          data-key="id"
          striped-rows
          size="small"
          @page="onPage"
          @row-dblclick="openEditDialog($event.data)"
        >
          <Column selection-mode="multiple" header-style="width: 3rem" />
          <Column field="id" :header="_('id') || 'ID'" style="width: 4rem" />
          <Column field="key" :header="_('ms3_ft_name') || 'Ключ'" />
          <Column field="caption" :header="_('ms3_ft_caption') || 'Название'" />
          <Column :header="_('ms3_ft_type') || 'Тип'">
            <template #body="{ data }">
              {{ typeCaption(data.type) }}
            </template>
          </Column>
          <Column :header="_('actions') || 'Действия'" style="width: 7rem">
            <template #body="{ data }">
              <Button
                icon="pi pi-pencil"
                severity="secondary"
                text
                rounded
                size="small"
                :title="_('edit') || 'Редактировать'"
                @click="openEditDialog(data)"
              />
              <Button
                icon="pi pi-trash"
                severity="danger"
                text
                rounded
                size="small"
                :title="_('delete') || 'Удалить'"
                @click="confirmDelete(data)"
              />
            </template>
          </Column>
          <template #empty>
            <div class="empty-message">{{ _('ms3_options_empty') || 'Опций не найдено' }}</div>
          </template>
        </DataTable>
      </section>
    </div>

    <!-- Create/Edit dialog -->
    <Dialog
      v-model:visible="dialogVisible"
      class="ms3-option-dialog vueApp"
      :header="
        isNewOption
          ? _('ms3_option_create') || 'Создать опцию'
          : _('ms3_option_update') || 'Редактировать опцию'
      "
      modal
      :style="{ width: '56rem' }"
      :closable="!dialogSaving"
    >
      <div class="dialog-layout">
        <div class="dialog-form">
          <div class="form-row two-cols">
            <div class="form-field">
              <label for="opt-key"
                >{{ _('ms3_ft_name') || 'Ключ' }} <span class="req">*</span></label
              >
              <InputText
                id="opt-key"
                v-model="editing.key"
                class="w-full"
                :disabled="dialogSaving"
              />
            </div>
            <div class="form-field">
              <label for="opt-caption">{{ _('ms3_ft_caption') || 'Название' }}</label>
              <InputText
                id="opt-caption"
                v-model="editing.caption"
                class="w-full"
                :disabled="dialogSaving"
              />
            </div>
          </div>

          <div class="form-row two-cols">
            <div class="form-field">
              <label for="opt-type">{{ _('ms3_ft_type') || 'Тип' }}</label>
              <Select
                id="opt-type"
                v-model="editing.type"
                :options="optionTypes"
                option-label="caption"
                option-value="name"
                class="w-full"
                :disabled="dialogSaving"
              />
            </div>
            <div class="form-field">
              <label for="opt-option-group">{{ _('ms3_option_group') }}</label>
              <Select
                id="opt-option-group"
                v-model="editing.option_group_id"
                :options="optionGroups"
                option-label="name"
                option-value="id"
                :placeholder="_('ms3_option_group_no_group')"
                show-clear
                class="w-full"
                :disabled="dialogSaving"
              />
            </div>
          </div>

          <div class="form-row">
            <div class="form-field">
              <label for="opt-measure">{{ _('ms3_ft_measure_unit') || 'Единица измерения' }}</label>
              <InputText
                id="opt-measure"
                v-model="editing.measure_unit"
                class="w-full"
                :disabled="dialogSaving"
              />
            </div>
          </div>

          <div class="form-row">
            <div class="form-field">
              <label for="opt-desc">{{ _('ms3_ft_description') || 'Описание' }}</label>
              <Textarea
                id="opt-desc"
                v-model="editing.description"
                class="w-full"
                rows="3"
                :disabled="dialogSaving"
              />
            </div>
          </div>

          <div v-if="showValuesEditor" class="form-row">
            <div class="form-field">
              <label>{{ _('ms3_default_values') || 'Значения' }}</label>
              <OptionValuesEditor v-model="editingPropertiesValues" :variant="valuesVariant" />
            </div>
          </div>
        </div>

        <div class="dialog-tree">
          <h4 class="pane-title">{{ _('ms3_categories') || 'Категории' }}</h4>
          <OptionCategoryTree
            v-model="editingCategories"
            :option-id="editing.id || 0"
          />
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel') || 'Отмена'"
          severity="secondary"
          :disabled="dialogSaving"
          @click="dialogVisible = false"
        />
        <Button :label="_('save') || 'Сохранить'" :loading="dialogSaving" @click="saveOption" />
      </template>
    </Dialog>

    <!-- Bulk assign dialog -->
    <Dialog
      v-model:visible="assignDialogVisible"
      class="ms3-option-dialog vueApp"
      :header="_('ms3_options_assign') || 'Назначить опции в категории'"
      modal
      :style="{ width: '32rem', height: '32rem' }"
      :closable="!assigning"
    >
      <p class="dialog-hint">
        {{
          _('ms3_options_assign_hint') ||
          'Выберите категории — в них будут созданы связи для выбранных опций (существующие останутся).'
        }}
      </p>
      <OptionCategoryTree v-model="assignCategories" />
      <template #footer>
        <Button
          :label="_('cancel') || 'Отмена'"
          severity="secondary"
          :disabled="assigning"
          @click="assignDialogVisible = false"
        />
        <Button
          :label="`${_('ms3_options_assign') || 'Назначить'} (${selectedRows.length} × ${assignCategories.length})`"
          :loading="assigning"
          :disabled="assignCategories.length === 0"
          @click="performAssign"
        />
      </template>
    </Dialog>
  </div>
</template>

<style>
/* Non-scoped with .vueApp prefix — avoids Vite scoped-hash mismatch across chunks */
.vueApp .options-grid-app {
  width: 100%;
}

.vueApp .options-grid-app .options-grid-layout {
  display: grid;
  grid-template-columns: 18rem 1fr;
  gap: 1rem;
  align-items: start;
}

.vueApp .options-grid-app .options-tree-pane,
.vueApp .options-grid-app .options-grid-pane {
  min-width: 0;
}

.vueApp .options-grid-app .pane-title {
  margin: 0 0 0.5rem;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--p-text-color, #374151);
}

.vueApp .options-grid-app .toolbar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
}

.vueApp .options-grid-app .toolbar .option-group-filter {
  min-width: 12rem;
}

.vueApp .options-grid-app .empty-message {
  padding: 1.5rem;
  text-align: center;
  color: var(--p-text-muted-color, #9ca3af);
}

/* Dialog is teleported out of .options-grid-app by PrimeVue — scope by .ms3-option-dialog instead */
.ms3-option-dialog .dialog-layout {
  display: grid;
  grid-template-columns: 1fr 16rem;
  gap: 1rem;
  min-height: 28rem;
}

.ms3-option-dialog .dialog-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 0;
}

.ms3-option-dialog .dialog-tree {
  border-left: 1px solid var(--p-content-border-color, #e5e7eb);
  padding-left: 1rem;
  display: flex;
  flex-direction: column;
}

.ms3-option-dialog .form-row {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.ms3-option-dialog .form-row.two-cols {
  flex-direction: row;
  gap: 0.75rem;
}

.ms3-option-dialog .form-row.two-cols > .form-field {
  flex: 1;
  min-width: 0;
}

.ms3-option-dialog .form-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex: 1;
  min-width: 0;
}

.ms3-option-dialog .form-field label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-text-color, #374151);
}

.ms3-option-dialog .form-field .req {
  color: var(--p-red-500, #ef4444);
}

.ms3-option-dialog .dialog-hint {
  margin-top: 0;
  color: var(--p-text-muted-color, #9ca3af);
  font-size: 0.9rem;
}

.ms3-option-dialog .option-category-tree {
  min-height: 18rem;
}
</style>
