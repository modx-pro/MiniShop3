<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import request from '../request.js'
import { useLexicon } from '@modxprovuecore/useLexicon'
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
  entityName: 'link',
  deleteBulk: async (ids) => {
    await request.delete('/api/mgr/links/bulk', { ids })
  },
  onSuccess: () => loadLinks(),
  getItemName: (item) => item.name
})

const loading = ref(false)
const links = ref([])
const totalRecords = ref(0)
const linkTypes = ref([])
const editDialogVisible = ref(false)
const editingLink = ref(null)
const isNewLink = ref(false)
const saving = ref(false)

/**
 * Get display name with lexicon translation
 */
function getDisplayName(name) {
  if (!name) return ''
  const translated = _(name)
  return translated !== name ? translated : name
}

/**
 * Load link types from API
 */
async function loadLinkTypes() {
  try {
    const response = await request.get('/api/mgr/links/types')
    if (response && response.results) {
      linkTypes.value = response.results
    }
  } catch (error) {
    console.error('[LinksGrid] Error loading link types:', error)
  }
}

/**
 * Load links list
 */
async function loadLinks() {
  loading.value = true

  try {
    const response = await request.get('/api/mgr/links', { limit: 0 })

    if (response && response.results) {
      links.value = response.results
      totalRecords.value = response.total || 0
    } else {
      links.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[LinksGrid] Error loading links:', error)
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
function createLink() {
  editingLink.value = {
    name: '',
    type: linkTypes.value.length > 0 ? linkTypes.value[0].value : '',
    description: ''
  }
  isNewLink.value = true
  editDialogVisible.value = true
}

/**
 * Open edit modal
 */
function editLink(link) {
  editingLink.value = { ...link }
  isNewLink.value = false
  editDialogVisible.value = true
}

/**
 * Save link (create or update)
 */
async function saveLink() {
  if (!editingLink.value.name) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('link_name_required'),
      life: 3000
    })
    return
  }

  if (isNewLink.value && !editingLink.value.type) {
    toast.add({
      severity: 'warn',
      summary: _('warning'),
      detail: _('link_type_required'),
      life: 3000
    })
    return
  }

  saving.value = true

  try {
    let response
    if (isNewLink.value) {
      response = await request.post('/api/mgr/links', editingLink.value)
    } else {
      response = await request.put(`/api/mgr/links/${editingLink.value.id}`, editingLink.value)
    }

    if (response) {
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: isNewLink.value ? _('link_created') : _('link_updated'),
        life: 3000
      })
      editDialogVisible.value = false
      loadLinks()
    }
  } catch (error) {
    console.error('[LinksGrid] Error saving link:', error)
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
 * Delete link with confirmation
 */
function deleteLink(link) {
  confirm.require({
    message: _('link_delete_confirm_message').replace('{name}', link.name),
    header: _('confirm_delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/links/${link.id}`)
        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('link_deleted'),
          life: 3000
        })
        loadLinks()
      } catch (error) {
        console.error('[LinksGrid] Error deleting link:', error)
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
 * Get actions config
 */
function getActionsConfig() {
  return [
    { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: _('edit') },
    { name: 'delete', handler: 'delete', icon: 'pi-trash', label: _('delete'), severity: 'danger', confirm: false }
  ]
}

/**
 * Get selected link type description
 */
const selectedTypeDescription = computed(() => {
  if (!editingLink.value?.type) return ''
  const type = linkTypes.value.find(t => t.value === editingLink.value.type)
  return type?.description || ''
})

onMounted(() => {
  loadLinkTypes()
  loadLinks()
})
</script>

<template>
  <div class="links-grid">
    <Toast />
    <ConfirmDialog />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('ms3_links') }}</span>
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
              @click="createLink"
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

        <!-- Table -->
        <DataTable
          v-model:selection="selectedItems"
          :value="links"
          :loading="loading"
          stripedRows
          responsiveLayout="scroll"
          dataKey="id"
        >
          <!-- Selection column -->
          <Column selectionMode="multiple" headerStyle="width: 3rem" />

          <!-- ID -->
          <Column field="id" :header="_('ms3_id')" style="width: 80px" sortable />

          <!-- Name -->
          <Column field="name" :header="_('ms3_name')" sortable>
            <template #body="{ data }">
              {{ getDisplayName(data.name) }}
            </template>
          </Column>

          <!-- Type -->
          <Column field="type" :header="_('ms3_type')" style="width: 200px">
            <template #body="{ data }">
              <span class="link-type-badge">{{ data.type_label || _('ms3_link_' + data.type) }}</span>
            </template>
          </Column>

          <!-- Description -->
          <Column field="description" :header="_('ms3_description')" />

          <!-- Actions -->
          <Column :header="_('ms3_actions')" style="width: 120px">
            <template #body="{ data }">
              <ActionsColumn
                :data="data"
                :actions="getActionsConfig()"
                grid-id="links"
                @edit="editLink"
                @delete="deleteLink"
                @refresh="loadLinks"
              />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Edit/Create Dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="isNewLink ? _('link_create') : _('link_edit')"
      :modal="true"
      :closable="true"
      :style="{ width: '550px' }"
    >
      <div v-if="editingLink" class="ms3-link-form">
        <!-- Name -->
        <div class="form-row">
          <label>{{ _('ms3_name') }} *</label>
          <InputText v-model="editingLink.name" class="w-full" />
        </div>

        <!-- Type -->
        <div class="form-row">
          <label>{{ _('ms3_type') }} *</label>
          <Select
            v-model="editingLink.type"
            :options="linkTypes"
            optionLabel="label"
            optionValue="value"
            :disabled="!isNewLink"
            class="w-full"
            :placeholder="_('select_type')"
          />
          <small v-if="selectedTypeDescription" class="type-description">
            {{ selectedTypeDescription }}
          </small>
          <small v-if="!isNewLink" class="type-hint">
            {{ _('link_type_readonly') }}
          </small>
        </div>

        <!-- Description -->
        <div class="form-row">
          <label>{{ _('ms3_description') }}</label>
          <Textarea v-model="editingLink.description" class="w-full" rows="3" />
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
          @click="saveLink"
        />
      </template>
    </Dialog>
  </div>
</template>

<style>
/* Dialog form styles - global because Dialog teleports to body */
.ms3-link-form {
  display: flex;
  flex-direction: column;
}

.ms3-link-form .form-row {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.ms3-link-form .form-row label {
  font-weight: 500;
  color: #374151;
}

.ms3-link-form .type-description {
  color: #6b7280;
  font-style: italic;
  padding: 0.5rem;
  background: #f3f4f6;
  border-radius: 4px;
  margin-top: 0.25rem;
}

.ms3-link-form .type-hint {
  color: #9ca3af;
  font-size: 0.8rem;
}

.ms3-link-form .w-full {
  width: 100%;
}
</style>

<style scoped>
.links-grid {
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

.mb-3 {
  margin-bottom: 1rem;
}

/* Link type badge */
.link-type-badge {
  display: inline-block;
  padding: 4px 10px;
  background: #e0e7ff;
  color: #3730a3;
  border-radius: 4px;
  font-size: 0.85rem;
  font-weight: 500;
}
</style>
