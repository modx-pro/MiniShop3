/**
 * useActions composable
 *
 * Provides convenient interface for working with grid actions
 */
import { useLexicon } from '@vuetools/useLexicon'
import { useConfirm } from 'primevue/useconfirm'

import actionRegistry from '../actionRegistry.js'
import { resolveUiGroup, toUiGroup, useGroupedToast } from './uiGroup.js'

/**
 * Composable for working with grid actions
 *
 * @param {Object} options
 * @param {string} options.gridId - Grid identifier (e.g.: 'customers', 'orders')
 * @param {Function} options.onRefresh - Callback for refreshing grid
 * @param {Function} options.onEdit - Callback for editing record
 * @param {Function} options.onDelete - Callback for deleting record
 * @param {Function} options.onView - Callback for viewing record
 * @param {Function} options.onAddresses - Callback for managing addresses
 * @param {Function} options.onPublish - Callback for publishing/unpublishing
 * @param {Function} options.onDuplicate - Callback for duplicating
 * @param {Function} options.onCustomAction - Callback for custom actions (event, data)
 * @param {string} [options.uiGroup] ConfirmDialog/Toast group (or app provide MS3_UI_GROUP).
 *   Omit to stay ungrouped (backward compatible).
 * @param {string} [options.confirmGroup] Deprecated alias of `uiGroup`.
 */
export function useActions(options = {}) {
  const confirm = useConfirm()
  const { _ } = useLexicon()

  const {
    gridId = 'unknown',
    uiGroup: uiGroupOption = null,
    confirmGroup = null,
    onRefresh = () => {},
    onEdit = () => {},
    onDelete = () => {},
    onView = () => {},
    onAddresses = () => {},
    onPublish = () => {},
    onDuplicate = () => {},
    onCustomAction = () => {},
  } = options

  const uiGroup = resolveUiGroup(uiGroupOption || confirmGroup)
  const toast = useGroupedToast(uiGroup)

  /**
   * Create context for action execution
   * @param {Object} customContext - Additional context
   */
  function createContext(customContext = {}) {
    return {
      gridId,
      toast,
      confirm,
      _,
      refresh: onRefresh,
      emit: (event, data) => {
        switch (event) {
          case 'edit':
            onEdit(data)
            break
          case 'delete':
            onDelete(data)
            break
          case 'view':
            onView(data)
            break
          case 'addresses':
            onAddresses(data)
            break
          case 'publish':
            onPublish(data)
            break
          case 'duplicate':
            onDuplicate(data)
            break
          default:
            onCustomAction(event, data)
        }
      },
      ...customContext,
    }
  }

  /**
   * Execute action
   *
   * @param {string} actionName - Action name
   * @param {Object} data - Row data
   * @param {Object} actionConfig - Action configuration from column
   */
  async function executeAction(actionName, data, actionConfig = {}) {
    const context = createContext()

    if (actionConfig.confirm) {
      return new Promise(resolve => {
        let message = actionConfig.confirmMessage
          ? _(actionConfig.confirmMessage)
          : _('action_confirm_message')

        if (data && typeof data === 'object') {
          Object.keys(data).forEach(key => {
            message = message.replace(new RegExp(`\\{${key}\\}`, 'g'), data[key] ?? '')
          })
        }
        const header = actionConfig.confirmTitle
          ? _(actionConfig.confirmTitle)
          : _('action_confirm_title')

        confirm.require({
          group: toUiGroup(uiGroup),
          message,
          header,
          icon: 'pi pi-exclamation-triangle',
          acceptLabel: _(actionConfig.confirmAccept || 'confirm'),
          rejectLabel: _(actionConfig.confirmReject || 'cancel'),
          acceptClass: actionConfig.severity === 'danger' ? 'p-button-danger' : '',
          accept: async () => {
            try {
              const result = await actionRegistry.execute(actionName, data, context)
              resolve(result)
            } catch {
              resolve(null)
            }
          },
          reject: () => {
            resolve(null)
          },
        })
      })
    }

    return actionRegistry.execute(actionName, data, context)
  }

  /**
   * Check if action is available
   * @param {string} actionName - Action name
   */
  function hasAction(actionName) {
    return actionRegistry.has(actionName)
  }

  /**
   * Get list of available actions
   */
  function getAvailableActions() {
    return actionRegistry.getRegisteredActions()
  }

  /**
   * Register custom action for this grid
   *
   * @param {string} name - Action name
   * @param {Function} handler - Handler
   */
  function registerAction(name, handler) {
    return actionRegistry.register(name, handler)
  }

  return {
    executeAction,
    hasAction,
    getAvailableActions,
    registerAction,
    registry: actionRegistry,
  }
}
