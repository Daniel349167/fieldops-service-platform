import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

const config = {
  base: process.env.VITE_BASE_PATH || '/',
  plugins: [vue()],
  server: {
    port: 4173,
    host: '0.0.0.0',
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/tests/setup.ts'],
    css: true,
  },
}

export default defineConfig(config)
