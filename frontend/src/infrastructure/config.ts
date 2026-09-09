/** Единственное место, где читаются переменные окружения сборки. */
export const config = {
  apiBase: import.meta.env.VITE_API_BASE ?? '/api',
  wsPath: import.meta.env.VITE_WS_PATH ?? '/ws',
} as const

/** Абсолютный адрес WebSocket-эндпоинта: nginx проксирует его в Centrifugo. */
export function websocketUrl(): string {
  const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'

  return `${protocol}//${window.location.host}${config.wsPath}`
}
