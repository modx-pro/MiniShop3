import { useLexicon } from '@vuetools/useLexicon'
import { useConfirm } from 'primevue/useconfirm'
import { computed, ref } from 'vue'

import { resolveUiGroup, toUiGroup, useGroupedToast } from './uiGroup.js'

/**
 * Universal composable for managing row selection in DataTables
 *
 * Features:
 * - Checkbox selection column
 * - Select all / deselect all
 * - Ctrl+click for multi-select (handled by PrimeVue)
 * - Bulk actions (delete, etc.)
 *
 * @param {Object} options Configuration options
 * @param {string} options.entityName Entity name for messages (e.g., 'customer', 'order')
 * @param {Function} options.deleteOne Function to delete single item (item) => Promise
 * @param {Function} options.deleteBulk Function to delete multiple items (ids) => Promise
 * @param {Function} options.onSuccess Callback after successful bulk action
 * @param {Function} options.getItemId Function to get item ID, default: (item) => item.id
 * @param {Function} options.getItemName Function to get item display name for messages
 * @param {string} [options.uiGroup] ConfirmDialog/Toast group (or app provide MS3_UI_GROUP)
 * @param {string} [options.confirmGroup] Deprecated alias of `uiGroup`
 * @returns {Object} Selection state and methods
 */
export function useSelection(options = {}) {
  const {
    deleteOne = null,
    deleteBulk = null,
    onSuccess = null,
    getItemId = item => item.id,
    uiGroup: uiGroupOption = null,
    confirmGroup = null,
  } = options

  const confirm = useConfirm()
  const uiGroup = resolveUiGroup(uiGroupOption || confirmGroup)
  const toast = useGroupedToast(uiGroup)
  const { _ } = useLexicon()

  // Selected items (array of full objects for PrimeVue DataTable)
  const selectedItems = ref([])

  // Processing state
  const processing = ref(false)

  // Computed: selected IDs
  const selectedIds = computed(() => {
    return selectedItems.value.map(item => getItemId(item))
  })

  // Computed: has selection
  const hasSelection = computed(() => {
    return selectedItems.value.length > 0
  })

  // Computed: selection count
  const selectionCount = computed(() => {
    return selectedItems.value.length
  })

  /**
   * Clear selection
   */
  function clearSelection() {
    selectedItems.value = []
  }

  /**
   * Select all items from provided array
   */
  function selectAll(items) {
    selectedItems.value = [...items]
  }

  /**
   * Toggle item selection
   */
  function toggleItem(item) {
    const itemId = getItemId(item)
    const index = selectedItems.value.findIndex(i => getItemId(i) === itemId)

    if (index === -1) {
      selectedItems.value.push(item)
    } else {
      selectedItems.value.splice(index, 1)
    }
  }

  /**
   * Check if item is selected
   */
  function isSelected(item) {
    const itemId = getItemId(item)
    return selectedItems.value.some(i => getItemId(i) === itemId)
  }

  /**
   * Confirm and execute bulk delete
   */
  function confirmBulkDelete() {
    if (!hasSelection.value) {
      toast.add({
        severity: 'warn',
        summary: _('warning'),
        detail: _('no_items_selected'),
        life: 3000,
      })
      return
    }

    const count = selectionCount.value

    confirm.require({
      group: toUiGroup(uiGroup),
      message: _('bulk_delete_confirm_message').replace('{count}', count),
      header: _('bulk_delete_confirm_title'),
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: _('delete'),
      rejectLabel: _('cancel'),
      acceptClass: 'p-button-danger',
      accept: async () => {
        await executeBulkDelete()
      },
    })
  }

  /**
   * Execute bulk delete
   */
  async function executeBulkDelete() {
    if (!hasSelection.value) return

    processing.value = true
    const ids = selectedIds.value
    const count = ids.length

    try {
      // If bulk delete function provided, use it
      if (deleteBulk) {
        await deleteBulk(ids)
      }
      // Otherwise delete one by one
      else if (deleteOne) {
        for (const item of selectedItems.value) {
          await deleteOne(item)
        }
      } else {
        throw new Error('No delete function provided')
      }

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('bulk_delete_success').replace('{count}', count),
        life: 3000,
      })

      clearSelection()

      if (onSuccess) {
        await onSuccess()
      }
    } catch (error) {
      console.error('[useSelection] Bulk delete error:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('bulk_delete_error'),
        life: 5000,
      })
    } finally {
      processing.value = false
    }
  }

  /**
   * Get DataTable selection props
   * Use these props on DataTable component:
   *
   * <DataTable
   *   v-model:selection="selectedItems"
   *   :selectionMode="selectionMode"
   *   :dataKey="dataKey"
   *   ...
   * >
   */
  function getTableProps(dataKey = 'id') {
    return {
      selection: selectedItems,
      selectionMode: 'multiple',
      dataKey,
    }
  }

  return {
    // State
    selectedItems,
    selectedIds,
    hasSelection,
    selectionCount,
    processing,

    // Methods
    clearSelection,
    selectAll,
    toggleItem,
    isSelected,
    confirmBulkDelete,
    executeBulkDelete,
    getTableProps,
  }
}
