import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '127.0.0.1',
    port: 5173,
  },
  build: {
    // The production bundle is emitted into the Laravel public directory so
    // the private admin shell (AdminPanelController) can serve it via the
    // Vite manifest. Public pages keep using their existing static-host
    // deployment; only the private admin shell is served by Laravel.
    outDir: '../backend/public/build',
    emptyOutDir: true,
    manifest: true,
  },
})
