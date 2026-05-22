import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vike from 'vike/plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), vike(), tailwindcss()],
  envPrefix: ['VITE_', 'PUBLIC_'],
  server: {
    host: '0.0.0.0',
    port: 3000,
  },
  ssr: {
    noExternal: ['primevue', '@primevue/themes', 'primeicons'],
  },
  build: {
    target: 'esnext',
  },
})
