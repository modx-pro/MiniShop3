import assert from 'node:assert/strict'
import test from 'node:test'

import { unwrapResponsePayload } from '../src/request.js'

test('returns empty object payload instead of envelope', () => {
  const envelope = { success: true, message: '', object: {} }
  assert.deepEqual(unwrapResponsePayload(envelope), {})
})

test('returns list payload with empty results', () => {
  const envelope = { success: true, message: '', object: { results: [], total: 0 } }
  assert.deepEqual(unwrapResponsePayload(envelope), { results: [], total: 0 })
})

test('returns empty data array instead of envelope', () => {
  const envelope = { success: true, message: '', data: [] }
  assert.deepEqual(unwrapResponsePayload(envelope), [])
})

test('returns object array payload', () => {
  const envelope = { success: true, message: '', object: [] }
  assert.deepEqual(unwrapResponsePayload(envelope), [])
})

test('prefers object field over data field', () => {
  const envelope = { success: true, object: { a: 1 }, data: { b: 2 } }
  assert.deepEqual(unwrapResponsePayload(envelope), { a: 1 })
})

test('returns non-array data object', () => {
  const envelope = { success: true, data: { import_id: 'abc' } }
  assert.deepEqual(unwrapResponsePayload(envelope), { import_id: 'abc' })
})

test('falls back to envelope when payload fields are null', () => {
  const envelope = { success: true, message: 'Deleted', object: null, data: null }
  assert.equal(unwrapResponsePayload(envelope), envelope)
})

test('returns envelope when no payload keys', () => {
  const envelope = { success: true, message: 'ok' }
  assert.equal(unwrapResponsePayload(envelope), envelope)
})

test('returns falsy scalar object payload', () => {
  assert.equal(unwrapResponsePayload({ success: true, object: 0 }), 0)
  assert.equal(unwrapResponsePayload({ success: true, object: false }), false)
  assert.equal(unwrapResponsePayload({ success: true, object: '' }), '')
})

test('passes through non-object values', () => {
  assert.equal(unwrapResponsePayload(null), null)
  assert.equal(unwrapResponsePayload('ok'), 'ok')
})
