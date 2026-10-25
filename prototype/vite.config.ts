import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  // Raggiungibile dal telefono sulla stessa rete
  server: { host: true },
  test: { environment: 'jsdom', passWithNoTests: true },
})
