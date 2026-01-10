<script setup>
import { onMounted, ref } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import ColorPicker from 'primevue/colorpicker'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import draggable from 'vuedraggable'
import request from '../request.js'
import { useLexicon } from '@vuetools/useLexicon'
import { useSelection } from '../composables/useSelection.js'
import ActionsColumn from './ActionsColumn.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

// Bulk selection
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete
} = useSelection({
  entityName: 'status',
  deleteBulk: async (ids) => {
    await request.delete('/api/mgr/statuses/bulk', { ids })
  },
  onSuccess: () => loadStatuses(),
  getItemName: (item) => item.name
})

const loading = ref(false)
const statuses = ref([])
const totalRecords = ref(0)
const editDialogVisible = ref(false)
const editingStatus = ref(null)
const isNewStatus = ref(false)
const saving = ref(false)
const selectAll = ref(false)

// Default color palette (similar to ExtJS)
const colorPalette = [
  '000000', '993300', '333300', '003300', '003366', '000080', '333399', '333333',
  '800000', 'FF6600', '808000', '008000', '008080', '0000FF', '666699', '808080',
  'FF0000', 'FF9900', '99CC00', '339966', '33CCCC', '3366FF', '800080', '969696',
  'FF00FF', 'FFCC00', 'FFFF00', '00FF00', '00FFFF', '00CCFF', '993366', 'C0C0C0',
  'FF99CC', 'FFCC99', 'FFFF99', 'CCFFCC', 'CCFFFF', '99CCFF', 'CC99FF', 'FFFFFF'
]

/**
 * Load statuses list
 */
async function loadStatuses() {
  loading.value = true

  try {
    const response = await request.get('/api/mgr/statuses', { limit: 0 })

    if (response && response.results) {
      statuses.value = response.results
      totalRecords.value = response.total || 0
    } else {
      statuses.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[StatusesGrid] Error loading statuses:', error)
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
 * Open create modal
 */
function createStatus() {
  editingStatus.value = {
    name: '',
    description: '',
    color: '000000',
    active: true,
    final: false,
    fixed: false
  }
  isNewStatus.value = true
  editDialogVisible.value = true
}

/**
 * Open edit modal
 */
function editStatus(status) {
  editingStatus.value = { ...status }
  isNewStatus.value = false
  editDialogVisible.value = true
}

/**
 * Save status (create or update)
 */
async function saveStatus() {
  if (!editingStatus.value.name) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('status_name_required'),
      life: 3000
    })
    return
  }

  saving.value = true

  try {
    let response
    if (isNewStatus.value) {
      response = await request.post('/api/mgr/statuses', editingStatus.value)
    } else {
      response = await request.put(`/api/mgr/statuses/${editingStatus.value.id}`, editingStatus.value)
    }

    if (response) {
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: isNewStatus.value ? _('status_created') : _('status_updated'),
        life: 3000
      })
      editDialogVisible.value = false
      loadStatuses()
    }
  } catch (error) {
    console.error('[StatusesGrid] Error saving status:', error)
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
 * Delete status with confirmation
 */
function deleteStatus(status) {
  confirm.require({
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
          life: 3000
        })
        loadStatuses()
      } catch (error) {
        console.error('[StatusesGrid] Error deleting status:', error)
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
 * Handle drag end (vuedraggable)
 */
async function onDragEnd() {
  // Extract IDs in new order
  const ids = statuses.value.map(s => s.id)

  try {
    await request.post('/api/mgr/statuses/sort', { ids })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('status_order_saved'),
      life: 2000
    })
  } catch (error) {
    console.error('[StatusesGrid] Error saving order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000
    })
    // Reload to restore original order
    loadStatuses()
  }
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
    { name: 'delete', handler: 'delete', icon: 'pi-trash', label: _('delete'), severity: 'danger', confirm: false }
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
    <ConfirmDialog appendTo="self" />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('ms3_statuses') }}</span>
            <div class="grid-stats">
              <span class="stat-item">
                <i class="pi pi-list"></i>
                <span>{{ _('total') }}: <strong>{{ totalRecords }}</strong></span>
              </span>
            </div>
          </div>
          <div class="grid-header-right">
            <Button
              :label="_('create')"
              icon="pi pi-plus"
              severity="success"
              @click="createStatus"
            />
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
                    <Checkbox
                      v-model="selectAll"
                      :binary="true"
                      @change="onSelectAllChange"
                    />
                  </th>
                  <th style="width: 80px">{{ _('ms3_id') }}</th>
                  <th>{{ _('ms3_name') }}</th>
                  <th style="width: 100px">{{ _('ms3_status_final') }}</th>
                  <th style="width: 100px">{{ _('ms3_status_fixed') }}</th>
                  <th style="width: 100px">{{ _('ms3_active') }}</th>
                  <th style="width: 120px">{{ _('ms3_actions') }}</th>
                </tr>
              </thead>
              <draggable
                v-model="statuses"
                tag="tbody"
                class="p-datatable-tbody"
                handle=".drag-handle"
                item-key="id"
                @end="onDragEnd"
                :animation="200"
                ghost-class="ghost-row"
              >
                <template #item="{ element: status }">
                  <tr :class="{ 'p-row-odd': statuses.indexOf(status) % 2 === 1 }">
                    <td class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>
                      <Checkbox
                        v-model="selectedItems"
                        :value="status"
                        :binary="false"
                      />
                    </td>
                    <td>{{ status.id }}</td>
                    <td>
                      <span
                        class="status-badge"
                        :style="{ backgroundColor: '#' + status.color, color: getContrastColor(status.color) }"
                      >
                        {{ getDisplayName(status.name) }}
                      </span>
                    </td>
                    <td>
                      <i :class="status.final ? 'pi pi-check text-success' : 'pi pi-times text-muted'"></i>
                    </td>
                    <td>
                      <i :class="status.fixed ? 'pi pi-check text-success' : 'pi pi-times text-muted'"></i>
                    </td>
                    <td>
                      <i :class="status.active ? 'pi pi-check text-success' : 'pi pi-times text-danger'"></i>
                    </td>
                    <td>
                      <ActionsColumn
                        :data="status"
                        :actions="getActionsConfig()"
                        grid-id="statuses"
                        @edit="editStatus"
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
      :style="{ width: '500px' }"
      appendTo="self"
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
            <div class="color-preview" :style="{ backgroundColor: '#' + editingStatus.color }"></div>
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
            <Checkbox v-model="editingStatus.active" :binary="true" inputId="status-active" />
            <label for="status-active">{{ _('ms3_active') }}</label>
          </div>
          <div class="checkbox-item">
            <Checkbox v-model="editingStatus.final" :binary="true" inputId="status-final" />
            <label for="status-final">{{ _('ms3_status_final') }}</label>
          </div>
          <div class="checkbox-item">
            <Checkbox v-model="editingStatus.fixed" :binary="true" inputId="status-fixed" />
            <label for="status-fixed">{{ _('ms3_status_fixed') }}</label>
          </div>
        </div>

        <!-- Help texts -->
        <div class="help-texts">
          <small class="help-text"><strong>{{ _('ms3_status_final') }}:</strong> {{ _('ms3_status_final_help') }}</small>
          <small class="help-text"><strong>{{ _('ms3_status_fixed') }}:</strong> {{ _('ms3_status_fixed_help') }}</small>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          severity="secondary"
          @click="editDialogVisible = false"
        />
        <Button
          :label="_('save')"
          icon="pi pi-check"
          :loading="saving"
          @click="saveStatus"
        />
      </template>
    </Dialog>
  </div>
</template>

<style>
/* Status badge styles - global because used in table */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 4px;
  font-weight: 500;
  font-size: 0.875rem;
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
  color: #374151;
}

.ms3-status-form .color-picker-wrapper {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.ms3-status-form .color-preview {
  width: 40px;
  height: 40px;
  border-radius: 4px;
  border: 2px solid #e2e8f0;
}

.ms3-status-form .color-palette {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 0.5rem;
}

.ms3-status-form .color-swatch {
  width: 20px;
  height: 20px;
  border-radius: 2px;
  cursor: pointer;
  border: 1px solid #e2e8f0;
  transition: transform 0.15s;
}

.ms3-status-form .color-swatch:hover {
  transform: scale(1.2);
}

.ms3-status-form .color-swatch.selected {
  border: 2px solid #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
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
  gap: 0.5rem;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 4px;
}

.ms3-status-form .help-text {
  color: #64748b;
  font-size: 0.8rem;
}

.ms3-status-form .help-text strong {
  color: #374151;
}

.ms3-status-form .w-full {
  width: 100%;
}
</style>

<style scoped>
.statuses-grid {
  padding: 20px;
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
  color: #64748b;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.stat-item i {
  color: #94a3b8;
}

.stat-item strong {
  color: #334155;
}

/* Bulk actions toolbar */
.bulk-actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 6px;
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  color: #92400e;
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
  color: #22c55e;
}

.text-danger {
  color: #ef4444;
}

.text-muted {
  color: #94a3b8;
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
  opacity: 0.9;
  background: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255,255,255,0.7);
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
  border-bottom: 1px solid #dee2e6;
  background: #f8f9fa;
  font-weight: 600;
}

.p-datatable-tbody td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #dee2e6;
}

.p-datatable-tbody tr:hover {
  background: #f1f5f9;
}

.p-row-odd {
  background: #f8fafc;
}

</style>
