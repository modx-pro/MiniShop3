<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'

import { useCrudDialog } from '../composables/useCrudDialog.js'
import { useResourceList } from '../composables/useResourceList.js'
import { useSelection } from '../composables/useSelection.js'
import request from '../request.js'
import ActionsColumn from './ActionsColumn.vue'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const CONFIRM_GROUP = 'settings-links'

// Bulk selection
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete,
} = useSelection({
  entityName: 'link',
  confirmGroup: CONFIRM_GROUP,
  deleteBulk: async ids => {
    await request.delete('/api/mgr/links/bulk', { ids })
  },
  onSuccess: () => loadLinks(),
  getItemName: item => item.name,
})

const linkTypes = ref([])

const {
  loading,
  items: links,
  total: totalRecords,
  first,
  rows,
  load: loadLinks,
  onPage,
} = useResourceList({
  fetchPage: ({ first: start, rows: limit, signal }) =>
    request.get(
      '/api/mgr/links',
      {
        start,
        limit,
      },
      { signal }
    ),
})

const {
  visible: editDialogVisible,
  isNew: isNewLink,
  saving,
  item: editingLink,
  openCreate,
  openEdit,
  close,
  runSave,
  toastSuccess,
  toastWarn,
} = useCrudDialog({
  createDefaults: () => ({
    name: '',
    type: '',
    description: '',
  }),
})

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
 * Open create modal
 */
function createLink() {
  openCreate({
    type: linkTypes.value.length > 0 ? linkTypes.value[0].value : '',
  })
}

/**
 * Save link (create or update)
 */
async function saveLink() {
  if (!editingLink.value.name) {
    toastWarn(_('link_name_required'))
    return
  }

  if (isNewLink.value && !editingLink.value.type) {
    toastWarn(_('link_type_required'))
    return
  }

  const created = isNewLink.value
  await runSave(async () => {
    if (created) {
      await request.post('/api/mgr/links', editingLink.value)
      toastSuccess(_('link_created'))
    } else {
      await request.put(`/api/mgr/links/${editingLink.value.id}`, editingLink.value)
      toastSuccess(_('link_updated'))
    }
    await loadLinks()
  })
}

/**
 * Delete link with confirmation
 */
function deleteLink(link) {
  confirm.require({
    group: CONFIRM_GROUP,
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
          life: 3000,
        })
        loadLinks()
      } catch (error) {
        console.error('[LinksGrid] Error deleting link:', error)
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
    <ConfirmDialog :group="CONFIRM_GROUP" append-to="self" />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('ms3_links') }}</span>
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
            <Button :label="_('create')" icon="pi pi-plus" severity="success" @click="createLink" />
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
          striped-rows
          responsive-layout="scroll"
          data-key="id"
        >
          <!-- Selection column -->
          <Column selection-mode="multiple" header-style="width: 3rem" />

          <!-- ID -->
          <Column field="id" :header="_('ms3_id')" style="width: 5rem" sortable />

          <!-- Name -->
          <Column field="name" :header="_('ms3_name')" sortable>
            <template #body="{ data }">
              {{ getDisplayName(data.name) }}
            </template>
          </Column>

          <!-- Type -->
          <Column field="type" :header="_('ms3_type')" style="width: 12.5rem">
            <template #body="{ data }">
              <span class="link-type-badge">{{
                data.type_label || _('ms3_link_' + data.type)
              }}</span>
            </template>
          </Column>

          <!-- Description -->
          <Column field="description" :header="_('ms3_description')" />

          <!-- Actions -->
          <Column :header="_('ms3_actions')" style="width: 7.5rem">
            <template #body="{ data }">
              <ActionsColumn
                :data="data"
                :actions="getActionsConfig()"
                :confirm-group="CONFIRM_GROUP"
                grid-id="links"
                @edit="openEdit"
                @delete="deleteLink"
                @refresh="loadLinks"
              />
            </template>
          </Column>
        </DataTable>

        <!-- Pagination -->
        <Paginator
          :first="first"
          :rows="rows"
          :total-records="totalRecords"
          :rows-per-page-options="[10, 20, 50, 100]"
          @page="onPage"
        />
      </template>
    </Card>

    <!-- Edit/Create Dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="isNewLink ? _('link_create') : _('link_edit')"
      :modal="true"
      :closable="true"
      :style="{ width: '34.375rem' }"
      append-to="self"
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
            option-label="label"
            option-value="value"
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
        <Button :label="_('cancel')" icon="pi pi-times" severity="secondary" @click="close" />
        <Button :label="_('save')" icon="pi pi-check" :loading="saving" @click="saveLink" />
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
  color: var(--ms3-text-primary);
}

.ms3-link-form .type-description {
  color: var(--ms3-text-muted);
  font-style: italic;
  padding: 0.5rem;
  background: var(--ms3-bg-gray-100);
  border-radius: 0.25rem;
  margin-top: 0.25rem;
}

.ms3-link-form .type-hint {
  color: var(--ms3-text-muted-light);
  font-size: 0.8rem;
}

.ms3-link-form .w-full {
  width: 100%;
}
</style>

<style scoped>
.links-grid {
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
  gap: 0.5rem;
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
  padding: 0.75rem 1rem;
  background: var(--ms3-bg-warning);
  border: var(--ms3-border-width) solid var(--ms3-border-warning);
  border-radius: 0.375rem;
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
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

.mb-3 {
  margin-bottom: 1rem;
}

/* Link type badge */
.link-type-badge {
  display: inline-block;
  padding: 0.25rem 0.625rem;
  background: var(--ms3-bg-indigo);
  color: var(--ms3-text-indigo);
  border-radius: 0.25rem;
  font-size: 0.85rem;
  font-weight: 500;
}
</style>
