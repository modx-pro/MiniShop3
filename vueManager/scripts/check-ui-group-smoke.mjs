/**
 * Smoke: PrimeVue ConfirmDialog/Toast UI groups for shared EventBus (#539).
 *
 * Run: node vueManager/scripts/check-ui-group-smoke.mjs
 */

import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const srcRoot = path.join(__dirname, '..', 'src')

function fail(message) {
  console.error(`FAIL: ${message}`)
  process.exit(1)
}

function read(rel) {
  return fs.readFileSync(path.join(srcRoot, rel), 'utf8')
}

function mustContain(rel, snippets) {
  const text = read(rel)
  for (const snippet of snippets) {
    if (!text.includes(snippet)) {
      fail(`${rel} must contain: ${JSON.stringify(snippet)}`)
    }
  }
}

/**
 * In files with a grouped ConfirmDialog, every local confirm.require must
 * pass group:. If confirms go through useSelection/useActions, require uiGroup wiring.
 */
function assertGroupedConfirmWiring(rel) {
  const text = read(rel)
  if (!/<ConfirmDialog[^>]*:group=/.test(text) && !/<ConfirmDialog[^>]*\sgroup=/.test(text)) {
    return
  }

  const requireCalls = [...text.matchAll(/confirm\.require\s*\(\s*\{([\s\S]*?)\}\s*\)/g)]
  if (requireCalls.length === 0) {
    if (!/\buiGroup\s*:/.test(text) && !/:ui-group=/.test(text) && !/:confirm-group=/.test(text)) {
      fail(`${rel}: grouped ConfirmDialog without confirm.require group or uiGroup wiring`)
    }
    return
  }

  for (const [, body] of requireCalls) {
    if (!/\bgroup\s*:/.test(body)) {
      fail(`${rel}: confirm.require must pass group: when ConfirmDialog is grouped`)
    }
  }
}

mustContain('composables/uiGroup.js', [
  'MS3_UI_GROUP',
  'provideUiGroup',
  'useGroupedToast',
  'toUiGroup',
  'withToastGroup',
  '...toast',
])

mustContain('composables/useActions.js', ['useGroupedToast', 'toUiGroup', 'uiGroup'])
mustContain('composables/useSelection.js', ['useGroupedToast', 'toUiGroup', 'uiGroup'])

mustContain('components/gallery/ProductGallery.vue', [
  'group: UI_GROUP',
  ':group="UI_GROUP"',
])
mustContain('components/product/ProductLinksTab.vue', [
  'group: UI_GROUP',
  ':group="UI_GROUP"',
])

mustContain('components/CategoryProductsGrid.vue', [
  'useUiGroup()',
  'useGroupedToast',
  '<Toast :group="UI_GROUP"',
  '<ConfirmDialog :group="UI_GROUP"',
  'uiGroup:',
])
mustContain('components/CategoryOptionsTab.vue', [
  'useUiGroup()',
  'useGroupedToast',
  '<Toast :group="UI_GROUP"',
  '<ConfirmDialog :group="UI_GROUP"',
])

mustContain('entries/category-products.js', ["provideUiGroup(app, 'category-products')"])
mustContain('entries/category-options.js', ["provideUiGroup(app, 'category-options')"])

for (const rel of [
  'components/gallery/ProductGallery.vue',
  'components/product/ProductLinksTab.vue',
  'components/CategoryProductsGrid.vue',
  'components/CategoryOptionsTab.vue',
]) {
  assertGroupedConfirmWiring(rel)
}

console.warn('OK: check-ui-group-smoke')
