import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import prefixSelector from 'postcss-prefix-selector'
import { defineConfig } from 'vite'
import vueDevTools from 'vite-plugin-vue-devtools'

const output = {
  dir: '../',
  assetFileNames: 'assets/components/minishop3/css/mgr/vue-dist/[name].min[extname]', // css files
  chunkFileNames: 'assets/components/minishop3/js/mgr/vue-dist/[name]-[hash].min.js', // js chunks (hash avoids stale cache)
  entryFileNames: 'assets/components/minishop3/js/mgr/vue-dist/[name].min.js', // main js file (entry point)
}

const DevInput = {
  'fields-management': 'index.html',
}

const ProdInput = {
  'fields-management': 'src/entries/fields-management.js',
  'extra-fields': 'src/entries/extra-fields.js',
  'product-tabs': 'src/entries/product-tabs.js',
  'customers': 'src/entries/customers.js',
  'orders': 'src/entries/orders.js',
  'order': 'src/entries/order.js',
  'notifications': 'src/entries/notifications.js',
  'model-fields': 'src/entries/model-fields.js',
  'grid-fields-config': 'src/entries/grid-fields-config.js',
  'import': 'src/entries/import.js',
  'utilities-gallery': 'src/entries/utilities-gallery.js',
  'deliveries': 'src/entries/deliveries.js',
  'payments': 'src/entries/payments.js',
  'vendors': 'src/entries/vendors.js',
  'statuses': 'src/entries/statuses.js',
  'options': 'src/entries/options.js',
  'links': 'src/entries/links.js',
  'category-products': 'src/entries/category-products.js',
  'help': 'src/entries/help.js',
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
            // Uppy — стили рендерятся в body (overlay), не префиксируем
            /^\.uppy-/,
            /^\.uppy_/,
            /^\[class\^=["']pi-/,
            /^\[class\*=["'] pi-/,
            // PrimeVue компоненты - не префиксируем (Dialog рендерится в body)
            /^\.p-/,
            // MS3 компоненты внутри Dialog (рендерятся в body через teleport)
            /^\.ms3-/,
            // PrimeVue data-атрибуты для состояний (active, hidden и т.д.)
            /^\[data-p-/,
            /^\[data-pc-/,
            // Комбинированные селекторы с .p- классами
            /\.p-.*\[data-/,
          ],
          // Трансформация селектора
          transform: function (prefix, selector) {
            // Специальная обработка для :root - заменяем на .vueApp
            if (selector === ':root') {
              return '.vueApp'
            }
            return prefix + ' ' + selector
          },
        }),
      ],
    },
  }

  // Externalize Vue stack - loaded via Import Map from VueTools
  const external = ['vue', 'pinia', 'primevue']

  // Composables from VueTools — загружаются из Import Map (VueTools)
  const vuetoolsComposables = [
    '@vuetools/useApi',
    '@vuetools/useLexicon',
    '@vuetools/useModx',
    '@vuetools/usePermission',
    '@vuetools/usePrimeVueLocale',
  ]

  if (command === 'serve') {
    return {
      build: {
        rollupOptions: {
          output,
          input: DevInput,
          external: [...external, ...vuetoolsComposables],
        },
      },
      plugins: [vue(), vueDevTools()],
      resolve: {
        alias: {
          '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
      },
      css: cssConfig.postcss ? { postcss: cssConfig.postcss } : undefined,
    }
  } else {
    // command === 'build'
    return {
      build: {
        rollupOptions: {
          output,
          input: ProdInput,
          external: [...external, ...vuetoolsComposables],
        },
        cssMinify: false, // Отключаем минификацию CSS чтобы сохранить Unicode символы в PrimeIcons
        minify: 'esbuild',
      },
      plugins: [vue()],
      resolve: {
        alias: {
          '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
      },
      css: cssConfig.postcss ? { postcss: cssConfig.postcss } : undefined,
    }
  }
})
