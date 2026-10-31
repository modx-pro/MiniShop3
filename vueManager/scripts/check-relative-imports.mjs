/**
 * Smoke: каждый относительный импорт в src/ указывает на существующий файл (#792).
 *
 * ESLint путь не резолвит, поэтому неразрешимый импорт доезжает до `vite build`
 * и падает там трассировкой rollup. Проверка идёт до сборки и называет файл и строку.
 *
 * Run: node vueManager/scripts/check-relative-imports.mjs
 */

import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const srcRoot = path.join(__dirname, '..', 'src')

/**
 * Значение resolve.extensions по умолчанию у Vite. `.vue` в него не входит:
 * проект своего resolve.extensions не задаёт, поэтому импорт компонента без
 * расширения сборка не разрешит — и здесь он тоже обязан падать.
 */
const EXTENSIONS = ['', '.mjs', '.js', '.mts', '.ts', '.jsx', '.tsx', '.json']

/** Файлы, внутри которых ищем импорты. */
const SCANNED = new Set(['.js', '.mjs', '.ts', '.vue'])

/**
 * Относительные спецификаторы во всех трёх формах. Пакеты и алиасы
 * (`@vuetools/...`, `primevue`) пропускаем: их резолвит сборщик.
 */
const PATTERNS = [
  /\bfrom\s+['"](\.[^'"]*)['"]/g,
  /\bimport\s*\(\s*['"](\.[^'"]*)['"]\s*\)/g,
  /\bimport\s+['"](\.[^'"]*)['"]/g,
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

/**
 * Гасим комментарии пробелами, сохраняя длину и переводы строк, —
 * тогда позиции совпадений остаются верными для номера строки.
 *
 * Строчные комментарии режем только там, где строка с них и начинается:
 * это закомментированный импорт. Так `https://` в середине выражения цел.
 */
function maskComments(text) {
  const blank = (match) => match.replace(/[^\n]/g, ' ')

  return text
    .replace(/\/\*[\s\S]*?\*\//g, blank)
    .replace(/<!--[\s\S]*?-->/g, blank)
    .replace(/^[ \t]*(\/\/|\*).*$/gm, blank)
}

/** `./file.css?raw`, `./worker.js?worker&url` — суффикс к пути не относится. */
function stripQuery(spec) {
  const cut = spec.search(/[?#]/)

  return cut === -1 ? spec : spec.slice(0, cut)
}

/** Резолвим как сборщик: точный путь, затем расширения, затем index внутри каталога. */
function resolves(fromFile, spec) {
  const clean = stripQuery(spec)
  if (clean === '') {
    return true
  }

  const base = path.resolve(path.dirname(fromFile), clean)

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
const seen = new Set()
let checked = 0

for (const file of walk(srcRoot)) {
  const raw = fs.readFileSync(file, 'utf8')
  const text = maskComments(raw)

  for (const pattern of PATTERNS) {
    pattern.lastIndex = 0
    let match
    while ((match = pattern.exec(text)) !== null) {
      const key = `${file}:${match.index}:${match[1]}`
      if (seen.has(key)) {
        continue
      }
      seen.add(key)
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
