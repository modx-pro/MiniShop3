/**
 * Smoke: каждый относительный импорт в src/ указывает на существующий файл (#792).
 *
 * ESLint путь не резолвит, поэтому неразрешимый импорт доезжает до `vite build`
 * и падает там трассировкой rollup. Проверка идёт до сборки и называет файл и строку.
 *
 * Разбор живёт в relative-imports.mjs, здесь только запуск и вывод. Благодаря
 * этому тут нет условия «выполнять, только если вызвали напрямую»: такое условие
 * ошибается на пути через symlink или junction и тогда молча пропускает проверку
 * с нулевым кодом выхода.
 *
 * Run: node vueManager/scripts/check-relative-imports.mjs
 */

import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

import { collectBrokenImports } from './relative-imports.mjs'

const scriptDir = path.dirname(fileURLToPath(import.meta.url))
const projectRoot = path.join(scriptDir, '..')
const srcRoot = path.join(projectRoot, 'src')

const { broken, checked } = collectBrokenImports(srcRoot, projectRoot)

if (broken.length > 0) {
  console.error(`FAIL: неразрешимых относительных импортов — ${broken.length}:`)
  for (const item of broken) {
    console.error(`  ${item.file}:${item.line}  →  ${item.spec}`)
  }
  process.exit(1)
}

console.warn(`OK: relative imports (${checked} проверено)`)
