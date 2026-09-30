import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// O browser só conhece o Vite (:5173). Ele repassa /api e /docs para o nginx do backend,
// então não há CORS nem URL de backend "hardcoded" no código do front.
export default defineConfig({
  plugins: [vue()],
  server: {
    host: true,
    port: 5173,
    watch: { usePolling: true, interval: 300 },
    proxy: {
      '/api': 'http://nginx',
      '/docs': 'http://nginx',
    },
  },
})
