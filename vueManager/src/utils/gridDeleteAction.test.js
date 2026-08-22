import assert from 'node:assert/strict'
import test from 'node:test'

import { applyDeleteConfirmDefaults, gridDeleteAction } from './gridDeleteAction.js'

test('gridDeleteAction builds confirm delete action', () => {
  const action = gridDeleteAction({ confirmMessage: 'delivery_delete_confirm_message' })
  assert.equal(action.handler, 'delete')
  assert.equal(action.confirm, true)
  assert.equal(action.confirmMessage, 'delivery_delete_confirm_message')
  assert.equal(action.confirmTitle, 'confirm_delete')
})

test('applyDeleteConfirmDefaults merges delete action from API config', () => {
  const actions = applyDeleteConfirmDefaults(
    [{ name: 'edit', handler: 'edit' }, { name: 'delete', handler: 'delete', confirm: false }],
    { confirmMessage: 'payment_delete_confirm_message' }
  )
  assert.equal(actions[1].confirm, true)
  assert.equal(actions[1].confirmMessage, 'payment_delete_confirm_message')
  assert.equal(actions[0].confirm, undefined)
})
