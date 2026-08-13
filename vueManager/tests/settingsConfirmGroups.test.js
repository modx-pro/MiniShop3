import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { fileURLToPath } from 'node:url'

const srcRoot = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'src', 'components')

const GRIDS = [
  ['OptionsGrid.vue', 'settings-options'],
  ['OptionGroupsGrid.vue', 'settings-option-groups'],
  ['DeliveriesGrid.vue', 'settings-deliveries'],
  ['PaymentsGrid.vue', 'settings-payments'],
  ['StatusesGrid.vue', 'settings-statuses'],
  ['VendorsGrid.vue', 'settings-vendors'],
  ['LinksGrid.vue', 'settings-links'],
]

function read(name) {
  return fs.readFileSync(path.join(srcRoot, name), 'utf8')
}

test('settings tab grids isolate ConfirmDialog with unique groups (#548)', () => {
  const groups = GRIDS.map(([, group]) => group)
  assert.equal(new Set(groups).size, groups.length, 'CONFIRM_GROUP values must be unique')

  for (const [file, expectedGroup] of GRIDS) {
    const text = read(file)
    assert.match(
      text,
      new RegExp(`const CONFIRM_GROUP = '${expectedGroup}'`),
      `${file} must declare CONFIRM_GROUP = '${expectedGroup}'`
    )

    assert.match(text, /<ConfirmDialog[^>]*:group="CONFIRM_GROUP"/, `${file} ConfirmDialog must bind :group`)

    const requireAt = [...text.matchAll(/confirm\.require\s*\(/g)]
    assert.ok(requireAt.length > 0, `${file} must have confirm.require`)
    for (const match of requireAt) {
      const snippet = text.slice(match.index, match.index + 400)
      assert.match(snippet, /\bgroup:\s*CONFIRM_GROUP/, `${file} confirm.require must pass group: CONFIRM_GROUP`)
    }

    if (text.includes('<ActionsColumn')) {
      assert.match(text, /:confirm-group="CONFIRM_GROUP"/, `${file} ActionsColumn must pass confirm-group`)
    }

    if (/\bconfirmBulkDelete\b[\s\S]{0,200}=\s*useSelection\(/.test(text)) {
      assert.match(text, /confirmGroup:\s*CONFIRM_GROUP/, `${file} useSelection must pass confirmGroup`)
    }
  }
})
