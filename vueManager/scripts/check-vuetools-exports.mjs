#!/usr/bin/env node
/** Fail CI when vueManager imports a named export missing from pinned VueTools (#714). */

import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

import {
  auditSourcesAgainstFixture,
  EXTERNAL_MODULES,
  extractVendorExports,
  formatMissingReport,
  readResolverVueToolsVersion,
} from './vuetoolsExportGuard.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const vueManagerRoot = path.join(__dirname, '..')
const repoRoot = path.join(vueManagerRoot, '..')
const srcRoot = path.join(vueManagerRoot, 'src')
const fixturesDir = path.join(__dirname, 'fixtures')
const resolverPath = path.join(repoRoot, '_build/resolvers/resolver_01_setup.php')

function fail(message) {
  console.error(`FAIL: ${message}`)
  process.exit(1)
}

function expectedVueToolsVersion() {
  if (!fs.existsSync(resolverPath)) {
    fail(`Missing resolver: ${resolverPath}`)
  }
  try {
    return readResolverVueToolsVersion(fs.readFileSync(resolverPath, 'utf8'))
  } catch (error) {
    fail(error instanceof Error ? error.message : String(error))
  }
}

function fixturePathFor(version) {
  return path.join(fixturesDir, `vuetools-${version}.exports.json`)
}

function walkSourceFiles(dir, acc = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    if (ent.name.startsWith('.') || ent.name === 'test' || ent.name === 'node_modules') {
      continue
    }
    const full = path.join(dir, ent.name)
    if (ent.isDirectory()) {
      walkSourceFiles(full, acc)
      continue
    }
    if (/\.(vue|js|mjs|ts)$/.test(ent.name) && !/\.(test|spec)\.js$/.test(ent.name)) {
      acc.push(full)
    }
  }
  return acc
}

function loadFixture(version) {
  const fixturePath = fixturePathFor(version)
  if (!fs.existsSync(fixturePath)) {
    fail(
      `Missing fixture ${path.basename(fixturePath)} for resolver VueTools ${version}. Generate with:\n` +
        `  node scripts/check-vuetools-exports.mjs --write-fixture /path/to/vuetools/vendor`,
    )
  }
  const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'))
  if (fixture.vuetoolsVersion !== version) {
    fail(
      `Fixture vuetoolsVersion=${fixture.vuetoolsVersion} != resolver VueTools ${version} ` +
        `(_build/resolvers/resolver_01_setup.php)`,
    )
  }
  for (const mod of EXTERNAL_MODULES) {
    if (!Array.isArray(fixture.modules?.[mod])) {
      fail(`Fixture missing modules.${mod} array`)
    }
  }
  return fixture
}

function writeFixture(vendorDir) {
  const version = expectedVueToolsVersion()
  const abs = path.resolve(vendorDir)
  if (!fs.existsSync(abs)) {
    fail(`Vendor dir not found: ${abs}`)
  }

  const modules = {}
  for (const mod of EXTERNAL_MODULES) {
    const file = path.join(abs, `${mod}.min.js`)
    if (!fs.existsSync(file)) {
      fail(`Expected vendor file: ${file}`)
    }
    modules[mod] = extractVendorExports(fs.readFileSync(file, 'utf8'))
  }

  const out = fixturePathFor(version)
  fs.mkdirSync(fixturesDir, { recursive: true })
  fs.writeFileSync(
    out,
    `${JSON.stringify(
      {
        vuetoolsVersion: version,
        generatedFrom: `vuetools/vendor/{vue,pinia,primevue}.min.js (VueTools ${version})`,
        generatedAt: new Date().toISOString().slice(0, 10),
        note:
          'Keep in sync with _build/resolvers/resolver_01_setup.php VueTools.version. ' +
          'Regenerate: node scripts/check-vuetools-exports.mjs --write-fixture /path/to/vuetools/vendor',
        modules,
      },
      null,
      2,
    )}\n`,
  )
  console.warn(`Wrote ${out}`)
  for (const mod of EXTERNAL_MODULES) {
    console.warn(`  ${mod}: ${modules[mod].length} exports`)
  }
}

function runCheck() {
  const version = expectedVueToolsVersion()
  const fixture = loadFixture(version)
  const files = walkSourceFiles(srcRoot).map(full => ({
    path: path.relative(vueManagerRoot, full).replaceAll('\\', '/'),
    source: fs.readFileSync(full, 'utf8'),
  }))

  const result = auditSourcesAgainstFixture({ files, fixture })
  if (result.missing.length > 0) {
    for (const line of formatMissingReport(result.missing, result.vuetoolsVersion)) {
      console.error(`FAIL: ${line}`)
    }
    console.error(
      `\n${result.missing.length} missing export(s) vs VueTools ${result.vuetoolsVersion} fixture.\n` +
        `If you raised the VueTools minimum, bump resolver_01_setup.php and regenerate the fixture.`,
    )
    process.exit(1)
  }

  const counts = EXTERNAL_MODULES.map(m => `${m}=${result.used[m]?.length ?? 0}`).join(', ')
  console.warn(
    `OK VueTools export guard (${result.vuetoolsVersion}): scanned ${files.length} files (${counts})`,
  )
}

const [cmd, vendorDir] = process.argv.slice(2)
if (cmd === '--write-fixture') {
  if (!vendorDir) {
    fail('Usage: node scripts/check-vuetools-exports.mjs --write-fixture /path/to/vuetools/vendor')
  }
  writeFixture(vendorDir)
} else if (cmd) {
  fail(`Unknown args: ${process.argv.slice(2).join(' ')}`)
} else {
  runCheck()
}
