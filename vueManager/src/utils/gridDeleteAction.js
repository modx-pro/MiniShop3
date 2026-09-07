/**
 * Shared delete-action config for settings grids (ActionsColumn / useActions confirm).
 *
 * Row handlers must not call confirm.require — confirmation happens once in useActions.
 */
export function gridDeleteAction({
  confirmMessage,
  confirmTitle = 'confirm_delete',
  confirmAccept = 'delete',
}) {
  return {
    name: 'delete',
    handler: 'delete',
    icon: 'pi-trash',
    label: 'delete',
    severity: 'danger',
    confirm: true,
    confirmTitle,
    confirmMessage,
    confirmAccept,
  }
}

export function applyDeleteConfirmDefaults(
  actions,
  { confirmMessage, confirmTitle = 'confirm_delete', confirmAccept = 'delete' }
) {
  return actions.map(action => {
    const handler = action.handler || action.name
    if (handler !== 'delete') return action
    return {
      ...action,
      confirm: true,
      confirmTitle: action.confirmTitle || confirmTitle,
      confirmMessage: action.confirmMessage || confirmMessage,
      confirmAccept: action.confirmAccept || confirmAccept,
    }
  })
}
