import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    port: 5173,
    host: true, // bind to 0.0.0.0 inside the container
    proxy: {
      '/api': {
        target: 'https://crew-planner.ddev.site',
        changeOrigin: true,
        secure: false,
      },
    },
  },
  build: {
    outDir: '../public',
    emptyOutDir: false, // never delete index.php or other Symfony files
  },
})
