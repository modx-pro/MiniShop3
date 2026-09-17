import { describe, expect, it } from 'vitest'

import {
  auditSourcesAgainstFixture,
  collectNamedImports,
  extractVendorExports,
  findMissingExports,
  formatMissingReport,
  parseExportBindings,
  parseImportBindings,
  readResolverVueToolsVersion,
  stripBindingComments,
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

  it('stripBindingComments removes line and block comments', () => {
    expect(stripBindingComments('Card, // trailing\n NoSuch, /* x */ Button')).toMatch(
      /Card,\s+NoSuch,\s+Button/,
    )
  })

  it('parseImportBindings finds names after trailing comments', () => {
    expect(
      parseImportBindings(`{
  Card, // trailing comment
  NoSuch_AfterTrailingComment,
}`),
    ).toEqual(['Card', 'NoSuch_AfterTrailingComment'])
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

  it('collectNamedImports reports default and mixed default+named', () => {
    const map = collectNamedImports(`
import Foo from 'primevue'
import Bar, { Button } from 'vue'
import /* c */ { ref } from 'vue'
`)
    expect([...map.get('primevue')]).toEqual(['default'])
    expect([...map.get('vue')].sort()).toEqual(['Button', 'default', 'ref'])
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

  it('readResolverVueToolsVersion parses resolver PHP', () => {
    const php = `
$packages = [
    'pdoTools' => ['version' => '3.0.2-pl'],
    'VueTools' => [
        // Keep fixture in sync
        'version' => '1.2.0-pl',
        'service_url' => 'modstore.pro',
    ],
];
`
    expect(readResolverVueToolsVersion(php)).toBe('1.2.0-pl')
  })
})
