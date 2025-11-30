/**
 * useActions composable
 *
 * Предоставляет удобный интерфейс для работы с действиями в гридах
 */
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import actionRegistry from '../actionRegistry.js'
import { useLexicon } from './useLexicon.js'

/**
 * Composable для работы с действиями грида
 *
 * @param {Object} options
 * @param {string} options.gridId - Идентификатор грида (например: 'customers', 'orders')
 * @param {Function} options.onRefresh - Callback для обновления грида
 * @param {Function} options.onEdit - Callback для редактирования записи
 * @param {Function} options.onDelete - Callback для удаления записи
 * @param {Function} options.onView - Callback для просмотра записи
 * @param {Function} options.onAddresses - Callback для управления адресами
 * @param {Function} options.onCustomAction - Callback для кастомных действий (event, data)
 */
export function useActions(options = {}) {
  const toast = useToast()
  const confirm = useConfirm()
  const { _ } = useLexicon()

  const {
    gridId = 'unknown',
    onRefresh = () => {},
    onEdit = () => {},
    onDelete = () => {},
    onView = () => {},
    onAddresses = () => {},
    onCustomAction = () => {}
  } = options

  /**
   * Создание контекста для выполнения действия
   * @param {Object} customContext - Дополнительный контекст
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
          default:
            // Поддержка кастомных действий через общий callback
            onCustomAction(event, data)
        }
      },
      ...customContext
    }
  }

  /**
   * Выполнить действие
   *
   * @param {string} actionName - Имя действия
   * @param {Object} data - Данные строки
   * @param {Object} actionConfig - Конфигурация действия из колонки
   */
  async function executeAction(actionName, data, actionConfig = {}) {
    const context = createContext()

    // Если действие требует подтверждения
    if (actionConfig.confirm) {
      return new Promise((resolve) => {
        let message = actionConfig.confirmMessage
          ? _(actionConfig.confirmMessage)
          : _('action_confirm_message')

        // Заменяем все плейсхолдеры {field} на значения из data
        if (data && typeof data === 'object') {
          Object.keys(data).forEach(key => {
            message = message.replace(new RegExp(`\\{${key}\\}`, 'g'), data[key] ?? '')
          })
        }
        const header = actionConfig.confirmTitle
          ? _(actionConfig.confirmTitle)
          : _('action_confirm_title')

        confirm.require({
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
            } catch (error) {
              resolve(null)
            }
          },
          reject: () => {
            resolve(null)
          }
        })
      })
    }

    // Выполняем без подтверждения
    return actionRegistry.execute(actionName, data, context)
  }

  /**
   * Проверить доступность действия
   * @param {string} actionName - Имя действия
   */
  function hasAction(actionName) {
    return actionRegistry.has(actionName)
  }

  /**
   * Получить список доступных действий
   */
  function getAvailableActions() {
    return actionRegistry.getRegisteredActions()
  }

  /**
   * Регистрация кастомного действия для этого грида
   *
   * @param {string} name - Имя действия
   * @param {Function} handler - Обработчик
   */
  function registerAction(name, handler) {
    return actionRegistry.register(name, handler)
  }

  return {
    executeAction,
    hasAction,
    getAvailableActions,
    registerAction,
    registry: actionRegistry
  }
}
