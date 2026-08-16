<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { onMounted, ref, watch } from 'vue'

import { useGroupedToast, useUiGroup } from '../composables/uiGroup.js'
import request from '../request.js'

const props = defineProps({
  categoryId: { type: Number, required: true },
})

const confirm = useConfirm()
const { _ } = useLexicon()

// From entry provideUiGroup('category-options'); fallback for non-entry mounts (#538/#539).
const UI_GROUP = useUiGroup() || 'category-options'
const toast = useGroupedToast(UI_GROUP)

const links = ref([])
const loading = ref(false)
const searchQuery = ref('')
const selectedRows = ref([])
const optionTypes = ref([])

// Add option dialog
const addDialogVisible = ref(false)
const availableOptions = ref([])
const addOptionId = ref(null)
const addValue = ref('')
const addActive = ref(true)
const addRequired = ref(false)
const addCaptionOverride = ref('')
const addDescriptionOverride = ref('')
const addSaving = ref(false)

// Copy from category dialog
const copyDialogVisible = ref(false)
const availableCategories = ref([])
const copyFromCategoryId = ref(null)
const copying = ref(false)

function typeCaption(type) {
  const row = optionTypes.value.find(t => t.name === type)
  return row ? row.caption : type
}

async function loadLinks() {
  if (!props.categoryId) return
  loading.value = true
  try {
    const params = {}
    if (searchQuery.value.trim() !== '') params.query = searchQuery.value.trim()
    const r = await request.get(`/api/mgr/categories/${props.categoryId}/options`, params)
    links.value = r?.results || []
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error') || 'Error', detail: e.message, life: 5000 })
  } finally {
    loading.value = false
  }
}

async function loadOptionTypes() {
  const r = await request.get('/api/mgr/options/types')
  optionTypes.value = r?.results || []
}

async function loadAvailableOptions() {
  // Options that are NOT yet linked to this category.
  const r = await request.get('/api/mgr/options', { limit: 0 })
  const all = r?.results || []
  const linkedIds = new Set(links.value.map(l => l.option_id))
  availableOptions.value = all.filter(o => !linkedIds.has(o.id))
}

async function loadAvailableCategories() {
  // Source categories for copy — all selectable msCategory resources from the semantic tree.
  const categories = await collectSelectableCategories(0)
  availableCategories.value = categories
    .filter(c => c.id !== props.categoryId)
    .map(c => ({ id: c.id, label: c.label }))
}

/** Must match OptionCategoryTree: absent `selectable` means legacy msCategory-only payload. */
function isTreeRowSelectable(row) {
  return typeof row.selectable === 'boolean' ? row.selectable : true
}

async function collectSelectableCategories(parent = 0, level = 0) {
  const r = await request.get('/api/mgr/options/tree', { parent })
  const rows = r?.results || []
  const categories = []
  const indent = '  '.repeat(level)

  for (const row of rows) {
    if (isTreeRowSelectable(row)) {
      categories.push({ ...row, label: `${indent}${row.label}` })
    }
    if (!row.leaf) {
      categories.push(...(await collectSelectableCategories(row.id, level + 1)))
    }
  }

  return categories
}

async function saveCellEdit(event) {
  const { newData, field } = event
  // category_caption / category_description are per-link overrides on msCategoryOption
  // that fall back to msOption.caption / .description when empty.
  const editable = ['value', 'position', 'category_caption', 'category_description']
  if (!editable.includes(field)) return

  // Backend expects 'caption'/'description' for the per-link override (schema columns).
  const serverField =
    field === 'category_caption'
      ? 'caption'
      : field === 'category_description'
        ? 'description'
        : field

  try {
    await request.put(`/api/mgr/categories/${props.categoryId}/options/${newData.option_id}`, {
      [serverField]: newData[field],
    })
    const idx = links.value.findIndex(l => l.option_id === newData.option_id)
    if (idx >= 0) links.value[idx] = { ...links.value[idx], [field]: newData[field] }
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error') || 'Error', detail: e.message, life: 5000 })
  }
}

async function onRowReorder(event) {
  // PrimeVue passes already-reordered array in event.value.
  links.value = event.value
  const orderedOptionIds = links.value.map(l => l.option_id)
  try {
    await request.post(`/api/mgr/categories/${props.categoryId}/options/sort`, {
      option_ids: orderedOptionIds,
    })
    toast.add({
      severity: 'success',
      summary: _('success') || 'OK',
      detail: 'Order saved',
      life: 2000,
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error') || 'Error', detail: e.message, life: 5000 })
    loadLinks()
  }
}

async function performBulkAction(action) {
  if (selectedRows.value.length === 0) return
  const optionIds = selectedRows.value.map(r => r.option_id)
  try {
    await request.post(`/api/mgr/categories/${props.categoryId}/options/bulk`, {
      action,
      option_ids: optionIds,
    })
    toast.add({
      severity: 'success',
      summary: _('success') || 'OK',
      detail: `${action}: ${optionIds.length}`,
      life: 3000,
    })
    selectedRows.value = []
    loadLinks()
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error') || 'Error', detail: e.message, life: 5000 })
  }
}

function confirmBulkRemove() {
  if (selectedRows.value.length === 0) return
  confirm.require({
    group: UI_GROUP,
    message:
      _('ms3_options_remove_confirm') ||
      'Удалить выбранные опции из категории? Значения опций у товаров будут удалены.',
    header: _('confirm') || 'Подтверждение',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: () => performBulkAction('remove'),
  })
}

function openAddDialog() {
  addOptionId.value = null
  addValue.value = ''
  addActive.value = true
  addRequired.value = false
  addCaptionOverride.value = ''
  addDescriptionOverride.value = ''
  addDialogVisible.value = true
  loadAvailableOptions()
}

async function addOption() {
  if (!addOptionId.value) return
  addSaving.value = true
  try {
    await request.post(`/api/mgr/categories/${props.categoryId}/options`, {
      option_id: addOptionId.value,
      value: addValue.value,
      active: addActive.value,
      required: addRequired.value,
      caption: addCaptionOverride.value,
      description: addDescriptionOverride.value,
    })
    toast.add({ severity: 'success', summary: _('success') || 'OK', detail: 'Added', life: 3000 })
    addDialogVisible.value = false
    loadLinks()
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error') || 'Error', detail: e.message, life: 5000 })
  } finally {
    addSaving.value = false
  }
}

function openCopyDialog() {
  copyFromCategoryId.value = null
  copyDialogVisible.value = true
  loadAvailableCategories()
}

async function performCopy() {
  if (!copyFromCategoryId.value) return
  copying.value = true
  try {
    const r = await request.post(`/api/mgr/categories/${props.categoryId}/options/duplicate`, {
      category_from: copyFromCategoryId.value,
    })
    toast.add({
      severity: 'success',
      summary: _('success') || 'OK',
      detail: `Copied: ${r?.copied ?? 0}, skipped: ${r?.skipped ?? 0}`,
      life: 4000,
    })
    copyDialogVisible.value = false
    loadLinks()
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error') || 'Error', detail: e.message, life: 5000 })
  } finally {
    copying.value = false
  }
}

function confirmSingleRemove(row) {
  confirm.require({
    group: UI_GROUP,
    message:
      _('ms3_option_remove_confirm') || `Удалить опцию «${row.caption || row.key}» из категории?`,
    header: _('confirm') || 'Подтверждение',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/categories/${props.categoryId}/options/${row.option_id}`)
        toast.add({
          severity: 'success',
          summary: _('success') || 'OK',
          detail: 'Removed',
          life: 3000,
        })
        loadLinks()
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: _('error') || 'Error',
          detail: e.message,
          life: 5000,
        })
      }
    },
  })
}

watch(
  () => props.categoryId,
  () => loadLinks(),
  { immediate: false }
)

onMounted(() => {
  Promise.all([loadOptionTypes(), loadLinks()])
})
</script>

<template>
  <div class="category-options-tab">
    <Toast :group="UI_GROUP" />
    <ConfirmDialog :group="UI_GROUP" />

    <div class="toolbar">
      <Button
        icon="pi pi-plus"
        :label="_('ms3_btn_addoption') || 'Добавить опцию'"
        severity="primary"
        @click="openAddDialog"
      />
      <Button
        icon="pi pi-copy"
        :label="_('ms3_btn_copy') || 'Копировать из категории'"
        severity="secondary"
        @click="openCopyDialog"
      />

      <IconField class="search-field">
        <InputIcon><i class="pi pi-search" /></InputIcon>
        <InputText
          v-model="searchQuery"
          :placeholder="_('search') || 'Поиск'"
          @keyup.enter="loadLinks"
        />
      </IconField>

      <template v-if="selectedRows.length > 0">
        <Button
          icon="pi pi-check"
          :label="`${_('ms3_ft_selected_activate') || 'Включить'} (${selectedRows.length})`"
          severity="success"
          size="small"
          @click="performBulkAction('activate')"
        />
        <Button
          icon="pi pi-ban"
          :label="`${_('ms3_ft_selected_deactivate') || 'Выключить'} (${selectedRows.length})`"
          severity="secondary"
          size="small"
          @click="performBulkAction('deactivate')"
        />
        <Button
          icon="pi pi-asterisk"
          :label="`${_('ms3_ft_selected_require') || 'Обязательная'} (${selectedRows.length})`"
          severity="warn"
          size="small"
          @click="performBulkAction('require')"
        />
        <Button
          icon="pi pi-times"
          :label="`${_('ms3_ft_selected_unrequire') || 'Необязательная'} (${selectedRows.length})`"
          severity="secondary"
          size="small"
          @click="performBulkAction('unrequire')"
        />
        <Button
          icon="pi pi-trash"
          :label="`${_('delete') || 'Удалить'} (${selectedRows.length})`"
          severity="danger"
          size="small"
          @click="confirmBulkRemove"
        />
      </template>
    </div>

    <DataTable
      v-model:selection="selectedRows"
      :value="links"
      :loading="loading"
      data-key="option_id"
      edit-mode="cell"
      striped-rows
      size="small"
      @row-reorder="onRowReorder"
      @cell-edit-complete="saveCellEdit"
    >
      <Column row-reorder header-style="width: 3rem" />
      <Column selection-mode="multiple" header-style="width: 3rem" />
      <Column field="key" :header="_('ms3_ft_name') || 'Ключ'" />
      <Column field="global_caption" :header="_('ms3_global_caption') || 'Глобально'">
        <template #body="{ data }">
          <span style="opacity: 0.7">{{ data.global_caption }}</span>
        </template>
      </Column>
      <Column
        field="category_caption"
        :header="_('ms3_category_option_caption_override') || 'Название (для категории)'"
      >
        <template #body="{ data }">
          <span v-if="data.category_caption">{{ data.category_caption }}</span>
          <span v-else style="opacity: 0.5; font-style: italic">—</span>
        </template>
        <template #editor="{ data, field }">
          <InputText
            v-model="data[field]"
            class="w-full"
            :placeholder="
              _('ms3_category_option_caption_override_desc') || 'Пусто: берётся глобальное'
            "
            autofocus
            @keyup.enter.stop
          />
        </template>
      </Column>
      <Column :header="_('ms3_ft_type') || 'Тип'">
        <template #body="{ data }">{{ typeCaption(data.type) }}</template>
      </Column>
      <Column field="value" :header="_('ms3_default_value') || 'Значение по умолчанию'">
        <template #editor="{ data, field }">
          <InputText v-model="data[field]" class="w-full" autofocus @keyup.enter.stop />
        </template>
      </Column>
      <Column :header="_('ms3_ft_active') || 'Активна'" style="width: 5rem">
        <template #body="{ data }">
          <i v-if="data.active" class="pi pi-check" style="color: #10b981" />
          <i v-else class="pi pi-times" style="color: #9ca3af" />
        </template>
      </Column>
      <Column :header="_('ms3_ft_required') || 'Обязательная'" style="width: 6rem">
        <template #body="{ data }">
          <i v-if="data.required" class="pi pi-asterisk" style="color: #f59e0b" />
          <span v-else>—</span>
        </template>
      </Column>
      <Column :header="_('actions') || 'Действия'" style="width: 5rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            size="small"
            :title="_('delete') || 'Удалить'"
            @click="confirmSingleRemove(data)"
          />
        </template>
      </Column>
      <template #empty>
        <div class="empty-message">
          {{ _('ms3_category_options_empty') || 'Опций в этой категории ещё нет' }}
        </div>
      </template>
    </DataTable>

    <!-- Add option dialog -->
    <Dialog
      v-model:visible="addDialogVisible"
      :header="_('ms3_btn_addoption') || 'Добавить опцию'"
      modal
      :style="{ width: '32rem' }"
      :closable="!addSaving"
    >
      <div class="form-row">
        <label for="add-opt">{{ _('ms3_ft_name') || 'Опция' }} <span class="req">*</span></label>
        <Select
          id="add-opt"
          v-model="addOptionId"
          :options="availableOptions"
          option-label="caption"
          option-value="id"
          :placeholder="_('select') || 'Выберите'"
          :filter="true"
          class="w-full"
        >
          <template #option="{ option }">
            <div>
              <b>{{ option.caption || option.key }}</b>
              <span style="opacity: 0.6">— {{ option.key }}</span>
            </div>
            <small style="opacity: 0.6">{{ typeCaption(option.type) }}</small>
          </template>
        </Select>
      </div>
      <div class="form-row">
        <label for="add-value">{{ _('ms3_default_value') || 'Значение по умолчанию' }}</label>
        <InputText id="add-value" v-model="addValue" class="w-full" />
      </div>
      <div class="form-row">
        <label for="add-caption-override">
          {{ _('ms3_category_option_caption_override') || 'Название (для категории)' }}
        </label>
        <InputText
          id="add-caption-override"
          v-model="addCaptionOverride"
          class="w-full"
          :placeholder="
            _('ms3_category_option_caption_override_desc') || 'Пусто — берётся глобальное'
          "
        />
      </div>
      <div class="form-row">
        <label for="add-description-override">
          {{ _('ms3_category_option_description_override') || 'Описание (для категории)' }}
        </label>
        <InputText
          id="add-description-override"
          v-model="addDescriptionOverride"
          class="w-full"
          :placeholder="
            _('ms3_category_option_description_override_desc') || 'Пусто — берётся глобальное'
          "
        />
      </div>
      <div class="form-row form-row-inline">
        <Checkbox v-model="addActive" input-id="add-active" binary />
        <label for="add-active">{{ _('ms3_ft_active') || 'Активна' }}</label>
      </div>
      <div class="form-row form-row-inline">
        <Checkbox v-model="addRequired" input-id="add-required" binary />
        <label for="add-required">{{ _('ms3_ft_required') || 'Обязательная' }}</label>
      </div>

      <template #footer>
        <Button
          :label="_('cancel') || 'Отмена'"
          severity="secondary"
          :disabled="addSaving"
          @click="addDialogVisible = false"
        />
        <Button
          :label="_('save') || 'Сохранить'"
          :loading="addSaving"
          :disabled="!addOptionId"
          @click="addOption"
        />
      </template>
    </Dialog>

    <!-- Copy from category dialog -->
    <Dialog
      v-model:visible="copyDialogVisible"
      :header="_('ms3_btn_copy') || 'Копировать опции из категории'"
      modal
      :style="{ width: '32rem' }"
      :closable="!copying"
    >
      <div class="form-row">
        <label for="copy-from"
          >{{ _('ms3_copy_from_category') || 'Исходная категория' }}
          <span class="req">*</span></label
        >
        <Select
          id="copy-from"
          v-model="copyFromCategoryId"
          :options="availableCategories"
          option-label="label"
          option-value="id"
          :placeholder="_('select') || 'Выберите'"
          :filter="true"
          class="w-full"
        />
      </div>
      <small class="hint">
        {{
          _('ms3_copy_category_hint') ||
          'Опции, которые уже есть в текущей категории, будут пропущены. Значения опций у товаров будут обновлены автоматически.'
        }}
      </small>

      <template #footer>
        <Button
          :label="_('cancel') || 'Отмена'"
          severity="secondary"
          :disabled="copying"
          @click="copyDialogVisible = false"
        />
        <Button
          :label="_('ms3_btn_copy') || 'Копировать'"
          :loading="copying"
          :disabled="!copyFromCategoryId"
          @click="performCopy"
        />
      </template>
    </Dialog>
  </div>
</template>

<style>
/* Non-scoped with .vueApp prefix — avoids Vite scoped-hash mismatch across chunks */
.vueApp .category-options-tab {
  width: 100%;
}

.vueApp .category-options-tab .toolbar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
}

.vueApp .category-options-tab .toolbar .search-field {
  min-width: 14rem;
}

.vueApp .category-options-tab .empty-message {
  padding: 1.5rem;
  text-align: center;
  color: var(--p-text-muted-color, #9ca3af);
}

.vueApp .category-options-tab .form-row {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-bottom: 0.85rem;
}

.vueApp .category-options-tab .form-row-inline {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}

.vueApp .category-options-tab .form-row label {
  font-size: 0.85rem;
  font-weight: 600;
}

.vueApp .category-options-tab .form-row .req {
  color: var(--p-red-500, #ef4444);
}

.vueApp .category-options-tab .hint {
  color: var(--p-text-muted-color, #9ca3af);
  font-size: 0.85rem;
  display: block;
  margin-top: -0.5rem;
}
</style>
