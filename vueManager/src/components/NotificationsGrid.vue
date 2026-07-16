<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'

import { useCrudDialog } from '../composables/useCrudDialog.js'
import { useResourceList } from '../composables/useResourceList.js'
import request from '../request.js'

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

const references = ref({
  statuses: [],
  events: [],
  recipient_types: [],
  channels: [],
})

const filterStatusId = ref(null)
const filterChannel = ref(null)
const filterRecipientType = ref(null)

const {
  loading,
  items: notifications,
  load: loadNotifications,
  resetPageAndLoad,
} = useResourceList({
  fetchPage: ({ signal }) => {
    const params = {}

    if (filterStatusId.value !== null) {
      params.status_id = filterStatusId.value
    }
    if (filterChannel.value) {
      params.channel = filterChannel.value
    }
    if (filterRecipientType.value) {
      params.recipient_type = filterRecipientType.value
    }

    return request.get('/api/mgr/notifications', params, { signal })
  },
})

const {
  visible: editDialogVisible,
  isNew: isNewRecord,
  saving,
  item: editingNotification,
  openCreate,
  openEdit,
  close,
  runSave,
  toastSuccess,
} = useCrudDialog({
  createDefaults: () => ({
    event: 'order_status_changed',
    status_id: null,
    recipient_type: 'customer',
    channel: 'email',
    enabled: true,
    subject: '',
    template: '',
    delay: 0,
    position: 0,
    config: {},
  }),
})

/**
 * Load references
 */
async function loadReferences() {
  try {
    const response = await request.get('/api/mgr/notifications/references')
    if (response) {
      references.value = response
    }
  } catch (error) {
    console.error('[NotificationsGrid] Error loading references:', error)
  }
}

function clearFilters() {
  filterStatusId.value = null
  filterChannel.value = null
  filterRecipientType.value = null
  resetPageAndLoad()
}

async function saveNotification() {
  if (!editingNotification.value) return

  const created = isNewRecord.value
  await runSave(async () => {
    if (created) {
      await request.post('/api/mgr/notifications', editingNotification.value)
      toastSuccess(_('ms3_notification_created'))
    } else {
      await request.put(
        `/api/mgr/notifications/${editingNotification.value.id}`,
        editingNotification.value
      )
      toastSuccess(_('ms3_notification_updated'))
    }
    await loadNotifications()
  })
}

function deleteNotification(notification) {
  confirm.require({
    message: _('ms3_notification_delete_confirm'),
    header: _('confirm_delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/notifications/${notification.id}`)

        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('ms3_notification_deleted'),
          life: 3000,
        })

        await loadNotifications()
      } catch (error) {
        console.error('[NotificationsGrid] Error deleting notification:', error)
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

async function toggleEnabled(notification) {
  try {
    await request.put(`/api/mgr/notifications/${notification.id}`, {
      enabled: !notification.enabled,
    })

    notification.enabled = !notification.enabled

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: notification.enabled ? _('ms3_notification_was_enabled') : _('ms3_notification_disabled'),
      life: 2000,
    })
  } catch (error) {
    console.error('[NotificationsGrid] Error toggling enabled:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
  }
}

function getEventName(eventId) {
  const event = references.value.events?.find(e => e.id === eventId)
  return event?.name || eventId
}

function getRecipientTypeName(type) {
  const rt = references.value.recipient_types?.find(r => r.id === type)
  return rt?.name || type
}

function getStatusName(statusId) {
  if (!statusId) return _('ms3_notification_all_statuses')
  const status = references.value.statuses?.find(s => s.id === statusId)
  return status?.name || `ID: ${statusId}`
}

/**
 * Calculate contrast text color (black or white) for given background
 * @param {string} hexColor - HEX color without # (e.g., 'FF5722')
 * @returns {string} - '#000000' or '#FFFFFF'
 */
function getContrastTextColor(hexColor) {
  if (!hexColor || hexColor.length < 6) return '#000000'

  // HEX to RGB
  const r = parseInt(hexColor.substring(0, 2), 16) / 255
  const g = parseInt(hexColor.substring(2, 4), 16) / 255
  const b = parseInt(hexColor.substring(4, 6), 16) / 255

  const cmin = Math.min(r, g, b)
  const cmax = Math.max(r, g, b)
  const l = ((cmax + cmin) / 2) * 100

  return l > 50 ? '#000000' : '#FFFFFF'
}

function getStatusStyle(statusId) {
  if (!statusId) {
    return { backgroundColor: '#888888', color: '#FFFFFF' }
  }
  const status = references.value.statuses?.find(s => s.id === statusId)
  const color = status?.color || '888888'
  return {
    backgroundColor: `#${color}`,
    color: getContrastTextColor(color),
  }
}

const statusOptions = computed(() => {
  return [{ id: null, name: _('ms3_notification_all_statuses') }, ...references.value.statuses]
})

onMounted(async () => {
  await loadReferences()
  await loadNotifications()
})
</script>

<template>
  <div class="notifications-grid">
    <Toast />
    <ConfirmDialog append-to="self" />

    <Card>
      <template #title>
        {{ _('ms3_notifications_title') }}
      </template>

      <template #content>
        <!-- Toolbar -->
        <div class="toolbar mb-3">
          <Button :label="_('ms3_notification_add')" icon="pi pi-plus" @click="openCreate" />
        </div>

        <!-- Filters -->
        <div class="filters-form mb-3 p-3 surface-ground" style="border-radius: 0.375rem">
          <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end">
            <div style="flex: 1; min-width: 12.5rem">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500">{{
                _('ms3_notification_status')
              }}</label>
              <Select
                v-model="filterStatusId"
                :options="statusOptions"
                option-label="name"
                option-value="id"
                :placeholder="_('all')"
                style="width: 100%"
                show-clear
              />
            </div>
            <div style="flex: 1; min-width: 9.375rem">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500">{{
                _('ms3_notification_channel')
              }}</label>
              <Select
                v-model="filterChannel"
                :options="references.channels"
                option-label="name"
                option-value="id"
                :placeholder="_('all')"
                style="width: 100%"
                show-clear
              />
            </div>
            <div style="flex: 1; min-width: 9.375rem">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500">{{
                _('ms3_notification_recipient')
              }}</label>
              <Select
                v-model="filterRecipientType"
                :options="references.recipient_types"
                option-label="name"
                option-value="id"
                :placeholder="_('all')"
                style="width: 100%"
                show-clear
              />
            </div>
            <div style="display: flex; gap: 0.5rem">
              <Button :label="_('apply')" icon="pi pi-filter" @click="resetPageAndLoad" />
              <Button
                :label="_('clear')"
                icon="pi pi-filter-slash"
                severity="secondary"
                @click="clearFilters"
              />
            </div>
          </div>
        </div>

        <!-- Table -->
        <DataTable
          :value="notifications"
          :loading="loading"
          striped-rows
          responsive-layout="scroll"
        >
          <Column field="enabled" :header="_('ms3_notification_enabled')" style="width: 5rem">
            <template #body="{ data }">
              <Checkbox :model-value="data.enabled" :binary="true" @click="toggleEnabled(data)" />
            </template>
          </Column>

          <Column field="event" :header="_('ms3_notification_event')">
            <template #body="{ data }">
              {{ getEventName(data.event) }}
            </template>
          </Column>

          <Column field="status_id" :header="_('ms3_notification_status')">
            <template #body="{ data }">
              <Tag :value="getStatusName(data.status_id)" :style="getStatusStyle(data.status_id)" />
            </template>
          </Column>

          <Column field="recipient_type" :header="_('ms3_notification_recipient')">
            <template #body="{ data }">
              <Tag
                :value="getRecipientTypeName(data.recipient_type)"
                :severity="data.recipient_type === 'customer' ? 'info' : 'warning'"
              />
            </template>
          </Column>

          <Column field="channel" :header="_('ms3_notification_channel')">
            <template #body="{ data }">
              <span class="channel-badge">
                <i :class="data.channel === 'email' ? 'pi pi-envelope' : 'pi pi-send'"></i>
                {{ data.channel }}
              </span>
            </template>
          </Column>

          <Column field="subject" :header="_('ms3_notification_subject')">
            <template #body="{ data }">
              <span class="text-ellipsis" :title="data.subject">{{ data.subject || '-' }}</span>
            </template>
          </Column>

          <Column field="template" :header="_('ms3_notification_template')">
            <template #body="{ data }">
              <code v-if="data.template">{{ data.template }}</code>
              <span v-else>-</span>
            </template>
          </Column>

          <Column :header="_('actions')" style="width: 7.5rem">
            <template #body="{ data }">
              <div class="actions-cell">
                <Button
                  icon="pi pi-pencil"
                  text
                  severity="secondary"
                  :title="_('edit')"
                  @click="openEdit(data)"
                />
                <Button
                  icon="pi pi-trash"
                  text
                  severity="danger"
                  :title="_('delete')"
                  @click="deleteNotification(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Edit dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="isNewRecord ? _('ms3_notification_add') : _('ms3_notification_edit')"
      :modal="true"
      :closable="true"
      :style="{ width: '37.5rem' }"
      :append-to="'self'"
    >
      <div v-if="editingNotification" class="notification-form">
        <!-- Event -->
        <div class="form-row">
          <div class="form-col">
            <label for="event">{{ _('ms3_notification_event') }} *</label>
            <Select
              id="event"
              v-model="editingNotification.event"
              :options="references.events"
              option-label="name"
              option-value="id"
              class="w-full"
            />
          </div>
          <div class="form-col">
            <label for="status_id">{{ _('ms3_notification_status') }}</label>
            <Select
              id="status_id"
              v-model="editingNotification.status_id"
              :options="statusOptions"
              option-label="name"
              option-value="id"
              :placeholder="_('ms3_notification_all_statuses')"
              class="w-full"
              show-clear
            />
          </div>
        </div>

        <!-- Recipient and channel -->
        <div class="form-row">
          <div class="form-col">
            <label for="recipient_type">{{ _('ms3_notification_recipient') }} *</label>
            <Select
              id="recipient_type"
              v-model="editingNotification.recipient_type"
              :options="references.recipient_types"
              option-label="name"
              option-value="id"
              class="w-full"
            />
          </div>
          <div class="form-col">
            <label for="channel">{{ _('ms3_notification_channel') }} *</label>
            <Select
              id="channel"
              v-model="editingNotification.channel"
              :options="references.channels"
              option-label="name"
              option-value="id"
              class="w-full"
            />
          </div>
        </div>

        <!-- Email subject -->
        <div class="form-row">
          <div class="form-col-full">
            <label for="subject">{{ _('ms3_notification_subject') }}</label>
            <InputText
              id="subject"
              v-model="editingNotification.subject"
              class="w-full"
              :placeholder="_('ms3_notification_subject_placeholder')"
            />
            <small class="text-muted">{{ _('ms3_notification_subject_hint') }}</small>
          </div>
        </div>

        <!-- Template -->
        <div class="form-row">
          <div class="form-col-full">
            <label for="template">{{ _('ms3_notification_template') }}</label>
            <InputText
              id="template"
              v-model="editingNotification.template"
              class="w-full"
              :placeholder="_('ms3_notification_template_placeholder')"
            />
            <small class="text-muted">{{ _('ms3_notification_template_hint') }}</small>
          </div>
        </div>

        <!-- Delay and position -->
        <div class="form-row">
          <div class="form-col">
            <label for="delay">{{ _('ms3_notification_delay') }}</label>
            <InputNumber
              id="delay"
              v-model="editingNotification.delay"
              class="w-full"
              :min="0"
              suffix=" sec"
            />
          </div>
          <div class="form-col">
            <label for="position">{{ _('ms3_notification_position') }}</label>
            <InputNumber
              id="position"
              v-model="editingNotification.position"
              class="w-full"
              :min="0"
            />
          </div>
        </div>

        <!-- Enabled -->
        <div class="form-row">
          <div class="checkbox-col">
            <Checkbox v-model="editingNotification.enabled" input-id="enabled" :binary="true" />
            <label for="enabled">{{ _('ms3_notification_enabled') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="_('cancel')" icon="pi pi-times" class="p-button-text" @click="close" />
        <Button :label="_('save')" icon="pi pi-check" :loading="saving" @click="saveNotification" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.notifications-grid {
  padding: 1.25rem;
}

.toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1rem;
}

.notification-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: flex;
  gap: 1rem;
}

.form-col {
  flex: 1;
  min-width: 0;
}

.form-col label,
.form-col-full label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
}

.form-col-full {
  flex: 1 1 100%;
}

.form-col-full small.text-muted {
  display: block;
  margin-top: 0.25rem;
  color: var(--ms3-text-muted);
  font-size: 0.75rem;
}

.checkbox-col {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.checkbox-col label {
  margin-bottom: 0;
  cursor: pointer;
}

.w-full {
  width: 100%;
}

.channel-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.channel-badge i {
  font-size: 0.875rem;
}

.text-ellipsis {
  display: block;
  max-width: 12.5rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.actions-cell {
  display: flex;
  gap: 0.25rem;
}
</style>
