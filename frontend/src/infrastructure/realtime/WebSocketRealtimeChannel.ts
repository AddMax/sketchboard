import type { RealtimeChannel, RealtimeHandlers } from '../../application/ports/RealtimeChannel'
import { parseRealtimeEvent } from '../../domain/realtime/RealtimeEvent'

const MAX_BACKOFF_MS = 10_000

/**
 * Адаптер realtime-канала поверх браузерного WebSocket.
 *
 * Держит одно соединение и сам восстанавливает его с экспоненциальной
 * задержкой — вызывающий код о разрывах знает только по смене состояния.
 */
export class WebSocketRealtimeChannel implements RealtimeChannel {
  private socket: WebSocket | null = null
  private attempt = 0
  private timer: number | undefined

  constructor(private readonly path: string) {}

  connect(handlers: RealtimeHandlers): () => void {
    let disposed = false

    const open = () => {
      if (disposed) return

      handlers.onStateChange(this.socket === null ? 'connecting' : 'online')

      const socket = new WebSocket(this.url())
      this.socket = socket

      socket.onopen = () => {
        this.attempt = 0
        handlers.onStateChange('online')
      }

      socket.onmessage = (message: MessageEvent<string>) => {
        const event = parseRealtimeEvent(message.data)

        if (event === null) {
          console.warn('Неизвестное сообщение канала:', message.data)

          return
        }

        handlers.onEvent(event)
      }

      socket.onclose = () => {
        this.socket = null
        if (disposed) return

        handlers.onStateChange('offline')
        const delay = Math.min(2 ** this.attempt * 500, MAX_BACKOFF_MS)
        this.attempt += 1
        this.timer = window.setTimeout(open, delay)
      }

      socket.onerror = () => socket.close()
    }

    open()

    return () => {
      disposed = true
      window.clearTimeout(this.timer)
      this.socket?.close()
      this.socket = null
    }
  }

  send(event: string, payload: unknown): void {
    if (this.socket?.readyState === WebSocket.OPEN) {
      this.socket.send(JSON.stringify({ event, payload }))
    }
  }

  private url(): string {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'

    return `${protocol}//${window.location.host}${this.path}`
  }
}
