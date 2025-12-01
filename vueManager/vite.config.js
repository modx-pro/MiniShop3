import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import prefixSelector from 'postcss-prefix-selector'


const output = {
  dir: '../',
  assetFileNames: 'assets/components/minishop3/css/mgr/vue-dist/[name].min[extname]', // css files
  chunkFileNames: 'assets/components/minishop3/js/mgr/vue-dist/[name].min.js', // js libs and common code
  entryFileNames: 'assets/components/minishop3/js/mgr/vue-dist/[name].min.js' // main js file (entry point)
}

const DevInput = {
  'fields-management': 'index.html'
}

const ProdInput = {
  'fields-management': 'src/entries/fields-management.js',
  'extra-fields': 'src/entries/extra-fields.js',
  'gallery-uploader': 'src/entries/gallery-uploader.js',
  'customers': 'src/entries/customers.js',
  'notifications': 'src/entries/notifications.js',
  'grid-fields-config': 'src/entries/grid-fields-config.js',
  'main': 'src/main.js'
}
// https://vite.dev/config/
export default defineConfig(({ command }) => {
  // Общая конфигурация PostCSS для изоляции стилей Vue
  // Все стили будут работать только внутри контейнеров с классом .vueApp
  const cssConfig = {
    postcss: {
      plugins: [
        prefixSelector({
          prefix: '.vueApp',
          // Исключаем селекторы, которые не должны иметь префикс
          exclude: [
            // Псевдо-элементы и состояния
            /^:root/,
            /^html/,
            /^body/,
            // Глобальные селекторы для box-sizing
            /^\*/,
            /^::before/,
            /^::after/,
            // Keyframes анимации
            /^@keyframes/,
            /^@-webkit-keyframes/,
            // Font-face
            /^@font-face/,
            // Медиа-запросы (префикс добавится к вложенным селекторам)
            /^@media/,
            // Уже префиксованные селекторы (избегаем дублирования)
            /^\.vueApp/,
            // PrimeIcons - не префиксируем иконочные классы
            /^\.pi/,
            /^\[class\^=["']pi-/,
            /^\[class\*=["'] pi-/,
            // PrimeVue компоненты - не префиксируем (Dialog рендерится в body)
            /^\.p-/
          ],
          // Трансформация селектора
          transform: function (prefix, selector, prefixToIgnore) {
            // Специальная обработка для :root - заменяем на .vueApp
            if (selector === ':root') {
              return '.vueApp'
            }
            return prefix + ' ' + selector
          }
        })
      ]
    }
  }

  if (command === 'serve') {
    return {
      build: {
        rollupOptions: {
          output,
          input: DevInput
        }
      },
      plugins: [vue(), vueDevTools(),],
      resolve: {
        alias: {
          '@': fileURLToPath(new URL('./src', import.meta.url))
        },
      },
      css: cssConfig.postcss ? { postcss: cssConfig.postcss } : undefined
    }
  } else {
    // command === 'build'
    return {
      build: {
        rollupOptions: {
          output,
          input: ProdInput
        },
        cssMinify: false, // Отключаем минификацию CSS чтобы сохранить Unicode символы в PrimeIcons
        minify: 'esbuild'
      },
      plugins: [vue()],
      resolve: {
        alias: {
          '@': fileURLToPath(new URL('./src', import.meta.url))
        },
      },
      css: cssConfig.postcss ? { postcss: cssConfig.postcss } : undefined
    }
  }
})
