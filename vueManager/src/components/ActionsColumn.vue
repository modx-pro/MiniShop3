<script setup>
/**
 * ActionsColumn - Universal component for grid action columns
 *
 * Renders action buttons based on configuration.
 * Supports built-in and custom handlers via MS3ActionRegistry.
 *
 * Usage:
 * <ActionsColumn
 *   :data="rowData"
 *   :actions="actionsConfig"
 *   :grid-id="'customers'"
 *   @edit="handleEdit"
 *   @delete="handleDelete"
 *   @refresh="loadData"
 * />
 *
 * Action configuration:
 * {
 *   name: 'edit',           // Name (identifier)
 *   handler: 'edit',        // Handler from registry
 *   icon: 'pi-pencil',      // PrimeIcons icon
 *   label: 'edit',          // Lexicon key or text
 *   severity: null,         // PrimeVue severity: danger, secondary, success, etc.
 *   confirm: false,         // Confirmation required
 *   confirmTitle: '...',    // Confirm dialog title (lexicon key, optional)
 *   confirmMessage: '...',  // Confirmation message (lexicon key)
 *   confirmAccept: '...',   // Accept button label (lexicon key, optional)
 *   confirmReject: '...',   // Reject button label (lexicon key, optional)
 *   permission: 'ms3_save', // Access permission (optional)
 *   visible: true,          // Button visibility
 *   disabled: false,        // Disabled state
 *   toggleField: 'published', // Toggle based on data field (shows iconOff/labelOff when true)
 *   iconOff: 'pi-times',    // Icon when toggleField is true
 *   labelOff: 'unpublish'   // Label when toggleField is true
 * }
 */
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import { computed } from 'vue'

import { useActions } from '../composables/useActions.js'

const props = defineProps({
  /**
   * Grid row data
   */
  data: {
    type: Object,
    required: true,
  },

  /**
   * Array of action configurations
   */
  actions: {
    type: Array,
    default: () => [],
  },

  /**
   * Grid identifier
   */
  gridId: {
    type: String,
    default: 'unknown',
  },

  /**
   * ConfirmDialog/Toast group. Matches `<ConfirmDialog :group>` / `<Toast :group>`.
   * Prefer this over `confirmGroup`.
   */
  uiGroup: {
    type: String,
    default: null,
  },

  /**
   * @deprecated Use `uiGroup`. Kept as alias for existing call sites.
   */
  confirmGroup: {
    type: String,
    default: null,
  },

  /**
   * Show icons only (without text)
   */
  iconOnly: {
    type: Boolean,
    default: true,
  },

  /**
   * Button size: 'small', 'normal', 'large'
   */
  size: {
    type: String,
    default: 'small',
  },
})

const emit = defineEmits([
  'edit',
  'delete',
  'view',
  'addresses',
  'publish',
  'duplicate',
  'refresh',
  'action',
])

const { _ } = useLexicon()

const { executeAction } = useActions({
  gridId: props.gridId,
  uiGroup: props.uiGroup || props.confirmGroup,
  onRefresh: () => emit('refresh'),
  onEdit: data => emit('edit', data),
  onDelete: data => emit('delete', data),
  onView: data => emit('view', data),
  onAddresses: data => emit('addresses', data),
  onPublish: data => emit('publish', data),
  onDuplicate: data => emit('duplicate', data),
  onCustomAction: (event, data) => emit('action', { name: event, data }),
})

/**
 * Default configurations for built-in actions
 */
const defaultActionConfigs = {
  edit: {
    icon: 'pi-pencil',
    label: 'edit',
    severity: null,
    confirm: false,
  },
  delete: {
    icon: 'pi-trash',
    label: 'delete',
    severity: 'danger',
    confirm: true,
    confirmMessage: 'action_delete_confirm',
  },
  view: {
    icon: 'pi-eye',
    label: 'view',
    severity: 'secondary',
    confirm: false,
  },
  addresses: {
    icon: 'pi-map-marker',
    label: 'addresses',
    severity: 'secondary',
    confirm: false,
  },
}

/**
 * Processed actions with defaults applied
 */
const processedActions = computed(() => {
  return props.actions
    .filter(action => action.visible !== false)
    .map(action => {
      const handlerName = action.handler || action.name
      const defaults = defaultActionConfigs[handlerName] || {}

      // Handle toggle actions (e.g., publish/unpublish)
      let icon = action.icon || defaults.icon || 'pi-cog'
      let label = action.label || defaults.label || action.name

      if (action.toggleField && props.data[action.toggleField]) {
        // Toggle is ON - show "off" state (e.g., published=true -> show unpublish)
        icon = action.iconOff || icon
        label = action.labelOff || label
      }

      return {
        ...defaults,
        ...action,
        handler: handlerName,
        iconClass: `pi ${icon}`,
        displayLabel: _(label),
        isDisabled: checkDisabled(action),
      }
    })
})

/**
 * Check if button is disabled
 */
function checkDisabled(action) {
  if (action.disabled === true) return true
  if (typeof action.disabled === 'function') {
    return action.disabled(props.data)
  }
  if (action.disabledField && props.data[action.disabledField]) {
    return true
  }
  return false
}

/**
 * Handle action button click
 */
async function handleActionClick(action) {
  if (action.isDisabled) return

  try {
    emit('action', {
      name: action.name,
      handler: action.handler,
      data: props.data,
    })

    await executeAction(action.handler, props.data, action)
  } catch (error) {
    console.error(`[ActionsColumn] Error executing action "${action.name}":`, error)
  }
}

/**
 * Get CSS classes for button
 */
function getButtonClasses() {
  const classes = ['p-button-text']

  if (props.size === 'small') classes.push('p-button-sm')
  if (props.size === 'large') classes.push('p-button-lg')

  return classes.join(' ')
}
</script>

<template>
  <div class="actions-column">
    <Button
      v-for="action in processedActions"
      :key="action.name"
      :icon="action.iconClass"
      :label="iconOnly ? undefined : action.displayLabel"
      :title="action.displayLabel"
      :severity="action.severity"
      :class="getButtonClasses(action)"
      :disabled="action.isDisabled"
      text
      @click="handleActionClick(action)"
    />
  </div>
</template>

<style scoped>
.actions-column {
  display: flex;
  gap: 0.25rem;
  align-items: center;
  flex-wrap: nowrap;
}

.actions-column :deep(.p-button) {
  padding: 0.25rem 0.5rem;
}

.actions-column :deep(.p-button-sm) {
  font-size: 0.875rem;
}
</style>
