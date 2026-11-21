import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // web/ is bind-mounted into Docker; on macOS, file events from the host don't reach the
    // container, so the dev server would keep serving stale modules. Polling fixes that (DECISIONS.md).
    watch: { usePolling: true, interval: 300 },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.ts',
  },
})
