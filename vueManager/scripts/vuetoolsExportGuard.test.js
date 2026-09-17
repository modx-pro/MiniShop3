import { describe, expect, it } from 'vitest'

import {
  auditSourcesAgainstFixture,
  collectNamedImports,
  extractVendorExports,
  findMissingExports,
  formatMissingReport,
  parseExportBindings,
  parseImportBindings,
} from './vuetoolsExportGuard.js'

describe('vuetoolsExportGuard (#714)', () => {
  it('parseImportBindings keeps remote names before as', () => {
    expect(parseImportBindings('{ Button, DataTable as DT }')).toEqual(['Button', 'DataTable'])
  })

  it('parseExportBindings keeps public names after as', () => {
    expect(parseExportBindings('n as ref, DataTable, foo as Button')).toEqual([
      'ref',
      'DataTable',
      'Button',
    ])
  })

  it('parseImportBindings skips inline type bindings', () => {
    expect(parseImportBindings('{ type Ref, ref }')).toEqual(['ref'])
  })

  it('collectNamedImports finds multiline primevue imports and skips type-only', () => {
    const source = `
import type { Foo } from 'primevue'
import {
  Button,
  DataTable as Table,
} from 'primevue'
import { ref } from 'vue'
export { createPinia } from 'pinia'
`
    const map = collectNamedImports(source)
    expect([...map.get('primevue')].sort()).toEqual(['Button', 'DataTable'])
    expect([...map.get('vue')]).toEqual(['ref'])
    expect([...map.get('pinia')]).toEqual(['createPinia'])
  })

  it('findMissingExports reports unknown names with file label', () => {
    const used = collectNamedImports(`import { InputChips, Button } from 'primevue'`)
    const missing = findMissingExports(
      used,
      { primevue: ['Button'], vue: [], pinia: [] },
      'src/components/X.vue',
    )
    expect(missing).toEqual([
      { module: 'primevue', name: 'InputChips', file: 'src/components/X.vue' },
    ])
    expect(formatMissingReport(missing, '1.1.2-pl')[0]).toContain('InputChips')
    expect(formatMissingReport(missing, '1.1.2-pl')[0]).toContain('VueTools 1.1.2-pl')
  })

  it('auditSourcesAgainstFixture aggregates across files', () => {
    const fixture = {
      vuetoolsVersion: '1.2.0-pl',
      modules: {
        vue: ['ref'],
        pinia: ['createPinia'],
        primevue: ['Button'],
      },
    }
    const result = auditSourcesAgainstFixture({
      fixture,
      files: [
        { path: 'a.vue', source: `import { Button, Toast } from 'primevue'` },
        { path: 'b.js', source: `import { ref } from 'vue'` },
      ],
    })
    expect(result.missing).toEqual([{ module: 'primevue', name: 'Toast', file: 'a.vue' }])
    expect(result.used.vue).toEqual(['ref'])
  })

  it('extractVendorExports uses public name after as', () => {
    const src = `const x=1;export{n as ref,DataTable,foo as Button};`
    expect(extractVendorExports(src)).toEqual(['Button', 'DataTable', 'ref'])
  })
})
