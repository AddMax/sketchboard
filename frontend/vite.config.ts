import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // Браузер обращается к приложению через nginx на 8080, поэтому
    // HMR-клиент должен стучаться туда же, а не на внутренний 5173
    hmr: {
      clientPort: Number(process.env.VITE_HMR_CLIENT_PORT ?? 8080),
    },
    // Bind-mount из хоста: inotify не всегда доходит до контейнера
    watch: {
      usePolling: true,
      interval: 300,
    },
    // Запросы приходят с Host, который проставил прокси
    allowedHosts: true,
  },
})
