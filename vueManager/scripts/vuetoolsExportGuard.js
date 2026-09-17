/** Named imports from vue / pinia / primevue vs pinned VueTools fixture (#714). */

export const EXTERNAL_MODULES = Object.freeze(['vue', 'pinia', 'primevue'])

/**
 * Read VueTools.version from `_build/resolvers/resolver_01_setup.php`.
 * @param {string} phpSource
 * @returns {string}
 */
export function readResolverVueToolsVersion(phpSource) {
  const match = phpSource.match(
    /'VueTools'\s*=>\s*\[[\s\S]*?'version'\s*=>\s*'([^']+)'/,
  )
  if (!match) {
    throw new Error(
      "Could not parse VueTools 'version' from resolver_01_setup.php",
    )
  }
  return match[1]
}

/**
 * @param {string} source
 * @param {readonly string[]} [modules]
 * @returns {Map<string, Set<string>>}
 */
export function collectNamedImports(source, modules = EXTERNAL_MODULES) {
  const modAlt = modules.map(escapeRegExp).join('|')
  const out = new Map(modules.map(m => [m, new Set()]))

  // import|export [type] [Default,] [/*c*/] { … } from 'mod'
  const braced = new RegExp(
    String.raw`(?:(?<![.\w$])(?:import|export)\s+)(?<type>type\s+)?(?:(?<defaultBind>[A-Za-z_$][\w$]*)\s*,\s*)?(?:\/\*[\s\S]*?\*\/\s*)*(?<clause>\{[^}]*\})\s*from\s*['"](?<mod>${modAlt})['"]`,
    'g',
  )
  for (const match of source.matchAll(braced)) {
    if (match.groups?.type) continue
    const set = out.get(match.groups.mod)
    if (!set) continue
    if (match.groups.defaultBind) {
      set.add('default')
    }
    for (const name of parseImportBindings(match.groups.clause)) {
      set.add(name)
    }
  }

  // import Default from 'mod' (no named clause; vue/pinia/primevue have no default)
  const defaultOnly = new RegExp(
    String.raw`(?:(?<![.\w$])import\s+)(?!type\s)(?:\/\*[\s\S]*?\*\/\s*)*(?<defaultBind>[A-Za-z_$][\w$]*)\s+from\s*['"](?<mod>${modAlt})['"]`,
    'g',
  )
  for (const match of source.matchAll(defaultOnly)) {
    const set = out.get(match.groups.mod)
    if (!set) continue
    set.add('default')
  }

  return out
}

/**
 * Import/re-export bindings: public name is left of `as`.
 * Skips inline `type Foo`. Accepts `{ … }` or bare inner list.
 * @param {string} braceOrInner
 */
export function parseImportBindings(braceOrInner) {
  return parseBindingList(braceOrInner, 'import')
}

/**
 * Vendor `export { local as public }`: public name is right of `as`.
 * @param {string} braceOrInner
 */
export function parseExportBindings(braceOrInner) {
  return parseBindingList(braceOrInner, 'export')
}

/**
 * @param {Map<string, Set<string>>} usedByModule
 * @param {Record<string, string[]>} allowedByModule
 * @param {string} fileLabel
 */
export function findMissingExports(usedByModule, allowedByModule, fileLabel) {
  const missing = []
  for (const [mod, used] of usedByModule) {
    const allowed = new Set(allowedByModule[mod] ?? [])
    for (const name of used) {
      if (!allowed.has(name)) {
        missing.push({ module: mod, name, file: fileLabel })
      }
    }
  }
  return missing
}

/**
 * @param {{
 *   files: { path: string, source: string }[],
 *   fixture: { vuetoolsVersion: string, modules: Record<string, string[]> },
 *   modules?: readonly string[],
 * }}
 */
export function auditSourcesAgainstFixture({ files, fixture, modules = EXTERNAL_MODULES }) {
  const missing = []
  const used = Object.fromEntries(modules.map(m => [m, new Set()]))

  for (const file of files) {
    const collected = collectNamedImports(file.source, modules)
    for (const [mod, names] of collected) {
      for (const name of names) used[mod].add(name)
    }
    missing.push(...findMissingExports(collected, fixture.modules, file.path))
  }

  return {
    vuetoolsVersion: fixture.vuetoolsVersion,
    missing,
    used: Object.fromEntries(Object.entries(used).map(([k, v]) => [k, [...v].sort()])),
  }
}

/** @param {{ module: string, name: string, file: string }[]} missing */
export function formatMissingReport(missing, vuetoolsVersion) {
  return missing.map(
    m =>
      `Missing export "${m.name}" from "${m.module}" (VueTools ${vuetoolsVersion}) in ${m.file}`,
  )
}

/** @param {string} source minified VueTools vendor ESM */
export function extractVendorExports(source) {
  const names = new Set()
  for (const m of source.matchAll(/\bexport\s*\{([^}]+)\}/g)) {
    for (const name of parseExportBindings(m[1])) {
      names.add(name)
    }
  }
  for (const m of source.matchAll(/\bexport\s+(?:async\s+)?function\s+([A-Za-z_$][\w$]*)/g)) {
    names.add(m[1])
  }
  for (const m of source.matchAll(/\bexport\s+(?:const|let|var|class)\s+([A-Za-z_$][\w$]*)/g)) {
    names.add(m[1])
  }
  return [...names].sort()
}

/** Strip line and block comments so trailing comments cannot hide the next binding. */
export function stripBindingComments(text) {
  return text.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n\r]*/g, ' ')
}

/**
 * @param {string} braceOrInner
 * @param {'import'|'export'} side
 */
function parseBindingList(braceOrInner, side) {
  let inner =
    braceOrInner.trim().startsWith('{') && braceOrInner.trim().endsWith('}')
      ? braceOrInner.trim().slice(1, -1)
      : braceOrInner
  inner = stripBindingComments(inner)
  const names = []
  for (const part of inner.split(',')) {
    const token = part.trim()
    if (!token) continue
    if (/^type\s+/.test(token)) continue
    const sides = token.split(/\s+as\s+/)
    const pick = side === 'import' ? sides[0].trim() : sides[sides.length - 1].trim()
    const id = pick.match(/^([A-Za-z_$][\w$]*)$/)
    if (id) names.push(id[1])
  }
  return names
}

function escapeRegExp(s) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
