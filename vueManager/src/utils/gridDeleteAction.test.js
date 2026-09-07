import { describe, expect, it } from 'vitest'

import { applyDeleteConfirmDefaults, gridDeleteAction } from './gridDeleteAction.js'

describe('gridDeleteAction', () => {
  it('builds confirm delete action', () => {
    const action = gridDeleteAction({ confirmMessage: 'delivery_delete_confirm_message' })
    expect(action.handler).toBe('delete')
    expect(action.confirm).toBe(true)
    expect(action.confirmMessage).toBe('delivery_delete_confirm_message')
    expect(action.confirmTitle).toBe('confirm_delete')
  })

  it('applyDeleteConfirmDefaults merges delete action from API config', () => {
    const actions = applyDeleteConfirmDefaults(
      [{ name: 'edit', handler: 'edit' }, { name: 'delete', handler: 'delete', confirm: false }],
      { confirmMessage: 'payment_delete_confirm_message' }
    )
    expect(actions[1].confirm).toBe(true)
    expect(actions[1].confirmMessage).toBe('payment_delete_confirm_message')
    expect(actions[0].confirm).toBeUndefined()
  })
})
