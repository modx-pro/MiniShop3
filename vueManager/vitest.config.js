import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@vuetools/useLexicon': fileURLToPath(
        new URL('./src/test/stubs/useLexicon.js', import.meta.url)
      ),
      '@vuetools/useTheme': fileURLToPath(new URL('./src/test/stubs/useTheme.js', import.meta.url)),
      '@vuetools/usePrimeVueLocale': fileURLToPath(
        new URL('./src/test/stubs/usePrimeVueLocale.js', import.meta.url)
      ),
    },
  },
  test: {
    environment: 'happy-dom',
    include: ['src/**/*.{test,spec}.{js,mjs}', 'scripts/**/*.{test,spec}.{js,mjs}'],
    clearMocks: true,
  },
})
