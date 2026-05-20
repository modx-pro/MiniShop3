<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import draggable from 'vuedraggable'

import request from '../request.js'

/**
 * Option groups grid — manage msOptionGroup rows (#10).
 *
 * Sortable list (drag-n-drop), inline create/edit/delete, bulk selection.
 * Endpoints under /api/mgr/option-groups.
 *
 * Cross-component sync: emits a global `ms3:option-groups:changed` DOM event
 * on every mutation so that sibling components (e.g. OptionsGrid) can refresh
 * their cached lists.
 */

const OPTION_GROUPS_CHANGED_EVENT = 'ms3:option-groups:changed'

function notifyOptionGroupsChanged() {
  document.dispatchEvent(new CustomEvent(OPTION_GROUPS_CHANGED_EVENT))
}

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const groups = ref([])
const loading = ref(false)
const reordering = ref(false)
const searchQuery = ref('')
const selectedIds = ref(new Set())
const editDialogVisible = ref(false)
const editingGroup = ref(null)
const isNewGroup = ref(false)
const saving = ref(false)

const filteredGroups = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return groups.value
  return groups.value.filter(g =>
    (g.name || '').toLowerCase().includes(q)
    || (g.description || '').toLowerCase().includes(q),
  )
})

const hasGroups = computed(() => groups.value.length > 0)
const selectionCount = computed(() => selectedIds.value.size)
const allSelected = computed(() =>
  hasGroups.value && groups.value.every(g => selectedIds.value.has(g.id)),
)

async function loadGroups() {
  loading.value = true
  try {
    const response = await request.get('/api/mgr/option-groups', { limit: 0 })
    groups.value = response.results || []
  } catch (e) {
    console.error('Failed to load option groups:', e)
    toast.add({
      severity: 'error',
      summary: _('ms3_error'),
      detail: e?.message || _('ms3_option_groups_load_error'),
      life: 5000,
    })
  } finally {
    loading.value = false
  }
}

function toggleSelect(id) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  selectedIds.value = next
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(groups.value.map(g => g.id))
  }
}

function openCreateDialog() {
  isNewGroup.value = true
  editingGroup.value = { id: null, name: '', description: '' }
  editDialogVisible.value = true
}

function openEditDialog(group) {
  isNewGroup.value = false
  editingGroup.value = { ...group }
  editDialogVisible.value = true
}

async function saveGroup() {
  if (!editingGroup.value) return
  const name = (editingGroup.value.name || '').trim()
  if (!name) {
    toast.add({
      severity: 'warn',
      summary: _('ms3_warning'),
      detail: _('ms3_option_group_name_required'),
      life: 4000,
    })
    return
  }

  saving.value = true
  try {
    const payload = {
      name,
      description: editingGroup.value.description ?? null,
    }
    if (isNewGroup.value) {
      await request.post('/api/mgr/option-groups', payload)
      toast.add({
        severity: 'success',
        summary: _('ms3_success'),
        detail: _('ms3_option_group_created'),
        life: 3000,
      })
    } else {
      await request.put(`/api/mgr/option-groups/${editingGroup.value.id}`, payload)
      toast.add({
        severity: 'success',
        summary: _('ms3_success'),
        detail: _('ms3_option_group_updated'),
        life: 3000,
      })
    }
    editDialogVisible.value = false
    editingGroup.value = null
    await loadGroups()
    notifyOptionGroupsChanged()
  } catch (e) {
    console.error('Failed to save option group:', e)
    toast.add({
      severity: 'error',
      summary: _('ms3_error'),
      detail: e?.message || _('ms3_option_group_save_error'),
      life: 5000,
    })
  } finally {
    saving.value = false
  }
}

function confirmDelete(group) {
  const count = Number(group.options_count) || 0
  const detail = count > 0
    ? _('ms3_option_group_delete_confirm_with_options').replace('{count}', String(count))
    : _('ms3_option_group_delete_confirm')

  confirm.require({
    message: detail,
    header: _('ms3_option_group_delete_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: _('cancel'),
    acceptLabel: _('delete'),
    acceptClass: 'p-button-danger',
    accept: () => deleteGroup(group),
  })
}

async function deleteGroup(group) {
  try {
    await request.delete(`/api/mgr/option-groups/${group.id}`)
    toast.add({
      severity: 'success',
      summary: _('ms3_success'),
      detail: _('ms3_option_group_deleted'),
      life: 3000,
    })
    selectedIds.value.delete(group.id)
    await loadGroups()
    notifyOptionGroupsChanged()
  } catch (e) {
    console.error('Failed to delete option group:', e)
    toast.add({
      severity: 'error',
      summary: _('ms3_error'),
      detail: e?.message || _('ms3_option_group_delete_error'),
      life: 5000,
    })
  }
}

function confirmBulkDelete() {
  const ids = [...selectedIds.value]
  if (!ids.length) return
  confirm.require({
    message: _('ms3_option_group_bulk_delete_confirm').replace('{count}', String(ids.length)),
    header: _('ms3_option_group_delete_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: _('cancel'),
    acceptLabel: _('delete'),
    acceptClass: 'p-button-danger',
    accept: () => bulkDelete(ids),
  })
}

async function bulkDelete(ids) {
  try {
    await request.delete('/api/mgr/option-groups/bulk', { ids })
    toast.add({
      severity: 'success',
      summary: _('ms3_success'),
      detail: _('ms3_option_groups_bulk_deleted').replace('{count}', String(ids.length)),
      life: 3000,
    })
    selectedIds.value = new Set()
    await loadGroups()
    notifyOptionGroupsChanged()
  } catch (e) {
    console.error('Failed to bulk delete option groups:', e)
    toast.add({
      severity: 'error',
      summary: _('ms3_error'),
      detail: e?.message || _('ms3_option_group_delete_error'),
      life: 5000,
    })
  }
}

async function onDragEnd() {
  reordering.value = true
  try {
    const ids = groups.value.map(g => g.id)
    await request.put('/api/mgr/option-groups/positions', { ids })
    notifyOptionGroupsChanged()
  } catch (e) {
    console.error('Failed to reorder option groups:', e)
    toast.add({
      severity: 'error',
      summary: _('ms3_error'),
      detail: e?.message || _('ms3_option_group_reorder_error'),
      life: 5000,
    })
    await loadGroups()
  } finally {
    reordering.value = false
  }
}

onMounted(() => {
  loadGroups()
})
</script>

<template>
  <div class="ms3-option-groups">
    <Toast />
    <ConfirmDialog />

    <Card>
      <template #content>
        <div class="grid-toolbar">
          <div class="left">
            <Button
              :label="_('ms3_option_group_create')"
              icon="pi pi-plus"
              severity="success"
              size="small"
              @click="openCreateDialog"
            />
            <Button
              v-if="selectionCount > 0"
              :label="`${_('delete')} (${selectionCount})`"
              icon="pi pi-trash"
              severity="danger"
              size="small"
              @click="confirmBulkDelete"
            />
          </div>
          <div class="right">
            <InputText
              v-model="searchQuery"
              :placeholder="_('search')"
              size="small"
            />
          </div>
        </div>

        <div v-if="loading" class="status-row">
          <i class="pi pi-spin pi-spinner" /> {{ _('loading') }}
        </div>

        <div v-else-if="!hasGroups" class="empty-state">
          {{ _('ms3_option_groups_empty') }}
        </div>

        <template v-else>
          <div class="list-header">
            <Checkbox
              :model-value="allSelected"
              :binary="true"
              @change="toggleSelectAll"
            />
            <span class="col-handle"></span>
            <span class="col-name">{{ _('ms3_option_group_name') }}</span>
            <span class="col-description">{{ _('ms3_option_group_description') }}</span>
            <span class="col-count">{{ _('ms3_option_group_options_count') }}</span>
            <span class="col-actions"></span>
          </div>

          <div class="reorder-hint">
            {{ _('ms3_option_groups_reorder_hint') }}
          </div>

          <draggable
            v-model="groups"
            item-key="id"
            handle=".reorder-handle"
            ghost-class="dragging-ghost"
            :disabled="searchQuery.length > 0 || reordering"
            @end="onDragEnd"
          >
            <template #item="{ element }">
              <div
                v-show="filteredGroups.includes(element)"
                class="list-row"
                :class="{ selected: selectedIds.has(element.id) }"
              >
                <Checkbox
                  :model-value="selectedIds.has(element.id)"
                  :binary="true"
                  @change="toggleSelect(element.id)"
                />
                <span class="col-handle">
                  <i
                    class="pi pi-bars reorder-handle"
                    :title="_('ms3_option_groups_drag_to_reorder')"
                  />
                </span>
                <span class="col-name">{{ element.name }}</span>
                <span class="col-description text-muted">
                  {{ element.description || '—' }}
                </span>
                <span class="col-count">
                  <Badge :value="element.options_count || 0" severity="info" />
                </span>
                <span class="col-actions">
                  <Button
                    icon="pi pi-pencil"
                    severity="secondary"
                    text
                    rounded
                    size="small"
                    :aria-label="_('edit')"
                    @click="openEditDialog(element)"
                  />
                  <Button
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    rounded
                    size="small"
                    :aria-label="_('delete')"
                    @click="confirmDelete(element)"
                  />
                </span>
              </div>
            </template>
          </draggable>
        </template>
      </template>
    </Card>

    <Dialog
      v-model:visible="editDialogVisible"
      :header="isNewGroup ? _('ms3_option_group_create') : _('ms3_option_group_edit')"
      modal
      :style="{ width: '32rem' }"
      append-to="self"
    >
      <div v-if="editingGroup" class="edit-form">
        <div class="form-row">
          <label>{{ _('ms3_option_group_name') }} <span class="required">*</span></label>
          <InputText
            v-model="editingGroup.name"
            class="w-full"
            :placeholder="_('ms3_option_group_name_placeholder')"
            @keyup.enter="saveGroup"
          />
        </div>
        <div class="form-row">
          <label>{{ _('ms3_option_group_description') }}</label>
          <Textarea
            v-model="editingGroup.description"
            rows="3"
            class="w-full"
            :placeholder="_('ms3_option_group_description_placeholder')"
          />
        </div>
      </div>
      <template #footer>
        <Button :label="_('cancel')" severity="secondary" @click="editDialogVisible = false" />
        <Button :label="_('save')" icon="pi pi-check" :loading="saving" @click="saveGroup" />
      </template>
    </Dialog>
  </div>
</template>

<style>
/* Non-scoped + .vueApp prefix: scoped styles fail across multiple Vite-bundled chunks
   in MS3 admin (hash mismatch). Same pattern as other shared MS3 admin grids. */

.vueApp .ms3-option-groups {
  width: 100%;
}

.vueApp .ms3-option-groups .grid-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.vueApp .ms3-option-groups .grid-toolbar .left {
  display: flex;
  gap: 0.5rem;
}

.vueApp .ms3-option-groups .status-row,
.vueApp .ms3-option-groups .empty-state {
  padding: 2rem;
  text-align: center;
  color: var(--ms3-text-muted);
}

.vueApp .ms3-option-groups .reorder-hint {
  margin: 0.5rem 0 0.75rem 0;
  font-size: 0.8rem;
  color: var(--ms3-text-muted);
}

.vueApp .ms3-option-groups .list-header,
.vueApp .ms3-option-groups .list-row {
  display: grid;
  grid-template-columns: 2rem 2rem 1fr 2fr 8rem 7rem;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
}

.vueApp .ms3-option-groups .list-header {
  font-weight: 600;
  color: var(--ms3-text-muted);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color);
}

.vueApp .ms3-option-groups .list-row {
  border: var(--ms3-border-width) solid var(--ms3-border-color);
  border-radius: 0.375rem;
  background: var(--ms3-bg-slate);
  margin-bottom: 0.5rem;
}

.vueApp .ms3-option-groups .list-row.selected {
  background: var(--ms3-bg-info);
}

.vueApp .ms3-option-groups .col-handle .reorder-handle {
  cursor: grab;
  color: var(--ms3-text-muted);
}

.vueApp .ms3-option-groups .col-handle .reorder-handle:active {
  cursor: grabbing;
}

.vueApp .ms3-option-groups .col-name {
  font-weight: 500;
}

.vueApp .ms3-option-groups .col-actions {
  display: flex;
  gap: 0.25rem;
  justify-content: flex-end;
}

.vueApp .ms3-option-groups .text-muted {
  color: var(--ms3-text-muted);
}

.vueApp .ms3-option-groups .dragging-ghost {
  opacity: 0.5;
  background: var(--ms3-bg-info);
}

.vueApp .ms3-option-groups .edit-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.vueApp .ms3-option-groups .form-row {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.vueApp .ms3-option-groups .form-row label {
  font-weight: 500;
}

.vueApp .ms3-option-groups .required {
  color: var(--ms3-text-danger-alt);
}

.vueApp .ms3-option-groups .w-full {
  width: 100%;
}
</style>
