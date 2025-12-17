import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// Use relative path for both localhost and domain
export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: './',   // <-- important
  build: {
    outDir: 'dist'
  }
})
