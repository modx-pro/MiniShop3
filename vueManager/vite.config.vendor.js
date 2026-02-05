import { defineConfig } from 'vite'

/**
 * Vite конфигурация для сборки vendor bundles (ES Modules)
 *
 * Собирает Vue, PrimeVue, Pinia в отдельные ES module файлы
 * которые используются через import map в браузере
 */
export default defineConfig({
  build: {
    outDir: '../assets/components/minishop3/js/mgr/utilities/vendor',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        vue: 'src/vendor-vue.js',
        pinia: 'src/vendor-pinia.js',
        primevue: 'src/vendor-primevue.js',
      },
      output: {
        format: 'es',
        entryFileNames: '[name].min.js',
        // Сохраняем исходную структуру модулей
        preserveModules: false,
      },
    },
    // Минификация
    minify: 'terser',
    terserOptions: {
      format: {
        comments: false,
      },
    },
  },
})
