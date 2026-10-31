/**
 * Smoke: каждый относительный импорт в src/ указывает на существующий файл (#790).
 *
 * ESLint путь не резолвит, а vue-dist лежит в .gitignore и собирается только
 * на машине сборки пакета, поэтому неразрешимый импорт доезжает до `vite build`
 * и всплывает там, где его меньше всего ждут.
 *
 * Run: node vueManager/scripts/check-relative-imports.mjs
 */

import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const srcRoot = path.join(__dirname, '..', 'src')

/** Порядок как у vite.resolve.extensions. */
const EXTENSIONS = ['', '.js', '.mjs', '.ts', '.vue', '.json']

/** Файлы, внутри которых ищем импорты. */
const SCANNED = new Set(['.js', '.mjs', '.ts', '.vue'])

/**
 * `from '...'`, `import('...')` и `export ... from '...'` — только относительные.
 * Пакеты и алиасы (`@vuetools/...`, `primevue`) пропускаем: их резолвит сборщик.
 */
const PATTERNS = [
  /\bfrom\s+['"](\.[^'"]*)['"]/g,
  /\bimport\s*\(\s*['"](\.[^'"]*)['"]\s*\)/g,
]

function walk(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) {
      walk(full, out)
    } else if (SCANNED.has(path.extname(entry.name))) {
      out.push(full)
    }
  }

  return out
}

/** Резолвим как сборщик: точный путь, затем расширения, затем index внутри каталога. */
function resolves(fromFile, spec) {
  const base = path.resolve(path.dirname(fromFile), spec)

  for (const ext of EXTENSIONS) {
    const candidate = base + ext
    if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
      return true
    }
  }

  if (fs.existsSync(base) && fs.statSync(base).isDirectory()) {
    for (const ext of EXTENSIONS.filter(Boolean)) {
      if (fs.existsSync(path.join(base, 'index' + ext))) {
        return true
      }
    }
  }

  return false
}

function lineOf(text, index) {
  return text.slice(0, index).split('\n').length
}

const broken = []
let checked = 0

for (const file of walk(srcRoot)) {
  const text = fs.readFileSync(file, 'utf8')

  for (const pattern of PATTERNS) {
    pattern.lastIndex = 0
    let match
    while ((match = pattern.exec(text)) !== null) {
      checked++
      if (!resolves(file, match[1])) {
        broken.push({
          file: path.relative(path.join(__dirname, '..'), file).replace(/\\/g, '/'),
          line: lineOf(text, match.index),
          spec: match[1],
        })
      }
    }
  }
}

if (broken.length > 0) {
  console.error(`FAIL: неразрешимых относительных импортов — ${broken.length}:`)
  for (const item of broken) {
    console.error(`  ${item.file}:${item.line}  →  ${item.spec}`)
  }
  process.exit(1)
}

console.warn(`OK: relative imports (${checked} проверено)`)
