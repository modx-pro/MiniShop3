import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'


const output = {
  dir: '../',
  assetFileNames: 'assets/components/minishop3/css/mgr/utilities/[name].min[extname]', // css files
  chunkFileNames: 'assets/components/minishop3/js/mgr/utilities/[name].min.js', // js libs and common code
  entryFileNames: 'assets/components/minishop3/js/mgr/utilities/[name].min.js' // main js file (entry point)
}

const DevInput = {
  'fields-management': 'index.html'
}

const ProdInput = {
  'fields-management': 'src/entries/fields-management.js',
  'main': 'src/main.js'
}
// https://vite.dev/config/
export default defineConfig(({ command }) => {
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
    }
  } else {
    // command === 'build'
    return {
      build: {
        rollupOptions: {
          output,
          input: ProdInput
        }
      },
      plugins: [vue()],
      resolve: {
        alias: {
          '@': fileURLToPath(new URL('./src', import.meta.url))
        },
      },
    }
  }
})
