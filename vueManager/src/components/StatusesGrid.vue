<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import ColorPicker from 'primevue/colorpicker'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import draggable from 'vuedraggable'

import { useCrudDialog } from '../composables/useCrudDialog.js'
import { useResourceList } from '../composables/useResourceList.js'
import { useSelection } from '../composables/useSelection.js'
import { useSortableList } from '../composables/useSortableList.js'
import request from '../request.js'
import ActionsColumn from './ActionsColumn.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const CONFIRM_GROUP = 'settings-statuses'

// Bulk selection
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete,
} = useSelection({
  entityName: 'status',
  confirmGroup: CONFIRM_GROUP,
  deleteBulk: async ids => {
    await request.delete('/api/mgr/statuses/bulk', { ids })
  },
  onSuccess: () => loadStatuses(),
  getItemName: item => item.name,
})

const {
  loading,
  items: statuses,
  total: totalRecords,
  load: loadStatuses,
} = useResourceList({
  fetchPage: ({ signal }) => request.get('/api/mgr/statuses', { limit: 0 }, { signal }),
})

const {
  visible: editDialogVisible,
  isNew: isNewStatus,
  saving,
  item: editingStatus,
  openCreate,
  openEdit,
  close,
  runSave,
  toastSuccess,
  toastWarn,
} = useCrudDialog({
  createDefaults: () => ({
    name: '',
    description: '',
    color: '000000',
    active: true,
    final: false,
    fixed: false,
  }),
})

const { onDragEnd } = useSortableList({
  items: statuses,
  sortUrl: '/api/mgr/statuses/sort',
  successMessage: _('status_order_saved'),
  reload: loadStatuses,
})

const selectAll = ref(false)

// Default color palette (similar to ExtJS)
const colorPalette = [
  '000000',
  '993300',
  '333300',
  '003300',
  '003366',
  '000080',
  '333399',
  '333333',
  '800000',
  'FF6600',
  '808000',
  '008000',
  '008080',
  '0000FF',
  '666699',
  '808080',
  'FF0000',
  'FF9900',
  '99CC00',
  '339966',
  '33CCCC',
  '3366FF',
  '800080',
  '969696',
  'FF00FF',
  'FFCC00',
  'FFFF00',
  '00FF00',
  '00FFFF',
  '00CCFF',
  '993366',
  'C0C0C0',
  'FF99CC',
  'FFCC99',
  'FFFF99',
  'CCFFCC',
  'CCFFFF',
  '99CCFF',
  'CC99FF',
  'FFFFFF',
]

/**
 * Save status (create or update)
 */
async function saveStatus() {
  if (!editingStatus.value.name) {
    toastWarn(_('status_name_required'))
    return
  }

  const created = isNewStatus.value
  await runSave(async () => {
    if (created) {
      await request.post('/api/mgr/statuses', editingStatus.value)
      toastSuccess(_('status_created'))
    } else {
      await request.put(`/api/mgr/statuses/${editingStatus.value.id}`, editingStatus.value)
      toastSuccess(_('status_updated'))
    }
    await loadStatuses()
  })
}

/**
 * Delete status with confirmation
 */
function deleteStatus(status) {
  confirm.require({
    group: CONFIRM_GROUP,
    message: _('status_delete_confirm_message').replace('{name}', status.name),
    header: _('confirm_delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/statuses/${status.id}`)
        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('status_deleted'),
          life: 3000,
        })
        loadStatuses()
      } catch (error) {
        console.error('[StatusesGrid] Error deleting status:', error)
        toast.add({
          severity: 'error',
          summary: _('error'),
          detail: error.message || _('error_deleting_data'),
          life: 5000,
        })
      }
    },
  })
}

/**
 * Handle select all checkbox
 */
function onSelectAllChange() {
  if (selectAll.value) {
    selectedItems.value = [...statuses.value]
  } else {
    selectedItems.value = []
  }
}

/**
 * Select color from palette
 */
function selectColor(color) {
  editingStatus.value.color = color
}

/**
 * Get actions config
 */
function getActionsConfig() {
  return [
    { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: _('edit') },
    {
      name: 'delete',
      handler: 'delete',
      icon: 'pi-trash',
      label: _('delete'),
      severity: 'danger',
      confirm: false,
    },
  ]
}

/**
 * Get contrasting text color (black or white) based on background color
 */
function getContrastColor(hexColor) {
  if (!hexColor) return '#000000'
  const r = parseInt(hexColor.substr(0, 2), 16)
  const g = parseInt(hexColor.substr(2, 2), 16)
  const b = parseInt(hexColor.substr(4, 2), 16)
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
  return luminance > 0.5 ? '#000000' : '#ffffff'
}

/**
 * Get display name with lexicon translation
 * If name is a lexicon key and translation exists, returns translation
 * Otherwise returns original name
 */
function getDisplayName(name) {
  if (!name) return ''
  const translated = _(name)
  // If translation is the same as key, it means no translation exists
  return translated !== name ? translated : name
}

onMounted(() => {
  loadStatuses()
})
</script>

<template>
  <div class="statuses-grid">
    <Toast />
    <ConfirmDialog :group="CONFIRM_GROUP" append-to="self" />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('ms3_statuses') }}</span>
            <div class="grid-stats">
              <span class="stat-item">
                <i class="pi pi-list"></i>
                <span
                  >{{ _('total') }}: <strong>{{ totalRecords }}</strong></span
                >
              </span>
            </div>
          </div>
          <div class="grid-header-right">
            <Button :label="_('create')" icon="pi pi-plus" severity="success" @click="openCreate" />
          </div>
        </div>
      </template>

      <template #content>
        <!-- Bulk actions toolbar -->
        <div v-if="hasSelection" class="bulk-actions-bar mb-3">
          <div class="bulk-info">
            <i class="pi pi-check-square"></i>
            <span>{{ _('selected_count').replace('{count}', selectionCount) }}</span>
          </div>
          <div class="bulk-buttons">
            <Button
              :label="_('clear_selection')"
              icon="pi pi-times"
              severity="secondary"
              size="small"
              text
              @click="clearSelection"
            />
            <Button
              :label="_('delete_selected')"
              icon="pi pi-trash"
              severity="danger"
              size="small"
              :loading="bulkProcessing"
              @click="confirmBulkDelete"
            />
          </div>
        </div>

        <!-- Table with drag-drop reorder -->
        <div class="p-datatable p-component p-datatable-striped">
          <div class="p-datatable-wrapper">
            <table class="p-datatable-table">
              <thead class="p-datatable-thead">
                <tr>
                  <th style="width: 3rem"></th>
                  <th style="width: 3rem">
                    <Checkbox v-model="selectAll" :binary="true" @change="onSelectAllChange" />
                  </th>
                  <th style="width: 5rem">{{ _('ms3_id') }}</th>
                  <th>{{ _('ms3_name') }}</th>
                  <th style="width: 6.25rem">{{ _('ms3_status_final') }}</th>
                  <th style="width: 6.25rem">{{ _('ms3_status_fixed') }}</th>
                  <th style="width: 6.25rem">{{ _('ms3_active') }}</th>
                  <th style="width: 7.5rem">{{ _('ms3_actions') }}</th>
                </tr>
              </thead>
              <draggable
                v-model="statuses"
                tag="tbody"
                class="p-datatable-tbody"
                handle=".drag-handle"
                item-key="id"
                :animation="200"
                ghost-class="ghost-row"
                @end="onDragEnd"
              >
                <template #item="{ element: status }">
                  <tr :class="{ 'p-row-odd': statuses.indexOf(status) % 2 === 1 }">
                    <td class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>
                      <Checkbox v-model="selectedItems" :value="status" :binary="false" />
                    </td>
                    <td>{{ status.id }}</td>
                    <td>
                      <span
                        class="status-badge"
                        :style="{
                          backgroundColor: '#' + status.color,
                          color: getContrastColor(status.color),
                        }"
                      >
                        {{ getDisplayName(status.name) }}
                      </span>
                    </td>
                    <td>
                      <i
                        :class="
                          status.final ? 'pi pi-check text-success' : 'pi pi-times text-muted'
                        "
                      ></i>
                    </td>
                    <td>
                      <i
                        :class="
                          status.fixed ? 'pi pi-check text-success' : 'pi pi-times text-muted'
                        "
                      ></i>
                    </td>
                    <td>
                      <i
                        :class="
                          status.active ? 'pi pi-check text-success' : 'pi pi-times text-danger'
                        "
                      ></i>
                    </td>
                    <td>
                      <ActionsColumn
                        :data="status"
                        :actions="getActionsConfig()"
                        :confirm-group="CONFIRM_GROUP"
                        grid-id="statuses"
                        @edit="openEdit"
                        @delete="deleteStatus"
                        @refresh="loadStatuses"
                      />
                    </td>
                  </tr>
                </template>
              </draggable>
            </table>
          </div>
          <div v-if="loading" class="loading-overlay">
            <i class="pi pi-spinner pi-spin"></i>
          </div>
        </div>
      </template>
    </Card>

    <!-- Edit/Create Dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="isNewStatus ? _('status_create') : _('status_edit')"
      :modal="true"
      :closable="true"
      :style="{ width: '31.25rem' }"
      append-to="self"
    >
      <div v-if="editingStatus" class="ms3-status-form">
        <!-- Name -->
        <div class="form-row mb-3">
          <label>{{ _('ms3_name') }} *</label>
          <InputText v-model="editingStatus.name" class="w-full" />
        </div>

        <!-- Color -->
        <div class="form-row mb-3">
          <label>{{ _('ms3_color') }}</label>
          <div class="color-picker-wrapper">
            <div
              class="color-preview"
              :style="{ backgroundColor: '#' + editingStatus.color }"
            ></div>
            <ColorPicker v-model="editingStatus.color" />
          </div>
          <!-- Color palette -->
          <div class="color-palette">
            <div
              v-for="color in colorPalette"
              :key="color"
              class="color-swatch"
              :class="{ selected: editingStatus.color === color }"
              :style="{ backgroundColor: '#' + color }"
              @click="selectColor(color)"
            ></div>
          </div>
        </div>

        <!-- Description -->
        <div class="form-row mb-3">
          <label>{{ _('ms3_description') }}</label>
          <Textarea v-model="editingStatus.description" class="w-full" rows="3" />
        </div>

        <!-- Checkboxes -->
        <div class="checkboxes-row">
          <div class="checkbox-item">
            <Checkbox v-model="editingStatus.active" :binary="true" input-id="status-active" />
            <label for="status-active">{{ _('ms3_active') }}</label>
          </div>
          <div class="checkbox-item">
            <Checkbox v-model="editingStatus.final" :binary="true" input-id="status-final" />
            <label for="status-final">{{ _('ms3_status_final') }}</label>
          </div>
          <div class="checkbox-item">
            <Checkbox v-model="editingStatus.fixed" :binary="true" input-id="status-fixed" />
            <label for="status-fixed">{{ _('ms3_status_fixed') }}</label>
          </div>
        </div>

        <!-- Help texts -->
        <div class="help-texts">
          <small class="help-text"
            ><strong>{{ _('ms3_status_final') }}:</strong> {{ _('ms3_status_final_help') }}</small
          >
          <small class="help-text"
            ><strong>{{ _('ms3_status_fixed') }}:</strong> {{ _('ms3_status_fixed_help') }}</small
          >
        </div>
      </div>

      <template #footer>
        <Button :label="_('cancel')" icon="pi pi-times" severity="secondary" @click="close" />
        <Button :label="_('save')" icon="pi pi-check" :loading="saving" @click="saveStatus" />
      </template>
    </Dialog>
  </div>
</template>

<style>
/* Status badge styles - global because used in table */
.status-badge {
  display: inline-block;
  padding: 0.125rem 0.5rem;
  border-radius: var(--ms3-radius-sm);
  font-weight: 500;
  font-size: 0.75rem;
}

/* Dialog form styles - global because Dialog teleports to body */
.ms3-status-form {
  display: flex;
  flex-direction: column;
}

.ms3-status-form .form-row {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.ms3-status-form .form-row label {
  font-weight: 500;
  color: var(--ms3-text-primary);
}

.ms3-status-form .color-picker-wrapper {
  display: flex;
  align-items: center;
  gap: var(--ms3-spacing-3);
}

.ms3-status-form .color-preview {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--ms3-radius-sm);
  border: var(--ms3-border-width-focus) solid var(--ms3-border-color);
}

.ms3-status-form .color-palette {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  margin-top: 0.5rem;
}

.ms3-status-form .color-swatch {
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 0.125rem;
  cursor: pointer;
  border: var(--ms3-border-width) solid var(--ms3-border-color);
  transition: transform 0.15s;
}

.ms3-status-form .color-swatch:hover {
  transform: scale(1.2);
}

.ms3-status-form .color-swatch.selected {
  border: var(--ms3-border-width-focus) solid var(--ms3-accent-primary);
  box-shadow: 0 0 0 var(--ms3-border-width-focus) var(--ms3-accent-focus);
}

.ms3-status-form .checkboxes-row {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.ms3-status-form .checkbox-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.ms3-status-form .checkbox-item label {
  cursor: pointer;
  font-weight: normal;
}

.ms3-status-form .help-texts {
  display: flex;
  flex-direction: column;
  gap: var(--ms3-spacing-2);
  padding: var(--ms3-spacing-3);
  background: var(--ms3-bg-slate);
  border-radius: var(--ms3-radius-sm);
}

.ms3-status-form .help-text {
  color: var(--ms3-text-muted);
  font-size: 0.8rem;
}

.ms3-status-form .help-text strong {
  color: var(--ms3-text-primary);
}

.ms3-status-form .w-full {
  width: 100%;
}
</style>

<style scoped>
.statuses-grid {
  padding: 1.25rem;
}

.grid-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.grid-header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.grid-stats {
  display: flex;
  gap: 1.5rem;
  font-size: 0.9rem;
  color: var(--ms3-text-muted);
}

.stat-item {
  display: flex;
  align-items: center;
  gap: var(--ms3-spacing-2);
}

.stat-item i {
  color: var(--ms3-text-light);
}

.stat-item strong {
  color: var(--ms3-text-dark);
}

/* Bulk actions toolbar */
.bulk-actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--ms3-spacing-3) var(--ms3-spacing-4);
  background: var(--ms3-bg-warning);
  border: var(--ms3-border-width) solid var(--ms3-border-warning);
  border-radius: var(--ms3-radius-md);
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: var(--ms3-spacing-2);
  font-weight: 500;
  color: var(--ms3-text-warning);
}

.bulk-info i {
  font-size: 1.1rem;
}

.bulk-buttons {
  display: flex;
  gap: 0.5rem;
}

.w-full {
  width: 100%;
}

.text-success {
  color: var(--ms3-text-success);
}

.text-danger {
  color: var(--ms3-text-danger);
}

.text-muted {
  color: var(--ms3-text-light);
}

.mb-3 {
  margin-bottom: 1rem;
}

/* Drag and drop styles */
.drag-handle-cell {
  text-align: center;
  vertical-align: middle;
  padding: 0.5rem;
}

.drag-handle {
  cursor: grab;
  color: var(--ms3-text-muted);
  font-size: 1.2rem;
  padding: var(--ms3-spacing-2);
  user-select: none;
}

.drag-handle:hover {
  color: var(--ms3-text-hint);
}

.drag-handle:active {
  cursor: grabbing;
}

:deep(.ghost-row) {
  opacity: 0.5;
  background: var(--ms3-bg-muted);
}

:deep(.sortable-drag) {
  opacity: 0.9;
  background: var(--ms3-bg-surface);
  box-shadow: var(--ms3-shadow-dropdown);
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

.p-datatable {
  position: relative;
}

.p-datatable-wrapper {
  overflow: auto;
}

.p-datatable-table {
  width: 100%;
  border-collapse: collapse;
}

.p-datatable-thead th {
  text-align: left;
  padding: 0.75rem 1rem;
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color-alt);
  background: var(--ms3-bg-muted);
  font-weight: 600;
}

.p-datatable-tbody td {
  padding: 0.75rem 1rem;
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color-alt);
}

.p-datatable-tbody tr:hover {
  background: var(--ms3-bg-slate-alt);
}

.p-row-odd {
  background: var(--ms3-bg-slate);
}
</style>
