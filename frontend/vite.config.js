import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
  ],
  server: {
    proxy: {
      // Chuyển hướng các request có /api sang backend
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      // Chuyển hướng request lấy cookie của Sanctum
      '/sanctum': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      }
    }
  }
})