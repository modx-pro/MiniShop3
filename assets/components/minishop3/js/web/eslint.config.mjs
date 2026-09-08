import { createRequire } from 'node:module'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Storefront plain scripts (<script> + globals), not Vue ESM.
 * Config lives next to the files so ESLint 9 base path covers this tree (#667).
 * Packages resolve from vueManager/node_modules (CI only installs there).
 */
const require = createRequire(
  path.join(path.dirname(fileURLToPath(import.meta.url)), '../../../../../vueManager/package.json'),
)
const js = require('@eslint/js')
const globals = require('globals')

/** Top-level names defined for other <script> files — unused in their own file. */
const exportedScriptNames =
  '^(ApiClient|TokenManager|CartAPI|OrderAPI|CustomerAPI|CartUI|OrderUI|CustomerUI|AuthUI|QuantityUI|ProductCardUI|ms3|getSelectors|defaultSelectors|ms3Confirm|CUSTOMER_UI_LEXICON|AUTH_UI_LEXICON)$'

export default [
  {
    ignores: ['lib/**', 'eslint.config.mjs'],
  },
  js.configs.recommended,
  {
    name: 'ms3-storefront/scripts',
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'script',
      globals: {
        ...globals.browser,
        ms3Config: 'readonly',
        bootstrap: 'readonly',
        iziToast: 'readonly',
        module: 'readonly',
      },
    },
    rules: {
      quotes: ['error', 'single', { avoidEscape: true }],
      'comma-dangle': ['error', 'always-multiline'],
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'no-unused-vars': [
        'error',
        {
          varsIgnorePattern: exportedScriptNames,
          argsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^_',
        },
      ],
    },
  },
]
