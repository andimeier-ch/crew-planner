import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'https://crew-planner.ddev.site',
        changeOrigin: true,
        secure: false,
      },
    },
  },
})
