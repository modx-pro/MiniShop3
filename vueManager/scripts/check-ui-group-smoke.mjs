/**
 * Smoke: PrimeVue ConfirmDialog/Toast UI groups for shared EventBus (#539).
 *
 * Run: node vueManager/scripts/check-ui-group-smoke.mjs
 */

import fs from 'node:fs'
import path from 'node:path'
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

mustContain('composables/uiGroup.js', [
  'MS3_UI_GROUP',
  'provideUiGroup',
  'useGroupedToast',
  'toUiGroup',
  'withToastGroup',
])

mustContain('composables/useActions.js', ['useGroupedToast', 'toUiGroup'])
mustContain('composables/useSelection.js', ['useGroupedToast', 'toUiGroup'])

mustContain('components/gallery/ProductGallery.vue', [
  "UI_GROUP = 'product-gallery'",
  'group: UI_GROUP',
  ':group="UI_GROUP"',
])
mustContain('components/product/ProductLinksTab.vue', [
  "UI_GROUP = 'product-links'",
  'group: UI_GROUP',
  ':group="UI_GROUP"',
])

mustContain('components/CategoryProductsGrid.vue', [
  'useUiGroup()',
  'useGroupedToast',
  '<Toast :group="UI_GROUP"',
  '<ConfirmDialog :group="UI_GROUP"',
])
mustContain('components/CategoryOptionsTab.vue', [
  'useUiGroup()',
  'useGroupedToast',
  '<Toast :group="UI_GROUP"',
  '<ConfirmDialog :group="UI_GROUP"',
])

mustContain('entries/category-products.js', ["provideUiGroup(app, 'category-products')"])
mustContain('entries/category-options.js', ["provideUiGroup(app, 'category-options')"])

console.log('OK: check-ui-group-smoke')
