/** Единственное место, где читаются переменные окружения сборки. */
export const config = {
  apiBase: import.meta.env.VITE_API_BASE ?? '/api',
  wsPath: import.meta.env.VITE_WS_PATH ?? '/ws',
} as const
