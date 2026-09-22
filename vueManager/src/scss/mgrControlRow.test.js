import { readdirSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

import { describe, expect, it } from 'vitest'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..')
const COMPONENTS = join(ROOT, 'components')
const PRIMEVUE_SCSS = join(ROOT, 'scss/primevue.scss')
const CONTROL_ROW_SCSS = join(ROOT, 'scss/mgrControlRow.scss')

/** Vue screens that opted into #760 control-row alignment. */
const CONTROL_ROW_SCREENS = [
  'CategoryProductsGrid.vue',
  'CustomersGrid.vue',
  'product/ProductLinksTab.vue',
]

/**
 * Screens with icon Buttons inside table rows. Those stay `small`: they sit in
 * their own cell, never next to an input, and the MODX control height would add
 * ~19% to every row of a grid people scroll through (#765 review).
 */
const ROW_ACTION_SCREENS = [
  'ActionsColumn.vue',
  'ActionsEditor.vue',
  'GridFieldsConfig.vue',
  'ProductDataConfig.vue',
]

function listVueFiles(dir, prefix = '') {
  const entries = readdirSync(dir, { withFileTypes: true })
  const files = []
  for (const entry of entries) {
    const rel = prefix ? `${prefix}/${entry.name}` : entry.name
    if (entry.isDirectory()) {
      files.push(...listVueFiles(join(dir, entry.name), rel))
    } else if (entry.name.endsWith('.vue')) {
      files.push(rel)
    }
  }
  return files
}

describe('mgrControlRow layout contract (#760)', () => {
  it('loads shared styles from primevue.scss (not per-SFC imports)', () => {
    const primevue = readFileSync(PRIMEVUE_SCSS, 'utf8')
    expect(primevue).toMatch(/@use\s+['"]mgrControlRow['"]/)
    expect(readFileSync(CONTROL_ROW_SCSS, 'utf8')).toMatch(/\.ms3-control-row/)
    expect(readFileSync(CONTROL_ROW_SCSS, 'utf8')).toMatch(/\.ms3-rows-per-page-select/)

    for (const rel of listVueFiles(COMPONENTS)) {
      const src = readFileSync(join(COMPONENTS, rel), 'utf8')
      expect(src, rel).not.toMatch(/mgrControlRow\.scss/)
    }
  })

  it('uses Modx control-height token and doubled-class specificity', () => {
    const scss = readFileSync(CONTROL_ROW_SCSS, 'utf8')
    expect(scss).toMatch(/--p-modx-control-height/)
    expect(scss).toMatch(/var\(--p-modx-control-height,\s*2\.25rem\)/)
    expect(scss).not.toMatch(/--p-button-height/)
    expect(scss).toMatch(/\.ms3-control-row\.ms3-control-row/)
    expect(scss).toMatch(
      /\.ms3-rows-per-page-select\.ms3-rows-per-page-select\.p-select/
    )
  })

  it('beats Modx field-height when theme CSS loads after MS3', () => {
    const ms3 = document.createElement('style')
    ms3.textContent = `
      .ms3-control-row.ms3-control-row .p-inputtext:not(.p-inputtext-sm, .p-inputtext-lg),
      .ms3-control-row.ms3-control-row .p-select:not(.p-select-sm, .p-select-lg),
      .ms3-control-row.ms3-control-row .p-button:not(.p-button-sm, .p-button-lg) {
        height: var(--p-modx-control-height, 2.25rem);
      }
      .ms3-rows-per-page-select.ms3-rows-per-page-select.p-select:not(.p-select-sm, .p-select-lg) {
        height: var(--p-modx-control-height, 2.25rem);
      }
    `
    const theme = document.createElement('style')
    theme.textContent = `
      :root { --p-modx-control-height: 2.25rem; --p-modx-field-height: 2rem; }
      .p-inputtext:not(.p-inputtext-sm):not(.p-inputtext-lg) { height: var(--p-modx-field-height); }
      .p-select:not(.p-select-sm):not(.p-select-lg) { height: var(--p-modx-field-height); }
      .p-button:not(.p-button-sm):not(.p-button-lg) { height: var(--p-modx-control-height); }
    `
    document.head.append(ms3, theme)

    const row = document.createElement('div')
    row.className = 'ms3-control-row'
    const input = document.createElement('input')
    input.className = 'p-inputtext'
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'p-button'
    const select = document.createElement('div')
    select.className = 'p-select ms3-rows-per-page-select'
    row.append(input, button, select)
    document.body.append(row)

    expect(getComputedStyle(input).height).toBe(getComputedStyle(button).height)
    expect(getComputedStyle(select).height).toBe(getComputedStyle(button).height)
    expect(getComputedStyle(input).height).toBe('36px')

    row.remove()
    ms3.remove()
    theme.remove()
  })

  it('keeps ms3-control-row on the three #760 toolbars', () => {
    for (const rel of CONTROL_ROW_SCREENS) {
      const src = readFileSync(join(COMPONENTS, rel), 'utf8')
      expect(src, rel).toMatch(/\bms3-control-row\b/)
    }
  })

  it('keeps compact rows-per-page Select on category products pager', () => {
    const src = readFileSync(join(COMPONENTS, 'CategoryProductsGrid.vue'), 'utf8')
    expect(src).toMatch(/ms3-rows-per-page-select/)
  })

  it('resolves control height to 36px via 2.25rem when theme tokens are absent', () => {
    const ms3 = document.createElement('style')
    ms3.textContent = `
      .ms3-control-row.ms3-control-row .p-inputtext:not(.p-inputtext-sm, .p-inputtext-lg),
      .ms3-control-row.ms3-control-row .p-button:not(.p-button-sm, .p-button-lg) {
        height: var(--p-modx-control-height, 2.25rem);
      }
    `
    document.head.append(ms3)

    const row = document.createElement('div')
    row.className = 'ms3-control-row'
    const input = document.createElement('input')
    input.className = 'p-inputtext'
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'p-button'
    row.append(input, button)
    document.body.append(row)

    expect(getComputedStyle(input).height).toBe('36px')
    expect(getComputedStyle(button).height).toBe('36px')

    row.remove()
    ms3.remove()
  })

  it('keeps toolbar Buttons at the MODX control height (#765)', () => {
    const buttonTagRe = /<Button\b[\s\S]*?\/?>/g
    for (const rel of listVueFiles(COMPONENTS)) {
      if (ROW_ACTION_SCREENS.includes(rel)) {
        continue
      }
      const src = readFileSync(join(COMPONENTS, rel), 'utf8')
      for (const match of src.matchAll(buttonTagRe)) {
        expect(match[0], rel).not.toMatch(/\bsize="small"/)
        expect(match[0], rel).not.toMatch(/\bp-button-sm\b/)
      }
    }
  })

  it('ActionsColumn default size stays small so grid rows stay compact', () => {
    const src = readFileSync(join(COMPONENTS, 'ActionsColumn.vue'), 'utf8')
    expect(src).toMatch(/default:\s*'small'/)
  })

  it('keeps ms3-control-row on OptionGroups toolbar after #765', () => {
    const src = readFileSync(join(COMPONENTS, 'OptionGroupsGrid.vue'), 'utf8')
    expect(src).toMatch(/\bms3-control-row\b/)
  })
})
