import { describe, expect, it } from 'vitest'

import { findRelativeImports } from './relative-imports.mjs'

const specs = source => findRelativeImports(source).map(item => item.spec)

describe('findRelativeImports', () => {
  it('находит все три формы относительного импорта', () => {
    const source = [
      "import Thing from './Thing.vue'",
      "import { a, b } from '../utils/helpers.js'",
      "const lazy = () => import('./Lazy.vue')",
      "import '../scss/app.scss'",
    ].join('\n')

    expect(specs(source)).toEqual(
      expect.arrayContaining([
        './Thing.vue',
        '../utils/helpers.js',
        './Lazy.vue',
        '../scss/app.scss',
      ])
    )
  })

  it('пропускает пакеты и алиасы: их резолвит сборщик', () => {
    const source = ["import { ref } from 'vue'", "import u from '@vuetools/useLexicon'"].join('\n')

    expect(specs(source)).toEqual([])
  })

  it('возвращает номер строки совпадения', () => {
    const source = ['const a = 1', '', "import('./Lazy.vue')"].join('\n')

    expect(findRelativeImports(source)).toEqual([{ line: 3, spec: './Lazy.vue' }])
  })

  // Регрессия: `/*` внутри строкового литерала принимался за начало блочного
  // комментария, и всё до ближайшего закрытия выпадало из проверки молча.
  it('не принимает слэш-звёздочку внутри строки за комментарий', () => {
    const source = [
      "const accept = 'image/*'",
      "import('./afterString')",
      'const x = 1 /* настоящий комментарий */',
      "import('./afterComment')",
    ].join('\n')

    expect(specs(source)).toEqual(expect.arrayContaining(['./afterString', './afterComment']))
  })

  // Регрессия: `\b` срабатывает между `@` и `i`, поэтому SCSS `@import` в блоке
  // <style> попадал в проверку, не резолвился по расширениям JS и валил CI
  // на здоровом коде.
  it('не считает импортом SCSS-правило @import', () => {
    const source = ['<style lang="scss" scoped>', "@import './vars';", '</style>'].join('\n')

    expect(specs(source)).toEqual([])
  })

  it('игнорирует закомментированные импорты в любом положении', () => {
    const source = [
      "const n = 1 // import('./tailGhost')",
      "// import('./lineGhost')",
      "/* import('./blockGhost') */",
      "<!-- import('./htmlGhost') -->",
    ].join('\n')

    expect(specs(source)).toEqual([])
  })

  it('не ломается на экранированных слэшах и адресах внутри строк', () => {
    const source = [
      'const re = /https?:\\/\\//',
      "const url = 'https://example.test'",
      "import('./afterRegex')",
    ].join('\n')

    expect(specs(source)).toContain('./afterRegex')
  })

  it('не ломается на делении', () => {
    const source = ['const q = a / b / c', "import('./afterDivide')"].join('\n')

    expect(specs(source)).toContain('./afterDivide')
  })

  // Известный предел, закреплён намеренно: слэш со звёздочкой внутри класса
  // символов регулярного выражения читается как начало блочного комментария.
  // Это недобор, а не ложное срабатывание — поломку поймает `vite build`.
  // Если когда-нибудь появится разбор регулярных литералов, тест упадёт
  // и подскажет снять ограничение.
  it('документирует пропуск после класса символов со слэшем и звёздочкой', () => {
    const source = ['const re = /[/*]/', "import('./afterCharClass')"].join('\n')

    expect(specs(source)).toEqual([])
  })

  it('класс символов без звёздочки пропуска не даёт', () => {
    const source = ['const re = /[/]/', "import('./afterPlainClass')"].join('\n')

    expect(specs(source)).toContain('./afterPlainClass')
  })

  it('не даёт незакрытой строке съесть остаток файла', () => {
    const source = ["const broken = 'oops", "import('./afterBrokenString')"].join('\n')

    expect(specs(source)).toContain('./afterBrokenString')
  })
})
