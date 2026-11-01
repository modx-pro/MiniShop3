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
  // `(?<![@\w])` отсекает SCSS `@import './vars'` в блоке <style>: `\b`
  // срабатывает между `@` и `i`, и правильный partial `_vars.scss`
  // не резолвился по расширениям JS — CI падал на здоровом коде.
  /(?<![@\w])import\s+['"](\.[^'"]*)['"]/g,
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
 * Проход посимвольный, а не регулярками, потому что регулярка не отличает
 * комментарий от строкового литерала. На строке вида `'image/*'` она считала,
 * что начался блочный комментарий, и молча съедала всё до ближайшего закрытия:
 * импорт в этом промежутке проходил мимо проверки. А чтобы при этом не портить
 * `https://` внутри строки, прежняя версия резала строчные комментарии только
 * в начале строки — и пропускала `const n = 1 // import('./x')`.
 *
 * Знание про строки снимает оба случая сразу.
 *
 * Остаётся известный пропуск: регулярное литерал-выражение вида `/a\/\/b/`
 * здесь не распознаётся как литерал. Экранированные слэши пропускаются, так что
 * обычные `/https?:\/\//` безопасны, но сконструированный случай может погасить
 * хвост строки. Это недобор, а не ложное срабатывание: что пройдёт мимо
 * проверки, поймает следующий за ней `vite build`.
 */
function maskComments(text) {
  const chars = text.split('')
  const length = text.length

  const blank = (from, to) => {
    for (let k = from; k < to && k < length; k++) {
      if (chars[k] !== '\n') {
        chars[k] = ' '
      }
    }
  }

  let i = 0

  while (i < length) {
    const ch = text[i]

    // Экранированный символ: в том числе `\/` внутри регулярного выражения.
    if (ch === '\\') {
      i += 2
      continue
    }

    // Строковый литерал — внутри него комментариев нет.
    if (ch === '"' || ch === "'" || ch === '`') {
      i++
      while (i < length) {
        if (text[i] === '\\') {
          i += 2
          continue
        }
        if (text[i] === ch) {
          i++
          break
        }
        // Незакрытая обычная строка не должна съесть весь файл.
        if (ch !== '`' && text[i] === '\n') {
          break
        }
        i++
      }
      continue
    }

    if (ch === '/' && text[i + 1] === '*') {
      const end = text.indexOf('*/', i + 2)
      const stop = end === -1 ? length : end + 2
      blank(i, stop)
      i = stop
      continue
    }

    if (ch === '/' && text[i + 1] === '/') {
      const end = text.indexOf('\n', i)
      const stop = end === -1 ? length : end
      blank(i, stop)
      i = stop
      continue
    }

    if (ch === '<' && text.startsWith('<!--', i)) {
      const end = text.indexOf('-->', i + 4)
      const stop = end === -1 ? length : end + 3
      blank(i, stop)
      i = stop
      continue
    }

    i++
  }

  return chars.join('')
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

/**
 * Относительные импорты файла, как их видит проверка.
 *
 * Отдельной функцией, чтобы маскировку и шаблоны можно было прогнать тестом:
 * три дефекта этой логики нашлись внешним ревью именно потому, что её ничем
 * не покрывали.
 *
 * @returns {Array<{ line: number, spec: string }>}
 */
export function findRelativeImports(source) {
  const text = maskComments(source)
  const found = []

  for (const pattern of PATTERNS) {
    pattern.lastIndex = 0
    let match
    while ((match = pattern.exec(text)) !== null) {
      found.push({ line: lineOf(text, match.index), spec: match[1] })
    }
  }

  return found
}

function main() {
  const broken = []
  let checked = 0

  for (const file of walk(srcRoot)) {
    const raw = fs.readFileSync(file, 'utf8')

    for (const { line, spec } of findRelativeImports(raw)) {
      checked++

      if (!resolves(file, spec)) {
        broken.push({
          file: path.relative(path.join(__dirname, '..'), file).replace(/\\/g, '/'),
          line,
          spec,
        })
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
}

// Запускаем только при прямом вызове: тест импортирует модуль ради функции.
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main()
}
