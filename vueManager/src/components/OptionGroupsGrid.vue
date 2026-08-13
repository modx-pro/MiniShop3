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

import { useCrudDialog } from '../composables/useCrudDialog.js'
import { useResourceList } from '../composables/useResourceList.js'
import { useSelection } from '../composables/useSelection.js'
import { useSortableList } from '../composables/useSortableList.js'
import request from '../request.js'
import { notifyOptionGroupsChanged } from '../utils/optionGroupsBus.js'

/**
 * Option groups grid — manage msOptionGroup rows (#10).
 *
 * Sortable list (drag-n-drop), inline create/edit/delete, bulk selection.
 * Endpoints under /api/mgr/option-groups.
 *
 * Cross-component sync: emits `ms3:option-groups:changed` after every mutation
 * via optionGroupsBus so that sibling components (OptionsGrid) refresh.
 */

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const CONFIRM_GROUP = 'settings-option-groups'

const searchQuery = ref('')

const {
  loading,
  items: groups,
  load: loadGroups,
} = useResourceList({
  fetchPage: ({ signal }) => request.get('/api/mgr/option-groups', { limit: 0 }, { signal }),
})

const {
  visible: editDialogVisible,
  isNew: isNewGroup,
  saving,
  item: editingGroup,
  openCreate: openCreateDialog,
  openEdit: openEditDialog,
  close,
  runSave,
  toastSuccess,
  toastWarn,
} = useCrudDialog({
  createDefaults: () => ({ id: null, name: '', description: '' }),
})

const {
  selectedItems,
  selectedIds,
  selectionCount,
  isSelected,
  toggleItem,
  selectAll,
  clearSelection,
} = useSelection({})

const { sorting: reordering, onDragEnd } = useSortableList({
  items: groups,
  sortUrl: '/api/mgr/option-groups/positions',
  method: 'put',
  reload: loadGroups,
  onSuccess: () => {
    notifyOptionGroupsChanged()
  },
})

const filteredGroups = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return groups.value
  return groups.value.filter(
    g => (g.name || '').toLowerCase().includes(q) || (g.description || '').toLowerCase().includes(q)
  )
})

const hasGroups = computed(() => groups.value.length > 0)
const allSelected = computed(() => hasGroups.value && groups.value.every(g => isSelected(g)))

function toggleSelectAll() {
  if (allSelected.value) {
    clearSelection()
  } else {
    selectAll(groups.value)
  }
}

async function saveGroup() {
  if (!editingGroup.value) return
  const name = (editingGroup.value.name || '').trim()
  if (!name) {
    toastWarn(_('ms3_option_group_name_required'))
    return
  }

  const created = isNewGroup.value
  await runSave(async () => {
    const payload = {
      name,
      description: editingGroup.value.description ?? null,
    }
    if (created) {
      await request.post('/api/mgr/option-groups', payload)
      toastSuccess(_('ms3_option_group_created'))
    } else {
      await request.put(`/api/mgr/option-groups/${editingGroup.value.id}`, payload)
      toastSuccess(_('ms3_option_group_updated'))
    }
    editingGroup.value = null
    await loadGroups()
    notifyOptionGroupsChanged()
  })
}

function confirmDelete(group) {
  const count = Number(group.options_count) || 0
  const detail =
    count > 0
      ? _('ms3_option_group_delete_confirm_with_options').replace('{count}', String(count))
      : _('ms3_option_group_delete_confirm')

  confirm.require({
    group: CONFIRM_GROUP,
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
    selectedItems.value = selectedItems.value.filter(item => item.id !== group.id)
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
    group: CONFIRM_GROUP,
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
    clearSelection()
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

onMounted(() => {
  loadGroups()
})
</script>

<template>
  <div class="ms3-option-groups">
    <Toast />
    <ConfirmDialog :group="CONFIRM_GROUP" />

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
            <InputText v-model="searchQuery" :placeholder="_('search')" size="small" />
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
            <Checkbox :model-value="allSelected" :binary="true" @change="toggleSelectAll" />
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
                :class="{ selected: isSelected(element) }"
              >
                <Checkbox
                  :model-value="isSelected(element)"
                  :binary="true"
                  @change="toggleItem(element)"
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
        <Button :label="_('cancel')" severity="secondary" @click="close" />
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
