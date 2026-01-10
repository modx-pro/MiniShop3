<script setup>
/**
 * ActionsEditor - Editor for actions column
 *
 * Allows adding, removing and editing actions in grid column
 */
import { ref, computed, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Checkbox from 'primevue/checkbox'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { useLexicon } from '@vuetools/useLexicon'
import actionRegistry from '../actionRegistry.js'

const props = defineProps({
  /**
   * Array of action configurations
   */
  modelValue: {
    type: Array,
    default: () => []
  },

  /**
   * Grid identifier (for filtering available handlers)
   */
  gridId: {
    type: String,
    default: 'customers'
  }
})

const emit = defineEmits(['update:modelValue'])

const { _ } = useLexicon()

const localActions = ref([])

watch(() => props.modelValue, (newVal) => {
  localActions.value = JSON.parse(JSON.stringify(newVal || []))
}, { immediate: true, deep: true })

const showDialog = ref(false)
const editingAction = ref(null)
const editingIndex = ref(null)

/**
 * Available handlers from registry
 */
const availableHandlers = computed(() => {
  const handlers = actionRegistry.getRegisteredActions()
  return handlers.map(name => {
    const info = actionRegistry.getActionInfo(name)
    return {
      value: name,
      label: _(info?.labelKey || name),
      icon: info?.icon || 'pi-cog'
    }
  })
})

/**
 * Available severity options for buttons
 */
const severityOptions = [
  { value: null, label: _('severity_default') },
  { value: 'secondary', label: _('severity_secondary') },
  { value: 'success', label: _('severity_success') },
  { value: 'info', label: _('severity_info') },
  { value: 'warn', label: _('severity_warning') },
  { value: 'danger', label: _('severity_danger') }
]

/**
 * Popular icons
 */
const iconOptions = [
  { value: 'pi-pencil', label: 'Pencil' },
  { value: 'pi-trash', label: 'Trash' },
  { value: 'pi-eye', label: 'Eye' },
  { value: 'pi-check', label: 'Check' },
  { value: 'pi-times', label: 'Times' },
  { value: 'pi-ban', label: 'Ban' },
  { value: 'pi-lock', label: 'Lock' },
  { value: 'pi-unlock', label: 'Unlock' },
  { value: 'pi-user', label: 'User' },
  { value: 'pi-cog', label: 'Cog' },
  { value: 'pi-copy', label: 'Copy' },
  { value: 'pi-download', label: 'Download' },
  { value: 'pi-external-link', label: 'External Link' },
  { value: 'pi-list', label: 'List' },
  { value: 'pi-refresh', label: 'Refresh' },
  { value: 'pi-send', label: 'Send' },
  { value: 'pi-print', label: 'Print' }
]

/**
 * Open add dialog
 */
function openAddDialog() {
  editingAction.value = {
    name: '',
    handler: 'edit',
    icon: 'pi-cog',
    label: '',
    severity: null,
    confirm: false,
    confirmMessage: '',
    visible: true
  }
  editingIndex.value = null
  showDialog.value = true
}

/**
 * Open edit dialog
 */
function openEditDialog(action, index) {
  editingAction.value = { ...action }
  editingIndex.value = index
  showDialog.value = true
}

/**
 * Save action
 */
function saveAction() {
  if (!editingAction.value.name) return

  const newActions = [...localActions.value]

  if (editingIndex.value !== null) {
    newActions[editingIndex.value] = { ...editingAction.value }
  } else {
    newActions.push({ ...editingAction.value })
  }

  localActions.value = newActions
  emit('update:modelValue', newActions)
  showDialog.value = false
}

/**
 * Remove action
 */
function removeAction(index) {
  const newActions = localActions.value.filter((_, i) => i !== index)
  localActions.value = newActions
  emit('update:modelValue', newActions)
}

/**
 * Move action up
 */
function moveUp(index) {
  if (index === 0) return
  const newActions = [...localActions.value]
  ;[newActions[index - 1], newActions[index]] = [newActions[index], newActions[index - 1]]
  localActions.value = newActions
  emit('update:modelValue', newActions)
}

/**
 * Move action down
 */
function moveDown(index) {
  if (index === localActions.value.length - 1) return
  const newActions = [...localActions.value]
  ;[newActions[index], newActions[index + 1]] = [newActions[index + 1], newActions[index]]
  localActions.value = newActions
  emit('update:modelValue', newActions)
}

/**
 * Close dialog
 */
function closeDialog() {
  showDialog.value = false
  editingAction.value = null
  editingIndex.value = null
}
</script>

<template>
  <div class="actions-editor">
    <!-- Current actions table -->
    <DataTable :value="localActions" size="small" class="mb-2">
      <Column field="name" :header="_('action_name')" style="width: 150px">
        <template #body="{ data }">
          <span class="font-semibold">{{ data.name }}</span>
        </template>
      </Column>

      <Column field="handler" :header="_('action_handler')" style="width: 120px">
        <template #body="{ data }">
          <span class="text-muted">{{ data.handler }}</span>
        </template>
      </Column>

      <Column field="icon" :header="_('action_icon')" style="width: 80px">
        <template #body="{ data }">
          <i :class="`pi ${data.icon}`"></i>
        </template>
      </Column>

      <Column field="severity" :header="_('action_severity')" style="width: 100px">
        <template #body="{ data }">
          <span :class="`p-badge p-badge-${data.severity || 'secondary'}`">
            {{ data.severity || 'default' }}
          </span>
        </template>
      </Column>

      <Column :header="_('actions')" style="width: 150px">
        <template #body="{ data, index }">
          <Button
            icon="pi pi-arrow-up"
            size="small"
            text
            :disabled="index === 0"
            @click="moveUp(index)"
          />
          <Button
            icon="pi pi-arrow-down"
            size="small"
            text
            :disabled="index === localActions.length - 1"
            @click="moveDown(index)"
          />
          <Button
            icon="pi pi-pencil"
            size="small"
            text
            @click="openEditDialog(data, index)"
          />
          <Button
            icon="pi pi-trash"
            size="small"
            text
            severity="danger"
            @click="removeAction(index)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Add button -->
    <Button
      :label="_('add_action')"
      icon="pi pi-plus"
      size="small"
      @click="openAddDialog"
    />

    <!-- Action edit dialog -->
    <Dialog
      v-model:visible="showDialog"
      :header="editingIndex !== null ? _('edit_action') : _('add_action')"
      :modal="true"
      :style="{ width: '550px' }"
      appendTo="self"
    >
      <div v-if="editingAction" class="action-form">
        <!-- Row 1: Name and Handler -->
        <div class="form-row">
          <div class="form-col">
            <label for="action-name" class="required">{{ _('action_name') }}</label>
            <InputText
              id="action-name"
              v-model="editingAction.name"
              :placeholder="_('action_name_placeholder')"
              class="w-full"
            />
            <small class="text-muted">{{ _('action_name_hint') }}</small>
          </div>

          <div class="form-col">
            <label for="action-handler" class="required">{{ _('action_handler') }}</label>
            <Dropdown
              id="action-handler"
              v-model="editingAction.handler"
              :options="availableHandlers"
              option-label="label"
              option-value="value"
              :placeholder="_('select_handler')"
              class="w-full"
            >
              <template #option="{ option }">
                <i :class="`pi ${option.icon} mr-2`"></i>
                {{ option.label }}
              </template>
            </Dropdown>
            <small class="text-muted">{{ _('action_handler_hint') }}</small>
          </div>
        </div>

        <!-- Row 2: Label -->
        <div class="form-row">
          <div class="form-col-full">
            <label for="action-label">{{ _('action_label') }}</label>
            <InputText
              id="action-label"
              v-model="editingAction.label"
              :placeholder="_('action_label_placeholder')"
              class="w-full"
            />
            <small class="text-muted">{{ _('action_label_hint') }}</small>
          </div>
        </div>

        <!-- Row 3: Icon and Style -->
        <div class="form-row">
          <div class="form-col">
            <label for="action-icon">{{ _('action_icon') }}</label>
            <Dropdown
              id="action-icon"
              v-model="editingAction.icon"
              :options="iconOptions"
              option-label="label"
              option-value="value"
              :placeholder="_('select_icon')"
              class="w-full"
            >
              <template #option="{ option }">
                <i :class="`pi ${option.value} mr-2`"></i>
                {{ option.label }}
              </template>
              <template #value="{ value }">
                <span v-if="value">
                  <i :class="`pi ${value} mr-2`"></i>
                  {{ value }}
                </span>
              </template>
            </Dropdown>
          </div>

          <div class="form-col">
            <label for="action-severity">{{ _('action_severity') }}</label>
            <Dropdown
              id="action-severity"
              v-model="editingAction.severity"
              :options="severityOptions"
              option-label="label"
              option-value="value"
              :placeholder="_('select_severity')"
              class="w-full"
            />
          </div>
        </div>

        <!-- Row 4: Confirmation -->
        <div class="form-row">
          <div class="form-col-full">
            <div class="confirm-checkbox">
              <Checkbox
                id="action-confirm"
                v-model="editingAction.confirm"
                :binary="true"
              />
              <label for="action-confirm" class="ml-2">{{ _('action_requires_confirm') }}</label>
            </div>
          </div>
        </div>

        <!-- Row 5: Confirmation message (conditional) -->
        <div v-if="editingAction.confirm" class="form-row">
          <div class="form-col-full">
            <label for="action-confirm-message">{{ _('action_confirm_message') }}</label>
            <InputText
              id="action-confirm-message"
              v-model="editingAction.confirmMessage"
              :placeholder="_('action_confirm_message_placeholder')"
              class="w-full"
            />
            <small class="text-muted">{{ _('action_confirm_message_hint') }}</small>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="_('cancel')"
          icon="pi pi-times"
          text
          @click="closeDialog"
        />
        <Button
          :label="_('save')"
          icon="pi pi-check"
          :disabled="!editingAction?.name"
          @click="saveAction"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.actions-editor :deep(.p-datatable) {
  font-size: 0.875rem;
}

.actions-editor :deep(.p-datatable .p-datatable-tbody > tr > td) {
  padding: 0.5rem;
}

/* Form grid */
.action-form {
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

.form-col-full {
  flex: 1 1 100%;
}

.form-col label,
.form-col-full label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
}

.confirm-checkbox {
  display: flex;
  align-items: center;
  padding: 0.5rem 0;
}

.confirm-checkbox label {
  margin-bottom: 0;
  cursor: pointer;
}

label.required::after {
  content: ' *';
  color: #dc3545;
}

small.text-muted {
  display: block;
  margin-top: 0.25rem;
  color: #6c757d;
  font-size: 0.75rem;
}

.w-full {
  width: 100%;
}

.ml-2 {
  margin-left: 0.5rem;
}

.mr-2 {
  margin-right: 0.5rem;
}
</style>
