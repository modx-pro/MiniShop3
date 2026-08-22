import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { fileURLToPath } from 'node:url'

const srcRoot = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'src', 'components')

/** Grids that delete via ActionsColumn + useActions confirm (#630). */
const ROW_DELETE_HANDLERS = [
  ['DeliveriesGrid.vue', 'deleteDelivery'],
  ['PaymentsGrid.vue', 'deletePayment'],
  ['StatusesGrid.vue', 'deleteStatus'],
  ['VendorsGrid.vue', 'deleteVendor'],
  ['LinksGrid.vue', 'deleteLink'],
]

function read(name) {
  return fs.readFileSync(path.join(srcRoot, name), 'utf8')
}

test('settings grids row delete: single confirm via ActionsColumn, not handler (#630)', () => {
  for (const [file, fnName] of ROW_DELETE_HANDLERS) {
    const text = read(file)
    const fnRe = new RegExp(`async function ${fnName}[\\s\\S]*?^}`, 'm')
    const match = text.match(fnRe)
    assert.ok(match, `${file} must define async function ${fnName}`)
    assert.ok(
      !match[0].includes('confirm.require'),
      `${file} ${fnName} must not open a second ConfirmDialog`
    )
    assert.match(text, /confirm:\s*true/, `${file} delete action must use confirm: true in grid config`)
  }
})
